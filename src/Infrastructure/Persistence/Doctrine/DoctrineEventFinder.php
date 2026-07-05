<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Search\EventFinder;
use App\Application\Search\EventSummary;
use App\Infrastructure\Persistence\Doctrine\Entity\EventRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEventFinder implements EventFinder
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findBetween(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('e')
            ->from(EventRecord::class, 'e')
            // Decision #14 containment, not a plain BETWEEN: a bounded event must fit whole, an
            // unbounded one (no end_date) matches on its start alone — no upper bound on start.
            ->where(
                'e.startDate >= :starts AND ('
                . '(e.endDate IS NULL AND e.startDate <= :ends) '
                . 'OR (e.endDate IS NOT NULL AND e.endDate <= :ends)'
                . ')'
            )
            ->orderBy('e.startDate', 'ASC')
            ->setParameter('starts', $startsAt)
            ->setParameter('ends', $endsAt)
            ->getQuery()
            ->getArrayResult();

        return array_map($this->toSummary(...), $rows);
    }

    /** @param array{id: \Symfony\Component\Uid\Uuid, title: string, startDate: \DateTimeImmutable, endDate: ?\DateTimeImmutable, minPrice: string, maxPrice: string} $row */
    private function toSummary(array $row): EventSummary
    {
        return new EventSummary(
            id:        $row['id']->toRfc4122(),
            title:     $row['title'],
            startDate: $row['startDate']->format('Y-m-d'),
            startTime: $row['startDate']->format('H:i:s'),
            endDate:   $row['endDate']?->format('Y-m-d'),
            endTime:   $row['endDate']?->format('H:i:s'),
            minPrice:  (float) $row['minPrice'],
            maxPrice:  (float) $row['maxPrice'],
        );
    }
}
