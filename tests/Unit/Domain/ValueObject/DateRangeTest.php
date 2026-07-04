<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\DateRange;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    private DateRange $june;

    protected function setUp(): void
    {
        $this->june = new DateRange(
            new \DateTimeImmutable('2021-06-01T00:00:00+00:00'),
            new \DateTimeImmutable('2021-06-30T23:59:59+00:00'),
        );
    }

    public function testContainsTrueWhenEventTotallyInsideRange(): void
    {
        self::assertTrue($this->june->contains(
            new \DateTimeImmutable('2021-06-10T10:00:00+00:00'),
            new \DateTimeImmutable('2021-06-10T12:00:00+00:00'),
        ));
    }

    public function testContainsFalseWhenStartDateBeforeRangeStart(): void
    {
        self::assertFalse($this->june->contains(
            new \DateTimeImmutable('2021-05-31T23:59:00+00:00'),
            new \DateTimeImmutable('2021-06-10T12:00:00+00:00'),
        ));
    }

    public function testContainsFalseWhenStartDateAfterRangeEndAndEndDateNull(): void
    {
        // Bug-fix: event with startDate beyond range.end and no endDate must not pass
        self::assertFalse($this->june->contains(
            new \DateTimeImmutable('2021-07-01T00:00:00+00:00'),
            null,
        ));
    }

    public function testContainsFalseWhenEndDateAfterRangeEnd(): void
    {
        self::assertFalse($this->june->contains(
            new \DateTimeImmutable('2021-06-20T10:00:00+00:00'),
            new \DateTimeImmutable('2021-07-05T12:00:00+00:00'),
        ));
    }

    public function testContainsTrueWhenEndDateNullAndStartDateInsideRange(): void
    {
        self::assertTrue($this->june->contains(
            new \DateTimeImmutable('2021-06-15T10:00:00+00:00'),
            null,
        ));
    }
}
