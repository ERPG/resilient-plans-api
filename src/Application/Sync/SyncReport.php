<?php

declare(strict_types=1);

namespace App\Application\Sync;

/** `processed` = online plans upserted (created/updated indistinctly — the upsert doesn't split them). */
final readonly class SyncReport
{
    public function __construct(
        public int $processed,
        public int $skippedOffline,
    ) {}
}
