<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Domain\Geo\Service\LocationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(LocationService::class)]
final class LocationServiceTest extends TestCase
{
    private function makeConfig(array $overrides = []): array
    {
        return array_merge([
            'geoip_db' => '/nonexistent.mmdb',
            'default_coordinates' => null,
            'cookie_name' => 'minoo_location',
            'cookie_ttl' => 86400 * 30,
        ], $overrides);
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

    private function makeQueryObj(array $ids = []): EntityQueryInterface
    {
        return new class($ids) implements EntityQueryInterface {
            /** @param array<int> $ids */
            public function __construct(private readonly array $ids) {}
            public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
            public function exists(string $field): static { return $this; }
            public function notExists(string $field): static { return $this; }
            public function sort(string $field, string $direction = 'ASC'): static { return $this; }
            public function range(int $offset, int $limit): static { return $this; }
            public function count(): static { return $this; }
            public function accessCheck(bool $check = true): static { return $this; }
            /** @return array<int> */
            public function execute(): array { return $this->ids; }
        };
    }

    #[Test]
    public function from_request_returns_context_from_session(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $service = new LocationService($etm, $this->makeConfig());

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

        $ctx = $service->fromRequest($request);

        $this->assertTrue($ctx->hasLocation());
        $this->assertSame(1, $ctx->communityId);
        $this->assertSame('manual', $ctx->source);
    }

    #[Test]
    public function from_request_returns_context_from_cookie(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $service = new LocationService($etm, $this->makeConfig());

        $cookieData = json_encode([
            'communityId' => 2,
            'communityName' => 'Sudbury',
            'latitude' => 46.49,
            'longitude' => -81.00,
            'source' => 'browser',
        ], JSON_THROW_ON_ERROR);

        $request = HttpRequest::create('/', 'GET', [], ['minoo_location' => $cookieData]);
        $request->attributes->set('_session', []);

        $ctx = $service->fromRequest($request);

        $this->assertTrue($ctx->hasLocation());
        $this->assertSame(2, $ctx->communityId);
    }

    #[Test]
    public function from_request_returns_none_when_no_location_data(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $service = new LocationService($etm, $this->makeConfig([
            'geoip_db' => '/nonexistent.mmdb',
            'default_coordinates' => null,
        ]));

        $request = HttpRequest::create('/');
        $request->attributes->set('_session', []);

        $ctx = $service->fromRequest($request);

        $this->assertFalse($ctx->hasLocation());
        $this->assertSame('none', $ctx->source);
    }

    #[Test]
    public function from_request_uses_default_coordinates_for_private_ip(): void
    {
        $community = $this->makeCommunity(2, 'Sudbury', 46.49, -81.00);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($this->makeQueryObj([2]));
        $storage->method('loadMultiple')->with([2])->willReturn([2 => $community]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $service = new LocationService($etm, $this->makeConfig([
            'default_coordinates' => [46.49, -81.00],
        ]));

        $request = HttpRequest::create('/');
        $request->attributes->set('_session', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $ctx = $service->fromRequest($request);

        $this->assertTrue($ctx->hasLocation());
        $this->assertSame('ip', $ctx->source);
    }

    #[Test]
    public function resolve_from_coordinates_finds_nearest(): void
    {
        $community = $this->makeCommunity(1, 'Sagamok', 46.15, -81.77);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($this->makeQueryObj([1]));
        $storage->method('loadMultiple')->with([1])->willReturn([1 => $community]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $service = new LocationService($etm, $this->makeConfig());

        $ctx = $service->resolveFromCoordinates(46.16, -81.78);

        $this->assertTrue($ctx->hasLocation());
        $this->assertSame(1, $ctx->communityId);
        $this->assertSame('Sagamok', $ctx->communityName);
    }

    #[Test]
    public function resolve_from_community_id_returns_context(): void
    {
        $community = $this->makeCommunity(3, 'Sault Ste. Marie', 46.52, -84.35);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->with(3)->willReturn($community);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $service = new LocationService($etm, $this->makeConfig());

        $ctx = $service->resolveFromCommunityId(3);

        $this->assertTrue($ctx->hasLocation());
        $this->assertSame(3, $ctx->communityId);
        $this->assertSame('manual', $ctx->source);
    }
}
