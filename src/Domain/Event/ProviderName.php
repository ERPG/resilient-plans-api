<?php

declare(strict_types=1);

namespace App\Domain\Event;

/**
 * Closed set of known providers. Each adapter hardcodes its own case — typo-safe, no env var needed.
 * Adding a second provider = a new case here + its adapter. Same pattern as SellMode.
 * The string value is what gets stored in provider_name and folded into the event's UUID — never change
 * an existing value without a migration that recomputes all affected UUIDs.
 */
enum ProviderName: string
{
    case Demo = 'demo';
}
