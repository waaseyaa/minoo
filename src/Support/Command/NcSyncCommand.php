<?php

declare(strict_types=1);

namespace App\Support\Command;

use App\Ingestion\NcContentSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ingest:nc-sync', description: 'Pull indigenous content from NorthCloud Search API into Minoo')]
final class NcSyncCommand extends Command
{
    public function __construct(private readonly NcContentSyncService $syncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum articles to fetch', '20');
        $this->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Fetch content from this date (YYYY-MM-DD)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be created without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $since = $input->getOption('since');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('<info>Dry run — no entities will be created.</info>');
        }

        $output->writeln(sprintf('Fetching up to %d articles from NorthCloud...', $limit));

        $result = $this->syncService->sync($limit, $since, $dryRun);

        if ($result->fetchFailed) {
            $output->writeln('<error>Failed to fetch content from NorthCloud. Check NORTHCLOUD_BASE_URL and network connectivity.</error>');
            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Done.</info> Created: %d | Skipped (duplicate): %d | Failed: %d',
            $result->created,
            $result->skipped,
            $result->failed,
        ));

        $this->writeStatusFile($result);

        return $result->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function writeStatusFile(\App\Ingestion\NcSyncResult $result): void
    {
        $statusPath = dirname(__DIR__, 3) . '/storage/nc-sync-status.json';

        try {
            $data = json_encode([
                'last_sync' => date('c'),
                'created' => $result->created,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'fetch_failed' => $result->fetchFailed,
                'cycles' => 1,
                'last_manual_run' => date('c'),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException) {
            return;
        }

        $tmp = $statusPath . '.tmp';
        if (file_put_contents($tmp, $data) === false) {
            return;
        }
        rename($tmp, $statusPath);
    }
}
