<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\NewsletterAdminApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(NewsletterAdminApiController::class)]
final class NewsletterAdminApiControllerTest extends TestCase
{
    private EntityTypeManager $etm;
    private Environment $twig;
    private EntityStorageInterface $editionStorage;
    private EntityStorageInterface $itemStorage;
    private AccountInterface $account;
    private NewsletterAdminApiController $controller;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->twig = $this->createMock(Environment::class);
        $this->editionStorage = $this->createMock(EntityStorageInterface::class);
        $this->itemStorage = $this->createMock(EntityStorageInterface::class);

        $this->etm->method('getStorage')->willReturnMap([
            ['newsletter_edition', $this->editionStorage],
            ['newsletter_item', $this->itemStorage],
        ]);

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('id')->willReturn(42);

        $this->controller = new NewsletterAdminApiController($this->etm, $this->twig);
    }

    // --- listEditions ---

    #[Test]
    public function list_editions_returns_empty_array_when_no_editions(): void
    {
        $this->editionStorage->method('loadMultiple')->willReturn([]);

        $response = $this->controller->listEditions([], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame([], $data);
    }

    #[Test]
    public function list_editions_returns_edition_data(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(1);
        $edition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'headline' => 'Spring Issue',
            'volume' => 1,
            'issue_number' => 1,
            'community_id' => 'manitoulin-regional',
            'status' => 'draft',
            'created_at' => 1700000000,
            default => null,
        });

        $this->editionStorage->method('loadMultiple')->willReturn([$edition]);

        $response = $this->controller->listEditions([], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame('Spring Issue', $data[0]['headline']);
        $this->assertSame('draft', $data[0]['status']);
    }

    // --- createEdition ---

    #[Test]
    public function create_edition_returns_422_for_empty_body(): void
    {
        $request = new HttpRequest(content: '');

        $response = $this->controller->createEdition([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function create_edition_returns_422_for_missing_required_fields(): void
    {
        $request = new HttpRequest(
            content: json_encode(['headline' => 'Test']),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->createEdition([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $data);
    }

    #[Test]
    public function create_edition_saves_and_returns_201(): void
    {
        $savedEdition = $this->createMock(ContentEntityBase::class);
        $savedEdition->method('id')->willReturn(5);
        $savedEdition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'headline' => 'Summer Issue',
            'volume' => 2,
            'issue_number' => 3,
            'community_id' => 'manitoulin-regional',
            'status' => 'draft',
            'created_by' => 42,
            'created_at' => 1700000000,
            default => null,
        });

        $this->editionStorage->method('create')->willReturn($savedEdition);
        $this->editionStorage->expects($this->once())->method('save')->with($savedEdition);

        $request = new HttpRequest(
            content: json_encode([
                'headline' => 'Summer Issue',
                'volume' => 2,
                'issue_number' => 3,
                'community_id' => 'manitoulin-regional',
            ]),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->createEdition([], [], $this->account, $request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame(5, $data['id']);
        $this->assertSame('Summer Issue', $data['headline']);
        $this->assertSame('draft', $data['status']);
        $this->assertSame(42, $data['created_by']);
    }

    // --- getEdition ---

    #[Test]
    public function get_edition_returns_404_when_not_found(): void
    {
        $this->editionStorage->method('load')->with(999)->willReturn(null);

        $response = $this->controller->getEdition(['id' => '999'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function get_edition_returns_edition_with_items_grouped_by_section(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(1);
        $edition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'headline' => 'Spring Issue',
            'volume' => 1,
            'issue_number' => 1,
            'community_id' => 'manitoulin-regional',
            'status' => 'draft',
            'created_by' => 42,
            'pdf_path' => null,
            'pdf_hash' => null,
            'sent_at' => null,
            'created_at' => 1700000000,
            default => null,
        });

        $this->editionStorage->method('load')->with(1)->willReturn($edition);

        // Two items: one in 'news' section, one in 'cover' section
        $item1 = $this->createMock(ContentEntityBase::class);
        $item1->method('id')->willReturn(10);
        $item1->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 1,
            'section' => 'cover',
            'position' => 1,
            'source_type' => null,
            'source_id' => null,
            'inline_title' => 'Cover Title',
            'inline_body' => 'Cover body',
            'editor_blurb' => '',
            'included' => 1,
            default => null,
        });

        $item2 = $this->createMock(ContentEntityBase::class);
        $item2->method('id')->willReturn(11);
        $item2->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 1,
            'section' => 'news',
            'position' => 1,
            'source_type' => 'post',
            'source_id' => 5,
            'inline_title' => null,
            'inline_body' => null,
            'editor_blurb' => 'Breaking news',
            'included' => 1,
            default => null,
        });

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([$item1, $item2]);
        $this->itemStorage->method('getQuery')->willReturn($query);

        $response = $this->controller->getEdition(['id' => '1'], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);

        // Check edition metadata
        $this->assertSame(1, $data['edition']['id']);
        $this->assertSame('Spring Issue', $data['edition']['headline']);

        // Check items_by_section
        $this->assertArrayHasKey('items_by_section', $data);
        $this->assertCount(1, $data['items_by_section']['cover']);
        $this->assertCount(1, $data['items_by_section']['news']);
        $this->assertSame(10, $data['items_by_section']['cover'][0]['id']);
        $this->assertSame(11, $data['items_by_section']['news'][0]['id']);

        // Check empty sections exist
        $this->assertSame([], $data['items_by_section']['events']);
        $this->assertSame([], $data['items_by_section']['teachings']);

        // Check section_order: inline_sections first, then sections
        $this->assertArrayHasKey('section_order', $data);
        $this->assertSame('cover', $data['section_order'][0]);
        $this->assertContains('news', $data['section_order']);
        $this->assertContains('back_page', $data['section_order']);
    }

    #[Test]
    public function get_edition_sorts_items_by_position(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(1);
        $edition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'headline' => 'Test',
            'volume' => 1,
            'issue_number' => 1,
            'community_id' => 'test',
            'status' => 'draft',
            'created_by' => 1,
            'pdf_path' => null,
            'pdf_hash' => null,
            'sent_at' => null,
            'created_at' => 1700000000,
            default => null,
        });

        $this->editionStorage->method('load')->with(1)->willReturn($edition);

        // Items in same section, out of position order
        $itemA = $this->createMock(ContentEntityBase::class);
        $itemA->method('id')->willReturn(20);
        $itemA->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 1,
            'section' => 'news',
            'position' => 3,
            'source_type' => 'post',
            'source_id' => 1,
            'inline_title' => null,
            'inline_body' => null,
            'editor_blurb' => 'Third',
            'included' => 1,
            default => null,
        });

        $itemB = $this->createMock(ContentEntityBase::class);
        $itemB->method('id')->willReturn(21);
        $itemB->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 1,
            'section' => 'news',
            'position' => 1,
            'source_type' => 'post',
            'source_id' => 2,
            'inline_title' => null,
            'inline_body' => null,
            'editor_blurb' => 'First',
            'included' => 1,
            default => null,
        });

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([$itemA, $itemB]);
        $this->itemStorage->method('getQuery')->willReturn($query);

        $response = $this->controller->getEdition(['id' => '1'], [], $this->account, new HttpRequest());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);

        // position=1 should come before position=3
        $this->assertSame(21, $data['items_by_section']['news'][0]['id']);
        $this->assertSame(20, $data['items_by_section']['news'][1]['id']);
    }

    // --- addItem ---

    #[Test]
    public function add_item_returns_404_when_edition_not_found(): void
    {
        $this->editionStorage->method('load')->with(999)->willReturn(null);

        $request = new HttpRequest(
            content: json_encode(['section' => 'news']),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->addItem(['id' => '999'], [], $this->account, $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function add_item_returns_422_when_section_missing(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(1);
        $this->editionStorage->method('load')->with(1)->willReturn($edition);

        $request = new HttpRequest(
            content: json_encode([]),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->addItem(['id' => '1'], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function add_item_creates_item_with_auto_position(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(1);
        $this->editionStorage->method('load')->with(1)->willReturn($edition);

        // Count query returns 2 existing items in this section
        $countQuery = $this->createMock(EntityQueryInterface::class);
        $countQuery->method('condition')->willReturnSelf();
        $countQuery->method('count')->willReturnSelf();
        $countQuery->method('execute')->willReturn([2]);
        $this->itemStorage->method('getQuery')->willReturn($countQuery);

        $createdItem = $this->createMock(ContentEntityBase::class);
        $createdItem->method('id')->willReturn(50);
        $createdItem->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 1,
            'section' => 'news',
            'position' => 3,
            'source_type' => 'inline',
            'source_id' => 0,
            'inline_title' => 'My Title',
            'inline_body' => 'My Body',
            'editor_blurb' => 'A blurb',
            'included' => 1,
            default => null,
        });

        $this->itemStorage->method('create')->willReturn($createdItem);
        $this->itemStorage->expects($this->once())->method('save')->with($createdItem);

        $request = new HttpRequest(
            content: json_encode([
                'section' => 'news',
                'inline_title' => 'My Title',
                'inline_body' => 'My Body',
                'editor_blurb' => 'A blurb',
            ]),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->addItem(['id' => '1'], [], $this->account, $request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame(50, $data['id']);
        $this->assertSame('news', $data['section']);
        $this->assertSame(3, $data['position']);
        $this->assertSame('My Title', $data['inline_title']);
        $this->assertSame(1, $data['included']);
    }

    // --- removeItem ---

    #[Test]
    public function remove_item_returns_404_when_item_not_found(): void
    {
        $this->itemStorage->method('load')->with(999)->willReturn(null);

        $response = $this->controller->removeItem(['id' => '1', 'itemId' => '999'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function remove_item_returns_404_when_item_belongs_to_different_edition(): void
    {
        $item = $this->createMock(ContentEntityBase::class);
        $item->method('id')->willReturn(10);
        $item->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 99,
            default => null,
        });
        $this->itemStorage->method('load')->with(10)->willReturn($item);

        $response = $this->controller->removeItem(['id' => '1', 'itemId' => '10'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function remove_item_deletes_and_returns_200(): void
    {
        $item = $this->createMock(ContentEntityBase::class);
        $item->method('id')->willReturn(10);
        $item->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 1,
            default => null,
        });
        $this->itemStorage->method('load')->with(10)->willReturn($item);
        $this->itemStorage->expects($this->once())->method('delete')->with([$item]);

        $response = $this->controller->removeItem(['id' => '1', 'itemId' => '10'], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['deleted']);
    }

    // --- reorderItem ---

    #[Test]
    public function reorder_item_returns_404_when_item_not_found(): void
    {
        $this->itemStorage->method('load')->with(999)->willReturn(null);

        $request = new HttpRequest(
            content: json_encode(['position' => 2]),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->reorderItem(['id' => '1', 'itemId' => '999'], [], $this->account, $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function reorder_item_returns_404_when_item_belongs_to_different_edition(): void
    {
        $item = $this->createMock(ContentEntityBase::class);
        $item->method('id')->willReturn(10);
        $item->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'edition_id' => 99,
            default => null,
        });
        $this->itemStorage->method('load')->with(10)->willReturn($item);

        $request = new HttpRequest(
            content: json_encode(['position' => 2]),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->reorderItem(['id' => '1', 'itemId' => '10'], [], $this->account, $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function reorder_item_updates_position_and_returns_200(): void
    {
        $item = $this->createMock(ContentEntityBase::class);
        $item->method('id')->willReturn(10);

        $getValues = [
            'edition_id' => 1,
            'section' => 'news',
            'position' => 5,
            'source_type' => 'inline',
            'source_id' => 0,
            'inline_title' => 'Title',
            'inline_body' => 'Body',
            'editor_blurb' => 'Blurb',
            'included' => 1,
        ];

        $item->method('get')->willReturnCallback(function (string $field) use (&$getValues) {
            return $getValues[$field] ?? null;
        });

        $item->expects($this->once())->method('set')->with('position', 5)->willReturnSelf();
        $this->itemStorage->method('load')->with(10)->willReturn($item);
        $this->itemStorage->expects($this->once())->method('save')->with($item);

        $request = new HttpRequest(
            content: json_encode(['position' => 5]),
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        $response = $this->controller->reorderItem(['id' => '1', 'itemId' => '10'], [], $this->account, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame(10, $data['id']);
        $this->assertSame(5, $data['position']);
    }
}
