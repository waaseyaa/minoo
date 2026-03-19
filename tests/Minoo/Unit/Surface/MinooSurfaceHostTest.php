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
    public function build_catalog_marks_config_entities_read_only(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getDefinitions')->willReturn([
            new EntityType(
                id: 'event_type',
                label: 'Event Type',
                class: \Minoo\Entity\EventType::class,
                keys: ['id' => 'type'],
                group: 'events',
            ),
        ]);

        $host = new MinooSurfaceHost($etm);
        $session = new AdminSurfaceSessionData(
            accountId: '1',
            accountName: 'Admin',
            roles: ['administrator'],
            policies: [],
        );

        $built = $host->buildCatalog($session)->build();

        $this->assertFalse($built[0]['capabilities']['create']);
        $this->assertFalse($built[0]['capabilities']['update']);
        $this->assertFalse($built[0]['capabilities']['delete']);
        $this->assertTrue($built[0]['capabilities']['list']);
        $this->assertTrue($built[0]['capabilities']['get']);
    }

    #[Test]
    public function build_catalog_marks_ingest_log_read_only(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getDefinitions')->willReturn([
            new EntityType(
                id: 'ingest_log',
                label: 'Ingestion Log',
                class: \stdClass::class,
                keys: ['id' => 'ilid'],
                group: 'ingestion',
            ),
        ]);

        $host = new MinooSurfaceHost($etm);
        $session = new AdminSurfaceSessionData(
            accountId: '1',
            accountName: 'Admin',
            roles: ['administrator'],
            policies: [],
        );

        $built = $host->buildCatalog($session)->build();

        $this->assertFalse($built[0]['capabilities']['create']);
        $this->assertFalse($built[0]['capabilities']['delete']);
    }

    #[Test]
    public function build_catalog_adds_delete_action_for_content_entities(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getDefinitions')->willReturn([
            new EntityType(
                id: 'event',
                label: 'Event',
                class: \stdClass::class,
                keys: ['id' => 'eid'],
                group: 'events',
            ),
        ]);

        $host = new MinooSurfaceHost($etm);
        $session = new AdminSurfaceSessionData(
            accountId: '1',
            accountName: 'Admin',
            roles: ['administrator'],
            policies: [],
        );

        $built = $host->buildCatalog($session)->build();

        $this->assertTrue($built[0]['capabilities']['delete']);
        $this->assertCount(1, $built[0]['actions']);
        $this->assertSame('delete', $built[0]['actions'][0]['id']);
        $this->assertTrue($built[0]['actions'][0]['dangerous']);
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
}
