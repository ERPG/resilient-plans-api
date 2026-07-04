<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

readonly class DateRange
{
    public function __construct(
        private \DateTimeImmutable $start,
        private \DateTimeImmutable $end,
    ) {}

    public function contains(\DateTimeImmutable $eventStart, ?\DateTimeImmutable $eventEnd): bool
    {
        $startOk = $eventStart >= $this->start && $eventStart <= $this->end;
        $endOk   = $eventEnd === null || $eventEnd <= $this->end;

        return $startOk && $endOk;
    }
}
