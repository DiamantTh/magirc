<?php

declare(strict_types=1);

namespace MagIRC\Tests;

use DI\Container;
use MagIRC\I18n\LocaleResolver;
use MagIRC\Cache\StatisticsCache;
use MagIRC\Http\PublicStatisticsCacheMiddleware;
use MagIRC\Http\SecurityHeadersMiddleware;
use MagIRC\Logging\SecretRedactionProcessor;
use MagIRC\Security\HtmlSanitizer;
use MagIRC\Routes\RestRoutes;
use MagIRC\Routes\WebRoutes;
use MagIRC\Twig\MarkdownExtension;
use MagIRC\Twig\TranslationExtension;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

final class ModernizationTest extends TestCase
{
    public function testLocaleNegotiationUsesExactSupportedCatalogAndFallback(): void
    {
        self::assertSame(
            'de_DE',
            LocaleResolver::resolve(null, null, 'fr-FR;q=0.4, de-DE;q=0.9, en-US;q=0.8', ['en_US', 'de_DE'], 'en_US')
        );
        self::assertSame(
            'en_US',
            LocaleResolver::resolve('xx_XX', null, '*;q=0.2', ['en_US', 'de_DE'], 'en_US')
        );
    }

    public function testTwig3CompilesTrackedTemplatesAndRendersMarkdown(): void
    {
        $theme = Twig::create(dirname(__DIR__) . '/theme/default/tpl', ['cache' => false]);
        $theme->addExtension(new TranslationExtension());
        foreach (glob(dirname(__DIR__) . '/theme/default/tpl/*.twig') ?: [] as $file) {
            $theme->getEnvironment()->load(basename($file));
        }

        $modern = Twig::create(dirname(__DIR__) . '/theme/modern-mature/tpl', ['cache' => false]);
        $modern->addExtension(new TranslationExtension());
        foreach (glob(dirname(__DIR__) . '/theme/modern-mature/tpl/*.twig') ?: [] as $file) {
            $modern->getEnvironment()->load(basename($file));
        }

        $admin = Twig::create(dirname(__DIR__) . '/admin/tpl', ['cache' => false]);
        $admin->addExtension(new TranslationExtension());
        $admin->addExtension(new MarkdownExtension());
        foreach (glob(dirname(__DIR__) . '/admin/tpl/*.twig') ?: [] as $file) {
            $admin->getEnvironment()->load(basename($file));
        }
        $setup = Twig::create(dirname(__DIR__) . '/setup/tpl', ['cache' => false]);
        foreach (glob(dirname(__DIR__) . '/setup/tpl/*.twig') ?: [] as $file) {
            $setup->getEnvironment()->load(basename($file));
        }
        $response = $admin->render(new \Slim\Psr7\Response(), 'support_markdown.twig', ['text' => '# Report']);

        self::assertStringContainsString('<h1>Report</h1>', (string) $response->getBody());
        $welcome = $admin->render(new \Slim\Psr7\Response(), 'configuration_welcome.twig', [
            'content' => '<p>Existing <strong>welcome</strong></p>',
            'cfg' => ['welcome_mode' => 'statuspage', 'theme' => 'default'],
            'csrf' => ['csrf_name' => 'csrf_name', 'csrf_value' => 'csrf_value'],
        ]);
        self::assertStringContainsString('welcome-editor.bundle.js', (string) $welcome->getBody());
        self::assertStringContainsString('Existing', (string) $welcome->getBody());
        self::assertStringNotContainsString('CKEDITOR', (string) $welcome->getBody());
    }

    public function testProductionTemplatesUseBuiltAssetsInsteadOfNodeModules(): void
    {
        foreach (
            [
                dirname(__DIR__) . '/theme/default/tpl/layout.twig',
                dirname(__DIR__) . '/theme/modern-mature/tpl/layout.twig',
                dirname(__DIR__) . '/admin/tpl/layout.twig',
                dirname(__DIR__) . '/setup/tpl/layout.twig',
            ] as $template
        ) {
            $source = (string) file_get_contents($template);
            self::assertStringNotContainsString('node_modules/', $source, $template);
            self::assertStringContainsString('assets/vendor/', $source, $template);
        }
        self::assertStringNotContainsString("node_modules'", (string) file_get_contents(dirname(__DIR__) . '/index.php'));
        self::assertStringNotContainsString("node_modules'", (string) file_get_contents(dirname(__DIR__) . '/admin/index.php'));
    }

