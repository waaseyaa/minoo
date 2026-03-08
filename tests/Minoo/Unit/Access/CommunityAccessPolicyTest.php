<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\CommunityAccessPolicy;
use Minoo\Entity\Community;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommunityAccessPolicy::class)]
final class CommunityAccessPolicyTest extends TestCase
{
    #[Test]
    public function anyone_can_view_a_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $entity = new Community(['name' => 'Sagamok', 'community_type' => 'first_nation']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($entity, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('community', '', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_create_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $account = $this->createAdminAccount();

        $result = $policy->createAccess('community', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_update_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $entity = new Community(['name' => 'Sagamok', 'community_type' => 'first_nation']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($entity, 'update', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $entity = new Community(['name' => 'Sagamok', 'community_type' => 'first_nation']);
        $account = $this->createAdminAccount();

        $result = $policy->access($entity, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function it_applies_to_community_type(): void
    {
        $policy = new CommunityAccessPolicy();

        $this->assertTrue($policy->appliesTo('community'));
        $this->assertFalse($policy->appliesTo('event'));
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool { return false; }
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
