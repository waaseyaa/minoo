<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\ElderSupportAccessPolicy;
use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElderSupportAccessPolicy::class)]
final class ElderSupportAccessPolicyTest extends TestCase
{
    #[Test]
    public function anyone_can_create_elder_support_request(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('elder_support_request', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anyone_can_create_volunteer(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('volunteer', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anyone_can_view_elder_support_request(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $entity = new ElderSupportRequest(['name' => 'Test', 'phone' => '555', 'type' => 'ride']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($entity, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_update_elder_support_request(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $entity = new ElderSupportRequest(['name' => 'Test', 'phone' => '555', 'type' => 'ride']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($entity, 'update', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_elder_support_request(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $entity = new ElderSupportRequest(['name' => 'Test', 'phone' => '555', 'type' => 'ride']);
        $account = $this->createAdminAccount();

        $result = $policy->access($entity, 'update', $account);

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
