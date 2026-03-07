<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\VolunteerDashboardController;
use Minoo\Entity\ElderSupportRequest;
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

#[CoversClass(VolunteerDashboardController::class)]
final class VolunteerDashboardControllerTest extends TestCase
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
            ->with('elder_support_request')
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'dashboard/volunteer.html.twig' => '{% for r in requests %}|{{ r.get("name") }}{% endfor %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('id')->willReturn(5);
        $this->account->method('getRoles')->willReturn(['volunteer']);

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function index_returns_200_with_assigned_requests(): void
    {
        $req1 = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $req1]);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Mary', $response->content);
    }

    #[Test]
    public function index_returns_200_when_no_assignments(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
    }
}
