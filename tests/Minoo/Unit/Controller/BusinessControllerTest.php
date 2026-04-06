<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\BusinessController;
use Minoo\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(BusinessController::class)]
final class BusinessControllerTest extends TestCase
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

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'businesses.html.twig' => '{{ path }}{% for b in businesses|default([]) %}|{{ b.get("name") }}{% endfor %}{% if business is defined and business %}|{{ business.get("name") }}{% endif %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function list_returns_200_with_businesses(): void
    {
        $salon = new Group(['gid' => 1, 'name' => 'Nginaajiiw Salon & Spa', 'slug' => 'nginaajiiw-salon-spa', 'type' => 'business']);
        $shop = new Group(['gid' => 2, 'name' => 'Cedar & Stone', 'slug' => 'cedar-and-stone', 'type' => 'business']);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $salon, 2 => $shop]);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Nginaajiiw Salon &amp; Spa', $response->getContent());
        $this->assertStringContainsString('Cedar &amp; Stone', $response->getContent());
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/businesses', $response->getContent());
    }

    #[Test]
    public function show_returns_200_for_existing_business(): void
    {
        $salon = new Group(['gid' => 1, 'name' => 'Nginaajiiw Salon & Spa', 'slug' => 'nginaajiiw-salon-spa', 'type' => 'business']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($salon);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nginaajiiw-salon-spa'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Nginaajiiw Salon &amp; Spa', $response->getContent());
    }

    #[Test]
    public function show_returns_404_for_missing_business(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function show_returns_404_for_non_business_group(): void
    {
        $group = new Group(['gid' => 1, 'name' => 'Some Community Group', 'slug' => 'some-group', 'type' => 'offline']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($group);

        $controller = new BusinessController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'some-group'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
