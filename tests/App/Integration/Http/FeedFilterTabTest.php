<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the feed filter tabs' active state to the URL-facing filter key.
 * Regression guard for #923: activeFilter is the internal type id
 * ('community_group'), which never equals the 'group' tab key — the template
 * must compare filterParam. Missed by four review layers because nothing
 * covered the active-state before.
 */
#[CoversNothing]
final class FeedFilterTabTest extends HttpKernelTestCase
{
    private static int $uid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getRepository('user');
        $user = $users->create(['name' => 'Feed Tab Tester', 'mail' => 'feed-tab@example.test', 'status' => true, 'created' => time(), 'roles' => [], 'permissions' => []]);
        $users->save($user);
        self::$uid = (int) $user->id();
    }

    #[Test]
    public function groups_filter_tab_is_active_when_filtering_by_group(): void
    {
        $response = $this->sendAuthenticated('/feed', 'filter=group');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '/filter=group"\s+class="feed__filter feed__filter--active"/',
            $body,
            'The Groups tab must carry the active state for ?filter=group.',
        );
    }

    #[Test]
    public function all_tab_is_active_by_default(): void
    {
        $response = $this->sendAuthenticated('/feed');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '/filter=all"\s+class="feed__filter feed__filter--active"/',
            $body,
        );
    }

    private function sendAuthenticated(string $path, string $queryString = ''): Response
    {
        $_GET = [];
        parse_str($queryString, $_GET);
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = $_GET;
        $_FILES = [];
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $path . ($queryString !== '' ? '?' . $queryString : ''),
            'QUERY_STRING' => $queryString,
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
        $_SESSION['waaseyaa_uid'] = self::$uid;

        try {
            return self::$kernel->handle();
        } finally {
            unset($_SESSION['waaseyaa_uid']);
        }
    }
}
