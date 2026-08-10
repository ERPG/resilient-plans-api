<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Event\Event;
use App\Infrastructure\Persistence\Doctrine\Entity\EventRecord;
use Symfony\Component\Uid\Uuid;

/**
 * Maps the domain Event to its Doctrine model. The id is a deterministic UUIDv5 of
 * (providerName, externalIdentity): the spec wants a uuid, deriving it (vs random) keeps it stable
 * without a DB read, and folding providerName in keeps two providers from colliding when they reuse
 * the same external id. min/max arrive already computed; zones aren't persisted.
 */
final class EventMapper
{
    // Fixed UUIDv5 namespace. Not a secret (v5 is public); it must just never change — a new value
    // reassigns every id and the upsert would duplicate. Schema identity, so code not env.
    private const NAMESPACE_NAME = 'urn:resilient-plans-api:events';

    public function id(Event $event): Uuid
    {
        return Uuid::v5(
            $this->namespace(),
            $event->providerName()->value . ':' . $event->externalIdentity(),
        );
    }

    private function namespace(): Uuid
    {
        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), self::NAMESPACE_NAME);
    }

    public function toRecord(Event $event): EventRecord
    {
        return new EventRecord(
            $this->id($event),
            $event->providerName()->value,
            $event->externalIdentity(),
            $event->title(),
            $event->startDate(),
            $event->endDate(),
            $this->decimal($event->minPrice()),
            $this->decimal($event->maxPrice()),
            $this->now(),
        );
    }

    public function refresh(EventRecord $record, Event $event): void
    {
        $record->refresh(
            $event->title(),
            $event->startDate(),
            $event->endDate(),
            $this->decimal($event->minPrice()),
            $this->decimal($event->maxPrice()),
            $this->now(),
        );
    }

    private function decimal(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
