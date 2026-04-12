<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\CoordinatorDashboardController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(CoordinatorDashboardController::class)]
final class CoordinatorApplicationsTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->twig = new Environment(new ArrayLoader([
            'dashboard/coordinator-applications.html.twig' => 'apps',
            'dashboard/coordinator.html.twig' => 'dash',
        ]));
        $this->account = $this->createMock(AccountInterface::class);
    }

    #[Test]
    public function applications_lists_pending_volunteers(): void
    {
        $vol1 = $this->createMock(EntityInterface::class);

        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('execute')->willReturn([1]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->with([1])->willReturn([$vol1]);

        $this->entityTypeManager->method('getStorage')
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications');
        $response = $controller->applications([], [], $this->account, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function approve_sets_status_active_and_grants_volunteer_role(): void
    {
        $volunteer = $this->createMock(ContentEntityBase::class);
        $volunteer->method('get')->willReturnMap([
            ['status', 'pending'],
            ['account_id', 42],
        ]);
        $volunteer->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $field, mixed $value) use ($volunteer) {
                match ($field) {
                    'status' => $this->assertSame('active', $value),
                    'updated_at' => $this->assertIsInt($value),
                    default => $this->fail("Unexpected set('$field')"),
                };
                return $volunteer;
            });

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([1]);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(1)->willReturn($volunteer);
        $volStorage->expects($this->once())->method('save')->with($volunteer);

        $user = new User([
            'uid' => 42,
            'name' => 'Jane',
            'mail' => 'jane@example.com',
            'roles' => [],
            'status' => 1,
        ]);

        $userStorage = $this->createMock(EntityStorageInterface::class);
        $userStorage->method('load')->with(42)->willReturn($user);
        $userStorage->expects($this->once())->method('save')->with($user);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['volunteer', $volStorage],
                ['user', $userStorage],
            ]);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/approve', 'POST');
        $response = $controller->approveApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('volunteer', $user->getRoles());
    }

    #[Test]
    public function deny_sets_status_denied(): void
    {
        $volunteer = $this->createMock(ContentEntityBase::class);
        $volunteer->method('get')->willReturnMap([
            ['status', 'pending'],
        ]);
        $volunteer->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $field, mixed $value) use ($volunteer) {
                match ($field) {
                    'status' => $this->assertSame('denied', $value),
                    'updated_at' => $this->assertIsInt($value),
                    default => $this->fail("Unexpected set('$field')"),
                };
                return $volunteer;
            });

        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([1]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('load')->with(1)->willReturn($volunteer);
        $storage->expects($this->once())->method('save');

        $this->entityTypeManager->method('getStorage')
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/deny', 'POST');
        $response = $controller->denyApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

        $this->assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function approve_already_processed_returns_redirect_without_save(): void
    {
        $volunteer = $this->createMock(ContentEntityBase::class);
        $volunteer->method('get')->willReturnMap([
            ['status', 'active'],
        ]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([1]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('load')->with(1)->willReturn($volunteer);
        $storage->expects($this->never())->method('save');

        $this->entityTypeManager->method('getStorage')
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/approve', 'POST');
        $response = $controller->approveApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

        $this->assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function approve_without_account_id_skips_role_grant(): void
    {
        $volunteer = $this->createMock(ContentEntityBase::class);
        $volunteer->method('get')->willReturnMap([
            ['status', 'pending'],
            ['account_id', null],
        ]);
        $volunteer->method('set')->willReturnSelf();

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([1]);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(1)->willReturn($volunteer);
        $volStorage->expects($this->once())->method('save');

        $userStorage = $this->createMock(EntityStorageInterface::class);
        $userStorage->expects($this->never())->method('load');
        $userStorage->expects($this->never())->method('save');

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['volunteer', $volStorage],
                ['user', $userStorage],
            ]);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/approve', 'POST');
        $response = $controller->approveApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

        $this->assertSame(302, $response->getStatusCode());
    }
}
