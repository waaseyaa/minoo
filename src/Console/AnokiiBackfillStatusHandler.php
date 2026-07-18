<?php

declare(strict_types=1);

namespace App\Console;

use App\Anokii\Pipeline\PipelineStageResolver;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * `bin/waaseyaa anokii:backfill-status [--dry-run]` (#876).
 *
 * Persists `pipeline_status` on corpus example_sentence rows that predate the
 * field, deriving each row's stage with {@see PipelineStageResolver}. Rows that
 * already carry an explicit stage are left untouched, so re-running is safe and
 * never clobbers a workspace transition. The dashboard already resolves stage on
 * the fly; this just materializes it so future transitions start from a known
 * value.
 */
final class AnokiiBackfillStatusHandler
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly PipelineStageResolver $resolver = new PipelineStageResolver(),
    ) {
    }

    public function execute(SymfonyCommandIO $io): int
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            $io->error('example_sentence entity type is not registered.');
            return 1;
        }

        $dryRun = (bool) $io->option('dry-run');
        $repository = $this->entityTypeManager->getRepository('example_sentence');

        $ids = $repository->getQuery()
            ->accessCheck(false)
            ->condition('source_sentence_id', 'corpus:%', 'LIKE')
            ->execute();

        if ($ids === []) {
            $io->writeln('No corpus rows found.');
            return 0;
        }

        $updated = 0;
        $skipped = 0;
        foreach ($repository->findMany($ids) as $sentence) {
            $existing = trim((string) ($sentence->get('pipeline_status') ?? ''));
            if ($existing !== '') {
                ++$skipped;
                continue;
            }

            $stage = $this->resolver->resolve($sentence);
            $io->writeln(sprintf('%s #%s -> %s', $dryRun ? '[dry-run]' : '[set]', $sentence->id(), $stage));

            if (!$dryRun) {
                $sentence->set('pipeline_status', $stage);
                $sentence->set('updated_at', time());
                $repository->save($sentence);
            }
            ++$updated;
        }

        $io->writeln(sprintf("\nDone. %s=%d skipped(explicit)=%d", $dryRun ? 'would-update' : 'updated', $updated, $skipped));

        return 0;
    }
}
