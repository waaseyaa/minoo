<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CommunityController;
use Minoo\Entity\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(CommunityController::class)]
final class CommunityControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EntityStorageInterface $storage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();
        $this->query->method('range')->willReturnSelf();

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('community')
            ->willReturn($this->storage);

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function autocomplete_returns_json_array(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok Anishnawbek', 'community_type' => 'first_nation']);
        $serpent = new Community(['cid' => 2, 'name' => 'Serpent River First Nation', 'community_type' => 'first_nation']);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $sagamok, 2 => $serpent]);

        $this->request = HttpRequest::create('/?q=Sa');
        $controller = new CommunityController($this->entityTypeManager);
        $response = $controller->autocomplete([], ['q' => 'Sa'], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $data = json_decode($response->content, true);
        $this->assertCount(2, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame('Sagamok Anishnawbek', $data[0]['name']);
    }

    #[Test]
    public function autocomplete_returns_empty_array_when_no_matches(): void
    {
        $this->query->method('execute')->willReturn([]);

        $this->request = HttpRequest::create('/?q=zzz');
        $controller = new CommunityController($this->entityTypeManager);
        $response = $controller->autocomplete([], ['q' => 'zzz'], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $data = json_decode($response->content, true);
        $this->assertSame([], $data);
    }
}
