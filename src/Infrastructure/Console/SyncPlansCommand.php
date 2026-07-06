<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Sync\SyncPlansUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Maps the sync outcome to an exit code. Unreachable providers yield FAILURE; events from providers
 * that did respond are still persisted.
 */
#[AsCommand(
    name: 'app:sync-plans',
    description: 'Fetch provider plans and upsert online events into the local store',
)]
final class SyncPlansCommand extends Command
{
    public function __construct(
        private readonly SyncPlansUseCase $useCase,
        #[Target('search.cache')]
        private readonly TagAwareCacheInterface $searchCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $report = $this->useCase->run();

        // Guard on writes, not exit code: partial failure still upserts events from healthy providers.
        if ($report->processed > 0) {
            $this->searchCache->invalidateTags(['search_results']);
        }

        if ($report->failedProviders > 0) {
            $io->error(sprintf(
                '%d processed, %d skipped (offline), %d provider(s) unavailable.',
                $report->processed,
                $report->skippedOffline,
                $report->failedProviders,
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Sync complete: %d processed, %d skipped (offline).',
            $report->processed,
            $report->skippedOffline,
        ));

        return Command::SUCCESS;
    }
}
