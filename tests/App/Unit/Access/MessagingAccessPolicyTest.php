<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\MessagingAccessPolicy;
use Waaseyaa\Messaging\ThreadParticipant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(MessagingAccessPolicy::class)]
final class MessagingAccessPolicyTest extends TestCase
{
    private function mockAccount(int $id): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('hasPermission')->willReturn(false);

        return $account;
    }

    #[Test]
    public function thread_participant_delete_allows_participant_to_remove_self(): void
    {
        $policy = new MessagingAccessPolicy();
        $entity = new ThreadParticipant([
            'thread_id' => 1,
            'user_id' => 5,
            'thread_creator_id' => 99,
        ]);

        $result = $policy->access($entity, 'delete', $this->mockAccount(5));

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function thread_participant_delete_allows_thread_creator(): void
    {
        $policy = new MessagingAccessPolicy();
        $entity = new ThreadParticipant([
            'thread_id' => 1,
            'user_id' => 5,
            'thread_creator_id' => 99,
        ]);

        $result = $policy->access($entity, 'delete', $this->mockAccount(99));

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function thread_participant_delete_neutral_for_other_members(): void
    {
        $policy = new MessagingAccessPolicy();
        $entity = new ThreadParticipant([
            'thread_id' => 1,
            'user_id' => 5,
            'thread_creator_id' => 99,
        ]);

        $result = $policy->access($entity, 'delete', $this->mockAccount(7));

        $this->assertFalse($result->isAllowed());
        $this->assertTrue($result->isNeutral());
    }
}
