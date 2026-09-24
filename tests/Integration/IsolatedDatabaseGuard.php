<?php

declare(strict_types=1);

namespace MagIRCTests\Integration;

/** Prevent destructive fixture tests from running against an arbitrary DSN. */
final class IsolatedDatabaseGuard
{
    public static function assertSafe(): void
    {
        $dsn = getenv('MAGIRC_TEST_DSN');
        if (getenv('MAGIRC_TEST_ISOLATED') !== '1' || !is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
            throw new \RuntimeException('Isolated database tests require MAGIRC_TEST_ISOLATED=1 and a MySQL/MariaDB test DSN.');
        }

        $databaseNames = [];
        foreach (explode(';', substr($dsn, strlen('mysql:'))) as $part) {
            if (str_starts_with($part, 'dbname=')) {
                $databaseNames[] = substr($part, strlen('dbname='));
            }
        }
        if ($databaseNames !== ['magirc_test']) {
            throw new \RuntimeException('Isolated database tests require exactly dbname=magirc_test. They create and drop fixture tables.');
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        IsolatedDatabaseGuard::assertSafe();
    } catch (\Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(2);
    }
}
