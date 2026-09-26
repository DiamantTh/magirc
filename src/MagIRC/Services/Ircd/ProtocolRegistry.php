<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd;

use RuntimeException;

/** Resolves the selected IRC daemon's protocol metadata without global classes. */
final class ProtocolRegistry
{
    private static ?string $protocolClass = null;

    public static function select(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $name)) {
            throw new RuntimeException('Configured IRC daemon is not supported.');
        }
        $class = __NAMESPACE__ . '\\' . strtolower($name) . '\\Protocol';
        if (!class_exists($class)) {
            throw new RuntimeException('Configured IRC daemon is not supported.');
        }
        self::$protocolClass = $class;
    }

    public static function constant(string $name): mixed
    {
        $class = self::selectedClass();
        $constant = strtoupper($name);
        if (!defined($class . '::' . $constant)) {
            throw new RuntimeException('IRC daemon protocol metadata is incomplete.');
        }
        return constant($class . '::' . $constant);
    }

    public static function staticProperty(string $name): mixed
    {
        $properties = new \ReflectionClass(self::selectedClass())->getStaticProperties();
        if (!array_key_exists($name, $properties)) {
            throw new RuntimeException('IRC daemon protocol metadata is incomplete.');
        }
        return $properties[$name];
    }

    private static function selectedClass(): string
    {
        if (self::$protocolClass === null) {
            self::select('unreal32');
        }
        return self::$protocolClass;
    }
}
