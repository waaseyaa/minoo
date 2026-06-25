<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Anokii\Pipeline\PipelineStage;
use App\Http\Controller\Anokii\IngestController;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Anokii Ingest tab (#877): the drop zone is role-gated; an upload stages the
 * video and creates an ingested draft example_sentence; the status endpoint
 * reports per-row pipeline stage.
 *
 * The upload + status handlers are exercised directly (a real multipart upload
 * through the kernel needs SAPI move_uploaded_file + fileinfo, neither present
 * under CLI), with a test-mode UploadedFile and a temp MINOO_CORPUS_PATH. Route
 * gating is exercised through the full kernel.
 */
#[CoversNothing]
final class AnokiiIngestTest extends HttpKernelTestCase
{
    private static int $adminUid = 0;
    private static string $corpusDir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$corpusDir = sys_get_temp_dir() . '/anokii-ingest-' . bin2hex(random_bytes(4));
        mkdir(self::$corpusDir, 0o755, true);
        putenv('MINOO_CORPUS_PATH=' . self::$corpusDir);

        $users = self::$kernel->getEntityTypeManager()->getStorage('user');
        $admin = $users->create(['name' => 'Ig Admin', 'mail' => 'ig-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $users->save($admin);
        self::$adminUid = (int) $admin->id();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('MINOO_CORPUS_PATH');
        parent::tearDownAfterClass();
    }

    private function controller(): IngestController
    {
        return new IngestController(
            self::$kernel->getEntityTypeManager(),
            SsrServiceProvider::getTwigEnvironment(),
            new \App\Tests\Support\StubMediaFetcher(),
        );
    }

    private function account(): AccountInterface
    {
        $account = self::$kernel->getEntityTypeManager()->getStorage('user')->load(self::$adminUid);
        self::assertInstanceOf(AccountInterface::class, $account);

        return $account;
    }

    #[Test]
    public function the_drop_zone_renders_for_staff(): void
    {
        $response = $this->sendAs(self::$adminUid, '/admin/anokii/ingest');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('ig-drop', $body);
        self::assertStringContainsString('Choose files', $body);
        self::assertStringContainsString('anokii-pipeline', $body);
        self::assertStringNotContainsString('/_nuxt/', $body);
    }

    #[Test]
    public function anonymous_is_denied_the_ingest_tab(): void
    {
        $response = $this->send('GET', '/admin/anokii/ingest');
        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND, Response::HTTP_UNAUTHORIZED]);
        self::assertStringNotContainsString('ig-drop', (string) $response->getContent());
    }

    #[Test]
    public function upload_stages_the_file_and_creates_an_ingested_draft(): void
    {
        // A test-mode UploadedFile renames (no SAPI move_uploaded_file needed).
        $src = sys_get_temp_dir() . '/reel-src-' . bin2hex(random_bytes(3)) . '.mp4';
        file_put_contents($src, 'FAKE-MP4-BYTES');
        $upload = new UploadedFile($src, 'steven-reel.mp4', 'video/mp4', null, true);

        $request = new HttpRequest([], [], [], [], ['file' => $upload]);
        $response = $this->controller()->upload([], [], $this->account(), $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertCount(1, $payload['files']);

        $file = $payload['files'][0];
        self::assertSame(PipelineStage::INGESTED, $file['status']);
        self::assertNull($file['error']);
        self::assertStringStartsWith('upload-', $file['corpus_id']);
        self::assertGreaterThan(0, $file['esid']);

        // The staged source video exists under MINOO_CORPUS_PATH/source-videos.
        self::assertFileExists(self::$corpusDir . '/source-videos/' . $file['corpus_id'] . '.mp4');

        // The entity is an ingested draft, off public surfaces.
        $row = self::$kernel->getEntityTypeManager()->getStorage('example_sentence')->load($file['esid']);
        self::assertInstanceOf(EntityInterface::class, $row);
        self::assertSame(PipelineStage::INGESTED, (string) $row->get('pipeline_status'));
        self::assertSame('corpus:' . $file['corpus_id'], (string) $row->get('source_sentence_id'));
        self::assertSame(0, (int) $row->get('status'));
        self::assertSame(0, (int) $row->get('consent_public'));
        self::assertSame('/admin/anokii/media/video/' . $file['corpus_id'], (string) $row->get('video_url'));
    }

    #[Test]
    public function upload_rejects_a_non_video_extension(): void
    {
        $src = sys_get_temp_dir() . '/bad-' . bin2hex(random_bytes(3)) . '.txt';
        file_put_contents($src, 'not a video');
        $upload = new UploadedFile($src, 'notes.txt', 'text/plain', null, true);

        $request = new HttpRequest([], [], [], [], ['file' => $upload]);
        $payload = json_decode((string) $this->controller()->upload([], [], $this->account(), $request)->getContent(), true);

        self::assertSame('error', $payload['files'][0]['status']);
        self::assertNull($payload['files'][0]['esid']);
    }

    #[Test]
    public function status_reports_pipeline_stage_for_rows(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');
        $row = $storage->create([
            'ojibwe_text' => '', 'english_text' => '',
            'source_sentence_id' => 'corpus:st-1',
            'pipeline_status' => PipelineStage::DRAFTED,
            'status' => 0, 'consent_public' => 0, 'created_at' => time(), 'updated_at' => time(),
        ]);
        $storage->save($row);
        $esid = (int) $row->id();

        $request = new HttpRequest(['esids' => (string) $esid]);
        $payload = json_decode((string) $this->controller()->status([], [], $this->account(), $request)->getContent(), true);

        self::assertTrue($payload['ok']);
        self::assertCount(1, $payload['rows']);
        self::assertSame($esid, $payload['rows'][0]['esid']);
        self::assertSame(PipelineStage::DRAFTED, $payload['rows'][0]['pipeline_status']);
    }

    #[Test]
    public function fb_url_path_registers_an_ingested_draft(): void
    {
        $request = new HttpRequest();
        $request->initialize([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['url' => 'https://www.facebook.com/reel/123']));
        $payload = json_decode((string) $this->controller()->url([], [], $this->account(), $request)->getContent(), true);

        self::assertTrue($payload['ok']);
        self::assertStringStartsWith('fetch-', $payload['corpus_id']);
        self::assertSame(PipelineStage::INGESTED, $payload['pipeline_status']);
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
