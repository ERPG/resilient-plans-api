<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Event\Event;
use App\Domain\Event\EventRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\EventRecord;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the EventRepository port. Upsert with plain ORM (not a native DBAL upsert):
 * at this volume the extra find() is irrelevant and the code stays engine-portable. Nothing is
 * ever deleted — an event that drops out of the feed just stops being refreshed.
 */
final readonly class DoctrineEventRepository implements EventRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventMapper $mapper,
    ) {}

    public function save(Event $event): void
    {
        $record = $this->em->find(EventRecord::class, $this->mapper->id($event));

        if ($record === null) {
            $this->em->persist($this->mapper->toRecord($event));
        } else {
            $this->mapper->refresh($record, $event);
        }

        $this->em->flush();
    }
}
