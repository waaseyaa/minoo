<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Surface;

use Minoo\Surface\MinooSurfaceHost;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\AdminSurface\Catalog\CatalogBuilder;
use Waaseyaa\AdminSurface\Host\AdminSurfaceSessionData;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(MinooSurfaceHost::class)]
final class MinooSurfaceHostTest extends TestCase
{
    #[Test]
    public function resolve_session_returns_null_for_unauthenticated_request(): void
    {
        $host = new MinooSurfaceHost($this->createMock(EntityTypeManager::class));
        $request = Request::create('/admin/surface/session');

        $this->assertNull($host->resolveSession($request));
    }

    #[Test]
    public function resolve_session_returns_null_for_non_admin(): void
    {
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('hasPermission')->willReturn(false);
        $account->method('getRoles')->willReturn(['authenticated']);

        $host = new MinooSurfaceHost($this->createMock(EntityTypeManager::class));
        $request = Request::create('/admin/surface/session');
        $request->attributes->set('account', $account);

        $this->assertNull($host->resolveSession($request));
    }

    #[Test]
    public function resolve_session_returns_data_for_admin(): void
    {
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn(42);
        $account->method('hasPermission')->willReturn(true);
        $account->method('getRoles')->willReturn(['administrator']);

        $host = new MinooSurfaceHost($this->createMock(EntityTypeManager::class));
        $request = Request::create('/admin/surface/session');
        $request->attributes->set('account', $account);

        $session = $host->resolveSession($request);

        $this->assertNotNull($session);
        $this->assertSame('42', $session->accountId);
        $this->assertSame('minoo', $session->tenantId);
        $this->assertContains('administrator', $session->roles);
    }

    #[Test]
    public function build_catalog_returns_entity_definitions(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getDefinitions')->willReturn([
            new EntityType(
                id: 'event',
                label: 'Event',
                class: \stdClass::class,
                keys: ['id' => 'eid'],
                group: 'events',
                fieldDefinitions: [
                    'title' => ['type' => 'string', 'label' => 'Title'],
                ],
            ),
        ]);

        $host = new MinooSurfaceHost($etm);
        $session = new AdminSurfaceSessionData(
            accountId: '1',
            accountName: 'Admin',
            roles: ['administrator'],
            policies: [],
        );

        $catalog = $host->buildCatalog($session);

        $this->assertInstanceOf(CatalogBuilder::class, $catalog);
        $built = $catalog->build();
        $this->assertCount(1, $built);
        $this->assertSame('event', $built[0]['id']);
        $this->assertSame('Event', $built[0]['label']);
        $this->assertSame('events', $built[0]['group']);
    }

    #[Test]
    public function list_returns_error_for_unknown_type(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(false);

        $host = new MinooSurfaceHost($etm);
        $result = $host->list('nonexistent');

        $this->assertFalse($result->ok);
    }

    #[Test]
    public function get_returns_error_for_unknown_type(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(false);

        $host = new MinooSurfaceHost($etm);
        $result = $host->get('nonexistent', '123');

        $this->assertFalse($result->ok);
    }

    #[Test]
    public function action_returns_error_for_unknown_action(): void
    {
        $storage = $this->createMock(EntityStorageInterface::class);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturn($storage);

        $host = new MinooSurfaceHost($etm);
        $result = $host->action('event', 'nonexistent');

        $this->assertFalse($result->ok);
    }

    #[Test]
    public function resolve_session_populates_policy_names(): void
    {
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('hasPermission')->willReturn(true);
        $account->method('getRoles')->willReturn(['administrator']);

        $host = new MinooSurfaceHost($this->createMock(EntityTypeManager::class));
        $request = Request::create('/admin/surface/session');
        $request->attributes->set('account', $account);

        $session = $host->resolveSession($request);

        $this->assertNotNull($session);
        $this->assertIsArray($session->policies);
        // Minoo has 11 access policy classes in src/Access/
        $this->assertNotEmpty($session->policies);
        $this->assertContains('Minoo\\Access\\EventAccessPolicy', $session->policies);
    }

    #[Test]
    public function get_returns_403_when_access_denied(): void
    {
        $entity = $this->createStub(\Waaseyaa\Entity\EntityInterface::class);
        $entity->method('toArray')->willReturn(['id' => '1']);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->willReturn($entity);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturn($storage);

        $accessResult = \Waaseyaa\Access\AccessResult::neutral('Denied.');
        $accessHandler = $this->createMock(\Waaseyaa\Access\EntityAccessHandler::class);
        $accessHandler->method('check')->willReturn($accessResult);

        $host = new MinooSurfaceHost($etm, $accessHandler);

        // Simulate resolveSession to set currentAccount
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('hasPermission')->willReturn(true);
        $account->method('getRoles')->willReturn(['authenticated']);
        $request = Request::create('/admin/surface/session');
        $request->attributes->set('account', $account);
        $host->resolveSession($request);

        $result = $host->get('event', '1');

        $this->assertFalse($result->ok);
        $this->assertSame(403, $result->error['status']);
    }

    #[Test]
    public function list_filters_entities_by_access(): void
    {
        $allowed = $this->createStub(\Waaseyaa\Entity\EntityInterface::class);
        $allowed->method('toArray')->willReturn(['id' => '1', 'title' => 'Visible']);
        $denied = $this->createStub(\Waaseyaa\Entity\EntityInterface::class);
        $denied->method('toArray')->willReturn(['id' => '2', 'title' => 'Hidden']);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('loadMultiple')->willReturn([$allowed, $denied]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturn($storage);

        $accessHandler = $this->createMock(\Waaseyaa\Access\EntityAccessHandler::class);
        $accessHandler->method('check')->willReturnCallback(
            fn($entity) => $entity === $allowed
                ? \Waaseyaa\Access\AccessResult::allowed('OK')
                : \Waaseyaa\Access\AccessResult::neutral('Denied'),
        );

        $host = new MinooSurfaceHost($etm, $accessHandler);

        // Simulate resolveSession
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('hasPermission')->willReturn(true);
        $account->method('getRoles')->willReturn(['authenticated']);
        $request = Request::create('/');
        $request->attributes->set('account', $account);
        $host->resolveSession($request);

        $result = $host->list('event');

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
        $this->assertSame('Visible', $result->data[0]['title']);
    }
}
