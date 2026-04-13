<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\NewsletterAdminApiController;
use App\Domain\Newsletter\Exception\DispatchException;
use App\Domain\Newsletter\Exception\RenderException;
use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterDispatcher;
use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use App\Domain\Newsletter\ValueObject\PdfArtifact;
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
    private EditionLifecycle $lifecycle;
    private NewsletterRenderer $renderer;
    private NewsletterDispatcher $dispatcher;
    private RenderTokenStore $tokenStore;
    private EntityStorageInterface $editionStorage;
    private EntityStorageInterface $itemStorage;
    private AccountInterface $account;
    private NewsletterAdminApiController $controller;

    /** @var array<string, EntityStorageInterface> */
    private array $entityStorages = [];

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->twig = $this->createMock(Environment::class);
        $this->lifecycle = $this->createMock(EditionLifecycle::class);
        $this->renderer = $this->createMock(NewsletterRenderer::class);
        $this->dispatcher = $this->createMock(NewsletterDispatcher::class);
        $this->tokenStore = $this->createMock(RenderTokenStore::class);
        $this->editionStorage = $this->createMock(EntityStorageInterface::class);
        $this->itemStorage = $this->createMock(EntityStorageInterface::class);

        $this->entityStorages = [
            'newsletter_edition' => $this->editionStorage,
            'newsletter_item' => $this->itemStorage,
        ];

        $this->etm->method('getStorage')->willReturnCallback(function (string $type): EntityStorageInterface {
            if (!isset($this->entityStorages[$type])) {
                $this->entityStorages[$type] = $this->createMock(EntityStorageInterface::class);
            }
            return $this->entityStorages[$type];
        });

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('id')->willReturn(42);

        $this->controller = new NewsletterAdminApiController(
            $this->etm,
            $this->twig,
            $this->lifecycle,
            $this->renderer,
            $this->dispatcher,
            $this->tokenStore,
        );
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

    // --- entitySearch ---

    #[Test]
    public function entity_search_returns_empty_results_when_q_is_empty(): void
    {
        $response = $this->controller->entitySearch([], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame([], $data['results']);
    }

    #[Test]
    public function entity_search_returns_empty_results_when_q_is_whitespace(): void
    {
        $response = $this->controller->entitySearch([], ['q' => '   '], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame([], $data['results']);
    }

    #[Test]
    public function entity_search_queries_all_searchable_types_by_default(): void
    {
        // Set up mock entities for two types, empty for others
        $eventEntity = $this->createMock(ContentEntityBase::class);
        $eventEntity->method('id')->willReturn(10);
        $eventEntity->method('label')->willReturn('Spring Gathering');

        $teachingEntity = $this->createMock(ContentEntityBase::class);
        $teachingEntity->method('id')->willReturn(20);
        $teachingEntity->method('label')->willReturn('Spring Teachings');

        foreach (NewsletterAdminApiController::SEARCHABLE_TYPES as $type) {
            $storage = $this->entityStorages[$type] ?? $this->createMock(EntityStorageInterface::class);
            $this->entityStorages[$type] = $storage;

            $query = $this->createMock(EntityQueryInterface::class);
            $query->method('condition')->willReturnSelf();
            $query->method('range')->willReturnSelf();

            $entities = match ($type) {
                'event' => [$eventEntity],
                'teaching' => [$teachingEntity],
                default => [],
            };
            $query->method('execute')->willReturn($entities);
            $storage->method('getQuery')->willReturn($query);
        }

        $response = $this->controller->entitySearch([], ['q' => 'Spring'], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $data['results']);

        $this->assertSame('event', $data['results'][0]['entity_type']);
        $this->assertSame(10, $data['results'][0]['entity_id']);
        $this->assertSame('Spring Gathering', $data['results'][0]['label']);

        $this->assertSame('teaching', $data['results'][1]['entity_type']);
        $this->assertSame(20, $data['results'][1]['entity_id']);
        $this->assertSame('Spring Teachings', $data['results'][1]['label']);
    }

    #[Test]
    public function entity_search_filters_to_requested_types(): void
    {
        $eventEntity = $this->createMock(ContentEntityBase::class);
        $eventEntity->method('id')->willReturn(10);
        $eventEntity->method('label')->willReturn('Pow Wow');

        $eventStorage = $this->createMock(EntityStorageInterface::class);
        $this->entityStorages['event'] = $eventStorage;

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([$eventEntity]);
        $eventStorage->method('getQuery')->willReturn($query);

        $response = $this->controller->entitySearch([], ['q' => 'Pow', 'types' => 'event'], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['results']);
        $this->assertSame('event', $data['results'][0]['entity_type']);
    }

    #[Test]
    public function entity_search_ignores_disallowed_types(): void
    {
        // Request a type not in SEARCHABLE_TYPES — should be ignored
        foreach (NewsletterAdminApiController::SEARCHABLE_TYPES as $type) {
            $storage = $this->entityStorages[$type] ?? $this->createMock(EntityStorageInterface::class);
            $this->entityStorages[$type] = $storage;
        }

        $response = $this->controller->entitySearch([], ['q' => 'test', 'types' => 'user,admin'], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame([], $data['results']);
    }

    #[Test]
    public function entity_search_intersects_requested_types_with_allowed(): void
    {
        $teachingEntity = $this->createMock(ContentEntityBase::class);
        $teachingEntity->method('id')->willReturn(30);
        $teachingEntity->method('label')->willReturn('Cedar Teaching');

        $teachingStorage = $this->createMock(EntityStorageInterface::class);
        $this->entityStorages['teaching'] = $teachingStorage;

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([$teachingEntity]);
        $teachingStorage->method('getQuery')->willReturn($query);

        // Request teaching (allowed) + user (disallowed) — only teaching should be queried
        $response = $this->controller->entitySearch([], ['q' => 'Cedar', 'types' => 'teaching,user'], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['results']);
        $this->assertSame('teaching', $data['results'][0]['entity_type']);
        $this->assertSame(30, $data['results'][0]['entity_id']);
    }

    // --- previewToken ---

    #[Test]
    public function preview_token_returns_404_when_edition_not_found(): void
    {
        $this->editionStorage->method('load')->with(999)->willReturn(null);

        $response = $this->controller->previewToken(['id' => '999'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function preview_token_returns_url_with_token(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(7);
        $this->editionStorage->method('load')->with(7)->willReturn($edition);

        $this->tokenStore->expects($this->once())
            ->method('issue')
            ->with(7)
            ->willReturn('abc123def456');

        $response = $this->controller->previewToken(['id' => '7'], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('/newsletter/_internal/7/print?token=abc123def456', $data['preview_url']);
    }

    // --- generate ---

    #[Test]
    public function generate_returns_404_when_edition_not_found(): void
    {
        $this->editionStorage->method('load')->with(999)->willReturn(null);

        $response = $this->controller->generate(['id' => '999'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function generate_renders_and_returns_artifact_data(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $edition->method('set')->willReturnSelf();
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $artifact = new PdfArtifact(
            path: '/storage/newsletters/regional/1-3.pdf',
            bytes: 84200,
            sha256: 'abcdef1234567890',
        );

        $this->renderer->expects($this->once())
            ->method('render')
            ->with($edition)
            ->willReturn($artifact);

        $this->lifecycle->expects($this->once())
            ->method('markGenerated')
            ->with($edition, '/storage/newsletters/regional/1-3.pdf', 'abcdef1234567890');

        $this->editionStorage->expects($this->once())->method('save')->with($edition);

        $response = $this->controller->generate(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('/storage/newsletters/regional/1-3.pdf', $data['path']);
        $this->assertSame('abcdef1234567890', $data['sha256']);
        $this->assertSame(84200, $data['bytes']);
    }

    #[Test]
    public function generate_returns_500_on_render_exception(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $this->renderer->method('render')->willThrowException(
            RenderException::timeout(30),
        );

        $response = $this->controller->generate(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Render failed', $data['error']);
    }

    #[Test]
    public function generate_returns_409_on_domain_exception(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $this->renderer->method('render')->willThrowException(
            new \DomainException('Cannot generate from draft state'),
        );

        $response = $this->controller->generate(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('Cannot generate from draft state', $data['error']);
    }

    // --- download ---

    #[Test]
    public function download_returns_404_when_edition_not_found(): void
    {
        $this->editionStorage->method('load')->with(999)->willReturn(null);

        $response = $this->controller->download(['id' => '999'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function download_returns_404_when_pdf_path_not_set(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $edition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'pdf_path' => null,
            default => null,
        });
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $response = $this->controller->download(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('PDF not yet generated.', $data['error']);
    }

    #[Test]
    public function download_returns_404_when_pdf_file_missing(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $edition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'pdf_path' => '/nonexistent/path/to/newsletter.pdf',
            default => null,
        });
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $response = $this->controller->download(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function download_returns_pdf_content_with_correct_headers(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'newsletter_test_');
        file_put_contents($tmpFile, '%PDF-1.4 fake content');

        try {
            $edition = $this->createMock(ContentEntityBase::class);
            $edition->method('id')->willReturn(3);
            $edition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
                'pdf_path' => $tmpFile,
                default => null,
            });
            $this->editionStorage->method('load')->with(3)->willReturn($edition);

            $response = $this->controller->download(['id' => '3'], [], $this->account, new HttpRequest());

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
            $this->assertSame('%PDF-1.4 fake content', $response->getContent());
        } finally {
            @unlink($tmpFile);
        }
    }

    // --- send ---

    #[Test]
    public function send_returns_404_when_edition_not_found(): void
    {
        $this->editionStorage->method('load')->with(999)->willReturn(null);

        $response = $this->controller->send(['id' => '999'], [], $this->account, new HttpRequest());

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function send_dispatches_and_returns_recipient(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $edition->method('set')->willReturnSelf();
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($edition)
            ->willReturn('sales@ojgraphix.com');

        $this->lifecycle->expects($this->once())
            ->method('markSent')
            ->with($edition);

        $this->editionStorage->expects($this->once())->method('save')->with($edition);

        $response = $this->controller->send(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['sent']);
        $this->assertSame('sales@ojgraphix.com', $data['recipient']);
    }

    #[Test]
    public function send_returns_500_on_dispatch_exception(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $this->dispatcher->method('dispatch')->willThrowException(
            DispatchException::notConfigured(),
        );

        $response = $this->controller->send(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Send failed', $data['error']);
    }

    #[Test]
    public function send_returns_409_on_domain_exception(): void
    {
        $edition = $this->createMock(ContentEntityBase::class);
        $edition->method('id')->willReturn(3);
        $this->editionStorage->method('load')->with(3)->willReturn($edition);

        $this->dispatcher->method('dispatch')->willThrowException(
            new \DomainException('Edition must be in generated state to send'),
        );

        $response = $this->controller->send(['id' => '3'], [], $this->account, new HttpRequest());

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('Edition must be in generated state to send', $data['error']);
    }
}
