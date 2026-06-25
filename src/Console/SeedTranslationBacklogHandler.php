<?php

declare(strict_types=1);

namespace App\Console;

use App\Seed\TranslationBacklogData;
use App\Seed\TranslationBacklogSeeder;
use Waaseyaa\CLI\Command\SymfonyCommandIO;

/**
 * `bin/waaseyaa lang:seed-backlog` (#906): idempotent seed of the demand-ranked
 * English translation backlog from the committed dataset.
 *
 * Dev convenience only - production's ConsoleKernel is broken (#493), so the
 * prod path is `scripts/seed_translation_backlog.php` (HttpKernel reflection),
 * which calls the same {@see TranslationBacklogSeeder}.
 */
final class SeedTranslationBacklogHandler
{
    public function __construct(
        private readonly TranslationBacklogSeeder $seeder,
        private readonly string $projectRoot,
    ) {
    }

    public function execute(SymfonyCommandIO $io): int
    {
        $rows = TranslationBacklogData::load($this->projectRoot);
        $result = $this->seeder->seed($rows);

        $io->writeln(sprintf(
            'tm_backlog seeded: %d created, %d updated, %d total.',
            $result['created'],
            $result['updated'],
            $result['total'],
        ));
        foreach ($result['by_category'] as $category => $count) {
            $io->writeln(sprintf('  %-14s %d', $category, $count));
        }

        return 0;
    }
}
