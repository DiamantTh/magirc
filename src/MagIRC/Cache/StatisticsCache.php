<?php

declare(strict_types=1);

namespace MagIRC\Cache;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 facade used by both statistics services. Cache failures are treated
 * as misses so an unavailable cache never disables statistics.
 */
final readonly class StatisticsCache
{
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private string $source,
        private array $databaseIdentity = [],
        private int $currentTtl = 30,
        private int $historyTtl = 300,
    ) {
    }

    public function remember(string $queryType, array $parameters, callable $resolver, bool $historical = false, string $scope = 'public'): mixed
    {
        $key = $this->key($queryType, $parameters, $scope);
        try {
            $cached = $this->cache->get($key);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Statistics cache read failed.', ['exception_class' => $exception::class]);
        }

        $value = $resolver();
        try {
            $this->cache->set($key, $value, $historical ? $this->historyTtl : $this->currentTtl);
        } catch (\Throwable $exception) {
            $this->logger->warning('Statistics cache write failed.', ['exception_class' => $exception::class]);
        }
        return $value;
    }

    public function invalidate(string $queryType, array $parameters = [], string $scope = 'public'): void
    {
        try {
            $this->cache->delete($this->key($queryType, $parameters, $scope));
        } catch (\Throwable $exception) {
            $this->logger->warning('Statistics cache invalidation failed.', ['exception_class' => $exception::class]);
        }
    }

    public function key(string $queryType, array $parameters = [], string $scope = 'public'): string
    {
        ksort($parameters);
        return 'magirc_stats_' . hash('sha256', json_encode([
            'source' => $this->source,
            'database' => $this->databaseIdentity,
            'query' => $queryType,
            'parameters' => $parameters,
            'scope' => $scope,
        ], JSON_THROW_ON_ERROR));
    }
}
