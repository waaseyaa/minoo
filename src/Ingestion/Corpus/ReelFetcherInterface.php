<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

/**
 * Fetches a reel's media into the corpus directory: a whiteboard keyframe and an
 * audio clip. The Facebook download + ffmpeg muxing live behind this seam so the
 * ingest orchestration ({@see CorpusIngestor}) can be tested without network or
 * system tools.
 */
interface ReelFetcherInterface
{
    /**
     * @return array{keyframe: string, audio: string} absolute paths under the corpus dir
     *
     * @throws ReelFetchException when the media could not be produced
     */
    public function fetch(string $reelUrl, string $itemId): array;
}
