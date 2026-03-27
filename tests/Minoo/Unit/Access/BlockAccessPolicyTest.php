<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\BlockAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(BlockAccessPolicy::class)]
final class BlockAccessPolicyTest extends TestCase
{
    private BlockAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new BlockAccessPolicy();
    }

    private function mockAccount(int $id, bool $admin = false): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn(true);
        $account->method('hasPermission')->willReturnCallback(
            static fn(string $perm): bool => $admin && $perm === 'administer content',
        );

        return $account;
    }

    private function mockBlock(int $blockerId): ContentEntityBase
    {
        $block = $this->createMock(ContentEntityBase::class);
        $block->method('get')->willReturnCallback(
            static fn(string $field): mixed => match ($field) {
                'blocker_id' => $blockerId,
                default => null,
            },
        );

        return $block;
    }

    #[Test]
    public function appliesTo_returns_true_for_user_block(): void
    {
        $this->assertTrue($this->policy->appliesTo('user_block'));
    }

    #[Test]
    public function appliesTo_returns_false_for_other_types(): void
    {
        $this->assertFalse($this->policy->appliesTo('post'));
    }

    #[Test]
    public function admin_is_allowed(): void
    {
        $account = $this->mockAccount(99, admin: true);
        $block = $this->mockBlock(1);

        $result = $this->policy->access($block, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function blocker_can_access_own_block(): void
    {
        $account = $this->mockAccount(1);
        $block = $this->mockBlock(1);

        $result = $this->policy->access($block, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function non_blocker_gets_neutral(): void
    {
        $account = $this->mockAccount(2);
        $block = $this->mockBlock(1);

        $result = $this->policy->access($block, 'view', $account);

        $this->assertTrue($result->isNeutral());
    }

    #[Test]
    public function createAccess_allows_authenticated_users(): void
    {
        $account = $this->mockAccount(1);

        $result = $this->policy->createAccess('user_block', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function createAccess_denies_anonymous(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(0);
        $account->method('isAuthenticated')->willReturn(false);
        $account->method('hasPermission')->willReturn(false);

        $result = $this->policy->createAccess('user_block', '', $account);

        $this->assertTrue($result->isNeutral());
    }
}
