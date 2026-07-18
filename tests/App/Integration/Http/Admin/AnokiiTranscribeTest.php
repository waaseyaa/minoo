<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transcribe dashboard (#853): the tab renders into the Anokii shell for staff
 * (admin / elder_coordinator) and is denied for anonymous; the autosave API
 * updates the entity verbatim and rejects unauthorized writes.
 */
#[CoversNothing]
final class AnokiiTranscribeTest extends HttpKernelTestCase
{
    private const string CSRF = 'transcribe-test-csrf-token';
    private static int $adminUid = 0;
    private static int $coordinatorUid = 0;
    private static int $esid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getRepository('user');

        $admin = $users->create(['name' => 'Tx Admin', 'mail' => 'tx-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $users->save($admin);
        self::$adminUid = (int) $admin->id();

        $coord = $users->create(['name' => 'Tx Coordinator', 'mail' => 'tx-coord@example.test', 'status' => true, 'created' => time(), 'roles' => ['elder_coordinator'], 'permissions' => []]);
        $users->save($coord);
        self::$coordinatorUid = (int) $coord->id();

        // One untranscribed corpus row (English missing).
        $sentences = self::$kernel->getEntityTypeManager()->getRepository('example_sentence');
        $row = $sentences->create([
            'ojibwe_text' => 'Shoogan Mookman',
            'english_text' => '',
            'source_sentence_id' => 'corpus:sb-t1',
            'thumbnail_url' => '/media/corpus/thumb/sb-t1',
            'audio_url' => '/media/corpus/audio/sb-t1',
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $sentences->save($row);
        self::$esid = (int) $row->id();
    }

    #[Test]
    public function admin_sees_the_dashboard_in_the_anokii_shell(): void
    {
        $response = $this->sendAs(self::$adminUid, 'GET', '/admin/anokii/transcribe');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('anokii-app', $body);
        self::assertStringContainsString('Transcribe', $body);
        self::assertStringContainsString('tx-row', $body);
        self::assertStringContainsString('data-esid="' . self::$esid . '"', $body);
        self::assertStringNotContainsString('/_nuxt/', $body);
    }

    #[Test]
    public function elder_coordinator_is_also_allowed(): void
    {
        $response = $this->sendAs(self::$coordinatorUid, 'GET', '/admin/anokii/transcribe');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('tx-row', (string) $response->getContent());
    }

    #[Test]
    public function anonymous_is_denied(): void
    {
        $response = $this->send('GET', '/admin/anokii/transcribe');
        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]);
        self::assertStringNotContainsString('tx-row', (string) $response->getContent());
    }

    #[Test]
    public function save_updates_the_entity_verbatim(): void
    {
        // Includes orthography that must NOT be normalized (ADR 0003).
        $ojibwe = '  Zhooniyaans  '; // leading/trailing spaces preserved
        $response = $this->sendPost(self::$adminUid, '/admin/anokii/transcribe/save', [
            'esid' => (string) self::$esid,
            'ojibwe_text' => $ojibwe,
            'english_text' => 'butter knife',
            'notes' => 'reviewed',
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        self::assertTrue($payload['ok'] ?? false);

        $entity = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) self::$esid);
        self::assertNotNull($entity);
        self::assertSame($ojibwe, (string) $entity->get('ojibwe_text'), 'Ojibwe orthography must be stored verbatim.');
        self::assertSame('butter knife', (string) $entity->get('english_text'));
        self::assertSame('reviewed', (string) $entity->get('notes'));
    }

    #[Test]
    public function unauthorized_write_is_rejected_and_does_not_mutate(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getRepository('example_sentence');
        $before = (string) $storage->find((string) self::$esid)->get('english_text');

        // Anonymous (no role) but with a valid CSRF token: the role gate must reject.
        $response = $this->sendPost(0, '/admin/anokii/transcribe/save', [
            'esid' => (string) self::$esid,
            'english_text' => 'HACKED',
        ]);

        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED]);
        $after = (string) $storage->find((string) self::$esid)->get('english_text');
        self::assertSame($before, $after, 'Denied write must not mutate the entity.');
    }

    private function sendAs(int $uid, string $method, string $uri): Response
    {
        $this->primeGlobals($method, $uri);
        $this->primeSession($uid);

        return self::$kernel->handle();
    }

    /**
     * @param array<string, string> $fields
     */
    private function sendPost(int $uid, string $uri, array $fields): Response
    {
        $this->primeGlobals('POST', $uri);
        $_POST = $fields + ['_csrf_token' => self::CSRF];
        $_REQUEST = $_POST;
        $this->primeSession($uid);

        return self::$kernel->handle();
    }

    private function primeGlobals(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_FILES = [];
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => $method,
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
    }

    private function primeSession(int $uid): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        // Valid CSRF token in session so the middleware accepts the matching field.
        $_SESSION['_csrf_token'] = self::CSRF;
        if ($uid > 0) {
            $_SESSION['waaseyaa_uid'] = $uid;
        } else {
            unset($_SESSION['waaseyaa_uid']);
        }
    }
}
