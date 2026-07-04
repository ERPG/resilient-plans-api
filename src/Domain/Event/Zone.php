<?php

declare(strict_types=1);

namespace App\Domain\Event;

readonly class Zone
{
    public function __construct(
        public string $zoneId,
        public int    $capacity,
        public float  $price,
        public string $name,
        public bool   $numbered,
    ) {}

    public function isAvailable(): bool
    {
        return $this->capacity > 0;
    }
}
