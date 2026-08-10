<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Event;

use App\Domain\Event\Event;
use App\Domain\Event\ProviderName;
use App\Domain\Event\SellMode;
use App\Domain\Event\Zone;
use App\Domain\ValueObject\DateRange;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    private function makeEvent(
        SellMode $sellMode = SellMode::Online,
        array $zones = [],
        string $startDate = '2021-06-01T10:00:00+00:00',
        ?string $endDate   = '2021-06-01T12:00:00+00:00',
    ): Event {
        return new Event(
            providerName:     ProviderName::Demo,
            externalIdentity: '291:123',
            title:            'Test Event',
            sellMode:         $sellMode,
            startDate:        new \DateTimeImmutable($startDate),
            endDate:          $endDate ? new \DateTimeImmutable($endDate) : null,
            zones:            $zones,
        );
    }

    private function makeZone(int $capacity, float $price, string $zoneId = 'z1'): Zone
    {
        return new Zone(zoneId: $zoneId, capacity: $capacity, price: $price, name: 'General', numbered: false);
    }

    public function testIsOnlineTrueForOnlineSellMode(): void
    {
        self::assertTrue($this->makeEvent(SellMode::Online)->isOnline());
    }

    public function testIsOnlineFalseForOfflineSellMode(): void
    {
        self::assertFalse($this->makeEvent(SellMode::Offline)->isOnline());
    }

    public function testMinPriceExcludesZonesWithZeroCapacity(): void
    {
        $zones = [
            $this->makeZone(capacity: 0,  price: 5.0,  zoneId: 'z1'),
            $this->makeZone(capacity: 10, price: 15.0, zoneId: 'z2'),
            $this->makeZone(capacity: 5,  price: 30.0, zoneId: 'z3'),
        ];

        self::assertSame(15.0, $this->makeEvent(zones: $zones)->minPrice());
    }

    public function testMaxPriceExcludesZonesWithZeroCapacity(): void
    {
        $zones = [
            $this->makeZone(capacity: 10, price: 15.0, zoneId: 'z1'),
            $this->makeZone(capacity: 5,  price: 30.0, zoneId: 'z2'),
            $this->makeZone(capacity: 0,  price: 99.0, zoneId: 'z3'),
        ];

        self::assertSame(30.0, $this->makeEvent(zones: $zones)->maxPrice());
    }

    public function testDuplicateZoneIdsAllContributeToPrice(): void
    {
        // zone_id duplication is real — both zones must be iterated, not deduplicated
        $zones = [
            $this->makeZone(capacity: 10, price: 10.0, zoneId: 'z1'),
            $this->makeZone(capacity: 5,  price: 50.0, zoneId: 'z1'),
        ];

        self::assertSame(10.0, $this->makeEvent(zones: $zones)->minPrice());
        self::assertSame(50.0, $this->makeEvent(zones: $zones)->maxPrice());
    }

    public function testPriceIsZeroPointZeroWhenAllZonesHaveZeroCapacity(): void
    {
        $zones = [
            $this->makeZone(capacity: 0, price: 15.0, zoneId: 'z1'),
            $this->makeZone(capacity: 0, price: 30.0, zoneId: 'z2'),
        ];

        self::assertSame(0.0, $this->makeEvent(zones: $zones)->minPrice());
        self::assertSame(0.0, $this->makeEvent(zones: $zones)->maxPrice());
    }

    public function testFallsWithinReturnsTrueWhenEventIsInsideRange(): void
    {
        $range = new DateRange(
            new \DateTimeImmutable('2021-06-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+00:00'),
        );

        $event = $this->makeEvent(
            startDate: '2021-06-15T10:00:00+00:00',
            endDate:   '2021-06-15T12:00:00+00:00',
        );

        self::assertTrue($event->fallsWithin($range));
    }

    public function testFallsWithinReturnsFalseWhenStartDateBeforeRangeStart(): void
    {
        $range = new DateRange(
            new \DateTimeImmutable('2021-06-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+00:00'),
        );

        $event = $this->makeEvent(
            startDate: '2021-05-20T10:00:00+00:00',
            endDate:   '2021-06-10T12:00:00+00:00',
        );

        self::assertFalse($event->fallsWithin($range));
    }

    public function testFallsWithinReturnsFalseWhenStartDateAfterRangeEndAndEndDateNull(): void
    {
        // Bug-fix case: startDate beyond range.end, endDate null — must return false
        $range = new DateRange(
            new \DateTimeImmutable('2021-06-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+00:00'),
        );

        $event = $this->makeEvent(
            startDate: '2021-08-01T10:00:00+00:00',
            endDate:   null,
        );

        self::assertFalse($event->fallsWithin($range));
    }

    public function testFallsWithinReturnsTrueWhenEndDateNullAndStartDateInsideRange(): void
    {
        $range = new DateRange(
            new \DateTimeImmutable('2021-06-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+00:00'),
        );

        $event = $this->makeEvent(
            startDate: '2021-06-15T10:00:00+00:00',
            endDate:   null,
        );

        self::assertTrue($event->fallsWithin($range));
    }
}
