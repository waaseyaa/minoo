<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use App\Controller\CommunityController;
use App\Entity\Community;
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
    private NorthCloudCommunityDictionaryClientInterface $northCloudClient;
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
            'pages/communities/index.html.twig' => '{{ path }}|{{ communities_json|raw }}',
            'pages/communities/show.html.twig' => '{{ path }}|{{ community.get("name") }}{% if people is defined and people %}|people:{{ people|length }}{% endif %}{% if band_office is defined and band_office %}|office:{{ band_office.phone }}{% endif %}',
            'pages/communities/spanish-river-flood.html.twig' => '{{ path }}|flood:{{ community.get("name") }}|{{ sagamok_flood_gallery[0].src|default("") }}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
        $this->northCloudClient = $this->createMock(NorthCloudCommunityDictionaryClientInterface::class);
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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->getContent());
        $this->assertStringContainsString('Blind River', $response->getContent());
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('[]', $response->getContent());
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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->getContent());
    }

    #[Test]
    public function show_returns_404_for_missing_community(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function spanish_river_flood_returns_200_for_sagamok(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok Anishnawbek', 'slug' => 'sagamok-anishnawbek', 'community_type' => 'first_nation']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')->with(1)->willReturn($sagamok);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->spanishRiverFlood(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/communities/sagamok-anishnawbek/spanish-river-flood', $response->getContent());
        $this->assertStringContainsString('flood:Sagamok Anishnawbek', $response->getContent());
        $this->assertStringContainsString('/img/crisis/sagamok-spanish-river-flood/flood-01.jpg', $response->getContent());
    }

    #[Test]
    public function spanish_river_flood_returns_404_for_other_community_slug(): void
    {
        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->spanishRiverFlood(['slug' => 'blind-river'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function spanish_river_flood_returns_404_when_sagamok_missing(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig);
        $response = $controller->spanishRiverFlood(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
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
            'pages/communities/show.html.twig' => '{{ path }}|{{ community.get("name") }}{% if people is defined and people %}|people:{{ people|length }}{% endif %}{% if band_office is defined and band_office %}|office:{{ band_office.phone }}{% endif %}',
            'pages/communities/spanish-river-flood.html.twig' => '{{ path }}|flood:{{ community.get("name") }}|{{ sagamok_flood_gallery[0].src|default("") }}',
        ]));

        $this->northCloudClient
            ->method('getPeople')
            ->with('nc-uuid-123')
            ->willReturn([
                ['id' => 'p1', 'name' => 'Chief Example', 'role' => 'chief', 'verified' => false],
            ]);

        $this->northCloudClient
            ->method('getBandOffice')
            ->with('nc-uuid-123')
            ->willReturn([
                'phone' => '705-555-0002',
                'verified' => false,
            ]);

        $controller = new CommunityController($this->entityTypeManager, $this->twig, $this->northCloudClient);
        $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->getContent());
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

        $this->northCloudClient
            ->method('getPeople')
            ->willThrowException(new \RuntimeException('NorthCloud unavailable'));

        $controller = new CommunityController($this->entityTypeManager, $this->twig, $this->northCloudClient);

        $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sagamok Anishnawbek', $response->getContent());
        $this->assertStringNotContainsString('people:', $response->getContent());
        $this->assertStringNotContainsString('office:', $response->getContent());
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
