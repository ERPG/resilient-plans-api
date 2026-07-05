<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Provider\Xml;

use App\Domain\Event\Event;
use App\Domain\Event\ProviderName;
use App\Domain\Event\SellMode;
use App\Infrastructure\Provider\ProviderUnavailable;
use App\Infrastructure\Provider\Xml\XmlPlanParser;
use PHPUnit\Framework\TestCase;

final class XmlPlanParserTest extends TestCase
{
    private const PROVIDER = ProviderName::FeverUp;

    private XmlPlanParser $parser;

    protected function setUp(): void
    {
        $this->parser = new XmlPlanParser();
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../../Fixtures/provider/' . $name);
    }

    /**
     * @param Event[] $events
     * @return string[]
     */
    private function identities(array $events): array
    {
        return array_map(fn(Event $e) => $e->externalIdentity(), $events);
    }

    public function testEachPlanChildBecomesOneEvent(): void
    {
        // base_plan 291 (1 plan) + 322 (2 plans) + 1591 (1 plan) = 4 events
        $events = $this->parser->parse($this->fixture('response_1.xml'), self::PROVIDER);

        self::assertCount(4, $events);
        self::assertContainsOnlyInstancesOf(Event::class, $events);
    }

    public function testPlanIdIsNotUniqueSoIdentityFoldsInTheBasePlan(): void
    {
        $events = $this->parser->parse($this->fixture('response_1.xml'), self::PROVIDER);

        // plan_id 1642 appears under BOTH base_plan 322 and 1591 in the same response: the adapter
        // folds base_plan_id into externalIdentity ("{base_plan_id}:{plan_id}") so they stay distinct.
        self::assertEqualsCanonicalizing(
            ['291:291', '322:1642', '322:1643', '1591:1642'],
            $this->identities($events),
        );
    }

    public function testMapsTitleSellModeAndDatesAsUtc(): void
    {
        $events = $this->parser->parse($this->fixture('response_1.xml'), self::PROVIDER);
        $event  = $this->eventWith($events, externalIdentity: '291:291');

        self::assertSame('Camela en concierto', $event->title());
        self::assertSame(SellMode::Online, $event->sellMode());
        self::assertSame('2021-06-30T21:00:00+00:00', $event->startDate()->format('c'));
        self::assertSame('2021-06-30T22:00:00+00:00', $event->endDate()?->format('c'));
    }

    public function testDuplicateZoneIdsAreBothKept(): void
    {
        $events = $this->parser->parse($this->fixture('response_1.xml'), self::PROVIDER);
        $event  = $this->eventWith($events, externalIdentity: '1591:1642');

        // Both zones share zone_id 186 — the parser must not deduplicate them.
        self::assertSame(75.0, $event->maxPrice());
        self::assertSame(65.0, $event->minPrice());
    }

    public function testOfflinePlansAreParsedAndFlaggedNotOnline(): void
    {
        // Offline filtering is a downstream concern; the parser still emits offline events.
        $events = $this->parser->parse($this->fixture('offline_only.xml'), self::PROVIDER);

        self::assertCount(1, $events);
        self::assertFalse($events[0]->isOnline());
        self::assertSame(SellMode::Offline, $events[0]->sellMode());
    }

    public function testEmptyOutputYieldsNoEvents(): void
    {
        self::assertSame([], $this->parser->parse($this->fixture('empty.xml'), self::PROVIDER));
    }

    public function testPlanWithImpossibleDateIsSkippedNotRolledOver(): void
    {
        // 2021-09-31 must NOT silently become 2021-10-01; the plan is dropped, the feed survives.
        $events = $this->parser->parse($this->fixture('invalid_date.xml'), self::PROVIDER);

        self::assertCount(1, $events);
        self::assertSame('701:901', $events[0]->externalIdentity());
        self::assertSame('2021-10-01T20:00:00+00:00', $events[0]->startDate()->format('c'));
    }

    public function testMalformedDocumentRaisesProviderUnavailable(): void
    {
        $this->expectException(ProviderUnavailable::class);

        $this->parser->parse($this->fixture('malformed.xml'), self::PROVIDER);
    }

    /** @param Event[] $events */
    private function eventWith(array $events, string $externalIdentity): Event
    {
        foreach ($events as $event) {
            if ($event->externalIdentity() === $externalIdentity) {
                return $event;
            }
        }

        self::fail(sprintf('No event found for identity %s.', $externalIdentity));
    }
}
