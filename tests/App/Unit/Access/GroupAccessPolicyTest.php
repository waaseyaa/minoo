<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\GroupAccessPolicy;
use App\Entity\Group;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupAccessPolicy::class)]
final class GroupAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_group(): void
    {
        $policy = new GroupAccessPolicy();
        $group = new Group(['name' => 'Test Group', 'type' => 'online', 'status' => 1]);

        $result = $policy->access($group, 'view', $this->createAnonymousAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_group(): void
    {
        $policy = new GroupAccessPolicy();
        $group = new Group(['name' => 'Draft', 'type' => 'online', 'status' => 0]);

        $result = $policy->access($group, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_group(): void
    {
        $policy = new GroupAccessPolicy();
        $group = new Group(['name' => 'Test', 'type' => 'online']);

        $result = $policy->access($group, 'update', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_group(): void
    {
        $policy = new GroupAccessPolicy();

        $result = $policy->createAccess('group', 'online', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    private function createAdminAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['administrator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
