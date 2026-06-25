<?php

declare(strict_types=1);

namespace App\Tests\Unit\Anokii\Ingest;

use App\Anokii\Ingest\FetchResult;
use App\Anokii\Ingest\YtDlpMediaFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(YtDlpMediaFetcher::class)]
#[CoversClass(FetchResult::class)]
final class YtDlpMediaFetcherTest extends TestCase
{
    #[Test]
    public function it_is_unavailable_when_the_binary_does_not_resolve(): void
    {
        $fetcher = new YtDlpMediaFetcher(self::missingBinary());

        self::assertFalse($fetcher->isAvailable());
    }

    #[Test]
    public function fetch_fails_closed_when_the_extractor_is_missing(): void
    {
        $dest = sys_get_temp_dir() . '/minoo-ytdlp-test-' . bin2hex(random_bytes(4)) . '.mp4';
        $result = (new YtDlpMediaFetcher(self::missingBinary()))->fetch('https://www.facebook.com/reel/123', $dest);

        self::assertFalse($result->ok);
        self::assertStringContainsString('yt-dlp is not installed', $result->error);
        self::assertFileDoesNotExist($dest, 'A failed fetch stages nothing.');
    }

    #[Test]
    public function the_result_value_object_distinguishes_success_from_failure(): void
    {
        self::assertTrue(FetchResult::success()->ok);
        $failure = FetchResult::failure('nope');
        self::assertFalse($failure->ok);
        self::assertSame('nope', $failure->error);
    }

    /**
     * A binary path guaranteed not to resolve, so the test never invokes a real
     * extractor or the network.
     */
    private static function missingBinary(): string
    {
        return sys_get_temp_dir() . '/no-such-yt-dlp-' . bin2hex(random_bytes(6));
    }
}
