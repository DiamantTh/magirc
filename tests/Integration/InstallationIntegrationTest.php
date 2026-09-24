<?php

declare(strict_types=1);

namespace MagIRCTests\Integration;

require_once __DIR__ . '/IsolatedDatabaseGuard.php';

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class InstallationIntegrationTest extends TestCase
{
    private static function database(): \PDO
    {
        $dsn = getenv('MAGIRC_TEST_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('MAGIRC_TEST_DSN is not configured; installation integration test is skipped.');
        }
        IsolatedDatabaseGuard::assertSafe();
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFreshInstallCreatesSchemaAdminMarkerAndRunsAnUpgrade(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/magirc/version.inc.php';
        $pdo = self::database();
        $dsn = (string) getenv('MAGIRC_TEST_DSN');
        $directory = sys_get_temp_dir() . '/magirc-install-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        if (!defined('MAGIRC_CONF_DIR')) {
            define('MAGIRC_CONF_DIR', $directory);
        }

        try {
            $pdo->exec('DROP TABLE IF EXISTS magirc_admin, magirc_content, magirc_config');
            $setup = (new \ReflectionClass(\Setup::class))->newInstanceWithoutConstructor();
            $setup->db = new \DB(
                $dsn,
                (string) (getenv('MAGIRC_TEST_DB_USER') ?: 'root'),
                (string) (getenv('MAGIRC_TEST_DB_PASSWORD') ?: '')
            );

            self::assertTrue($setup->configDump());
            self::assertNotFalse($setup->configCheck());
            $config = \MagircConfigStore::defaults('magirc');
            $config['database'] = 'magirc_test';
            \MagircConfigStore::save('magirc', $directory, $config);
            self::assertSame('magirc_test', \MagircConfigStore::load('magirc', $directory)['database']);

            self::assertTrue($setup->createAdmin('owner', 'Install-Pass-123'));
            self::assertFileExists($directory . '/.installed');
            $admin = $pdo->query("SELECT username, password FROM magirc_admin WHERE username = 'owner'")->fetch();
            self::assertSame('owner', $admin['username']);
            self::assertTrue(\MagircSecurity::verifyPassword('Install-Pass-123', $admin['password']));

            $welcome = $pdo->query("SELECT text FROM magirc_content WHERE name = 'welcome'")->fetchColumn();
            self::assertStringContainsString('Welcome to MagIRC', (string) $welcome);

            // Simulate an existing installation one schema revision behind.
            $setup->db->delete('magirc_config', ['parameter' => 'service_webchat_urlencode']);
            $setup->db->update('magirc_config', ['value' => 18], ['parameter' => 'db_version']);
            self::assertTrue($setup->configUpgrade());
            self::assertSame('19', (string) $pdo->query("SELECT value FROM magirc_config WHERE parameter = 'db_version'")->fetchColumn());
            self::assertNotFalse($pdo->query("SELECT value FROM magirc_config WHERE parameter = 'service_webchat_urlencode'")->fetchColumn());
            self::assertSame('owner', $pdo->query('SELECT username FROM magirc_admin WHERE id = 1')->fetchColumn());
            self::assertStringContainsString('Welcome to MagIRC', (string) $pdo->query("SELECT text FROM magirc_content WHERE name = 'welcome'")->fetchColumn());
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS magirc_admin, magirc_content, magirc_config');
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
