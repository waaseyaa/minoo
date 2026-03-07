<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Community::class)]
final class CommunityTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $community = new Community([
            'name' => 'Sagamok Anishnawbek',
            'community_type' => 'first_nation',
        ]);

        $this->assertSame('Sagamok Anishnawbek', $community->get('name'));
        $this->assertSame('first_nation', $community->get('community_type'));
        $this->assertSame('community', $community->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_timestamps_to_zero(): void
    {
        $community = new Community(['name' => 'Test', 'community_type' => 'town']);

        $this->assertSame(0, $community->get('created_at'));
        $this->assertSame(0, $community->get('updated_at'));
    }

    #[Test]
    public function it_supports_optional_geo_fields(): void
    {
        $community = new Community([
            'name' => 'Sagamok Anishnawbek',
            'community_type' => 'first_nation',
            'latitude' => 46.15,
            'longitude' => -81.95,
            'population' => 3200,
        ]);

        $this->assertSame(46.15, $community->get('latitude'));
        $this->assertSame(-81.95, $community->get('longitude'));
        $this->assertSame(3200, $community->get('population'));
    }
}
