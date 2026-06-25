<?php

declare(strict_types=1);

namespace App\Anokii\Ingest;

/**
 * The outcome of a {@see MediaFetcher} fetch (issue #904): either the media was
 * staged (ok) or it failed with a user-facing reason (a private/login-walled
 * reel, an unsupported host, or yt-dlp being unavailable). A value object so the
 * Ingest controller branches on `ok` and surfaces `error` inline, never a 500.
 */
final readonly class FetchResult
{
    private function __construct(
        public bool $ok,
        public string $error,
    ) {
    }

    public static function success(): self
    {
        return new self(true, '');
    }

    public static function failure(string $error): self
    {
        return new self(false, $error);
    }
}
