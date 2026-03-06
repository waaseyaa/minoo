<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\TeachingAccessPolicy;
use Minoo\Entity\Teaching;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeachingAccessPolicy::class)]
final class TeachingAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_teaching(): void
    {
        $policy = new TeachingAccessPolicy();
        $teaching = new Teaching(['title' => 'Seven Fires', 'type' => 'culture', 'status' => 1]);

        $result = $policy->access($teaching, 'view', $this->createAnonymousAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_teaching(): void
    {
        $policy = new TeachingAccessPolicy();
        $teaching = new Teaching(['title' => 'Draft', 'type' => 'culture', 'status' => 0]);

        $result = $policy->access($teaching, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_teaching(): void
    {
        $policy = new TeachingAccessPolicy();
        $teaching = new Teaching(['title' => 'Test', 'type' => 'culture']);

        $result = $policy->access($teaching, 'update', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_teaching(): void
    {
        $policy = new TeachingAccessPolicy();

        $result = $policy->createAccess('teaching', 'culture', $this->createAnonymousAccount());

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
