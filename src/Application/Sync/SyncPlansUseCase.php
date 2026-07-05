<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Provider\PlanProvider;
use App\Domain\Event\EventRepository;
use App\Infrastructure\Provider\ProviderUnavailable;

/**
 * Orchestrates the sync through the two ports only (no Doctrine/HttpClient), so it unit-tests
 * without Symfony. Runs every configured provider; one unreachable provider is isolated (caught and
 * counted) so it can't stop the others from syncing.
 */
final readonly class SyncPlansUseCase
{
    /** @param iterable<PlanProvider> $providers */
    public function __construct(
        private iterable $providers,
        private EventRepository $repository,
    ) {}

    public function run(): SyncReport
    {
        $processed = 0;
        $skippedOffline = 0;
        $failedProviders = 0;

        foreach ($this->providers as $provider) {
            try {
                $events = $provider->fetchEvents();
            } catch (ProviderUnavailable) {
                // One provider being down must not abort the run — count it and move on.
                ++$failedProviders;
                continue;
            }

            foreach ($events as $event) {
                // Business rule kept out of the parser: the marketplace only sells online plans.
                if (!$event->isOnline()) {
                    ++$skippedOffline;
                    continue;
                }

                $this->repository->save($event);
                ++$processed;
            }
        }

        return new SyncReport($processed, $skippedOffline, $failedProviders);
    }
}
