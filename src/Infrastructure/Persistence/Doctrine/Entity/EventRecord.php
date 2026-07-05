<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Mutable Doctrine model, kept separate from the readonly domain Event: it holds last_seen_at
 * (persistence-only) and the already-computed min/max instead of the zones, which aren't persisted.
 * UNIQUE(provider_name, external_identity) is a safety net; the PK-based write path never queries it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'events')]
#[ORM\Index(name: 'idx_events_dates', columns: ['start_date', 'end_date'])]
#[ORM\UniqueConstraint(name: 'uniq_events_identity', columns: ['provider_name', 'external_identity'])]
class EventRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'provider_name', length: 255)]
    private string $providerName;

    #[ORM\Column(name: 'external_identity', length: 255)]
    private string $externalIdentity;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(name: 'start_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate;

    #[ORM\Column(name: 'min_price', type: 'decimal', precision: 10, scale: 2)]
    private string $minPrice;

    #[ORM\Column(name: 'max_price', type: 'decimal', precision: 10, scale: 2)]
    private string $maxPrice;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(
        Uuid $id,
        string $providerName,
        string $externalIdentity,
        string $title,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        string $minPrice,
        string $maxPrice,
        \DateTimeImmutable $lastSeenAt,
    ) {
        $this->id               = $id;
        $this->providerName     = $providerName;
        $this->externalIdentity = $externalIdentity;
        $this->title            = $title;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->minPrice   = $minPrice;
        $this->maxPrice   = $maxPrice;
        $this->lastSeenAt = $lastSeenAt;
    }

    // Update path (Doctrine ORM 3 dropped merge()): mutate the managed entity, then flush.
    // Identity columns are never rewritten.
    public function refresh(
        string $title,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        string $minPrice,
        string $maxPrice,
        \DateTimeImmutable $lastSeenAt,
    ): void {
        $this->title      = $title;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->minPrice   = $minPrice;
        $this->maxPrice   = $maxPrice;
        $this->lastSeenAt = $lastSeenAt;
    }

    public function id(): Uuid { return $this->id; }
    public function providerName(): string { return $this->providerName; }
    public function externalIdentity(): string { return $this->externalIdentity; }
    public function title(): string { return $this->title; }
    public function startDate(): \DateTimeImmutable { return $this->startDate; }
    public function endDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function minPrice(): string { return $this->minPrice; }
    public function maxPrice(): string { return $this->maxPrice; }
    public function lastSeenAt(): \DateTimeImmutable { return $this->lastSeenAt; }
}
