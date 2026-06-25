<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Anokii\Ingest\FetchResult;
use App\Anokii\Ingest\MediaFetcher;

/**
 * A configurable {@see MediaFetcher} for tests: never touches the network. By
 * default it is available and succeeds, writing a small placeholder file at the
 * destination so the staged-file expectation holds. Pass an unavailable flag or a
 * failure result to exercise the fail-closed paths.
 */
final class StubMediaFetcher implements MediaFetcher
{
    public function __construct(
        private readonly bool $available = true,
        private readonly ?FetchResult $result = null,
        private readonly bool $createFile = true,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function fetch(string $url, string $destPath): FetchResult
    {
        $result = $this->result ?? FetchResult::success();
        if ($result->ok && $this->createFile) {
            @mkdir(dirname($destPath), 0o777, true);
            @file_put_contents($destPath, 'stub-media');
        }

        return $result;
    }
}
