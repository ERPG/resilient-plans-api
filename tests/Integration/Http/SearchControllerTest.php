<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Application\Provider\PlanProvider;
use App\Domain\Event\Event;
use App\Domain\Event\EventRepository;
use App\Domain\Event\ProviderName;
use App\Domain\Event\SellMode;
use App\Domain\Event\Zone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test of GET /search against a real (test) Postgres, seeded directly — no provider in
 * the loop. Covers the contract envelope + EventSummary shape, the decision #14 containment rule
 * (bounded vs unbounded events), the 400 validation cases, and — the hard requirement — that the
 * endpoint answers identically when the external provider is completely unreachable.
 */
final class SearchControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private EventRepository $repository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em         = $container->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->repository = $container->get(EventRepository::class);

        $this->connection->executeStatement('DELETE FROM events');
        $this->em->clear();
    }

    public function testReturnsContractEnvelopeAndSummaryShape(): void
    {
        $this->repository->save($this->event(
            basePlanId: '100',
            title:      'Bounded event',
            start:      '2021-06-15T21:00:00+00:00',
            end:        '2021-06-20T23:30:00+00:00',
            zones:      [
                new Zone(zoneId: 'z1', capacity: 10, price: 20.0, name: 'Grada', numbered: false),
                new Zone(zoneId: 'z2', capacity: 5,  price: 50.0, name: 'Platea', numbered: true),
            ],
        ));

        $this->client->request('GET', '/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->decode();

        self::assertNull($body['error']);
        self::assertCount(1, $body['data']['events']);

        $summary = $body['data']['events'][0];
        self::assertSame('Bounded event', $summary['title']);
        self::assertSame('2021-06-15', $summary['start_date']);
        self::assertSame('21:00:00', $summary['start_time']);
        self::assertSame('2021-06-20', $summary['end_date']);
        self::assertSame('23:30:00', $summary['end_time']);
        self::assertSame(20.0, (float) $summary['min_price']);
        self::assertSame(50.0, (float) $summary['max_price']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $summary['id']);
    }

    public function testUnboundedEventExposesNullEndFields(): void
    {
        $this->repository->save($this->event(
            basePlanId: '200',
            title:      'Open-ended event',
            start:      '2021-06-10T18:00:00+00:00',
            end:        null,
        ));

        $this->client->request('GET', '/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z');

        $summary = $this->decode()['data']['events'][0];
        self::assertSame('2021-06-10', $summary['start_date']);
        self::assertNull($summary['end_date']);
        self::assertNull($summary['end_time']);
    }

    public function testAppliesDecision14ContainmentRule(): void
    {
        // In range.
        $this->repository->save($this->event('10', 'Bounded inside',   '2021-06-15T10:00:00+00:00', '2021-06-20T10:00:00+00:00'));
        $this->repository->save($this->event('11', 'Unbounded inside', '2021-06-10T10:00:00+00:00', null));
        // Out of range.
        $this->repository->save($this->event('12', 'Bounded ends after',    '2021-06-15T10:00:00+00:00', '2021-07-15T10:00:00+00:00'));
        $this->repository->save($this->event('13', 'Unbounded starts after', '2021-07-05T10:00:00+00:00', null));
        $this->repository->save($this->event('14', 'Bounded starts before',  '2021-05-20T10:00:00+00:00', '2021-06-05T10:00:00+00:00'));

        $this->client->request('GET', '/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z');

        $titles = array_column($this->decode()['data']['events'], 'title');
        sort($titles);
        self::assertSame(['Bounded inside', 'Unbounded inside'], $titles);
    }

    /** @return array<string, string> name => query string (each already missing/broken in one way) */
    public static function badRequestProvider(): array
    {
        return [
            'missing ends_at'      => ['starts_at=2021-06-01T00:00:00Z'],
            'missing starts_at'    => ['ends_at=2021-06-30T00:00:00Z'],
            'both missing'         => [''],
            'unparseable date'     => ['starts_at=nonsense&ends_at=2021-06-30T00:00:00Z'],
            // Calendar overflow: Sept has 30 days. createFromFormat would silently normalise this
            // to 2021-10-01 (shifting the range a day) — the warning_count guard must reject it.
            'nonexistent calendar day' => ['starts_at=2021-09-31T20:00:00Z&ends_at=2021-12-31T00:00:00Z'],
            'starts equals ends'   => ['starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-01T00:00:00Z'],
            'starts after ends'    => ['starts_at=2021-07-01T00:00:00Z&ends_at=2021-06-01T00:00:00Z'],
        ];
    }

    #[DataProvider('badRequestProvider')]
    public function testInvalidRequestsReturn400Envelope(string $query): void
    {
        $this->client->request('GET', '/search?' . $query);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        $body = $this->decode();
        self::assertNull($body['data']);
        self::assertSame('invalid_request', $body['error']['code']);
        self::assertNotEmpty($body['error']['message']);
    }

    public function testAnswersIdenticallyWhenProviderIsUnreachable(): void
    {
        $this->repository->save($this->event('300', 'Still searchable', '2021-06-15T21:00:00+00:00', '2021-06-16T00:00:00+00:00'));

        // Any attempt to reach the provider during a search would blow up loudly.
        static::getContainer()->set(PlanProvider::class, new class implements PlanProvider {
            public function fetchEvents(): array
            {
                throw new \RuntimeException('the search endpoint must never call the provider');
            }
        });

        $this->client->request('GET', '/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $events = $this->decode()['data']['events'];
        self::assertSame('Still searchable', $events[0]['title']);
    }

    /** @return array<string, mixed> */
    private function decode(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param Zone[] $zones */
    private function event(string $basePlanId, string $title, string $start, ?string $end, array $zones = []): Event
    {
        return new Event(
            providerName:     ProviderName::Demo,
            externalIdentity: $basePlanId . ':1',
            title:            $title,
            sellMode:         SellMode::Online,
            startDate:        new \DateTimeImmutable($start),
            endDate:          $end === null ? null : new \DateTimeImmutable($end),
            zones:            $zones ?: [new Zone(zoneId: 'z1', capacity: 1, price: 10.0, name: 'A', numbered: false)],
        );
    }
}
