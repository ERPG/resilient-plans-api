<?php

declare(strict_types=1);

namespace App\Application\Search;

/**
 * Read projection: the 8 contract fields already in the shape the JSON needs (dates/times as
 * strings, prices as float). Not the domain Event; it carries no behaviour.
 */
final readonly class EventSummary
{
    public function __construct(
        public string  $id,
        public string  $title,
        public string  $startDate,
        public ?string $startTime,
        public ?string $endDate,
        public ?string $endTime,
        public float   $minPrice,
        public float   $maxPrice,
    ) {}

    /** @return array<string, string|float|null> */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'start_date' => $this->startDate,
            'start_time' => $this->startTime,
            'end_date'   => $this->endDate,
            'end_time'   => $this->endTime,
            'min_price'  => $this->minPrice,
            'max_price'  => $this->maxPrice,
        ];
    }
}
