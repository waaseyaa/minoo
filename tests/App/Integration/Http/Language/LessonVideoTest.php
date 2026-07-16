<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Language;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lesson reels (#873): the consent-gated video route streams mp4 with HTTP Range
 * (200 full + 206 partial), and the lesson card renders a <video> for an item
 * that has a reel while falling back to thumbnail + Listen for one that doesn't.
 */
#[CoversNothing]
final class LessonVideoTest extends HttpKernelTestCase
{
    private static string $corpusDir = '';
    private static ?string $previousCorpusPath = null;
    private const string MP4_BYTES = 'FAKE-MP4-DATA-0123456789';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$corpusDir = sys_get_temp_dir() . '/minoo_video_test_' . getmypid();
        @mkdir(self::$corpusDir . '/video', 0o777, true);
        // sb-005 is a real Lesson 1 (kitchen) card id; give it a reel.
        file_put_contents(self::$corpusDir . '/video/sb-005.mp4', self::MP4_BYTES);

        $prev = getenv('MINOO_CORPUS_PATH');
        self::$previousCorpusPath = $prev === false ? null : $prev;
        putenv('MINOO_CORPUS_PATH=' . self::$corpusDir);

        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('example_sentence');
        $entries = $etm->getStorage('dictionary_entry');

        // Lessons are dynamic (#912): a card shows only when its row is published,
        // public, curated (has a dictionary_entry) and assigned to the lesson.
        $deBowl = $entries->create(['word' => 'bowl-oji', 'slug' => 'bowl-oji', 'definition' => '["bowl"]', 'status' => 1, 'consent_public' => 1, 'created_at' => time(), 'updated_at' => time()]);
        $entries->save($deBowl);
        $deSoup = $entries->create(['word' => 'soup-bowl-oji', 'slug' => 'soup-bowl-oji', 'definition' => '["soup bowl"]', 'status' => 1, 'consent_public' => 1, 'created_at' => time(), 'updated_at' => time()]);
        $entries->save($deSoup);

        // Has a reel (Lesson 1 card).
        $withVideo = $storage->create([
            'ojibwe_text' => 'bowl-oji',
            'english_text' => 'bowl',
            'source_sentence_id' => 'corpus:sb-005',
            'thumbnail_url' => '/media/corpus/thumb/sb-005',
            'audio_url' => '/media/corpus/audio/sb-005',
            'video_url' => '/media/corpus/video/sb-005',
            'dictionary_entry_id' => (int) $deBowl->id(),
            'lesson_slug' => 'the-kitchen',
            'lesson_group' => 'Dishes and containers',
            'lesson_weight' => 0,
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($withVideo);

        // Another Lesson 1 card with NO reel → fallback to thumb + audio.
        $noVideo = $storage->create([
            'ojibwe_text' => 'soup-bowl-oji',
            'english_text' => 'soup bowl',
            'source_sentence_id' => 'corpus:sb-017',
            'thumbnail_url' => '/media/corpus/thumb/sb-017',
            'audio_url' => '/media/corpus/audio/sb-017',
            'video_url' => '',
            'dictionary_entry_id' => (int) $deSoup->id(),
            'lesson_slug' => 'the-kitchen',
            'lesson_group' => 'Dishes and containers',
            'lesson_weight' => 1,
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($noVideo);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$previousCorpusPath === null) {
            putenv('MINOO_CORPUS_PATH');
        } else {
            putenv('MINOO_CORPUS_PATH=' . self::$previousCorpusPath);
        }
        @unlink(self::$corpusDir . '/video/sb-005.mp4');
        @rmdir(self::$corpusDir . '/video');
        @rmdir(self::$corpusDir);

        parent::tearDownAfterClass();
    }

    #[Test]
    public function lesson_video_route_serves_full_mp4_with_accept_ranges(): void
    {
        $response = $this->send('GET', '/lessons/media/video/sb-005');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('video/mp4', $response->headers->get('Content-Type'));
        self::assertSame('bytes', $response->headers->get('Accept-Ranges'));
        self::assertSame((string) strlen(self::MP4_BYTES), $response->headers->get('Content-Length'));
        self::assertSame(self::MP4_BYTES, (string) $response->getContent());
    }

    #[Test]
    public function lesson_video_route_honors_a_range_request_with_206(): void
    {
        $response = $this->send('GET', '/lessons/media/video/sb-005', [], ['HTTP_RANGE' => 'bytes=0-3']);

        self::assertSame(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());
        self::assertSame('bytes', $response->headers->get('Accept-Ranges'));
        self::assertSame('bytes 0-3/' . strlen(self::MP4_BYTES), $response->headers->get('Content-Range'));
        self::assertSame('4', $response->headers->get('Content-Length'));
        self::assertSame(substr(self::MP4_BYTES, 0, 4), (string) $response->getContent());
    }

    #[Test]
    public function missing_reel_is_404(): void
    {
        // Consented row exists (sb-017) but no video file → 404.
        self::assertSame(Response::HTTP_NOT_FOUND, $this->send('GET', '/lessons/media/video/sb-017')->getStatusCode());
    }

    #[Test]
    public function legacy_corpus_video_path_redirects_301_to_the_lesson_route(): void
    {
        // Pre-2026-07 imports persisted '/media/corpus/video/{id}' into
        // example_sentence.video_url; the Anokii transcribe workspace renders
        // that stored value verbatim, so the retired route 301s (#920 review).
        $response = $this->send('GET', '/media/corpus/video/sb-005');

        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertSame('/lessons/media/video/sb-005', $response->headers->get('Location'));
    }

    #[Test]
    public function lesson_card_renders_video_for_a_reel_and_falls_back_otherwise(): void
    {
        $body = (string) $this->send('GET', '/lessons/the-kitchen')->getContent();

        // sb-005 has a reel → <video> with the lesson video route, no Listen button.
        self::assertStringContainsString('<video', $body);
        self::assertStringContainsString('/lessons/media/video/sb-005', $body);
        self::assertStringContainsString('poster="/lesson/media/thumb/sb-005"', $body);

        // sb-017 has no reel → falls back to the thumbnail image + a Listen button.
        self::assertStringContainsString('/lesson/media/thumb/sb-017', $body);
        self::assertStringContainsString('lesson-card__play', $body);
    }
}
