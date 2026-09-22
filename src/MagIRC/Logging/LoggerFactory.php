<?php

declare(strict_types=1);

namespace MagIRC\Logging;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    private static ?LoggerInterface $logger = null;

    public static function get(): LoggerInterface
    {
        if (self::$logger instanceof \Psr\Log\LoggerInterface) {
            return self::$logger;
        }

        $logger = new Logger('magirc');
        $logger->pushProcessor(new SecretRedactionProcessor());
        $logFile = dirname(__DIR__, 3) . '/tmp/magirc.log';
        try {
            $logger->pushHandler(new StreamHandler($logFile, Logger::WARNING));
        } catch (\Throwable) {
            $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING));
        }

        self::$logger = $logger;
        return self::$logger;
    }
}
