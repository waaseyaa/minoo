<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

/**
 * Produces a whiteboard keyframe + opus audio from a reel's source video using
 * ffmpeg, mirroring code/ingest/ingest.py's extract_keyframe / extract_audio.
 *
 * The Facebook download itself (the Python tool drives a real Playwright browser
 * because FB's page structure churns) stays an OPERATOR PREREQUISITE: place the
 * muxed reel at `<corpus>/source-videos/<id>.mp4` first. This fetcher then does
 * the deterministic ffmpeg stage. Nothing is committed to the repo — all media
 * lives under MINOO_CORPUS_PATH.
 */
final class FfmpegReelFetcher implements ReelFetcherInterface
{
    public function __construct(
        private readonly string $corpusPath,
        private readonly string $ffmpegBinary = 'ffmpeg',
        private readonly float $keyframeSeconds = 1.0,
    ) {
    }

    public function fetch(string $reelUrl, string $itemId): array
    {
        $video = $this->corpusPath . '/source-videos/' . $itemId . '.mp4';
        if (!is_file($video)) {
            throw new ReelFetchException(sprintf(
                'Source video not found: %s. Download the reel (%s) first — see the ingest:corpus help.',
                $video,
                $reelUrl,
            ));
        }

        $thumb = $this->corpusPath . '/thumbs/' . $itemId . '.jpg';
        $audio = $this->corpusPath . '/audio/' . $itemId . '.opus';
        $this->ensureDir(dirname($thumb));
        $this->ensureDir(dirname($audio));

        // Keyframe a second in (skips the intro fade), then opus audio at 64k.
        $this->run([$this->ffmpegBinary, '-y', '-ss', (string) $this->keyframeSeconds, '-i', $video, '-frames:v', '1', '-q:v', '3', $thumb]);
        $this->run([$this->ffmpegBinary, '-y', '-i', $video, '-vn', '-c:a', 'libopus', '-b:a', '64k', $audio]);

        if (!is_file($thumb) || !is_file($audio)) {
            throw new ReelFetchException("ffmpeg did not produce media for {$itemId}.");
        }

        return ['keyframe' => $thumb, 'audio' => $audio];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new ReelFetchException("Could not create directory: {$dir}");
        }
    }

    /**
     * @param list<string> $cmd
     */
    private function run(array $cmd): void
    {
        $command = implode(' ', array_map('escapeshellarg', $cmd)) . ' 2>&1';
        exec($command, $output, $code);
        if ($code !== 0) {
            throw new ReelFetchException('ffmpeg failed: ' . implode("\n", $output));
        }
    }
}
