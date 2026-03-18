<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CommunityController;
use Minoo\Entity\Community;
use Minoo\Support\NorthCloudClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(CommunityController::class)]
final class CommunityControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
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
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'communities/list.html.twig' => '{{ path }}|{{ communities_json|raw }}',
            'communities/detail.html.twig' => '{{ path }}|{{ community.get("name") }}{% if people is defined and people %}|people:{{ people|length }}{% endif %}{% if band_office is defined and band_office %}|office:{{ band_office.phone }}{% endif %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function list_returns_200_with_communities(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok Anishnawbek', 'slug' => 'sagamok-anishnawbek', 'community_type' => 'first_nation']);
        $blind = new Community(['cid' => 2, 'name' => 'Blind River', 'slug' => 'blind-river', 'community_type' => 'municipality']);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $sagamok, 2 => $blind]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->content);
        $this->assertStringContainsString('Blind River', $response->content);
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('[]', $response->content);
    }

    #[Test]
    public function show_returns_200_for_existing_community(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok Anishnawbek', 'slug' => 'sagamok-anishnawbek', 'community_type' => 'first_nation']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($sagamok);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->content);
    }

    #[Test]
    public function show_returns_404_for_missing_community(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function show_passes_people_and_band_office_to_template(): void
    {
        $sagamok = new Community([
            'cid' => 1,
            'name' => 'Sagamok Anishnawbek',
            'slug' => 'sagamok-anishnawbek',
            'community_type' => 'first_nation',
            'nc_id' => 'nc-uuid-123',
        ]);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')->with(1)->willReturn($sagamok);

        $this->twig = new Environment(new ArrayLoader([
            'communities/detail.html.twig' => '{{ path }}|{{ community.get("name") }}{% if people is defined and people %}|people:{{ people|length }}{% endif %}{% if band_office is defined and band_office %}|office:{{ band_office.phone }}{% endif %}',
        ]));

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->content);
    }

    #[Test]
    public function show_renders_without_contact_data_when_nc_unavailable(): void
    {
        $sagamok = new Community([
            'cid' => 1,
            'name' => 'Sagamok Anishnawbek',
            'slug' => 'sagamok-anishnawbek',
            'community_type' => 'first_nation',
            'nc_id' => 'nc-uuid-123',
        ]);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')->with(1)->willReturn($sagamok);

        $failingClient = new NorthCloudClient(
            baseUrl: 'https://invalid.test',
            timeout: 1,
            httpClient: static function () { throw new \RuntimeException('NorthCloud unavailable'); },
        );

        $controller = new CommunityController($this->entityTypeManager, $this->twig, $failingClient);

        $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->content);
        $this->assertStringNotContainsString('people:', $response->content);
        $this->assertStringNotContainsString('office:', $response->content);
    }

    #[Test]
    public function autocomplete_returns_json_response(): void
    {
        putenv('NORTHCLOUD_API_URL=https://northcloud.one');

        $this->request = HttpRequest::create('/?q=Sa');
        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->autocomplete([], ['q' => 'Sa'], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);

        putenv('NORTHCLOUD_API_URL');
    }

    #[Test]
    public function autocomplete_returns_empty_for_blank_query(): void
    {
        putenv('NORTHCLOUD_API_URL=https://northcloud.one');

        $this->request = HttpRequest::create('/?q=');
        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->autocomplete([], ['q' => ''], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);

        putenv('NORTHCLOUD_API_URL');
    }
}
