<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Anokii\Pipeline\PipelineStage;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language module re-homed under the catalog (#888):
 *   - the catalog dashboard lists Language as a live tool linking to
 *     /admin/anokii/language,
 *   - /admin/anokii/language is the corpus pipeline Overview funnel (live counts,
 *     a "do next" CTA, the cross-tab breadcrumb, flow-order nav),
 *   - the pipeline Overview nav points back to /admin/anokii/language,
 *   - anonymous is denied.
 *
 * Counts are deterministic: per-class :memory: DB, seeded below.
 */
#[CoversNothing]
final class LanguageWorkspaceTest extends HttpKernelTestCase
{
    private static int $adminUid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getStorage('user');
        $admin = $users->create(['name' => 'Lang Admin', 'mail' => 'lang-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $users->save($admin);
        self::$adminUid = (int) $admin->id();

        $sentences = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');
        $i = 0;
        $seed = static function (array $values) use ($sentences, &$i): void {
            ++$i;
            $sentences->save($sentences->create($values + [
                'source_sentence_id' => 'corpus:lw-' . $i,
                'consent_public' => 0,
                'status' => 0,
                'created_at' => time(),
                'updated_at' => time(),
            ]));
        };

        // 2 ingested, 3 drafted, 1 transcribed (derived), 1 curated. Total 7.
        $seed(['ojibwe_text' => '', 'english_text' => '', 'pipeline_status' => PipelineStage::INGESTED]);
        $seed(['ojibwe_text' => '', 'english_text' => '', 'pipeline_status' => PipelineStage::INGESTED]);
        $seed(['ojibwe_text' => 'a', 'english_text' => '', 'pipeline_status' => PipelineStage::DRAFTED]);
        $seed(['ojibwe_text' => 'b', 'english_text' => '', 'pipeline_status' => PipelineStage::DRAFTED]);
        $seed(['ojibwe_text' => 'c', 'english_text' => '', 'pipeline_status' => PipelineStage::DRAFTED]);
        $seed(['ojibwe_text' => 'Makwa', 'english_text' => 'bear']);
        $seed(['ojibwe_text' => 'Waaban', 'english_text' => 'dawn', 'pipeline_status' => PipelineStage::CURATED]);
    }

    #[Test]
    public function the_catalog_lists_language_as_a_live_tool(): void
    {
        $body = (string) $this->sendAs(self::$adminUid, '/admin/anokii')->getContent();

        self::assertStringContainsString('Your tools', $body, 'Live-tools section is present.');
        self::assertStringContainsString('Language', $body);
        self::assertStringContainsString('href="/admin/anokii/language"', $body, 'The Language card links to its tile.');
    }

    #[Test]
    public function the_language_tile_renders_the_pipeline_overview_funnel(): void
    {
        $response = $this->sendAs(self::$adminUid, '/admin/anokii/language');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('anokii-app', $body);
        self::assertStringContainsString('ov-funnel', $body);
        self::assertStringContainsString('7 items in the pipeline', $body);
        self::assertStringContainsString('/admin/anokii/ingest?stage=ingested', $body);
    }

    #[Test]
    public function the_do_next_cta_points_at_the_busiest_actionable_stage(): void
    {
        // drafted=3 beats transcribed=1 and ingested=2 -> CTA is "Transcribe".
        $body = (string) $this->sendAs(self::$adminUid, '/admin/anokii/language')->getContent();

        self::assertStringContainsString('3 drafts awaiting transcription', $body);
        self::assertStringContainsString('Transcribe', $body);
    }

    #[Test]
    public function the_overview_nav_points_at_the_language_tile(): void
    {
        $body = (string) $this->sendAs(self::$adminUid, '/admin/anokii/language')->getContent();

        self::assertStringContainsString('href="/admin/anokii/language"', $body, 'Overview nav points to the module landing.');

        $ingest = strpos($body, 'href="/admin/anokii/ingest"');
        $transcribe = strpos($body, 'href="/admin/anokii/transcribe"');
        self::assertNotFalse($ingest);
        self::assertNotFalse($transcribe);
        self::assertLessThan($transcribe, $ingest, 'Ingest precedes Transcribe in the pipeline nav.');
    }

    #[Test]
    public function anonymous_is_denied(): void
    {
        $response = $this->send('GET', '/admin/anokii/language');

        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND, Response::HTTP_UNAUTHORIZED]);
        self::assertStringNotContainsString('ov-funnel', (string) $response->getContent());
    }

    private function sendAs(int $uid, string $uri): Response
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
        $_SESSION['waaseyaa_uid'] = $uid;

        return self::$kernel->handle();
    }
}
