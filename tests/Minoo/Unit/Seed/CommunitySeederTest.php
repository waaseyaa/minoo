<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\CommunitySeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommunitySeeder::class)]
final class CommunitySeederTest extends TestCase
{
    #[Test]
    public function it_provides_north_shore_communities(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();

        $this->assertNotEmpty($communities);
        $this->assertArrayHasKey('name', $communities[0]);
        $this->assertArrayHasKey('community_type', $communities[0]);
    }

    #[Test]
    public function it_includes_first_nations_and_towns(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();
        $types = array_unique(array_column($communities, 'community_type'));

        $this->assertContains('first_nation', $types);
        $this->assertContains('town', $types);
        $this->assertContains('region', $types);
    }

    #[Test]
    public function it_includes_sagamok(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();
        $names = array_column($communities, 'name');

        $this->assertContains('Sagamok Anishnawbek', $names);
    }

    #[Test]
    public function it_provides_ten_communities(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();

        $this->assertCount(10, $communities);
    }
}
