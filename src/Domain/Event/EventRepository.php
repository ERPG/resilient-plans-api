<?php

declare(strict_types=1);

namespace App\Domain\Event;

/**
 * Write-side port for persisting events. Read/search is a separate port that reads projections
 * (it never rehydrates a domain Event) — the CQRS-lite split is documented in the README.
 */
interface EventRepository
{
    /** Upsert one event, keyed by its identity (basePlanId, planId). Never deletes. */
    public function save(Event $event): void;
}
