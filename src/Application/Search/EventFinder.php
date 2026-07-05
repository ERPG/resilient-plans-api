<?php

declare(strict_types=1);

namespace App\Application\Search;

/** Read-side port (CQRS-lite): returns flat projections, never rehydrates a domain Event. */
interface EventFinder
{
    /** @return EventSummary[] Ordered by start date. */
    public function findBetween(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): array;
}
