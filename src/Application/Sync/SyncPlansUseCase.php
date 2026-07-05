<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Provider\PlanProvider;
use App\Domain\Event\EventRepository;

/**
 * Orchestrates the sync through the two ports only (no Doctrine/HttpClient), so it unit-tests
 * without Symfony. ProviderUnavailable is left to propagate — the command maps it to an exit code.
 */
final readonly class SyncPlansUseCase
{
    public function __construct(
        private PlanProvider $provider,
        private EventRepository $repository,
    ) {}

    public function run(): SyncReport
    {
        $processed = 0;
        $skippedOffline = 0;

        foreach ($this->provider->fetchEvents() as $event) {
            // Business rule kept out of the parser: the marketplace only sells online plans.
            if (!$event->isOnline()) {
                ++$skippedOffline;
                continue;
            }

            $this->repository->save($event);
            ++$processed;
        }

        return new SyncReport($processed, $skippedOffline);
    }
}
