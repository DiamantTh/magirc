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
        $pdo = self::database();
        $dsn = (string) getenv('MAGIRC_TEST_DSN');
        $directory = sys_get_temp_dir() . '/magirc-install-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $paths = new \MagIRC\Bootstrap\ApplicationPaths($directory);
        mkdir($paths->private('conf'), 0700, true);
        mkdir($paths->private('tmp'), 0700, true);
        mkdir($paths->private('templates', 'setup'), 0700, true);
        mkdir($paths->private('resources', 'sql'), 0700, true);
        copy(dirname(__DIR__, 2) . '/templates/setup/step1.twig', $paths->private('templates', 'setup', 'step1.twig'));
        copy(dirname(__DIR__, 2) . '/resources/sql/schema.sql', $paths->private('resources', 'sql', 'schema.sql'));
        $configurationDirectory = $paths->private('conf');

        try {
            $pdo->exec('DROP TABLE IF EXISTS magirc_admin, magirc_content, magirc_config');
            $setup = new \MagIRC\Installation\Installer($paths);
            $setup->db = new \MagIRC\Database\Database(
                $dsn,
                (string) (getenv('MAGIRC_TEST_DB_USER') ?: 'root'),
                (string) (getenv('MAGIRC_TEST_DB_PASSWORD') ?: '')
            );

            self::assertTrue($setup->configDump());
            self::assertNotFalse($setup->configCheck());
            $config = \MagIRC\Config\ConfigurationStore::defaults('magirc');
            $config['database'] = 'magirc_test';
            \MagIRC\Config\ConfigurationStore::save('magirc', $configurationDirectory, $config);
            self::assertSame('magirc_test', \MagIRC\Config\ConfigurationStore::load('magirc', $configurationDirectory)['database']);

            self::assertTrue($setup->createAdmin('owner', 'Install-Pass-123'));
            self::assertFileExists($configurationDirectory . '/.installed');
            $admin = $pdo->query("SELECT username, password FROM magirc_admin WHERE username = 'owner'")->fetch();
            self::assertSame('owner', $admin['username']);
            self::assertTrue(\MagIRC\Security\Security::verifyPassword('Install-Pass-123', $admin['password']));

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
            @unlink($configurationDirectory . '/.installed');
            @unlink($configurationDirectory . '/.installing');
            @unlink($configurationDirectory . '/.setup_pending');
            foreach (glob($configurationDirectory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($configurationDirectory);
            @unlink($paths->private('templates', 'setup', 'step1.twig'));
            @rmdir($paths->private('templates', 'setup'));
            @rmdir($paths->private('templates'));
            @unlink($paths->private('resources', 'sql', 'schema.sql'));
            @rmdir($paths->private('resources', 'sql'));
            @rmdir($paths->private('resources'));
            @rmdir($paths->private('tmp'));
            @rmdir($directory);
        }
    }
}
