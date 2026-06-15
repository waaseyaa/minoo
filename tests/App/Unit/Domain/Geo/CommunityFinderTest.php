<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Geo;

use App\Domain\Geo\Service\CommunityFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(CommunityFinder::class)]
final class CommunityFinderTest extends TestCase
{
    private function community(string $name, float $lat, float $lon): ContentEntityBase
    {
        $c = $this->createMock(ContentEntityBase::class);
        $c->method('get')->willReturnCallback(static fn (string $f) => match ($f) {
            'name' => $name, 'latitude' => $lat, 'longitude' => $lon, default => null,
        });
        return $c;
    }

    #[Test]
    public function finds_the_nearest_community(): void
    {
        $finder = new CommunityFinder();
        $communities = [
            $this->community('Far', 50.0, -90.0),
            $this->community('Near', 46.2, -82.1),
            $this->community('Mid', 47.0, -83.0),
        ];

        $nearest = $finder->findNearest(46.217, -82.067, $communities);
        $this->assertNotNull($nearest);
        $this->assertSame('Near', $nearest['community']->get('name'));
    }

    #[Test]
    public function nearby_is_sorted_and_limited(): void
    {
        $finder = new CommunityFinder();
        $communities = [
            $this->community('Far', 50.0, -90.0),
            $this->community('Near', 46.2, -82.1),
            $this->community('Mid', 47.0, -83.0),
        ];

        $nearby = $finder->findNearby(46.217, -82.067, $communities, 2);
        $this->assertCount(2, $nearby);
        $this->assertSame('Near', $nearby[0]['community']->get('name'));
        $this->assertLessThanOrEqual($nearby[1]['distanceKm'], $nearby[0]['distanceKm'] + 0.0001);
    }

    #[Test]
    public function skips_communities_without_coordinates(): void
    {
        $finder = new CommunityFinder();
        $noCoords = $this->createMock(ContentEntityBase::class);
        $noCoords->method('get')->willReturn(null);

        $nearest = $finder->findNearest(46.217, -82.067, [$noCoords]);
        $this->assertNull($nearest);
    }
}
