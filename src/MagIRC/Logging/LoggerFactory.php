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
        $runtimeDirectory = getenv('MAGIRC_RUNTIME_DIR');
        $runtimeDirectory = is_string($runtimeDirectory) && $runtimeDirectory !== ''
            ? rtrim($runtimeDirectory, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 3) . '/tmp';
        if (!is_dir($runtimeDirectory)) {
            @mkdir($runtimeDirectory, 0700, true);
        }
        @chmod($runtimeDirectory, 0700);
        $logFile = $runtimeDirectory . '/magirc.log';
        try {
            $logger->pushHandler(new StreamHandler($logFile, Logger::WARNING, true, 0600));
        } catch (\Throwable) {
            $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING));
        }

        self::$logger = $logger;
        return self::$logger;
    }
}
