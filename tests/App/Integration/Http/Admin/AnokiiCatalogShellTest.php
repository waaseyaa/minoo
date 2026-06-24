<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The catalog-driven Anokii shell at /admin/anokii (#886):
 *   - the home renders the AdminModules catalog (hero + product-preview grid) in
 *     the package shell, with Minoo branding, for a staff user,
 *   - preview cards and nav link to /admin/anokii/m/{id}, which renders the
 *     package coming-soon page,
 *   - the corpus pipeline routes still respond (not re-homed yet, issue 3),
 *   - anonymous is denied.
 */
#[CoversNothing]
final class AnokiiCatalogShellTest extends HttpKernelTestCase
{
    private static int $adminUid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getStorage('user');
        $admin = $users->create(['name' => 'Cat Admin', 'mail' => 'cat-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $users->save($admin);
        self::$adminUid = (int) $admin->id();
    }

    #[Test]
    public function staff_sees_the_catalog_dashboard_in_the_package_shell(): void
    {
        $response = $this->sendAs(self::$adminUid, '/admin/anokii');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('anokii-app', $body, 'Package shell chrome.');
        self::assertStringContainsString('anokii-grid', $body, 'The dashboard module grid.');
        self::assertStringContainsString('Product preview', $body, 'Preview section for not-yet-live modules.');
        self::assertStringContainsString('Minoo', $body, 'Minoo branding in the package chrome.');

        // Nothing is enabled, so modules link to their coming-soon pages.
        self::assertStringContainsString('/admin/anokii/m/', $body);

        // The old bespoke pipeline funnel is gone from the home.
        self::assertStringNotContainsString('ov-funnel', $body);
        self::assertStringNotContainsString('/_nuxt/', $body, 'Not the Vue admin SPA.');
    }

    #[Test]
    public function a_preview_module_renders_the_coming_soon_page(): void
    {
        $response = $this->sendAs(self::$adminUid, '/admin/anokii/m/documents');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('anokii-app', $body);
        self::assertStringContainsString('Documents', $body, 'The module label.');
        self::assertStringContainsString('Product preview', $body);
    }

    #[Test]
    public function an_unknown_module_falls_back_to_the_dashboard(): void
    {
        $response = $this->sendAs(self::$adminUid, '/admin/anokii/m/not-a-real-module');

        self::assertContains($response->getStatusCode(), [Response::HTTP_FOUND, Response::HTTP_MOVED_PERMANENTLY]);
        self::assertSame('/admin/anokii', $response->headers->get('Location'));
    }

    #[Test]
    public function the_corpus_pipeline_routes_still_respond(): void
    {
        foreach (['/admin/anokii/ingest', '/admin/anokii/transcribe', '/admin/anokii/curate'] as $path) {
            $response = $this->sendAs(self::$adminUid, $path);
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "{$path} still serves.");
            self::assertStringContainsString('anokii-app', (string) $response->getContent(), "{$path} still in the workspace shell.");
        }
    }

    #[Test]
    public function anonymous_is_denied(): void
    {
        $response = $this->send('GET', '/admin/anokii');

        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND, Response::HTTP_UNAUTHORIZED]);
        self::assertStringNotContainsString('anokii-grid', (string) $response->getContent());
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
