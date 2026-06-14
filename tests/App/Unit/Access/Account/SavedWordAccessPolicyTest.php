<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access\Account;

use App\Access\Account\SavedWordAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

#[CoversClass(SavedWordAccessPolicy::class)]
final class SavedWordAccessPolicyTest extends TestCase
{
    private function account(int $id, bool $admin = false): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn($id > 0);
        $account->method('hasPermission')->willReturnCallback(
            static fn (string $p): bool => $admin && $p === 'administer content',
        );
        return $account;
    }

    private function savedWordOwnedBy(int $ownerId): EntityInterface
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('get')->willReturnCallback(static fn (string $f) => $f === 'user_id' ? $ownerId : null);
        return $entity;
    }

    #[Test]
    public function owner_may_act_on_their_saved_word(): void
    {
        $policy = new SavedWordAccessPolicy();
        $this->assertTrue($policy->access($this->savedWordOwnedBy(5), 'view', $this->account(5))->isAllowed());
        $this->assertTrue($policy->access($this->savedWordOwnedBy(5), 'delete', $this->account(5))->isAllowed());
    }

    #[Test]
    public function a_different_member_may_not_touch_someone_elses_saved_word(): void
    {
        $policy = new SavedWordAccessPolicy();
        $this->assertFalse($policy->access($this->savedWordOwnedBy(5), 'view', $this->account(9))->isAllowed());
        $this->assertFalse($policy->access($this->savedWordOwnedBy(5), 'delete', $this->account(9))->isAllowed());
    }

    #[Test]
    public function only_authenticated_members_may_create(): void
    {
        $policy = new SavedWordAccessPolicy();
        $this->assertTrue($policy->createAccess('saved_word', '', $this->account(5))->isAllowed());
        $this->assertFalse($policy->createAccess('saved_word', '', $this->account(0))->isAllowed());
    }
}
