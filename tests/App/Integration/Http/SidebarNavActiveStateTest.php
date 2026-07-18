<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sidebar nav active-state (#922): the `current_path()` Twig function derives
 * the request path at render time, so on every page exactly the sidebar link
 * matching the current path carries `sbx__item--active` — and no other link
 * does. Locks the exact-match ('/'), prefix-match ('/language', '/events'),
 * and exact-vs-prefix discrimination ('/language/search') behaviors.
 */
#[CoversNothing]
final class SidebarNavActiveStateTest extends HttpKernelTestCase
{
    private static int $uid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getStorage('user');
        $user = $users->create(['name' => 'Sidebar Nav Tester', 'mail' => 'sidebar-nav@example.test', 'status' => true, 'created' => time(), 'roles' => [], 'permissions' => []]);
        $users->save($user);
        self::$uid = (int) $user->id();
    }

    /**
     * Hrefs of sidebar links rendered with the active class.
     *
     * @return list<string>
     */
    private function activeSidebarHrefs(string $body): array
    {
        preg_match_all('/<a href="([^"]*)" class="sbx__item sbx__item--active"/', $body, $matches);

        return $matches[1];
    }

    #[Test]
    public function home_is_active_on_root_and_no_other_link_is(): void
    {
        $response = $this->send('GET', '/');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['/'], $this->activeSidebarHrefs((string) $response->getContent()));
    }

    #[Test]
    public function dictionary_is_active_on_language_but_search_and_home_are_not(): void
    {
        $response = $this->send('GET', '/language');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['/language'], $this->activeSidebarHrefs((string) $response->getContent()));
    }

    #[Test]
    public function search_is_active_on_language_search_but_dictionary_is_not(): void
    {
        $response = $this->send('GET', '/language/search', ['q' => 'nibi']);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['/language/search'], $this->activeSidebarHrefs((string) $response->getContent()));
    }

    #[Test]
    public function events_is_active_on_events_index(): void
    {
        $response = $this->send('GET', '/events');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['/events'], $this->activeSidebarHrefs((string) $response->getContent()));
    }

    #[Test]
    public function home_is_active_on_feed_for_an_authenticated_member(): void
    {
        $response = $this->sendAuthenticated('/feed');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['/'], $this->activeSidebarHrefs((string) $response->getContent()));
    }

    #[Test]
    public function language_prefixed_urls_still_activate_the_matching_link(): void
    {
        $response = $this->send('GET', '/oj/language');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        // With current language oj, lang_url renders prefixed hrefs — the
        // Dictionary link is /oj/language and must still be the active one.
        self::assertSame(['/oj/language'], $this->activeSidebarHrefs((string) $response->getContent()));
    }

    /**
     * Same session-injection pattern as FeedFilterTabTest::sendAuthenticated —
     * /feed redirects anonymous visitors to /, so the active-state on /feed is
     * only observable with a logged-in account.
     */
    private function sendAuthenticated(string $path): Response
    {
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
        $_SESSION['waaseyaa_uid'] = self::$uid;

        try {
            return self::$kernel->handle();
        } finally {
            unset($_SESSION['waaseyaa_uid']);
        }
    }
}
