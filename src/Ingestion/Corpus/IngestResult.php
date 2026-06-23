<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

/** Tally of a corpus ingest run. */
final readonly class IngestResult
{
    public function __construct(
        public int $created,
        public int $skipped,
        public int $failed,
    ) {
    }
}
