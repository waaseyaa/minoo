<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\EventAccessPolicy;
use App\Entity\Event;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventAccessPolicy::class)]
final class EventAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_event(): void
    {
        $policy = new EventAccessPolicy();
        $event = new Event(['title' => 'Powwow', 'type' => 'powwow', 'starts_at' => '2026-06-21', 'status' => 1]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($event, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_event(): void
    {
        $policy = new EventAccessPolicy();
        $event = new Event(['title' => 'Draft', 'type' => 'powwow', 'starts_at' => '2026-06-21', 'status' => 0]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($event, 'view', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_event(): void
    {
        $policy = new EventAccessPolicy();
        $event = new Event(['title' => 'Test', 'type' => 'powwow', 'starts_at' => '2026-06-21']);
        $account = $this->createAdminAccount();

        $result = $policy->access($event, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_event(): void
    {
        $policy = new EventAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('event', 'powwow', $account);

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
