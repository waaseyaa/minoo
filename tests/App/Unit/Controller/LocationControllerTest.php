<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\LocationController;
use App\Entity\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(LocationController::class)]
final class LocationControllerTest extends TestCase
{
    /**
     * @return array{LocationController, EntityStorageInterface}
     */
    private function makeController(): array
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);
        $twig = $this->createMock(Environment::class);
        $controller = new LocationController($etm, $twig);

        return [$controller, $storage];
    }

    #[Test]
    public function current_returns_none_when_no_location(): void
    {
        [$controller] = $this->makeController();
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/api/location/current');
        $request->attributes->set('_session', []);

        $response = $controller->current([], [], $account, $request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($data['hasLocation']);
    }

    #[Test]
    public function set_stores_community_and_returns_context(): void
    {
        [$controller, $storage] = $this->makeController();
        $account = $this->createMock(AccountInterface::class);

        $community = new Community([
            'cid' => 1,
            'name' => 'Sagamok Anishnawbek',
            'slug' => 'sagamok',
            'latitude' => 46.15,
            'longitude' => -82.0,
        ]);
        $storage->method('load')->with(1)->willReturn($community);

        $request = HttpRequest::create(
            '/api/location/set',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['community_id' => 1]),
        );

        $response = $controller->set([], [], $account, $request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('Sagamok Anishnawbek', $data['communityName']);
    }

    #[Test]
    public function update_resolves_from_coordinates(): void
    {
        [$controller, $storage] = $this->makeController();
        $account = $this->createMock(AccountInterface::class);

        $community = new Community([
            'cid' => 1,
            'name' => 'Sagamok Anishnawbek',
            'slug' => 'sagamok',
            'latitude' => 46.15,
            'longitude' => -82.0,
            'status' => 1,
        ]);

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([1]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->with([1])->willReturn([1 => $community]);

        $request = HttpRequest::create(
            '/api/location/update',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['latitude' => 46.15, 'longitude' => -82.0]),
        );

        $response = $controller->update([], [], $account, $request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('Sagamok Anishnawbek', $data['communityName']);
    }
}
