<?php

declare(strict_types=1);

require_once __DIR__ . '/Integration/IsolatedDatabaseGuard.php';

use MagIRCTests\Integration\IsolatedDatabaseGuard;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class IsolatedDatabaseGuardTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDestructiveFixturesRequireExplicitIsolationAndExactDatabase(): void
    {
        putenv('MAGIRC_TEST_DSN=mysql:host=127.0.0.1;dbname=magirc_test');
        putenv('MAGIRC_TEST_ISOLATED');
        $this->assertRejected();

        putenv('MAGIRC_TEST_ISOLATED=1');
        putenv('MAGIRC_TEST_DSN=mysql:host=127.0.0.1;dbname=production');
        $this->assertRejected();

        putenv('MAGIRC_TEST_DSN=mysql:host=127.0.0.1;dbname=magirc_test;dbname=production');
        $this->assertRejected();

        putenv('MAGIRC_TEST_DSN=mysql:host=127.0.0.1;dbname=magirc_test');
        IsolatedDatabaseGuard::assertSafe();
        self::assertTrue(true);
    }

    private function assertRejected(): void
    {
        try {
            IsolatedDatabaseGuard::assertSafe();
            self::fail('A destructive test database configuration was accepted.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }
}
