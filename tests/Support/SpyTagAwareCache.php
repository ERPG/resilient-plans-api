<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * In-memory tag-aware cache that records the tags it was asked to invalidate, so a test can assert
 * whether (and with what) a collaborator called invalidateTags(). Delegates get/delete to a real
 * array-backed adapter — no Redis.
 */
final class SpyTagAwareCache implements TagAwareCacheInterface
{
    /** @var list<string> */
    public array $invalidatedTags = [];

    private readonly TagAwareAdapter $inner;

    public function __construct()
    {
        $this->inner = new TagAwareAdapter(new ArrayAdapter());
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->inner->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    public function invalidateTags(array $tags): bool
    {
        $this->invalidatedTags = [...$this->invalidatedTags, ...$tags];

        return $this->inner->invalidateTags($tags);
    }
}
