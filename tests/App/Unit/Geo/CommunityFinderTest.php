<?php

declare(strict_types=1);

namespace App\Tests\Unit\Geo;

use App\Domain\Geo\Service\CommunityFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(CommunityFinder::class)]
final class CommunityFinderTest extends TestCase
{
    private CommunityFinder $finder;

    protected function setUp(): void
    {
        $this->finder = new CommunityFinder();
    }

    #[Test]
    public function find_nearest_returns_closest_community(): void
    {
        $communities = [
            $this->makeCommunity(1, 'Sagamok', 46.15, -81.77),
            $this->makeCommunity(2, 'Sudbury', 46.49, -81.00),
            $this->makeCommunity(3, 'Sault Ste. Marie', 46.52, -84.35),
        ];

        // Point near Sagamok
        $result = $this->finder->findNearest(46.16, -81.78, $communities);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['community']->id());
        $this->assertIsFloat($result['distanceKm']);
    }

    #[Test]
    public function find_nearest_returns_null_for_empty_list(): void
    {
        $result = $this->finder->findNearest(46.15, -81.77, []);

        $this->assertNull($result);
    }

    #[Test]
    public function find_nearby_returns_sorted_by_distance(): void
    {
        $communities = [
            $this->makeCommunity(1, 'Sagamok', 46.15, -81.77),
            $this->makeCommunity(2, 'Sudbury', 46.49, -81.00),
            $this->makeCommunity(3, 'Sault Ste. Marie', 46.52, -84.35),
        ];

        // Point near Sudbury — expect Sudbury first, Sagamok second
        $results = $this->finder->findNearby(46.50, -81.00, $communities, 2);

        $this->assertCount(2, $results);
        $this->assertSame(2, $results[0]['community']->id());
        $this->assertSame(1, $results[1]['community']->id());
        $this->assertLessThan($results[1]['distanceKm'], $results[0]['distanceKm']);
    }

    #[Test]
    public function skips_communities_without_coordinates(): void
    {
        $communities = [
            $this->makeCommunity(1, 'No Coords', null, null),
            $this->makeCommunity(2, 'Sudbury', 46.49, -81.00),
        ];

        $result = $this->finder->findNearest(46.50, -81.00, $communities);

        $this->assertNotNull($result);
        $this->assertSame(2, $result['community']->id());
    }

    private function makeCommunity(int $id, string $name, ?float $lat, ?float $lon): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnCallback(
            fn(string $field): mixed => match ($field) {
                'name' => $name,
                'latitude' => $lat,
                'longitude' => $lon,
                default => null,
            }
        );

        return $mock;
    }
}
