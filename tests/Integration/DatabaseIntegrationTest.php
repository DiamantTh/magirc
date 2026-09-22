<?php

declare(strict_types=1);

namespace MagIRC\Tests\Integration;

use MagIRC\Cache\StatisticsCache;
use MagIRC\Routes\RestRoutes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Phpfastcache\Helper\Psr16Adapter;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class DatabaseIntegrationTest extends TestCase
{
    private static function database(): \PDO
    {
        $dsn = getenv('MAGIRC_TEST_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('MAGIRC_TEST_DSN is not configured; MySQL/MariaDB integration tests are skipped.');
        }
        try {
            return new \PDO(
                $dsn,
                (string) (getenv('MAGIRC_TEST_DB_USER') ?: 'root'),
                (string) (getenv('MAGIRC_TEST_DB_PASSWORD') ?: ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
            );
        } catch (\PDOException $exception) {
            self::markTestSkipped('MySQL/MariaDB is not reachable: ' . $exception->getMessage());
        }
    }

    private static function executeSchema(\PDO $pdo, array $statements): void
    {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    private static function assertConfigurationRoundTrip(string $service): void
    {
        $directory = sys_get_temp_dir() . '/magirc-integration-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        try {
            $config = \MagircConfigStore::defaults($service);
            $config['database'] = 'magirc_test';
            $config['hostname'] = '127.0.0.1';
            $config['port'] = (int) (getenv('MAGIRC_TEST_DB_PORT') ?: 3306);
            \MagircConfigStore::save($service, $directory, $config);
            $loaded = \MagircConfigStore::load($service, $directory);
            self::assertSame('magirc_test', $loaded['database']);
            self::assertStringContainsString('charset=utf8mb4', \MagircConfigStore::dsn($loaded));
            self::assertFalse((bool) \MagircConfigStore::pdoOptions($loaded)[\PDO::ATTR_PERSISTENT]);
        } finally {
            @unlink($directory . '/' . $service . '.json');
            @rmdir($directory);
        }
    }

    private static function statisticsCache(string $source): StatisticsCache
    {
        return new StatisticsCache(new Psr16Adapter('Memory'), new NullLogger(), $source, ['database' => 'integration'], 60, 600);
    }

    private static function serviceWithFixtures(string $class, object $config): object
    {
        $db = new \DB(
            (string) getenv('MAGIRC_TEST_DSN'),
            (string) (getenv('MAGIRC_TEST_DB_USER') ?: 'root'),
            (string) (getenv('MAGIRC_TEST_DB_PASSWORD') ?: '')
        );
        $reflection = new \ReflectionClass($class);
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($service, $db);
        $reflection->getProperty('cfg')->setValue($service, $config);
        $reflection->getProperty('statisticsCache')->setValue($service, self::statisticsCache(strtolower((new \ReflectionClass($class))->getShortName())));
        return $service;
    }

    private static function magircForService(object $service): \Magirc
    {
        $magirc = (new \ReflectionClass(\Magirc::class))->newInstanceWithoutConstructor();
        $magirc->service = $service;
        return $magirc;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnopeConnectionStatisticsVisibilityAndRestResponse(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/magirc/ircds/unreal32.inc.php';
        require_once dirname(__DIR__, 2) . '/lib/magirc/objects/anope/Channel.class.php';
        require_once dirname(__DIR__, 2) . '/lib/magirc/objects/anope/Server.class.php';
        require_once dirname(__DIR__, 2) . '/lib/magirc/objects/anope/User.class.php';
        self::defineAnopeTables();
        $pdo = self::database();
        self::assertConfigurationRoundTrip('anope');
        self::prepareAnopeSchema($pdo);
        $config = (object) [
            'hide_ulined' => true,
            'hide_chans' => '#hidden',
            'block_schans' => true,
            'block_pchans' => true,
        ];
        $service = self::serviceWithFixtures('Anope', $config);

        self::assertSame(12, $service->getCurrentStatus()['users']['val']);
        self::assertSame(4, $service->getMaxValues()['users']['val']);
        self::assertSame(1, (int) $service->getUserCount());
        self::assertSame(200, $service->checkChannel('#public'));
        self::assertSame(403, $service->checkChannel('#hidden'));
        self::assertSame(404, $service->checkChannel('#missing'));
        self::assertCount(1, $service->getChannelList());
        self::assertSame('#public', $service->getChannelList()[0]->channel);
        $_GET = [
            'draw' => '3',
            'search' => ['value' => 'public'],
            'columns' => [['data' => 'channel', 'orderable' => 'true']],
            'order' => [['column' => '0', 'dir' => 'asc']],
            'start' => '0',
            'length' => '1',
        ];
        $table = $service->getChannelList(true);
        self::assertSame(3, $table['draw']);
        self::assertCount(1, $table['data']);
        self::assertSame('#public', $table['data'][0]->channel);
        $_GET = [];
        self::assertSame(1, $service->getUserHistory()[0][1]);

        $container = new \DI\Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $magirc = self::magircForService($service);
        RestRoutes::register($app, $magirc);
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/network/status'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"users":{"val":12', (string) $response->getBody());
        $hidden = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/channels/%23hidden'));
        self::assertSame(403, $hidden->getStatusCode());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDenoraConnectionUsesDifferentTableLayoutAndRestResponse(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/magirc/ircds/unreal32.inc.php';
        require_once dirname(__DIR__, 2) . '/lib/magirc/objects/denora/Channel.class.php';
        require_once dirname(__DIR__, 2) . '/lib/magirc/objects/denora/Server.class.php';
        require_once dirname(__DIR__, 2) . '/lib/magirc/objects/denora/User.class.php';
        self::defineDenoraTables();
        $pdo = self::database();
        self::assertConfigurationRoundTrip('denora');
        self::prepareDenoraSchema($pdo);
        $config = (object) [
            'hide_ulined' => true,
            'hide_chans' => '#hidden',
            'block_schans' => true,
            'block_pchans' => true,
        ];
        $service = self::serviceWithFixtures('Denora', $config);

        self::assertSame(12, $service->getCurrentStatus()['users']['val']);
        self::assertSame(4, $service->getMaxValues()['users']['val']);
        self::assertSame(1, (int) $service->getUserCount());
        self::assertSame(200, $service->checkChannel('#public'));
        self::assertSame(403, $service->checkChannel('#hidden'));

        $container = new \DI\Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $magirc = self::magircForService($service);
        RestRoutes::register($app, $magirc);
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/network/status'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"users":{"val":12', (string) $response->getBody());
    }

    private static function defineAnopeTables(): void
    {
        $tables = [
            'TBL_CHAN' => 'anope_chan', 'TBL_CHANSTATS' => 'anope_chanstats', 'TBL_ISON' => 'anope_ison',
            'TBL_MAXUSERS' => 'anope_maxusers', 'TBL_SERVER' => 'anope_server', 'TBL_USER' => 'anope_user',
            'TBL_CURRENTUSAGE' => 'anope_currentusage', 'TBL_MAXUSAGE' => 'anope_maxusage', 'TBL_HISTORY' => 'anope_history',
        ];
        foreach ($tables as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    private static function defineDenoraTables(): void
    {
        $tables = [
            'TBL_CURRENT' => 'denora_current', 'TBL_MAXVALUES' => 'denora_maxvalues', 'TBL_USER' => 'denora_user',
            'TBL_SERVER' => 'denora_server', 'TBL_USERSTATS' => 'denora_stats', 'TBL_CHANNELSTATS' => 'denora_channelstats',
            'TBL_SERVERSTATS' => 'denora_serverstats', 'TBL_USTATS' => 'denora_ustats', 'TBL_CSTATS' => 'denora_cstats',
            'TBL_CHAN' => 'denora_chan', 'TBL_ISON' => 'denora_ison', 'TBL_ALIASES' => 'denora_aliases',
        ];
        foreach ($tables as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    private static function prepareAnopeSchema(\PDO $pdo): void
    {
        self::executeSchema($pdo, [
            'DROP TABLE IF EXISTS anope_ison, anope_chanstats, anope_history, anope_maxusage, anope_maxusers, anope_chan, anope_user, anope_server, anope_currentusage',
            'CREATE TABLE anope_currentusage (datetime DATETIME NOT NULL, servers INT NOT NULL, channels INT NOT NULL, users INT NOT NULL, operators INT NOT NULL)',
            'CREATE TABLE anope_maxusage (type VARCHAR(16) PRIMARY KEY, count INT NOT NULL, datetime DATETIME NOT NULL)',
            'CREATE TABLE anope_history (datetime DATETIME PRIMARY KEY, users INT NOT NULL, channels INT NOT NULL, servers INT NOT NULL, operators INT NOT NULL)',
            'CREATE TABLE anope_server (id INT PRIMARY KEY, name VARCHAR(64) NOT NULL, online CHAR(1) NOT NULL, comment VARCHAR(255) NOT NULL, currentusers INT NOT NULL, ulined CHAR(1) NOT NULL)',
            'CREATE TABLE anope_user (id INT PRIMARY KEY, nickid INT NOT NULL, servid INT NOT NULL, nick VARCHAR(64) NOT NULL, account VARCHAR(64) NOT NULL, version VARCHAR(128) NOT NULL, geocode VARCHAR(8) NOT NULL, geocountry VARCHAR(64) NOT NULL, modes VARCHAR(32) NOT NULL, oper CHAR(1) NOT NULL)',
            'CREATE TABLE anope_chan (chanid INT PRIMARY KEY, channel VARCHAR(128) NOT NULL, topic TEXT NOT NULL, topicauthor VARCHAR(64) NOT NULL, topictime DATETIME NULL, modes VARCHAR(32) NOT NULL)',
            'CREATE TABLE anope_ison (nickid INT NOT NULL, chanid INT NOT NULL)',
            'CREATE TABLE anope_maxusers (name VARCHAR(64) PRIMARY KEY, maxusers INT NOT NULL, maxtime DATETIME NULL)',
            'CREATE TABLE anope_chanstats (chan VARCHAR(128) NOT NULL, nick VARCHAR(64) NOT NULL, type VARCHAR(16) NOT NULL, letters INT NOT NULL, words INT NOT NULL, line INT NOT NULL, actions INT NOT NULL, smileys INT NOT NULL, kicks INT NOT NULL, modes INT NOT NULL, topics INT NOT NULL, time0 INT NOT NULL DEFAULT 0, time1 INT NOT NULL DEFAULT 0, time2 INT NOT NULL DEFAULT 0, time3 INT NOT NULL DEFAULT 0, time4 INT NOT NULL DEFAULT 0, time5 INT NOT NULL DEFAULT 0, time6 INT NOT NULL DEFAULT 0, time7 INT NOT NULL DEFAULT 0, time8 INT NOT NULL DEFAULT 0, time9 INT NOT NULL DEFAULT 0, time10 INT NOT NULL DEFAULT 0, time11 INT NOT NULL DEFAULT 0, time12 INT NOT NULL DEFAULT 0, time13 INT NOT NULL DEFAULT 0, time14 INT NOT NULL DEFAULT 0, time15 INT NOT NULL DEFAULT 0, time16 INT NOT NULL DEFAULT 0, time17 INT NOT NULL DEFAULT 0, time18 INT NOT NULL DEFAULT 0, time19 INT NOT NULL DEFAULT 0, time20 INT NOT NULL DEFAULT 0, time21 INT NOT NULL DEFAULT 0, time22 INT NOT NULL DEFAULT 0, time23 INT NOT NULL DEFAULT 0)',
            "INSERT INTO anope_currentusage VALUES ('2026-01-01 00:00:00', 2, 3, 12, 1)",
            "INSERT INTO anope_maxusage VALUES ('users', 4, '2026-01-01 00:00:00'), ('channels', 3, '2026-01-01 00:00:00'), ('servers', 2, '2026-01-01 00:00:00'), ('operators', 1, '2026-01-01 00:00:00')",
            "INSERT INTO anope_history VALUES ('2026-01-01 00:00:00', 1, 2, 1, 0)",
            "INSERT INTO anope_server VALUES (1, 'irc.example.test', 'Y', 'Test server', 1, 'N')",
            "INSERT INTO anope_user VALUES (1, 10, 1, 'Alice', 'alice', 'client', 'US', 'United States', '', 'N'), (2, 11, 1, 'Service', 'service', 'client', 'DE', 'Germany', 'S', 'Y')",
            "INSERT INTO anope_chan VALUES (1, '#public', 'Topic', 'Alice', NULL, ''), (2, '#hidden', 'Hidden', 'Alice', NULL, 'p')",
            'INSERT INTO anope_ison VALUES (10, 1)',
            "INSERT INTO anope_maxusers VALUES ('irc.example.test', 10, NULL)",
        ]);
    }

    private static function prepareDenoraSchema(\PDO $pdo): void
    {
        self::executeSchema($pdo, [
            'DROP TABLE IF EXISTS denora_aliases, denora_ison, denora_cstats, denora_ustats, denora_serverstats, denora_channelstats, denora_stats, denora_chan, denora_user, denora_server, denora_maxvalues, denora_current',
            'CREATE TABLE denora_current (type VARCHAR(16) PRIMARY KEY, val INT NOT NULL, time INT NOT NULL)',
            'CREATE TABLE denora_maxvalues (type VARCHAR(16) PRIMARY KEY, val INT NOT NULL, time INT NOT NULL)',
            'CREATE TABLE denora_server (servid INT PRIMARY KEY, server VARCHAR(64) NOT NULL, online CHAR(1) NOT NULL, comment VARCHAR(255) NOT NULL, currentusers INT NOT NULL, uline INT NOT NULL, country VARCHAR(64) NOT NULL, countrycode VARCHAR(8) NOT NULL)',
            'CREATE TABLE denora_user (nick VARCHAR(64) PRIMARY KEY, servid INT NOT NULL, server VARCHAR(64) NOT NULL, online CHAR(1) NOT NULL, uline INT NOT NULL, mode_us CHAR(1) NOT NULL DEFAULT \'N\', mode_ls CHAR(1) NOT NULL DEFAULT \'N\', mode_lp CHAR(1) NOT NULL DEFAULT \'N\')',
            'CREATE TABLE denora_chan (channel VARCHAR(128) PRIMARY KEY, currentusers INT NOT NULL, maxusers INT NOT NULL, maxusertime INT NOT NULL, mode_ls CHAR(1) NOT NULL DEFAULT \'N\', mode_lp CHAR(1) NOT NULL DEFAULT \'N\')',
            'CREATE TABLE denora_ison (nickid INT NOT NULL, chanid INT NOT NULL)',
            'CREATE TABLE denora_stats (year INT NOT NULL, month INT NOT NULL, day INT NOT NULL, users INT NOT NULL, channels INT NOT NULL, servers INT NOT NULL)',
            'CREATE TABLE denora_channelstats (chan VARCHAR(128) NOT NULL, type INT NOT NULL, line INT NOT NULL)',
            'CREATE TABLE denora_serverstats (server VARCHAR(128) NOT NULL, year INT NOT NULL, month INT NOT NULL, day INT NOT NULL, users INT NOT NULL)',
            'CREATE TABLE denora_ustats (uname VARCHAR(128) NOT NULL, chan VARCHAR(128) NOT NULL, type INT NOT NULL, line INT NOT NULL)',
            'CREATE TABLE denora_cstats (chan VARCHAR(128) NOT NULL, type INT NOT NULL, line INT NOT NULL)',
            'CREATE TABLE denora_aliases (nick VARCHAR(64) NOT NULL, uname VARCHAR(64) NOT NULL)',
            "INSERT INTO denora_current VALUES ('users', 12, 1704067200), ('channels', 3, 1704067200), ('servers', 2, 1704067200), ('opers', 1, 1704067200)",
            "INSERT INTO denora_maxvalues VALUES ('users', 4, 1704067200), ('channels', 3, 1704067200), ('servers', 2, 1704067200), ('opers', 1, 1704067200)",
            "INSERT INTO denora_server VALUES (1, 'irc.example.test', 'Y', 'Test server', 1, 0, 'United States', 'US')",
            "INSERT INTO denora_user VALUES ('Alice', 1, 'irc.example.test', 'Y', 0, 'N', 'N', 'N'), ('Service', 1, 'irc.example.test', 'Y', 1, 'S', 'N', 'N')",
            "INSERT INTO denora_chan VALUES ('#public', 1, 100, 1704067200, 'N', 'N'), ('#hidden', 1, 100, 1704067200, 'N', 'Y')",
        ]);
    }
}
