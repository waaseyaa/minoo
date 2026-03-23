<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\RoleManagementController;
use Minoo\Support\Flash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(RoleManagementController::class)]
final class RoleManagementControllerTest extends TestCase
{
    private EntityTypeManager $etm;
    private Environment $twig;
    private RoleManagementController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->twig = $this->createMock(Environment::class);
        $this->controller = new RoleManagementController($this->etm, $this->twig);
    }

    private function mockAccount(int $id, array $roles = []): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('getRoles')->willReturn($roles);

        return $account;
    }

    private function mockStorage(): EntityStorageInterface
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $this->etm->method('getStorage')->with('user')->willReturn($storage);

        return $storage;
    }

    private function changeRoleRequest(string $action, string $role): HttpRequest
    {
        return HttpRequest::create('/', 'POST', ['action' => $action, 'role' => $role]);
    }

    // --- changeRole tests ---

    #[Test]
    public function changeRole_grants_volunteer_role(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();

        $targetUser = new User(['uid' => 2, 'name' => 'Alice', 'roles' => []]);
        $storage->method('load')->with(2)->willReturn($targetUser);
        $storage->expects($this->once())->method('save');

        $request = $this->changeRoleRequest('grant', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertContains('volunteer', $targetUser->getRoles());
    }

    #[Test]
    public function changeRole_revokes_volunteer_role(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();

        $targetUser = new User(['uid' => 2, 'name' => 'Bob', 'roles' => ['volunteer']]);
        $storage->method('load')->with(2)->willReturn($targetUser);
        $storage->expects($this->once())->method('save');

        $request = $this->changeRoleRequest('revoke', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertNotContains('volunteer', $targetUser->getRoles());
    }

    #[Test]
    public function changeRole_grants_elder_sets_field(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();

        $targetUser = new User(['uid' => 2, 'name' => 'Carol', 'is_elder' => 0]);
        $storage->method('load')->with(2)->willReturn($targetUser);
        $storage->expects($this->once())->method('save');

        $request = $this->changeRoleRequest('grant', 'elder');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertTrue($targetUser->isElder());
    }

    #[Test]
    public function changeRole_revokes_elder_unsets_field(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();

        $targetUser = new User(['uid' => 2, 'name' => 'Dan', 'is_elder' => 1]);
        $storage->method('load')->with(2)->willReturn($targetUser);
        $storage->expects($this->once())->method('save');

        $request = $this->changeRoleRequest('revoke', 'elder');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertFalse($targetUser->isElder());
    }

    #[Test]
    public function changeRole_rejects_self_modification(): void
    {
        $account = $this->mockAccount(5, ['elder_coordinator']);
        $storage = $this->mockStorage();
        $storage->expects($this->never())->method('save');

        $request = $this->changeRoleRequest('grant', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '5'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_coordinator_grant_by_non_admin(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();
        $storage->expects($this->never())->method('save');

        $request = $this->changeRoleRequest('grant', 'elder_coordinator');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_allows_coordinator_grant_by_admin(): void
    {
        $account = $this->mockAccount(1, ['admin']);
        $storage = $this->mockStorage();

        $targetUser = new User(['uid' => 2, 'name' => 'Eve', 'roles' => []]);
        $storage->method('load')->with(2)->willReturn($targetUser);
        $storage->expects($this->once())->method('save');

        $request = $this->changeRoleRequest('grant', 'elder_coordinator');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertContains('elder_coordinator', $targetUser->getRoles());
    }

    #[Test]
    public function changeRole_rejects_admin_modification(): void
    {
        $account = $this->mockAccount(1, ['admin']);
        $storage = $this->mockStorage();

        $targetUser = new User(['uid' => 2, 'name' => 'Admin User', 'roles' => ['admin']]);
        $storage->method('load')->with(2)->willReturn($targetUser);
        $storage->expects($this->never())->method('save');

        $request = $this->changeRoleRequest('grant', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_invalid_action(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);

        $request = $this->changeRoleRequest('destroy', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_unprivileged_user(): void
    {
        $account = $this->mockAccount(1, []);

        $request = $this->changeRoleRequest('grant', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_invalid_role(): void
    {
        $account = $this->mockAccount(1, ['admin']);

        $request = $this->changeRoleRequest('grant', 'superuser');
        $response = $this->controller->changeRole(['uid' => '2'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_handles_null_user(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();
        $storage->method('load')->with(999)->willReturn(null);
        $storage->expects($this->never())->method('save');

        $request = $this->changeRoleRequest('grant', 'volunteer');
        $response = $this->controller->changeRole(['uid' => '999'], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function coordinatorList_renders_user_list(): void
    {
        $account = $this->mockAccount(1, ['elder_coordinator']);
        $storage = $this->mockStorage();

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('execute')->willReturn([1, 2, 3]);
        $storage->method('getQuery')->willReturn($query);

        $user2 = new User(['uid' => 2, 'name' => 'Alice', 'mail' => 'alice@example.com', 'roles' => ['volunteer'], 'is_elder' => 0]);
        $user3 = new User(['uid' => 3, 'name' => 'Bob', 'mail' => 'bob@example.com', 'roles' => [], 'is_elder' => 1]);
        // uid=1 is the actor, should be excluded
        $actor = new User(['uid' => 1, 'name' => 'Coordinator', 'mail' => 'coord@example.com', 'roles' => ['elder_coordinator']]);

        $storage->method('loadMultiple')->with([1, 2, 3])->willReturn([
            1 => $actor,
            2 => $user2,
            3 => $user3,
        ]);

        $this->twig->expects($this->once())
            ->method('render')
            ->with('dashboard/coordinator-users.html.twig', $this->callback(function (array $vars) {
                return $vars['can_manage_coordinator'] === false
                    && $vars['path'] === '/dashboard/coordinator/users'
                    && count($vars['users']) === 2
                    && $vars['users'][0]['name'] === 'Alice'
                    && $vars['users'][1]['name'] === 'Bob';
            }))
            ->willReturn('<html>list</html>');

        $response = $this->controller->coordinatorList([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function adminList_renders_with_coordinator_management(): void
    {
        $account = $this->mockAccount(1, ['admin']);
        $storage = $this->mockStorage();

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('execute')->willReturn([2]);
        $storage->method('getQuery')->willReturn($query);

        $user2 = new User(['uid' => 2, 'name' => 'Dave', 'mail' => 'dave@example.com', 'roles' => [], 'is_elder' => 0]);
        $storage->method('loadMultiple')->with([2])->willReturn([2 => $user2]);

        $this->twig->expects($this->once())
            ->method('render')
            ->with('admin/users.html.twig', $this->callback(function (array $vars) {
                return $vars['can_manage_coordinator'] === true
                    && $vars['path'] === '/admin/users'
                    && count($vars['users']) === 1;
            }))
            ->willReturn('<html>admin list</html>');

        $response = $this->controller->adminList([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->statusCode);
    }
}
