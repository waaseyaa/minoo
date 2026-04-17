<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\VolunteerDashboardController;
use App\Entity\ElderSupportRequest;
use App\Entity\Volunteer;
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
    private EntityStorageInterface $volunteerStorage;
    private EntityQueryInterface $volunteerQuery;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->volunteerQuery = $this->createMock(EntityQueryInterface::class);
        $this->volunteerQuery->method('condition')->willReturnSelf();
        $this->volunteerQuery->method('execute')->willReturn([]);

        $this->volunteerStorage = $this->createMock(EntityStorageInterface::class);
        $this->volunteerStorage->method('getQuery')->willReturn($this->volunteerQuery);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->storage,
                'volunteer' => $this->volunteerStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $this->twig = new Environment(new ArrayLoader([
            'pages/dashboard/volunteer.html.twig' => '{% for r in requests %}|{{ r.get("name") }}{% endfor %}',
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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Mary', $response->getContent());
    }

    #[Test]
    public function index_returns_200_when_no_assignments(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function edit_form_returns_200_with_volunteer_data(): void
    {
        $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'phone' => '555-1234', 'availability' => 'Weekends', 'max_travel_km' => 50]);

        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([10]);

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(10)->willReturn($volunteer);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->storage,
                'volunteer' => $volStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $this->twig = new Environment(new ArrayLoader([
            'pages/dashboard/volunteer-edit.html.twig' => 'edit:{{ volunteer.get("name") }}',
        ]));

        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(100);
        $account->method('getRoles')->willReturn(['volunteer']);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->editForm([], [], $account, HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('edit:John', $response->getContent());
    }

    #[Test]
    public function edit_form_returns_404_when_no_volunteer_linked(): void
    {
        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([]);

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volStorage->method('getQuery')->willReturn($volQuery);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->storage,
                'volunteer' => $volStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->editForm([], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function submit_edit_updates_volunteer_and_redirects(): void
    {
        $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'phone' => '555-1234']);

        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([10]);

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(10)->willReturn($volunteer);
        $volStorage->expects($this->once())->method('save');

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->storage,
                'volunteer' => $volStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(100);

        $request = HttpRequest::create('/dashboard/volunteer/edit', 'POST', [
            'phone' => '555-9999',
            'availability' => 'Evenings',
            'max_travel_km' => '75',
            'notes' => 'Updated notes',
        ]);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->submitEdit([], [], $account, $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('555-9999', $volunteer->get('phone'));
        $this->assertSame('Evenings', $volunteer->get('availability'));
    }

    #[Test]
    public function toggle_availability_switches_active_to_unavailable(): void
    {
        $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'status' => 'active']);

        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([10]);

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(10)->willReturn($volunteer);
        $volStorage->expects($this->once())->method('save');

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->storage,
                'volunteer' => $volStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(100);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->toggleAvailability([], [], $account, HttpRequest::create('/', 'POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('unavailable', $volunteer->get('status'));
    }

    #[Test]
    public function toggle_availability_switches_unavailable_to_active(): void
    {
        $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'status' => 'unavailable']);

        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([10]);

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(10)->willReturn($volunteer);
        $volStorage->expects($this->once())->method('save');

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->storage,
                'volunteer' => $volStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(100);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->toggleAvailability([], [], $account, HttpRequest::create('/', 'POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('active', $volunteer->get('status'));
    }
}
