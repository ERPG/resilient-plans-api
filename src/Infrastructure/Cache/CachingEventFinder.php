<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\Search\EventFinder;
use Psr\Cache\CacheException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class CachingEventFinder implements EventFinder
{
    public function __construct(
        private EventFinder $inner,
        private TagAwareCacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    public function findBetween(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): array
    {
        // getTimestamp() so Z and +02:00 for the same instant share a key.
        $key = 'search_' . $startsAt->getTimestamp() . '_' . $endsAt->getTimestamp();

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($startsAt, $endsAt): array {
                $item->tag('search_results');
                $item->expiresAfter(86400); // 24h safety net; invalidateTags() is the real trigger.
                $this->logger->info('Search cache miss — querying store.', ['key' => $item->getKey()]);
                return $this->inner->findBetween($startsAt, $endsAt);
            });
        } catch (CacheException $e) {
            $this->logger->error('Search cache unavailable, serving from store.', ['exception' => $e]);

            return $this->inner->findBetween($startsAt, $endsAt);
        }
    }
}
