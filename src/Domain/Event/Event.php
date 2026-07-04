<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\DateRange;

readonly class Event
{
    /** @param Zone[] $zones */
    public function __construct(
        private string              $planId,
        private string              $title,
        private SellMode            $sellMode,
        private \DateTimeImmutable  $startDate,
        private ?\DateTimeImmutable $endDate,
        private array               $zones,
    ) {}

    public function planId(): string   { return $this->planId; }
    public function title(): string    { return $this->title; }
    public function sellMode(): SellMode { return $this->sellMode; }
    public function startDate(): \DateTimeImmutable { return $this->startDate; }
    public function endDate(): ?\DateTimeImmutable  { return $this->endDate; }

    public function isOnline(): bool
    {
        return $this->sellMode === SellMode::Online;
    }

    public function minPrice(): float
    {
        return $this->aggregatePrice('min');
    }

    public function maxPrice(): float
    {
        return $this->aggregatePrice('max');
    }

    public function fallsWithin(DateRange $range): bool
    {
        return $range->contains($this->startDate, $this->endDate);
    }

    private function aggregatePrice(string $fn): float
    {
        $prices = array_map(
            fn(Zone $z) => $z->price,
            array_filter($this->zones, fn(Zone $z) => $z->isAvailable()),
        );

        // Returns 0.0 (not null) because the API contract types these fields as number, not nullable
        return $prices ? ($fn === 'min' ? min($prices) : max($prices)) : 0.0;
    }
}
