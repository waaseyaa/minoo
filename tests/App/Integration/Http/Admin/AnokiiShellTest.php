<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityInterface;

/**
 * The Anokii shell at /admin/anokii (#851, catalog shell #886):
 *   - role-gated to admin / elder_coordinator (denied for anonymous),
 *   - renders the Anokii package shell, NOT the admin-surface Vue SPA
 *     (i.e. its priority-100 routes win over the admin_spa /admin/{path} catch-all),
 *   - the home is the AdminModules catalog dashboard (the module grid), not the
 *     old bespoke pipeline funnel.
 */
#[CoversNothing]
final class AnokiiShellTest extends HttpKernelTestCase
{
    private static int $adminUid = 0;
    private static int $coordinatorUid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $storage = self::$kernel->getEntityTypeManager()->getRepository('user');

        $admin = $storage->create([
            'name' => 'Anokii Admin',
            'mail' => 'anokii-admin@example.test',
            'status' => true,
            'created' => time(),
            'roles' => ['admin'],
            'permissions' => [],
        ]);
        $storage->save($admin);
        self::$adminUid = (int) $admin->id();

        $coordinator = $storage->create([
            'name' => 'Elder Coordinator',
            'mail' => 'anokii-coordinator@example.test',
            'status' => true,
            'created' => time(),
            'roles' => ['elder_coordinator'],
            'permissions' => [],
        ]);
        $storage->save($coordinator);
        self::$coordinatorUid = (int) $coordinator->id();
    }

    #[Test]
    public function anonymous_is_denied_and_does_not_get_the_spa(): void
    {
        $response = $this->send('GET', '/admin/anokii');
        $code = $response->getStatusCode();
        $body = (string) $response->getContent();

        self::assertContains(
            $code,
            [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND, Response::HTTP_UNAUTHORIZED],
            'Anonymous /admin/anokii must be denied, not served.',
        );
        self::assertNotSame(Response::HTTP_OK, $code);
        self::assertStringNotContainsString('/_nuxt/', $body, 'Denied response must not be the admin SPA shell.');
        self::assertStringNotContainsString('anokii-app', $body, 'Anonymous must not see the workspace chrome.');
    }

    #[Test]
    public function admin_sees_the_anokii_shell_not_the_spa(): void
    {
        $response = $this->sendAs(self::$adminUid, 'GET', '/admin/anokii');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Anokii package shell markers (proves it rendered the shell, not the SPA).
        self::assertStringContainsString('anokii-app', $body);
        self::assertStringContainsString('anokii-nav', $body);
        self::assertStringNotContainsString('/_nuxt/', $body, '/admin/anokii must not fall through to the Vue admin SPA.');

        // Catalog dashboard (#886): the AdminModules module grid and product
        // preview, with Minoo branding. NOT the old bespoke pipeline funnel.
        self::assertStringContainsString('anokii-grid', $body);
        self::assertStringContainsString('Product preview', $body);
        self::assertStringContainsString('Minoo', $body);
        self::assertStringNotContainsString('ov-funnel', $body);
    }

    #[Test]
    public function elder_coordinator_is_also_allowed(): void
    {
        $response = $this->sendAs(self::$coordinatorUid, 'GET', '/admin/anokii');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('anokii-app', (string) $response->getContent());
    }

    #[Test]
    public function seeded_admin_user_actually_has_the_admin_role(): void
    {
        // Guards the auth-helper assumption: if seeding/roles silently broke,
        // the 200s above would be false negatives.
        $user = self::$kernel->getEntityTypeManager()->getRepository('user')->find((string) self::$adminUid);
        self::assertInstanceOf(EntityInterface::class, $user);
        self::assertContains('admin', $user->getRoles());
    }

    /**
     * Drive a request as an authenticated user by seeding the session UID the
     * way AuthManager does ($_SESSION['waaseyaa_uid']). The base send() clears
     * the session, so this is a parallel helper that does not.
     *
     * Ensuring the session is active BEFORE setting the UID keeps SessionMiddleware
     * from re-running session_start() and wiping it.
     */
    private function sendAs(int $uid, string $method, string $uri): Response
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

        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['waaseyaa_uid'] = $uid;

        return self::$kernel->handle();
    }
}
