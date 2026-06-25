<?php

declare(strict_types=1);

namespace App\Anokii\Ingest;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * yt-dlp-backed {@see MediaFetcher} (issue #904).
 *
 * yt-dlp (open source) handles Facebook / Instagram / YouTube extraction, which
 * PHP cannot do directly, so this shells out to it as a backend process. The
 * binary is resolved from the `YT_DLP_BIN` env or PATH, so the extractor is
 * swappable without code changes. Fails closed: when yt-dlp is missing or the URL
 * cannot be fetched it returns a clear {@see FetchResult} failure, never throws.
 *
 * Deploy note: yt-dlp must be installed on the Pi for production import to work.
 */
final class YtDlpMediaFetcher implements MediaFetcher
{
    /** Hard ceiling on a single fetch (seconds). */
    private const int FETCH_TIMEOUT = 180;

    /** Ceiling on the availability probe (seconds). */
    private const int PROBE_TIMEOUT = 15;

    public function __construct(private readonly string $binary = '')
    {
    }

    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->bin(), '--version']);
            $process->setTimeout(self::PROBE_TIMEOUT);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function fetch(string $url, string $destPath): FetchResult
    {
        if (!$this->isAvailable()) {
            return FetchResult::failure('Media import is unavailable: yt-dlp is not installed on this server. Drop the file instead.');
        }

        try {
            $process = new Process([
                $this->bin(),
                '--no-playlist',
                '--no-warnings',
                '--quiet',
                '-f', 'mp4/best',
                '-o', $destPath,
                $url,
            ]);
            $process->setTimeout(self::FETCH_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                return FetchResult::failure($this->explain($process->getErrorOutput() . "\n" . $process->getOutput()));
            }
            if (!is_file($destPath) || filesize($destPath) === 0) {
                return FetchResult::failure('Fetched no media; the reel may be private or login-walled.');
            }

            return FetchResult::success();
        } catch (ProcessTimedOutException) {
            return FetchResult::failure('Import timed out fetching the media. Try a shorter clip or drop the file.');
        } catch (\Throwable $e) {
            return FetchResult::failure('Could not fetch the media: ' . $e->getMessage());
        }
    }

    private function bin(): string
    {
        if ($this->binary !== '') {
            return $this->binary;
        }

        return getenv('YT_DLP_BIN') ?: 'yt-dlp';
    }

    /**
     * Turn yt-dlp stderr into a short, user-facing reason.
     */
    private function explain(string $stderr): string
    {
        $s = strtolower($stderr);
        if (str_contains($s, 'private') || str_contains($s, 'log in') || str_contains($s, 'login') || str_contains($s, 'sign in')) {
            return 'That reel is private or login-walled and cannot be fetched.';
        }
        if (str_contains($s, 'unsupported url') || str_contains($s, 'no video') || str_contains($s, 'unable to extract')) {
            return 'Unsupported or unavailable link; no video could be extracted.';
        }

        return 'Could not fetch that reel; it may be unavailable or unsupported.';
    }
}
