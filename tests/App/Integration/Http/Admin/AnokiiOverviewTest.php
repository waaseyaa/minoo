<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Anokii\Pipeline\PipelineStage;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anokii Overview as the live pipeline dashboard (#876):
 *   - role-gated (admin / elder_coordinator), denied for anonymous,
 *   - renders a funnel with a live count per stage,
 *   - surfaces a single "do next" CTA = the actionable stage with the most work,
 *   - reorders the shell nav into flow order (Ingest before Transcribe),
 *   - shows the persistent cross-tab pipeline breadcrumb.
 *
 * Counts are deterministic: per-class :memory: DB, seeded below.
 */
#[CoversNothing]
final class AnokiiOverviewTest extends HttpKernelTestCase
{
    private static int $adminUid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getStorage('user');
        $admin = $users->create(['name' => 'Ov Admin', 'mail' => 'ov-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $users->save($admin);
        self::$adminUid = (int) $admin->id();

        $sentences = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');
        $i = 0;
        $seed = static function (array $values) use ($sentences, &$i): void {
            ++$i;
            $sentences->save($sentences->create($values + [
                'source_sentence_id' => 'corpus:ov-' . $i,
                'consent_public' => 0,
                'status' => 0,
                'created_at' => time(),
                'updated_at' => time(),
            ]));
        };

        // 2 ingested, 3 drafted (explicit), 1 transcribed (derived), 1 curated (explicit). Total 7.
        $seed(['ojibwe_text' => '', 'english_text' => '', 'pipeline_status' => PipelineStage::INGESTED]);
        $seed(['ojibwe_text' => '', 'english_text' => '', 'pipeline_status' => PipelineStage::INGESTED]);
        $seed(['ojibwe_text' => 'a', 'english_text' => '', 'pipeline_status' => PipelineStage::DRAFTED]);
        $seed(['ojibwe_text' => 'b', 'english_text' => '', 'pipeline_status' => PipelineStage::DRAFTED]);
        $seed(['ojibwe_text' => 'c', 'english_text' => '', 'pipeline_status' => PipelineStage::DRAFTED]);
        $seed(['ojibwe_text' => 'Makwa', 'english_text' => 'bear']); // derived -> transcribed
        $seed(['ojibwe_text' => 'Waaban', 'english_text' => 'dawn', 'pipeline_status' => PipelineStage::CURATED]);
    }

    #[Test]
    public function admin_sees_the_funnel_with_live_counts(): void
    {
        $response = $this->sendAs(self::$adminUid, '/admin/anokii');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('anokii-app', $body);
        self::assertStringContainsString('Overview', $body);
        self::assertStringContainsString('ov-funnel', $body);
        self::assertStringContainsString('7 items in the pipeline', $body);

        // Funnel buttons jump to each stage, filtered.
        self::assertStringContainsString('/admin/anokii/ingest?stage=ingested', $body);
        self::assertStringContainsString('/admin/anokii/transcribe?stage=drafted', $body);
        self::assertStringContainsString('/admin/anokii/curate?stage=transcribed', $body);
    }

    #[Test]
    public function the_do_next_cta_points_at_the_busiest_actionable_stage(): void
    {
        // drafted=3 beats transcribed=1 and ingested=2 -> CTA is "Transcribe".
        $body = (string) $this->sendAs(self::$adminUid, '/admin/anokii')->getContent();

        self::assertStringContainsString('3 drafts awaiting transcription', $body);
        self::assertStringContainsString('Transcribe →', $body);
    }

    #[Test]
    public function the_persistent_pipeline_breadcrumb_renders(): void
    {
        $body = (string) $this->sendAs(self::$adminUid, '/admin/anokii')->getContent();

        self::assertStringContainsString('anokii-pipeline', $body);
        self::assertStringContainsString('Awaiting transcription', $body);
        self::assertStringContainsString('Ready to curate', $body);
    }

    #[Test]
    public function the_nav_is_in_pipeline_flow_order(): void
    {
        $body = (string) $this->sendAs(self::$adminUid, '/admin/anokii')->getContent();

        $ingest = strpos($body, 'href="/admin/anokii/ingest"');
        $transcribe = strpos($body, 'href="/admin/anokii/transcribe"');
        $curate = strpos($body, 'href="/admin/anokii/curate"');

        self::assertNotFalse($ingest, 'Ingest nav link is present.');
        self::assertNotFalse($transcribe);
        self::assertNotFalse($curate);
        self::assertLessThan($transcribe, $ingest, 'Ingest must precede Transcribe in the nav.');
        self::assertLessThan($curate, $transcribe, 'Transcribe must precede Curate in the nav.');
    }

    #[Test]
    public function anonymous_is_denied(): void
    {
        $response = $this->send('GET', '/admin/anokii');
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
