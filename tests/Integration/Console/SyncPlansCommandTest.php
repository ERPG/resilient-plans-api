<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Application\Provider\PlanProvider;
use App\Domain\Event\ProviderName;
use App\Application\Sync\SyncPlansUseCase;
use App\Domain\Event\EventRepository;
use App\Infrastructure\Console\SyncPlansCommand;
use App\Infrastructure\Provider\Http\HttpPlanProvider;
use App\Application\Provider\ProviderUnavailable;
use App\Infrastructure\Provider\Xml\XmlPlanParser;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Drives app:sync-plans against real (test) Postgres with a fake provider (deterministic feed) and
 * the real repository — proves the use case is decoupled yet fetch -> filter -> upsert -> exit works.
 */
final class SyncPlansCommandTest extends KernelTestCase
{
    private const URL = 'https://provider.example.test/api/events';

    private EntityManagerInterface $em;
    private Connection $connection;
    private EventRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em         = $container->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->repository = $container->get(EventRepository::class);

        $this->connection->executeStatement('DELETE FROM events');
        $this->em->clear();
    }

    public function testSyncPersistsOnlineEventsAndReportsCount(): void
    {
        $tester = $this->tester($this->httpProvider('response_1.xml'));

        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(4, (int) $this->connection->fetchOne('SELECT count(*) FROM events'));
        self::assertStringContainsString('4 processed', $tester->getDisplay());
    }

    public function testOfflinePlansAreSkippedAndNotPersisted(): void
    {
        $tester = $this->tester($this->httpProvider('offline_only.xml'));

        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM events'));
        self::assertStringContainsString('1 skipped', $tester->getDisplay());
    }

    public function testRunningSyncTwiceIsIdempotent(): void
    {
        $this->tester($this->httpProvider('response_1.xml'))->execute([]);
        $this->tester($this->httpProvider('response_1.xml'))->execute([]);

        self::assertSame(
            4,
            (int) $this->connection->fetchOne('SELECT count(*) FROM events'),
            'a second sync of the same feed must not duplicate rows',
        );
    }

    public function testProviderUnavailableReturnsFailureAndPersistsNothing(): void
    {
        $failing = new class implements PlanProvider {
            public function fetchEvents(): array
            {
                throw ProviderUnavailable::badStatus(503);
            }
        };

        $tester = $this->tester($failing);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM events'));
        self::assertStringContainsString('provider(s) unavailable', $tester->getDisplay());
    }

    public function testOneProviderDownDoesNotStopTheOthers(): void
    {
        $healthy = $this->httpProvider('response_1.xml');
        $down    = new class implements PlanProvider {
            public function fetchEvents(): array
            {
                throw ProviderUnavailable::badStatus(503);
            }
        };

        $command = new SyncPlansCommand(new SyncPlansUseCase([$down, $healthy], $this->repository));
        $tester  = new CommandTester($command);
        $tester->execute([]);

        // The healthy provider's events are persisted even though the other was unreachable.
        self::assertSame(4, (int) $this->connection->fetchOne('SELECT count(*) FROM events'));
        // ...but the dead provider still surfaces as a non-zero exit, never a silent success.
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('4 processed', $tester->getDisplay());
        self::assertStringContainsString('provider(s) unavailable', $tester->getDisplay());
    }

    public function testEmptyFeedIsSuccessWithZeroProcessed(): void
    {
        $tester = $this->tester($this->httpProvider('empty.xml'));

        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM events'));
    }

    private function tester(PlanProvider $provider): CommandTester
    {
        $command = new SyncPlansCommand(new SyncPlansUseCase([$provider], $this->repository));

        return new CommandTester($command);
    }

    /** Real provider stack (HTTP + XML parse) fed a fixture, so the online filter runs for real. */
    private function httpProvider(string $fixture): HttpPlanProvider
    {
        return new HttpPlanProvider($this->mockClient($fixture), new XmlPlanParser(), self::URL);
    }

    private function mockClient(string $fixture): HttpClientInterface
    {
        $body = (string) file_get_contents(__DIR__ . '/../../Fixtures/provider/' . $fixture);

        return new MockHttpClient(new MockResponse($body, ['http_code' => 200]));
    }
}
