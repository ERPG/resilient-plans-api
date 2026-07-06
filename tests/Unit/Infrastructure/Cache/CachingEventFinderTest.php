<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Cache;

use App\Application\Search\EventFinder;
use App\Application\Search\EventSummary;
use App\Infrastructure\Cache\CachingEventFinder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * Behaviour of the caching decorator against an in-memory tag-aware cache — no Redis. Uses a
 * call-counting fake finder to assert exactly when the inner store is (not) hit.
 */
final class CachingEventFinderTest extends TestCase
{
    public function testSecondIdenticalCallIsServedFromCache(): void
    {
        $inner = $this->countingFinder();
        $finder = $this->decorate($inner);

        $starts = new \DateTimeImmutable('2021-06-01T00:00:00+00:00');
        $ends   = new \DateTimeImmutable('2021-06-30T23:59:59+00:00');

        $finder->findBetween($starts, $ends);
        $finder->findBetween($starts, $ends);

        self::assertSame(1, $inner->calls, 'The store must be queried once, then served from cache.');
    }

    public function testInvalidatingTheTagForcesAReQuery(): void
    {
        $inner = $this->countingFinder();
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $finder = new CachingEventFinder($inner, $cache, new NullLogger());

        $starts = new \DateTimeImmutable('2021-06-01T00:00:00+00:00');
        $ends   = new \DateTimeImmutable('2021-06-30T23:59:59+00:00');

        $finder->findBetween($starts, $ends);
        $cache->invalidateTags(['search_results']); // what SyncPlansCommand runs after a sync
        $finder->findBetween($starts, $ends);

        self::assertSame(2, $inner->calls, 'Invalidation must drop the entry and force a re-query.');
    }

    public function testDifferentRangesAreDistinctKeys(): void
    {
        $inner = $this->countingFinder();
        $finder = $this->decorate($inner);

        $finder->findBetween(
            new \DateTimeImmutable('2021-06-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+00:00'),
        );
        $finder->findBetween(
            new \DateTimeImmutable('2021-07-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-07-31T23:59:59+00:00'),
        );

        self::assertSame(2, $inner->calls, 'Different ranges must not collide on the same cache key.');
    }

    public function testSameInstantWithDifferentOffsetHitsTheSameKey(): void
    {
        $inner = $this->countingFinder();
        $finder = $this->decorate($inner);

        // 08:00Z and 10:00+02:00 are the same instant; the key is the timestamp, so the second hits.
        $finder->findBetween(
            new \DateTimeImmutable('2021-06-01T08:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T21:59:59+00:00'),
        );
        $finder->findBetween(
            new \DateTimeImmutable('2021-06-01T10:00:00+02:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+02:00'),
        );

        self::assertSame(1, $inner->calls, 'The same instant in a different offset must not miss.');
    }

    private function decorate(EventFinder $inner): CachingEventFinder
    {
        return new CachingEventFinder($inner, new TagAwareAdapter(new ArrayAdapter()), new NullLogger());
    }

    private function countingFinder(): EventFinder
    {
        return new class implements EventFinder {
            public int $calls = 0;

            public function findBetween(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): array
            {
                ++$this->calls;

                return [new EventSummary('id', 'Title', '2021-06-01', '10:00:00', null, null, 0.0, 0.0)];
            }
        };
    }
}
