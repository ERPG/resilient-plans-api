<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Sync\SyncPlansUseCase;
use App\Infrastructure\Provider\ProviderUnavailable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Thin trigger-agnostic delivery layer: maps the sync outcome to an exit code. A dead provider
 * yields FAILURE with "0 processed", never an uncaught stack trace. (Rationale in README.private.md.)
 */
#[AsCommand(
    name: 'app:sync-plans',
    description: 'Fetch provider plans and upsert online events into the local store',
)]
final class SyncPlansCommand extends Command
{
    public function __construct(
        private readonly SyncPlansUseCase $useCase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $report = $this->useCase->run();
        } catch (ProviderUnavailable $e) {
            $io->error(sprintf('Provider unavailable, 0 processed: %s', $e->getMessage()));

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
