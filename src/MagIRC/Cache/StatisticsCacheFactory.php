<?php

declare(strict_types=1);

namespace MagIRC\Cache;

use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

final class StatisticsCacheFactory
{
    public static function create(array $config, string $source, LoggerInterface $logger): StatisticsCache
    {
        $runtimeDirectory = getenv('MAGIRC_RUNTIME_DIR');
        $runtimeDirectory = is_string($runtimeDirectory) && $runtimeDirectory !== ''
            ? rtrim($runtimeDirectory, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 3) . '/tmp';
        $path = (string) ($config['cache_path'] ?? getenv('MAGIRC_CACHE_PATH') ?: $runtimeDirectory . '/cache');
        $currentTtl = max(1, (int) ($config['cache_current_ttl'] ?? getenv('MAGIRC_CACHE_CURRENT_TTL') ?: 30));
        $historyTtl = max(1, (int) ($config['cache_history_ttl'] ?? getenv('MAGIRC_CACHE_HISTORY_TTL') ?: 300));
        try {
            $adapter = new Psr16Adapter('Files', new ConfigurationOption(['path' => $path]));
        } catch (\Throwable $exception) {
            $logger->warning('Statistics filesystem cache could not be initialised.', ['exception_class' => $exception::class]);
            $adapter = new Psr16Adapter('Memory');
        }

        $identity = [
            'host' => (string) ($config['host'] ?? $config['hostname'] ?? ''),
            'port' => (string) ($config['port'] ?? ''),
            'database' => (string) ($config['database'] ?? $config['db'] ?? ''),
            'prefix' => (string) ($config['prefix'] ?? ''),
        ];
        return new StatisticsCache($adapter, $logger, $source, $identity, $currentTtl, $historyTtl);
    }
}
