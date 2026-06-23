<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

/**
 * One reel to ingest: a corpus id + its source video URL, plus optional
 * provenance the manifest may carry. Produced by parsing the ingest manifest.
 */
final readonly class IngestedReel
{
    public function __construct(
        public string $id,
        public string $url,
        public string $sourceDate = '',
        public string $contributor = 'Steven Bennett',
    ) {
    }
}
