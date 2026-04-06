<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\PeopleController;
use Minoo\Entity\ResourcePerson;
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

#[CoversClass(PeopleController::class)]
final class PeopleControllerTest extends TestCase
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
            ->with('resource_person')
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'people.html.twig' => '{{ path }}{% for p in people|default([]) %}|{{ p.get("name") }}{% endfor %}{% if person is defined and person %}|{{ person.get("name") }}{% endif %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function list_returns_200_with_people(): void
    {
        $mary = new ResourcePerson(['rpid' => 1, 'name' => 'Mary Trudeau', 'slug' => 'mary-trudeau']);
        $john = new ResourcePerson(['rpid' => 2, 'name' => 'John Beaucage', 'slug' => 'john-beaucage']);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $mary, 2 => $john]);

        $controller = new PeopleController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Mary Trudeau', $response->getContent());
        $this->assertStringContainsString('John Beaucage', $response->getContent());
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new PeopleController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/people', $response->getContent());
    }

    #[Test]
    public function show_returns_200_for_existing_person(): void
    {
        $mary = new ResourcePerson(['rpid' => 1, 'name' => 'Mary Trudeau', 'slug' => 'mary-trudeau']);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($mary);

        $controller = new PeopleController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'mary-trudeau'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Mary Trudeau', $response->getContent());
    }

    #[Test]
    public function show_returns_404_for_missing_person(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new PeopleController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
