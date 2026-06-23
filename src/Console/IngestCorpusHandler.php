<?php

declare(strict_types=1);

namespace App\Console;

use App\Ingestion\Corpus\CorpusIngestor;
use App\Ingestion\Corpus\IngestedReel;
use Waaseyaa\CLI\Command\SymfonyCommandIO;

/**
 * `bin/waaseyaa ingest:corpus <manifest>` (#854).
 *
 * Reads a manifest of reels and ingests each into Minoo entities directly via
 * {@see CorpusIngestor} — the PHP port of code/ingest/ingest.py. Entities are the
 * system of record; no items/*.json is written.
 *
 * Manifest: one reel per line, `id,url[,YYYY-MM-DD]`; blank lines and `#`
 * comments ignored. Example:
 *   sb-034,https://www.facebook.com/reel/123,2026-06-20
 *
 * Prerequisite for the (non --dry-run, non --skip-vision) path: the muxed source
 * video for each id must be at `<MINOO_CORPUS_PATH>/source-videos/<id>.mp4`
 * (the Facebook pull drives a real browser and is run separately), and an LLM
 * provider must be configured (ANTHROPIC_API_KEY) for the vision draft.
 */
final class IngestCorpusHandler
{
    public function __construct(
        private readonly CorpusIngestor $ingestor,
    ) {
    }

    public function execute(SymfonyCommandIO $io): int
    {
        $manifestPath = (string) $io->argument('manifest');
        if (!is_file($manifestPath)) {
            $io->error("Manifest not found: {$manifestPath}");
            return 1;
        }

        $reels = $this->parseManifest((string) file_get_contents($manifestPath));
        if ($reels === []) {
            $io->error('Manifest has no reels (expected lines: id,url[,date]).');
            return 1;
        }

        $dryRun = (bool) $io->option('dry-run');
        $skipVision = (bool) $io->option('skip-vision');
        $limitOpt = $io->option('limit');
        $limit = is_numeric($limitOpt) ? max(1, (int) $limitOpt) : null;

        if ($dryRun) {
            $io->writeln('Dry run — no media fetched, no entities written.');
        }

        $result = $this->ingestor->ingest(
            $reels,
            $dryRun,
            $skipVision,
            $limit,
            static function (string $line) use ($io): void {
                $io->writeln($line);
            },
        );

        $io->writeln(sprintf(
            "\nDone. created=%d skipped=%d failed=%d",
            $result->created,
            $result->skipped,
            $result->failed,
        ));

        return $result->failed > 0 ? 1 : 0;
    }

    /**
     * @return list<IngestedReel>
     */
    private function parseManifest(string $raw): array
    {
        $reels = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            $id = $parts[0];
            $url = $parts[1] ?? '';
            if ($id === '' || $url === '') {
                continue;
            }

            $reels[] = new IngestedReel(
                id: $id,
                url: $url,
                sourceDate: $parts[2] ?? '',
            );
        }

        return $reels;
    }
}
