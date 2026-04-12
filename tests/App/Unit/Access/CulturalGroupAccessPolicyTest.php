<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\CulturalGroupAccessPolicy;
use App\Entity\CulturalGroup;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalGroupAccessPolicy::class)]
final class CulturalGroupAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_cultural_group(): void
    {
        $policy = new CulturalGroupAccessPolicy();
        $group = new CulturalGroup(['name' => 'Anishinaabe', 'status' => 1]);

        $result = $policy->access($group, 'view', $this->createAnonymousAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_cultural_group(): void
    {
        $policy = new CulturalGroupAccessPolicy();
        $group = new CulturalGroup(['name' => 'Draft', 'status' => 0]);

        $result = $policy->access($group, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_delete_cultural_group(): void
    {
        $policy = new CulturalGroupAccessPolicy();
        $group = new CulturalGroup(['name' => 'Test']);

        $result = $policy->access($group, 'delete', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_cultural_group(): void
    {
        $policy = new CulturalGroupAccessPolicy();

        $result = $policy->createAccess('cultural_group', '', $this->createAnonymousAccount());

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
