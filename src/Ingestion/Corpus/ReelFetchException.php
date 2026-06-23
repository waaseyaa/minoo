<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

/** Raised when a reel's media cannot be produced (download or ffmpeg failure). */
final class ReelFetchException extends \RuntimeException
{
}
