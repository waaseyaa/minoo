<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Geo;

use App\Domain\Geo\Service\LocationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(LocationService::class)]
final class LocationServiceTest extends TestCase
{
    private function community(int $id, string $name, float $lat, float $lon): ContentEntityBase
    {
        $c = $this->createMock(ContentEntityBase::class);
        $c->method('id')->willReturn($id);
        $c->method('get')->willReturnCallback(static fn (string $f) => match ($f) {
            'name' => $name, 'latitude' => $lat, 'longitude' => $lon, default => null,
        });
        return $c;
    }

    /** @param list<ContentEntityBase> $rows */
    private function etmReturning(array $rows, ?ContentEntityBase $loadById = null): EntityTypeManager
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('accessCheck')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn(array_map(static fn ($c) => $c->id(), $rows));

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->willReturn($rows);
        $storage->method('load')->willReturn($loadById);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);
        return $etm;
    }

    #[Test]
    public function resolve_coordinates_returns_the_community_centroid_not_the_exact_position(): void
    {
        $near = $this->community(7, 'Near', 46.20, -82.10);
        $service = new LocationService($this->etmReturning([
            $this->community(1, 'Far', 50.0, -90.0),
            $near,
        ]));

        // A member shares a precise device position once.
        $ctx = $service->resolveCoordinates(46.217, -82.067);

        $this->assertTrue($ctx->hasLocation());
        $this->assertSame(7, $ctx->communityId);
        $this->assertSame('Near', $ctx->communityName);
        // Coarse: the context holds the COMMUNITY centroid, never the device coords.
        $this->assertSame(46.20, $ctx->latitude);
        $this->assertSame(-82.10, $ctx->longitude);
        $this->assertNotSame(46.217, $ctx->latitude);
    }

    #[Test]
    public function resolve_community_loads_the_chosen_home_territory(): void
    {
        $chosen = $this->community(42, 'Sagamok', 46.167, -82.217);
        $service = new LocationService($this->etmReturning([], $chosen));

        $ctx = $service->resolveCommunity(42);
        $this->assertSame(42, $ctx->communityId);
        $this->assertSame('chosen', $ctx->source);
    }

    #[Test]
    public function unknown_community_yields_no_location(): void
    {
        $service = new LocationService($this->etmReturning([], null));
        $this->assertFalse($service->resolveCommunity(999)->hasLocation());
        $this->assertFalse($service->resolveCommunity(0)->hasLocation());
    }
}
