<?php

declare(strict_types=1);

namespace App\Console;

use App\Anokii\Pipeline\PipelineStage;
use App\Ingestion\Corpus\ReelProcessor;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * `bin/waaseyaa anokii:process-reels [--limit=N] [--loop] [--sleep=N]` (#877).
 *
 * The async worker behind the Anokii drag-and-drop ingest. The upload endpoint
 * only stages the file + creates an `ingested` draft and returns immediately;
 * this command does the heavy lifting out-of-band: it finds `pipeline_status=
 * ingested` example_sentence rows and runs each through {@see ReelProcessor}
 * (ffmpeg keyframe + opus, then the vision draft), advancing it to `drafted`.
 *
 * On the Pi this runs as a long-lived service (`--loop`), which is the worker.
 * Locally it can be run once to drain the queue. A row is a plain DI service
 * call here (full container access), not a serialized framework Queue Job —
 * deserialized jobs can't resolve EntityTypeManager at handle() time.
 */
final class ProcessReelsHandler
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ReelProcessor $processor,
    ) {
    }

    public function execute(SymfonyCommandIO $io): int
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            $io->error('example_sentence entity type is not registered.');
            return 1;
        }

        $limitOpt = $io->option('limit');
        $limit = is_numeric($limitOpt) ? max(1, (int) $limitOpt) : null;
        $loop = (bool) $io->option('loop');
        $sleepOpt = $io->option('sleep');
        $sleep = is_numeric($sleepOpt) ? max(1, (int) $sleepOpt) : 5;

        $skipVision = (bool) $io->option('skip-vision');

        do {
            $processed = $this->drain($limit, $skipVision, $io);
            if ($loop && $processed === 0) {
                sleep($sleep);
            }
        } while ($loop);

        return 0;
    }

    private function drain(?int $limit, bool $skipVision, SymfonyCommandIO $io): int
    {
        $storage = $this->entityTypeManager->getStorage('example_sentence');
        $ids = $storage->getQuery()
            ->accessCheck(false)
            ->condition('pipeline_status', PipelineStage::INGESTED)
            ->condition('source_sentence_id', 'corpus:%', 'LIKE')
            ->sort('created_at', 'ASC')
            ->execute();

        if ($ids === []) {
            return 0;
        }

        $processed = 0;
        foreach ($ids as $id) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }
            $result = $this->processor->process((int) $id, $skipVision);
            if ($result['error'] !== null) {
                $io->writeln(sprintf('[fail] #%d: %s', $result['esid'], $result['error']));
            } else {
                $io->writeln(sprintf('[ok] #%d -> %s', $result['esid'], $result['status']));
            }
            ++$processed;
        }

        return $processed;
    }
}
