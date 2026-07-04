<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Event\Event;
use App\Infrastructure\Provider\ProviderUnavailable;

/**
 * Port to the external provider feed. Returns every plan the provider currently exposes,
 * mapped to domain Events (no filtering here — sell_mode filtering is a business rule applied
 * by the sync layer). The implementation must never let a network/parse failure escape as
 * anything other than ProviderUnavailable.
 */
interface PlanProvider
{
    /**
     * @return Event[]
     * @throws ProviderUnavailable
     */
    public function fetchEvents(): array;
}
