<?php

declare(strict_types=1);

namespace App\Anokii\Ingest;

/**
 * The seam for fetching reel media from a URL into the corpus staging area
 * (issue #904).
 *
 * PHP cannot extract Facebook / Instagram / YouTube media directly, so the
 * default implementation shells out to yt-dlp as a backend process (the same
 * worker-separation used for ASR). Kept behind this interface so the extractor is
 * swappable by binding, and so tests can substitute a fake and never hit the
 * network. Implementations fail closed: an unavailable extractor or an
 * unfetchable URL returns a {@see FetchResult} failure, never an exception that
 * would surface as a 500.
 */
interface MediaFetcher
{
    /**
     * Whether media fetching is available on this install (the backend extractor
     * is installed and runnable).
     */
    public function isAvailable(): bool;

    /**
     * Fetch the media at $url into $destPath. Returns a non-ok result rather than
     * throwing for the expected failure cases (private reel, unsupported host,
     * extractor missing).
     */
    public function fetch(string $url, string $destPath): FetchResult;
}