    public function testWebserverExamplesDenyInternalSourceDirectory(): void
    {
        foreach (['doc/apache-vhost.conf.example', 'doc/nginx.conf.example', 'htaccess.txt'] as $file) {
            $source = (string) file_get_contents(dirname(__DIR__) . '/' . $file);
            self::assertStringContainsString('src', $source, $file);
            self::assertStringContainsString('vendor', $source, $file);
            self::assertStringContainsString('.git', $source, $file);
        }
    }

    public function testRestStatusRouteRetainsJsonResponseFormat(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $magirc = (new \ReflectionClass(\Magirc::class))->newInstanceWithoutConstructor();
        $magirc->service = new class {
            public function getCurrentStatus(): array
            {
                return ['users' => 12];
            }

            public function checkChannel(string $channel): bool
            {
                return true;
            }
        };

        RestRoutes::register($app, $magirc);
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/network/status');
        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"users":12}', (string) $response->getBody());
    }

    public function testWebRoutesAreRegisteredOutsideThemeDirectories(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $container->set(Twig::class, Twig::create(dirname(__DIR__) . '/theme/default/tpl', ['cache' => false]));
        $magirc = (new \ReflectionClass(\Magirc::class))->newInstanceWithoutConstructor();
        $magirc->cfg = (object) ['theme' => 'default', 'config' => []];
        $magirc->service = new class {
            public function checkChannel(string $channel): int
            {
                return 0;
            }

            public function checkUser(string $user, string $mode): bool
            {
                return true;
            }
        };
        WebRoutes::register($app, $magirc);

        self::assertNotEmpty($app->getRouteCollector()->getRoutes());
        self::assertFileDoesNotExist(dirname(__DIR__) . '/theme/default/slim/routes.inc.php');
        self::assertFileDoesNotExist(dirname(__DIR__) . '/theme/modern-mature/slim/routes.inc.php');
    }

    public function testPdoLayerUsesExceptionsAndReturnsSafeErrors(): void
    {
        $database = new \DB('sqlite::memory:', '', '', []);
        self::assertTrue($database->query('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)'));
        self::assertTrue($database->query("INSERT INTO items (label) VALUES ('Anope / Denora')"));
        self::assertTrue($database->query('SELECT label FROM items', SQL_ALL, SQL_ASSOC));
        self::assertSame('Anope / Denora', $database->record[0]['label']);
        self::assertFalse($database->query('SELECT missing FROM absent_table'));
        self::assertSame('Database query failed.', $database->error);
    }

    public function testDataTablesFilteringOrderingAndPagingUseSafeServerSideFragments(): void
    {
        $database = new \DB('sqlite::memory:', '', '', []);
        $_GET = [
            'search' => ['value' => 'public'],
            'columns' => [['data' => 'channel', 'orderable' => 'true']],
            'order' => [['column' => '0', 'dir' => 'desc']],
            'start' => '10',
            'length' => '25',
        ];
        self::assertSame('ORDER BY `channel` DESC', $database->datatablesOrdering(['channel' => 'channel']));
        self::assertSame('(`channel` LIKE \'%public%\' OR `topic` LIKE \'%public%\')', $database->datatablesFiltering(['channel', 'topic']));
        self::assertSame('LIMIT 10, 25', $database->datatablesPaging());
        $_GET['columns'][0]['data'] = 'channel` DROP TABLE records --';
        self::assertSame('', $database->datatablesOrdering(['channel' => 'channel']));
        $_GET = [];
    }

    public function testGenericDatabaseHelpersRejectUntrustedIdentifiers(): void
    {
        $database = new \DB('sqlite::memory:', '', '', []);
        self::assertTrue($database->query('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)'));
        $this->expectException(\InvalidArgumentException::class);
        $database->selectOne('items` WHERE 1=1 --', ['id' => 1]);
    }

    public function testGenericDatabaseHelpersRejectUntrustedLimits(): void
    {
        $database = new \DB('sqlite::memory:', '', '', []);
        self::assertTrue($database->query('CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT)'));
        $this->expectException(\InvalidArgumentException::class);
        $database->selectAll('items', null, null, 'ASC', '1 UNION SELECT password FROM users');
    }

    public function testDatabaseConnectionFailureKeepsInternalDetailsPrivate(): void
    {
        $database = new \DB('mysql:host=127.0.0.1;port=1;dbname=unavailable', '', '', [\PDO::ATTR_TIMEOUT => 1]);
        self::assertFalse($database->error === null);
        self::assertSame('Database connection failed.', $database->error);
    }

    public function testAnopeAndDenoraCountryStatisticsRemainAvailable(): void
    {
        $fixtures = [
            ['country' => 'United States', 'country_code' => 'US', 'count' => 60],
            ['country' => 'Unknown', 'country_code' => '??', 'count' => 40],
        ];
        foreach ([\Anope::class, \Denora::class] as $serviceClass) {
            $service = (new \ReflectionClass($serviceClass))->newInstanceWithoutConstructor();
            $result = $service->makeCountryPieData($fixtures, 100);
            self::assertCount(2, $result, $serviceClass . ' returned an unexpected data shape.');
            self::assertSame(60.0, $result[0]['y']);
            self::assertSame(40.0, $result[1]['y']);
            self::assertSame([], $service->makeCountryPieData($fixtures, 0));
            self::assertSame(['clients' => [], 'versions' => []], $service->makeClientPieData([], 0));
            $clientStats = $service->makeClientPieData([
                ['client' => 'Beta 1.0', 'count' => 2],
                ['client' => 'Alpha 2.0', 'count' => 8],
            ], 10);
            self::assertSame('Alpha', $clientStats['clients'][0]['name'], $serviceClass . ' client chart is not sorted by descending count.');
        }
    }

    public function testStatisticsCacheHitsAndInvalidatesWithoutSharingScopes(): void
    {
        $values = [];
        $cache = new class ($values) implements CacheInterface {
            public array $ttls = [];

            public function __construct(private array &$values)
            {
            }
            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->values[$key] = $value;
                $this->ttls[] = $ttl;
                return true;
            }
            public function delete(string $key): bool
            {
                unset($this->values[$key]);
                return true;
            }
            public function clear(): bool
            {
                $this->values = [];
                return true;
            }
            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                foreach ($keys as $key) {
                    yield $key => $this->get($key, $default);
                }
            }
            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                foreach ($values as $key => $value) {
                    $this->set($key, $value, $ttl);
                } return true;
            }
            public function deleteMultiple(iterable $keys): bool
            {
                foreach ($keys as $key) {
                    $this->delete($key);
                } return true;
            }
            public function has(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }
        };
        $statistics = new StatisticsCache($cache, new NullLogger(), 'anope', ['database' => 'stats']);
        $calls = 0;
        self::assertSame(['count' => 1], $statistics->remember('users', ['day' => 'today'], function () use (&$calls): array {
            $calls++;
            return ['count' => 1];
        }));
        self::assertSame(['count' => 1], $statistics->remember('users', ['day' => 'today'], function () use (&$calls): array {
            $calls++;
            return ['count' => 2];
        }));
        self::assertSame(1, $calls);
        self::assertNotSame($statistics->key('users', ['scope' => 'public']), $statistics->key('users', ['scope' => 'private']));
        $statistics->invalidate('users', ['day' => 'today']);
        self::assertSame(['count' => 2], $statistics->remember('users', ['day' => 'today'], function () use (&$calls): array {
            $calls++;
            return ['count' => 2];
        }));
        self::assertSame(2, $calls);
        $statistics->remember('history', [], static fn (): array => ['count' => 3], true);
        self::assertSame(30, $cache->ttls[0]);
        self::assertSame(300, $cache->ttls[array_key_last($cache->ttls)]);
    }

    public function testDenoraPieStatisticsUseTheSharedTimeBasedCache(): void
    {
        require_once dirname(__DIR__) . '/lib/magirc/ircds/unreal32.inc.php';
        if (!defined('TBL_USER')) {
            define('TBL_USER', 'denora_user');
        }
        if (!defined('TBL_SERVER')) {
            define('TBL_SERVER', 'denora_server');
        }
        $database = new class {
            public int $queries = 0;

            public function prepare(string $query): object
            {
                return new class ($this, $query) {
                    public function __construct(private object $database, private string $query)
                    {
                    }

                    public function bindValue(string $name, mixed $value, int $type): bool
                    {
                        return true;
                    }

                    public function execute(): bool
                    {
                        $this->database->queries++;
                        return true;
                    }

                    public function fetchAll(int $mode): array
                    {
                        return str_contains($this->query, 'AS client')
                            ? [['client' => 'test client', 'count' => 4]]
                            : [['country' => 'Germany', 'country_code' => 'DE', 'count' => 4]];
                    }
                };
            }
        };
        $service = (new \ReflectionClass(\Denora::class))->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(\Denora::class);
        $reflection->getProperty('db')->setValue($service, $database);
        $reflection->getProperty('cfg')->setValue($service, (object) ['hide_ulined' => false]);
        $reflection->getProperty('statisticsCache')->setValue(
            $service,
            new StatisticsCache(new \Phpfastcache\Helper\Psr16Adapter('Memory'), new NullLogger(), 'denora-test', ['database' => 'stats'])
        );

        self::assertSame($service->getClientStats(), $service->getClientStats());
        self::assertSame(1, $database->queries, 'Repeated client statistics should use one database query inside the cache TTL.');
        self::assertSame([['country' => 'Germany', 'country_code' => 'DE', 'count' => 4]], $service->getCountryStats());
        self::assertSame(2, $database->queries, 'Country statistics need a separate cache entry from client statistics.');
    }

    public function testWelcomeHtmlSanitizerRemovesScriptsEventsAndUnsafeUrls(): void
    {
        $safe = HtmlSanitizer::sanitize('<p onclick="alert(1)">Hello <strong>world</strong></p><script>alert(1)</script><a href="javascript:bad()">bad</a><a href="file:///etc/passwd">local</a><a href="\\\\evil.example">network</a><a href="https://example.test" target="_blank">ok</a>');
        self::assertStringContainsString('<strong>world</strong>', $safe);
        self::assertStringNotContainsString('onclick', $safe);
        self::assertStringNotContainsString('script', $safe);
        self::assertStringNotContainsString('javascript:', $safe);
        self::assertStringNotContainsString('file:///etc/passwd', $safe);
        self::assertStringNotContainsString('evil.example', $safe);
        self::assertStringContainsString('noopener noreferrer', $safe);
    }

    public function testIrcTextCannotBreakGeneratedLinkAttributes(): void
    {
        $rendered = \Magirc::irc2html("https://example.test/' onmouseover='alert(1)");
        self::assertStringNotContainsString('class="topic" onmouseover=', $rendered);
        self::assertStringContainsString('href="https://example.test/&#039;"', $rendered);
    }

    public function testAdminWelcomeSaveSanitizesExistingContentBeforePersistence(): void
    {
        $admin = (new \ReflectionClass(\Admin::class))->newInstanceWithoutConstructor();
        $database = new class {
            public array $update = [];

            public function update(string $table, array $values, array $where): bool
            {
                $this->update = [$table, $values, $where];
                return true;
            }
        };
        $admin->db = $database;

        self::assertTrue($admin->saveContent('content_welcome', '<p>Keep</p><script>alert(1)</script><img src="x" onerror="bad()">'));
        self::assertSame('magirc_content', $database->update[0]);
        self::assertSame(['name' => 'welcome'], $database->update[2]);
        self::assertStringContainsString('<p>Keep</p>', $database->update[1]['text']);
        self::assertStringNotContainsString('<script', $database->update[1]['text']);
        self::assertStringNotContainsString('onerror', $database->update[1]['text']);
    }

    public function testPublicWelcomeContentIsSanitizedBeforeRendering(): void
    {
        $magirc = (new \ReflectionClass(\Magirc::class))->newInstanceWithoutConstructor();
        $database = new class {
            public function prepare(string $query): object
            {
                return new class {
                    public function bindParam(string $name, mixed &$value, int $type): void
                    {
                    }

                    public function execute(): bool
                    {
                        return true;
                    }

                    public function fetch(int $mode): string
                    {
                        return '<p>Welcome</p><script>alert(1)</script>';
                    }
                };
            }
        };
        $magirc->db = $database;

        self::assertSame('<p>Welcome</p>', $magirc->getContent('welcome'));
    }

    public function testPublicContentRouteDoesNotExposeArbitraryDatabaseContent(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $container->set(Twig::class, Twig::create(dirname(__DIR__) . '/theme/default/tpl', ['cache' => false]));
        $magirc = (new \ReflectionClass(\Magirc::class))->newInstanceWithoutConstructor();
        $magirc->cfg = (object) ['theme' => 'default', 'config' => []];
        $magirc->service = new class {
            public function checkChannel(string $channel): int
            {
                return 0;
            }

            public function checkUser(string $user, string $mode): bool
            {
                return true;
            }
        };
        WebRoutes::register($app, $magirc);
        $app->addRoutingMiddleware();

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/content/admin-secret'));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testAdminConfigUpdatesRejectUnknownDatabaseIdentifiers(): void
    {
        $admin = (new \ReflectionClass(\Admin::class))->newInstanceWithoutConstructor();
        $config = (object) ['config' => ['theme' => 'default']];
        $admin->cfg = $config;
        $database = new class {
            public int $updates = 0;

            public function update(string $table, array $values, array $where): bool
            {
                $this->updates++;
                return true;
            }
        };
        $admin->db = $database;

        self::assertFalse($admin->saveConfig('theme` = 1, password = `x', 'default'));
        self::assertTrue($admin->saveConfig('theme', 'modern-mature'));
        self::assertSame(1, $database->updates);
    }

    public function testApplicationConfigurationCannotInjectUrlsOrJavaScript(): void
    {
        self::assertSame('', \Config::normalizeValue('service_webchat', 'javascript:alert(1)'));
        self::assertSame('', \Config::normalizeValue('service_webchat', 'https://chat.example.test/\" onmouseover=alert(1)'));
        self::assertSame('https://chat.example.test/?chan=', \Config::normalizeValue('service_webchat', 'https://chat.example.test/?chan='));
        self::assertSame('', \Config::normalizeValue('base_url', 'data:text/html,<script>alert(1)</script>'));
        self::assertSame('15', \Config::normalizeValue('live_interval', '15'));
        self::assertSame('0', \Config::normalizeValue('live_interval', '15;alert(1)'));
    }

    public function testSetupTemplatesEscapeLegacyConfigurationValues(): void
    {
        $_GET['step'] = '1';
        $setup = new \Setup();
        $html = $setup->tpl->render('step2.twig', [
            'status' => ['error' => 'new'],
            'db_magirc' => [
                'username' => '\"><script>alert(1)</script>',
                'password' => '',
                'database' => 'magirc',
                'hostname' => 'localhost',
                'port' => 3306,
                'ssl' => false,
                'ssl_key' => '',
                'ssl_cert' => '',
                'ssl_ca' => '',
            ],
            'csrf_token' => 'csrf',
            'savedb' => false,
            'check' => false,
            'dump' => false,
            'updated' => false,
            'version' => 19,
        ]);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        unset($_GET['step']);
    }

    public function testBrowserRenderingEscapesNetworkValuesUsedInHtml(): void
    {
        $runtime = (string) file_get_contents(dirname(__DIR__) . '/js/magirc.js');
        self::assertStringContainsString(".replace(/\"/g, '&quot;')", $runtime);
        self::assertStringContainsString("escapeTags(this.point.name)", $runtime);
        foreach (
            [
                dirname(__DIR__) . '/theme/default/js/theme.js',
                dirname(__DIR__) . '/theme/modern-mature/js/theme.js',
            ] as $themeRuntime
        ) {
            $source = (string) file_get_contents($themeRuntime);
            self::assertStringContainsString("escapeTags(user['nickname'])", $source);
            self::assertStringContainsString("escapeTags(user['operator_level'])", $source);
        }
        foreach (
            [
                dirname(__DIR__) . '/theme/default/tpl/network_clients.twig',
                dirname(__DIR__) . '/theme/default/tpl/server_clients.twig',
                dirname(__DIR__) . '/theme/default/tpl/channel_clients.twig',
            ] as $chartTemplate
        ) {
            self::assertStringContainsString('escapeTags(client.substring', (string) file_get_contents($chartTemplate), $chartTemplate);
        }
        foreach (
            [
                dirname(__DIR__) . '/theme/default/tpl/server_list.twig',
                dirname(__DIR__) . '/theme/modern-mature/tpl/server_list.twig',
                dirname(__DIR__) . '/theme/default/tpl/user_info.twig',
                dirname(__DIR__) . '/theme/modern-mature/tpl/user_info.twig',
            ] as $template
        ) {
            self::assertStringContainsString('escapeTags', (string) file_get_contents($template), $template);
        }
    }

    public function testSetupUsesTheModernDatabaseFactory(): void
    {
        self::assertStringNotContainsString('Magirc_DB::', (string) file_get_contents(dirname(__DIR__) . '/setup/index.php'));
        self::assertStringNotContainsString('Magirc_DB::', (string) file_get_contents(dirname(__DIR__) . '/setup/inc/step2.php'));
        self::assertStringContainsString('MagircDB::getInstance()', (string) file_get_contents(dirname(__DIR__) . '/setup/index.php'));
    }

    public function testPublicStatisticsSetEtagAndRespectConditionalRequest(): void
    {
        $_SESSION = [];
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->get('/network/status', function ($request, $response) {
            $response->getBody()->write('{"users":1}');
            return $response->withHeader('Content-Type', 'application/json');
        });
        $app->add(new PublicStatisticsCacheMiddleware());
        $app->addRoutingMiddleware();
        $first = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/network/status'));
        self::assertSame('public, max-age=30', $first->getHeaderLine('Cache-Control'));
        $second = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/network/status')->withHeader('If-None-Match', $first->getHeaderLine('ETag')));
        self::assertSame(304, $second->getStatusCode());
        self::assertSame('', (string) $second->getBody());
        $_SESSION['username'] = 'admin';
        $private = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/network/status'));
        self::assertSame('private, no-store', $private->getHeaderLine('Cache-Control'));
        unset($_SESSION['username']);
    }

    public function testSlimCsrfMiddlewareHandlesRealHttpRequests(): void
    {
        $storage = [];
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $guard = new \Slim\Csrf\Guard($app->getResponseFactory(), 'csrf', $storage, null, 10, 16, true);
        $app->post('/action', function ($request, $response) {
            $response->getBody()->write('accepted');
            return $response;
        });
        $app->add($guard);
        $app->addRoutingMiddleware();
        $token = $guard->generateToken();
        $validRequest = (new ServerRequestFactory())->createServerRequest('POST', 'http://localhost/action')->withParsedBody($token);
        self::assertSame(200, $app->handle($validRequest)->getStatusCode());
        $invalidRequest = (new ServerRequestFactory())->createServerRequest('POST', 'http://localhost/action')->withParsedBody([
            'csrf_name' => 'invalid',
            'csrf_value' => 'invalid',
        ]);
        self::assertSame(400, $app->handle($invalidRequest)->getStatusCode());
    }

    public function testLogProcessorRedactsSecrets(): void
    {
        $processor = new SecretRedactionProcessor();
        $record = new \Monolog\LogRecord(new \DateTimeImmutable(), 'test', \Monolog\Level::Error, 'password=topsecret', ['token' => 'secret-value']);
        $redacted = $processor($record);
        self::assertStringNotContainsString('topsecret', $redacted->message);
        self::assertSame('[REDACTED]', $redacted->context['token']);
    }

    public function testSecurityHeadersArePresentOnSlimResponses(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->get('/health', fn ($request, $response) => $response);
        $app->add(new SecurityHeadersMiddleware());
        $app->addRoutingMiddleware();

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/health'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('', $response->getHeaderLine('X-Powered-By'));
        self::assertStringContainsString("object-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString("frame-ancestors 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testRestEntryPointReadsExistingSessionBeforePublicCache(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/rest/service.php');
        self::assertStringContainsString("isset(\$_COOKIE[session_name()])", $source);
        self::assertStringContainsString('MagircSecurity::startSession()', $source);
    }
}
