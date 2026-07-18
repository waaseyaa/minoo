<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anokii workspace accessibility markers (#879). Server-rendered guarantees that
 * back the (non-blocking) axe Playwright spec: keyboard-operable drop zone with
 * a file-picker fallback, visible focus, live regions, and a responsive,
 * labelled curate table. These run in the suite so a regression fails the gate.
 */
#[CoversNothing]
final class AnokiiAccessibilityTest extends HttpKernelTestCase
{
    private static int $coordUid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $users = self::$kernel->getEntityTypeManager()->getRepository('user');
        $coord = $users->create(['name' => 'A11y Coordinator', 'mail' => 'a11y-coord@example.test', 'status' => true, 'created' => time(), 'roles' => ['elder_coordinator'], 'permissions' => []]);
        $users->save($coord);
        self::$coordUid = (int) $coord->id();

        // One row at each tab's default stage so per-row status regions render.
        $sentences = self::$kernel->getEntityTypeManager()->getRepository('example_sentence');
        $sentences->save($sentences->create([
            'ojibwe_text' => 'gaa', 'english_text' => '', 'source_sentence_id' => 'corpus:a11y-1',
            'pipeline_status' => 'drafted', 'status' => 0, 'consent_public' => 0, 'created_at' => time(), 'updated_at' => time(),
        ]));
        $sentences->save($sentences->create([
            'ojibwe_text' => 'Makwa', 'english_text' => 'bear', 'source_sentence_id' => 'corpus:a11y-2',
            'pipeline_status' => 'transcribed', 'status' => 0, 'consent_public' => 0, 'created_at' => time(), 'updated_at' => time(),
        ]));
    }

    #[Test]
    public function the_drop_zone_is_keyboard_accessible_with_a_file_picker_fallback(): void
    {
        $body = (string) $this->sendAs('/admin/anokii/ingest')->getContent();

        self::assertStringContainsString('role="group"', $body);
        self::assertStringContainsString('aria-label="Upload reels', $body);
        self::assertStringContainsString('id="ig-pick"', $body, 'Keyboard-focusable Choose files button.');
        self::assertStringContainsString('type="file"', $body, 'File-picker fallback input.');
    }

    #[Test]
    public function every_tab_ships_visible_focus_and_the_sr_only_helper(): void
    {
        // The corpus pipeline tabs. The /admin/anokii home is now the package
        // catalog shell (#886), a different a11y surface covered by the axe
        // Playwright spec, so it is not asserted for minoo's pipeline helpers here.
        foreach (['/admin/anokii/ingest', '/admin/anokii/transcribe', '/admin/anokii/curate'] as $path) {
            $body = (string) $this->sendAs($path)->getContent();
            self::assertStringContainsString(':focus-visible', $body, "Focus ring CSS on {$path}");
            self::assertStringContainsString('.sr-only', $body, "sr-only helper on {$path}");
        }
    }

    #[Test]
    public function the_curate_table_is_responsive_and_labelled(): void
    {
        $body = (string) $this->sendAs('/admin/anokii/curate')->getContent();

        self::assertStringContainsString('cu-tablewrap', $body, 'Horizontal-scroll wrapper for mobile.');
        self::assertStringContainsString('scope="col"', $body, 'Column header scope.');
        self::assertStringContainsString('class="sr-only">Transcribed utterances', $body, 'Table caption.');
    }

    #[Test]
    public function status_regions_announce_to_screen_readers(): void
    {
        $tx = (string) $this->sendAs('/admin/anokii/transcribe')->getContent();
        self::assertStringContainsString('aria-live="polite"', $tx);

        $cu = (string) $this->sendAs('/admin/anokii/curate')->getContent();
        self::assertStringContainsString('aria-live="polite"', $cu);
    }

    private function sendAs(string $uri): Response
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_FILES = [];
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $path,
            'QUERY_STRING' => '',
            'PATH_INFO' => $path,
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ]);

        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['waaseyaa_uid'] = self::$coordUid;

        return self::$kernel->handle();
    }
}
