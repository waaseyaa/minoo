<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Service;

use Minoo\Domain\Geo\Service\CommunityFinder;
use Minoo\Domain\Geo\ValueObject\LocationContext;
use Minoo\Service\LocationResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

// CommunityFinder is final — tests use real instances with mock entities.

#[CoversClass(LocationResolver::class)]
final class LocationResolverTest extends TestCase
{
    private function makeLocation(float $lat, float $lon): LocationContext
    {
        return new LocationContext(
            communityId: 1,
            communityName: 'Test Community',
            latitude: $lat,
            longitude: $lon,
            source: 'manual',
        );
    }

    private function makeCommunity(int $id, string $name, float $lat, float $lon): ContentEntityBase
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

    #[Test]
    public function testResolveLocationFromRequest(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $finder = new CommunityFinder();

        $request = HttpRequest::create('/');
        $request->attributes->set('_session', [
            'minoo_location' => [
                'communityId' => 1,
                'communityName' => 'Sagamok',
                'latitude' => 46.15,
                'longitude' => -81.77,
                'source' => 'manual',
            ],
        ]);

        $resolver = new LocationResolver($etm, $finder);
        $location = $resolver->resolveLocation($request);

        $this->assertTrue($location->hasLocation());
        $this->assertSame(1, $location->communityId);
        $this->assertSame('manual', $location->source);
    }

    #[Test]
    public function testResolveCoordinates(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $finder = new CommunityFinder();

        $resolver = new LocationResolver($etm, $finder);
        $location = $this->makeLocation(46.49, -81.00);
        $coords = $resolver->resolveCoordinates($location);

        $this->assertSame(['lat' => 46.49, 'lng' => -81.00], $coords);
    }

    #[Test]
    public function testResolveCoordinatesReturnsNullWhenNoCoords(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $finder = new CommunityFinder();

        $resolver = new LocationResolver($etm, $finder);
        $coords = $resolver->resolveCoordinates(LocationContext::none());

        $this->assertNull($coords);
    }

    #[Test]
    public function testResolveNearbyCommunitiesDelegatesToCommunityFinder(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $finder = new CommunityFinder();

        $community = $this->makeCommunity(1, 'Sagamok', 46.15, -81.77);
        $location = $this->makeLocation(46.15, -81.77);

        $resolver = new LocationResolver($etm, $finder);
        $result = $resolver->resolveNearbyCommunities($location, [$community]);

        $this->assertCount(1, $result);
        $this->assertSame($community, $result[0]['community']);
        $this->assertEqualsWithDelta(0.0, $result[0]['distanceKm'], 1.0);
    }

    #[Test]
    public function testResolveNearbyCommunitiesReturnsEmptyWhenNoCoords(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $finder = new CommunityFinder();

        $resolver = new LocationResolver($etm, $finder);
        $result = $resolver->resolveNearbyCommunities(LocationContext::none(), []);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testResolveMixedNearbyDelegatesToCommunityFinder(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $finder = new CommunityFinder();

        $fn = $this->makeCommunity(1, 'Sagamok First Nation', 46.15, -81.77);
        $muni = $this->makeCommunity(2, 'Massey', 46.21, -82.07);
        $location = $this->makeLocation(46.15, -81.77);

        $resolver = new LocationResolver($etm, $finder);
        $result = $resolver->resolveMixedNearby($location, [$fn], [$muni]);

        $this->assertCount(2, $result);
        $this->assertSame($fn, $result[0]['community']);
        $this->assertSame($muni, $result[1]['community']);
        $this->assertLessThan($result[1]['distanceKm'], $result[0]['distanceKm'] + 0.1);
    }
}
