<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CoordinatorDashboardController;
use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
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

#[CoversClass(CoordinatorDashboardController::class)]
final class CoordinatorDashboardControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $requestStorage;
    private EntityStorageInterface $volunteerStorage;
    private EntityQueryInterface $requestQuery;
    private EntityQueryInterface $volunteerQuery;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->requestQuery = $this->createMock(EntityQueryInterface::class);
        $this->requestQuery->method('condition')->willReturnSelf();
        $this->requestQuery->method('sort')->willReturnSelf();

        $this->volunteerQuery = $this->createMock(EntityQueryInterface::class);
        $this->volunteerQuery->method('condition')->willReturnSelf();
        $this->volunteerQuery->method('sort')->willReturnSelf();

        $this->requestStorage = $this->createMock(EntityStorageInterface::class);
        $this->requestStorage->method('getQuery')->willReturn($this->requestQuery);

        $this->volunteerStorage = $this->createMock(EntityStorageInterface::class);
        $this->volunteerStorage->method('getQuery')->willReturn($this->volunteerQuery);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->requestStorage,
                'volunteer' => $this->volunteerStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $this->twig = new Environment(new ArrayLoader([
            'dashboard/coordinator.html.twig' => '{% for r in open_requests %}|{{ r.get("name") }}{% endfor %}{% for v in volunteers %}|{{ v.get("name") }}{% endfor %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('getRoles')->willReturn(['elder_coordinator']);

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function index_returns_200_with_requests_and_volunteers(): void
    {
        $req = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $vol = new Volunteer(['vid' => 1, 'name' => 'John Helper', 'status' => 'active']);

        $this->requestQuery->method('execute')->willReturn([1]);
        $this->requestStorage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $req]);

        $this->volunteerQuery->method('execute')->willReturn([1]);
        $this->volunteerStorage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $vol]);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Elder Mary', $response->content);
        $this->assertStringContainsString('John Helper', $response->content);
    }
}
