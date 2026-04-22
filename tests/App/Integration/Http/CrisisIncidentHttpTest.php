<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Entity\Community;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class CrisisIncidentHttpTest extends HttpKernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $etm = self::$kernel->getEntityTypeManager();
        $communityStorage = $etm->getStorage('community');

        $sagamok = new Community([
            'name' => 'Sagamok Anishnawbek',
            'slug' => 'sagamok-anishnawbek',
            'community_type' => 'first_nation',
            'type' => 'unceded',
            'status' => 1,
            'content' => 'Sagamok Anishnawbek First Nation.',
            'latitude' => 46.15,
            'longitude' => -81.72,
            'province' => 'ON',
        ]);
        $communityStorage->save($sagamok);

        $sudbury = new Community([
            'name' => 'Greater Sudbury',
            'slug' => 'sudbury',
            'community_type' => 'municipality',
            'type' => 'unceded',
            'status' => 1,
            'content' => 'Greater Sudbury.',
            'latitude' => 46.49,
            'longitude' => -80.99,
            'province' => 'ON',
        ]);
        $communityStorage->save($sudbury);
    }

    #[Test]
    public function sagamok_spanish_river_flood_returns_200_with_expected_meta(): void
    {
        $response = $this->send('GET', '/communities/sagamok-anishnawbek/spanish-river-flood');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $html = $response->getContent();
        self::assertStringContainsString('<title>Spanish River flood response — Sagamok Anishnawbek — Minoo</title>', $html);
        self::assertStringContainsString('property="og:image" content="https://minoo.live/og/crisis/sagamok-spanish-river-flood.png"', $html);
        self::assertStringContainsString('name="description" content="Community flood status for Sagamok Anishnawbek:', $html);
        self::assertStringContainsString('Spanish River flood response', $html);
        self::assertStringContainsString('https://www.sagamokanishnawbek.com/sagamok-news', $html);
        self::assertStringContainsString('datetime="2026-04-22"', $html);
    }

    #[Test]
    public function sudbury_state_of_emergency_returns_200_with_expected_meta(): void
    {
        $response = $this->send('GET', '/communities/sudbury/state-of-emergency');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $html = $response->getContent();
        self::assertStringContainsString('<title>Municipal emergency status — Greater Sudbury — Minoo</title>', $html);
        self::assertStringContainsString('property="og:image" content="https://minoo.live/img/og-default.png"', $html);
        self::assertStringContainsString('name="description" content="Greater Sudbury declared a municipal state of emergency in April 2026 amid spring flooding', $html);
    }

    #[Test]
    public function unknown_incident_returns_404(): void
    {
        $response = $this->send('GET', '/communities/sagamok-anishnawbek/not-a-real-incident');
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
