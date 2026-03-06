<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\CulturalCollectionAccessPolicy;
use Minoo\Entity\CulturalCollection;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalCollectionAccessPolicy::class)]
final class CulturalCollectionAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_collection(): void
    {
        $policy = new CulturalCollectionAccessPolicy();
        $collection = new CulturalCollection(['title' => 'Fishing', 'status' => 1]);

        $result = $policy->access($collection, 'view', $this->createAnonymousAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_collection(): void
    {
        $policy = new CulturalCollectionAccessPolicy();
        $collection = new CulturalCollection(['title' => 'Draft', 'status' => 0]);

        $result = $policy->access($collection, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_delete_collection(): void
    {
        $policy = new CulturalCollectionAccessPolicy();
        $collection = new CulturalCollection(['title' => 'Test']);

        $result = $policy->access($collection, 'delete', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_collection(): void
    {
        $policy = new CulturalCollectionAccessPolicy();

        $result = $policy->createAccess('cultural_collection', '', $this->createAnonymousAccount());

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
