<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\OralHistoryAccessPolicy;
use App\Entity\OralHistory;
use App\Entity\OralHistoryCollection;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistoryAccessPolicy::class)]
final class OralHistoryAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_oral_history(): void
    {
        $policy = new OralHistoryAccessPolicy();
        $story = new OralHistory(['title' => 'Seven Fires', 'type' => 'prophecy', 'status' => 1]);

        $result = $policy->access($story, 'view', $this->createAnonymousAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_oral_history(): void
    {
        $policy = new OralHistoryAccessPolicy();
        $story = new OralHistory(['title' => 'Draft', 'type' => 'prophecy', 'status' => 0]);

        $result = $policy->access($story, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_oral_history(): void
    {
        $policy = new OralHistoryAccessPolicy();
        $story = new OralHistory(['title' => 'Test', 'type' => 'prophecy']);

        $result = $policy->access($story, 'update', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_oral_history(): void
    {
        $policy = new OralHistoryAccessPolicy();

        $result = $policy->createAccess('oral_history', 'prophecy', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function it_applies_to_all_oral_history_types(): void
    {
        $policy = new OralHistoryAccessPolicy();

        $this->assertTrue($policy->appliesTo('oral_history'));
        $this->assertTrue($policy->appliesTo('oral_history_type'));
        $this->assertTrue($policy->appliesTo('oral_history_collection'));
        $this->assertFalse($policy->appliesTo('teaching'));
    }

    #[Test]
    public function anonymous_can_view_published_collection(): void
    {
        $policy = new OralHistoryAccessPolicy();
        $collection = new OralHistoryCollection(['title' => 'Elder Stories', 'status' => 1]);

        $result = $policy->access($collection, 'view', $this->createAnonymousAccount());

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
