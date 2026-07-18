<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the engagement-UI wiring (#817): the feed page must load
 * engagement.js (with a cache-bust param), and the sidebar Feed entry must be
 * authenticated-only — anonymous visitors are redirected off /feed, so an
 * anonymous Feed link would dead-end at the homepage.
 */
#[CoversNothing]
final class FeedEngagementUiTest extends HttpKernelTestCase
{
    private static int $uid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $users = self::$kernel->getEntityTypeManager()->getRepository('user');
        $user = $users->create(['name' => 'Engagement UI Tester', 'mail' => 'engagement-ui@example.test', 'status' => true, 'created' => time(), 'roles' => [], 'permissions' => []]);
        $users->save($user);
        self::$uid = (int) $user->id();
    }

    #[Test]
    public function feed_page_loads_engagement_js_with_cache_bust(): void
    {
        $response = $this->sendAuthenticated('/feed');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '#<script src="/js/engagement\.js\?v=\d+" defer></script>#',
            $body,
            'The feed page must load engagement.js with a ?v=N cache-bust.',
        );
    }

    #[Test]
    public function sidebar_feed_entry_is_present_for_authenticated_users(): void
    {
        $response = $this->sendAuthenticated('/feed');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '#<a href="[^"]*/feed" class="sbx__item#',
            $body,
            'Authenticated pages must show the Feed sidebar entry.',
        );
    }

    #[Test]
    public function sidebar_feed_entry_is_absent_for_anonymous_visitors(): void
    {
        $response = $this->send('GET', '/');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertDoesNotMatchRegularExpression(
            '#<a href="[^"]*/feed" class="sbx__item#',
            $body,
            'Anonymous visitors must not see the Feed sidebar entry (anonymous /feed redirects home).',
        );
    }

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
