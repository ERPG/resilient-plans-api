<?php

declare(strict_types=1);

namespace App\Application\Sync;

/**
 * `processed` = online plans upserted (created/updated indistinctly — the upsert doesn't split them).
 * `failedProviders` = providers that were unreachable this run; their failure is isolated (the others
 * still sync), but the count is surfaced so a dead provider doesn't pass silently.
 */
final readonly class SyncReport
{
    public function __construct(
        public int $processed,
        public int $skippedOffline,
        public int $failedProviders,
    ) {}
}
