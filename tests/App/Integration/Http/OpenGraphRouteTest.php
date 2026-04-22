<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Entity\Community;
use App\Entity\Event;
use App\Entity\Group;
use App\Entity\Teaching;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Open Graph PNG routes: public entity visibility matches HTML detail pages.
 */
#[CoversNothing]
final class OpenGraphRouteTest extends HttpKernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $etm = self::$kernel->getEntityTypeManager();

        $communityStorage = $etm->getStorage('community');
        $community = new Community([
            'title' => 'Wiikwemkoong',
            'slug' => 'wiikwemkoong',
            'type' => 'unceded',
            'status' => 1,
            'content' => 'Wiikwemkoong Unceded Territory.',
        ]);
        $communityStorage->save($community);
        $communityId = $community->id();

        $groupStorage = $etm->getStorage('group');
        $public = new Group([
            'name' => 'Nginaajiiw Salon & Spa',
            'slug' => 'nginaajiiw-salon-spa',
            'type' => 'business',
            'status' => 1,
            'consent_public' => 1,
            'consent_ai_training' => 0,
            'description' => 'A community-owned wellness studio.',
            'community_id' => (string) $communityId,
            'copyright_status' => 'community_owned',
        ]);
        $groupStorage->save($public);

        $eventStorage = $etm->getStorage('event');
        $event = new Event([
            'title' => 'Spring Gathering',
            'slug' => 'og-fixture-event',
            'type' => 'gathering',
            'description' => 'Public fixture event for OG route tests.',
            'community_id' => (string) $communityId,
            'copyright_status' => 'community_owned',
        ]);
        $eventStorage->save($event);

        $teachingStorage = $etm->getStorage('teaching');
        $teaching = new Teaching([
            'title' => 'Seven Grandfather Teachings',
            'slug' => 'og-fixture-teaching',
            'type' => 'story',
            'content' => 'Fixture teaching for OG route tests.',
            'community_id' => (string) $communityId,
            'consent_public' => 1,
            'copyright_status' => 'community_owned',
        ]);
        $teachingStorage->save($teaching);

        $sagamok = new Community([
            'name' => 'Sagamok Anishnawbek',
            'slug' => 'sagamok-anishnawbek',
            'type' => 'first_nation',
            'status' => 1,
            'content' => 'Sagamok Anishnawbek First Nation.',
        ]);
        $communityStorage->save($sagamok);
    }

    #[Test]
    public function business_og_png_ok(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $response = $this->send('GET', '/og/business/nginaajiiw-salon-spa.png');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $body);
        $cache = strtolower((string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('public', $cache);
    }

    #[Test]
    public function business_og_png_not_found(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $response = $this->send('GET', '/og/business/does-not-exist-zzzz.png');
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function event_og_png_ok(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $response = $this->send('GET', '/og/event/og-fixture-event.png');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $response->getContent());
    }

    #[Test]
    public function teaching_og_png_ok(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $response = $this->send('GET', '/og/teaching/og-fixture-teaching.png');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $response->getContent());
    }

    #[Test]
    public function sagamok_spanish_river_flood_crisis_og_png_ok(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $response = $this->send('GET', '/og/crisis/sagamok-spanish-river-flood.png');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $body);
        $cache = strtolower((string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('public', $cache);
    }

    #[Test]
    public function sagamok_crisis_og_png_ok_via_parameterized_route(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $response = $this->send('GET', '/og/crisis/sagamok-anishnawbek/spanish-river-flood.png');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $response->getContent());
    }

    #[Test]
    public function business_og_png_returns_304_when_etag_matches(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }

        $first = $this->send('GET', '/og/business/nginaajiiw-salon-spa.png');
        self::assertSame(200, $first->getStatusCode());
        $etag = $first->headers->get('ETag');
        self::assertNotNull($etag);

        $second = $this->send('GET', '/og/business/nginaajiiw-salon-spa.png', [], [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);
        self::assertSame(Response::HTTP_NOT_MODIFIED, $second->getStatusCode());
    }
}
