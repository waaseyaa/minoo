<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access\ElderSupport;

use App\Access\ElderSupport\ElderSupportAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

#[CoversClass(ElderSupportAccessPolicy::class)]
final class ElderSupportAccessPolicyTest extends TestCase
{
    private function account(bool $admin, array $roles, bool $authenticated = true): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturnCallback(
            static fn (string $p): bool => $admin && $p === 'administer content',
        );
        $account->method('getRoles')->willReturn($roles);
        $account->method('isAuthenticated')->willReturn($authenticated);
        return $account;
    }

    #[Test]
    public function applies_only_to_elder_support_requests(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $this->assertTrue($policy->appliesTo('elder_support_request'));
        $this->assertFalse($policy->appliesTo('dictionary_entry'));
    }

    #[Test]
    public function any_signed_in_member_may_create_but_anonymous_may_not(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $member = $this->account(admin: false, roles: ['authenticated']);
        $anon = $this->account(admin: false, roles: [], authenticated: false);

        $this->assertTrue($policy->createAccess('elder_support_request', '', $member)->isAllowed());
        $this->assertFalse($policy->createAccess('elder_support_request', '', $anon)->isAllowed());
    }

    #[Test]
    public function only_coordinators_and_admins_may_read_requests(): void
    {
        $policy = new ElderSupportAccessPolicy();
        $entity = $this->createMock(EntityInterface::class);

        $coordinator = $this->account(admin: false, roles: ['authenticated', 'elder_coordinator']);
        $admin = $this->account(admin: true, roles: ['authenticated']);
        $member = $this->account(admin: false, roles: ['authenticated']);

        $this->assertTrue($policy->access($entity, 'view', $coordinator)->isAllowed());
        $this->assertTrue($policy->access($entity, 'view', $admin)->isAllowed());
        $this->assertFalse($policy->access($entity, 'view', $member)->isAllowed());
    }
}
