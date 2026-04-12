<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\FeaturedItemAccessPolicy;
use App\Entity\FeaturedItem;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeaturedItemAccessPolicy::class)]
final class FeaturedItemAccessPolicyTest extends TestCase
{
    #[Test]
    public function applies_to_featured_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $this->assertTrue($policy->appliesTo('featured_item'));
    }

    #[Test]
    public function does_not_apply_to_other_types(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $this->assertFalse($policy->appliesTo('event'));
    }

    #[Test]
    public function anonymous_can_view_published_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $item = new FeaturedItem(['headline' => 'Welcome', 'status' => 1]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($item, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $item = new FeaturedItem(['headline' => 'Draft', 'status' => 0]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($item, 'view', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_update_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $item = new FeaturedItem(['headline' => 'Test', 'status' => 1]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($item, 'update', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('featured_item', 'featured_item', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_view_unpublished_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $item = new FeaturedItem(['headline' => 'Draft', 'status' => 0]);
        $account = $this->createAdminAccount();

        $result = $policy->access($item, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $item = new FeaturedItem(['headline' => 'Test', 'status' => 1]);
        $account = $this->createAdminAccount();

        $result = $policy->access($item, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function admin_can_create_item(): void
    {
        $policy = new FeaturedItemAccessPolicy();
        $account = $this->createAdminAccount();

        $result = $policy->createAccess('featured_item', 'featured_item', $account);

        $this->assertTrue($result->isAllowed());
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
