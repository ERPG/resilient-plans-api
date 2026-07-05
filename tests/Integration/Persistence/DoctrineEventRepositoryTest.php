<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Event\Event;
use App\Domain\Event\EventRepository;
use App\Domain\Event\SellMode;
use App\Domain\Event\Zone;
use App\Infrastructure\Persistence\Doctrine\EventMapper;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test against a real (test) Postgres database. Exercises the persistence rules that
 * unit tests can't reach: the derived UUID id, upsert idempotency on the business identity, the
 * never-delete/refresh behaviour, and that a repeated plan_id under a different base_plan is a
 * distinct row.
 */
final class DoctrineEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;
    private EventRepository $repository;
    private EventMapper $mapper;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em         = $container->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->repository = $container->get(EventRepository::class);
        $this->mapper     = $container->get(EventMapper::class);

        // Clean slate. No physical deletion happens in production code — this is test hygiene only.
        $this->connection->executeStatement('DELETE FROM events');
        $this->em->clear();
    }

    public function testSaveInsertsOneRowWithDerivedIdAndComputedPrices(): void
    {
        $event = $this->makeEvent(
            basePlanId: '322',
            planId:     '1642',
            zones:      [
                new Zone(zoneId: 'z1', capacity: 0,  price: 5.0,  name: 'Sold out', numbered: false),
                new Zone(zoneId: 'z2', capacity: 10, price: 15.0, name: 'Grada',     numbered: false),
                new Zone(zoneId: 'z3', capacity: 5,  price: 30.0, name: 'Platea',    numbered: true),
            ],
        );

        $this->repository->save($event);

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM events');
        self::assertCount(1, $rows);

        $row = $rows[0];
        self::assertSame($this->mapper->id($event)->toRfc4122(), $row['id']);
        self::assertSame('322', $row['base_plan_id']);
        self::assertSame('1642', $row['plan_id']);
        self::assertSame('15.00', $row['min_price']); // capacity=0 zone excluded
        self::assertSame('30.00', $row['max_price']);
        self::assertNotEmpty($row['last_seen_at']);
    }

    public function testSaveOnSameIdentityUpdatesInPlaceAndNeverDuplicates(): void
    {
        $first = $this->makeEvent(basePlanId: '322', planId: '1642', zones: [
            new Zone(zoneId: 'z1', capacity: 10, price: 20.0, name: 'A', numbered: false),
        ]);
        $this->repository->save($first);

        $seenAtBefore = $this->connection->fetchOne('SELECT last_seen_at FROM events');

        // Same business identity, different price — a later sync of the same plan.
        $second = $this->makeEvent(basePlanId: '322', planId: '1642', zones: [
            new Zone(zoneId: 'z1', capacity: 10, price: 99.0, name: 'A', numbered: false),
        ]);
        $this->repository->save($second);

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM events');
        self::assertCount(1, $rows, 'upsert must not create a second row for the same identity');
        self::assertSame('99.00', $rows[0]['min_price']);
        self::assertSame('99.00', $rows[0]['max_price']);
        self::assertGreaterThanOrEqual($seenAtBefore, $rows[0]['last_seen_at']);
    }

    public function testSamePlanIdUnderDifferentBasePlanAreDistinctRows(): void
    {
        // plan_id 1642 legitimately appears under two different base_plans in one feed response.
        $a = $this->makeEvent(basePlanId: '322',  planId: '1642');
        $b = $this->makeEvent(basePlanId: '1591', planId: '1642');

        $this->repository->save($a);
        $this->repository->save($b);

        $ids = $this->connection->fetchFirstColumn('SELECT id FROM events ORDER BY base_plan_id');
        self::assertCount(2, $ids);
        self::assertNotSame($ids[0], $ids[1]);
        self::assertSame($this->mapper->id($a)->toRfc4122(), $this->connection->fetchOne(
            'SELECT id FROM events WHERE base_plan_id = ?', ['322'],
        ));
    }

    /** @param Zone[] $zones */
    private function makeEvent(
        string $basePlanId,
        string $planId,
        array $zones = [],
    ): Event {
        return new Event(
            basePlanId: $basePlanId,
            planId:     $planId,
            title:      'Test Event',
            sellMode:   SellMode::Online,
            startDate:  new \DateTimeImmutable('2021-06-30T21:00:00+00:00'),
            endDate:    new \DateTimeImmutable('2021-06-30T22:00:00+00:00'),
            zones:      $zones ?: [new Zone(zoneId: 'z1', capacity: 1, price: 10.0, name: 'A', numbered: false)],
        );
    }
}
