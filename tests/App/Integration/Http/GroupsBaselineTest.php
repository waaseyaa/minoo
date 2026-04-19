<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Entity\Community;
use App\Entity\Group;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * Captures byte-equivalent baselines of the four public surfaces that render
 * group / business data:
 *
 *   - GET /businesses                         → businesses_index.html
 *   - GET /communities/{slug}                 → communities_show_with_businesses.html
 *   - GET /businesses/{public-slug}           → business_show_public.html
 *   - GET /businesses/{private-slug}          → business_show_private.html
 *
 * These baselines are the contract the group-extraction refactor (replacing
 * App\Entity\Group with Waaseyaa\Groups\Group) must preserve. Any byte-level
 * divergence after the refactor surfaces here.
 *
 * Capture flow:
 *   UPDATE_BASELINES=1 ./vendor/bin/phpunit tests/App/Integration/Http/GroupsBaselineTest.php
 * Normal run:
 *   ./vendor/bin/phpunit tests/App/Integration/Http/GroupsBaselineTest.php
 */
#[CoversNothing]
final class GroupsBaselineTest extends HttpKernelTestCase
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
            'phone' => '+17055551234',
            'email' => 'salon@example.com',
            'address' => '1 Lakeshore Rd, Wiikwemkoong, ON',
            'community_id' => (string) $communityId,
            'latitude' => 45.7800,
            'longitude' => -81.7400,
            'coordinate_source' => 'address',
            'copyright_status' => 'community_owned',
        ]);
        $groupStorage->save($public);

        $private = new Group([
            'name' => 'Cedar & Stone',
            'slug' => 'cedar-and-stone',
            'type' => 'business',
            'status' => 1,
            'consent_public' => 0,
            'consent_ai_training' => 0,
            'description' => 'Private business record.',
            'phone' => '+17055555678',
            'email' => 'cedar@example.com',
            'address' => '2 Shore Rd, Wiikwemkoong, ON',
            'community_id' => (string) $communityId,
            'latitude' => 45.7801,
            'longitude' => -81.7401,
            'coordinate_source' => 'address',
            'copyright_status' => 'community_owned',
        ]);
        $groupStorage->save($private);
    }

    #[Test]
    public function businesses_index(): void
    {
        $response = $this->send('GET', '/businesses');
        self::assertSame(200, $response->getStatusCode());
        $this->assertBaseline('businesses_index.html', $response);
    }

    #[Test]
    public function communities_show_with_businesses(): void
    {
        $response = $this->send('GET', '/communities/wiikwemkoong');
        self::assertSame(200, $response->getStatusCode());
        $this->assertBaseline('communities_show_with_businesses.html', $response);
    }

    #[Test]
    public function business_show_public(): void
    {
        $response = $this->send('GET', '/businesses/nginaajiiw-salon-spa');
        self::assertSame(200, $response->getStatusCode());
        $this->assertBaseline('business_show_public.html', $response);
    }

    #[Test]
    public function business_show_private(): void
    {
        $response = $this->send('GET', '/businesses/cedar-and-stone');
        self::assertSame(200, $response->getStatusCode());
        $this->assertBaseline('business_show_private.html', $response);
    }
}
