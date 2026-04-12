<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\GameAccessPolicy;
use App\Entity\GameSession;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GameAccessPolicy::class)]
final class GameAccessPolicyTest extends TestCase
{
    #[Test]
    public function it_applies_to_game_entity_types(): void
    {
        $policy = new GameAccessPolicy();

        $this->assertTrue($policy->appliesTo('game_session'));
        $this->assertTrue($policy->appliesTo('daily_challenge'));
        $this->assertFalse($policy->appliesTo('post'));
    }

    #[Test]
    public function anonymous_can_view_game_session(): void
    {
        $policy = new GameAccessPolicy();
        $session = new GameSession([
            'mode' => 'daily',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 1,
        ]);

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return false; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->access($session, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_can_create_game_session(): void
    {
        $policy = new GameAccessPolicy();

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return false; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->createAccess('game_session', '', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_daily_challenge(): void
    {
        $policy = new GameAccessPolicy();

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return false; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->createAccess('daily_challenge', '', $account);
        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function owner_can_update_own_session(): void
    {
        $policy = new GameAccessPolicy();
        $session = new GameSession([
            'mode' => 'daily',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 1,
            'user_id' => 5,
        ]);

        $account = new class implements AccountInterface {
            public function id(): int { return 5; }
            public function hasPermission(string $p): bool { return false; }
            public function getRoles(): array { return ['authenticated']; }
            public function isAuthenticated(): bool { return true; }
        };

        $result = $policy->access($session, 'update', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function admin_can_create_daily_challenge(): void
    {
        $policy = new GameAccessPolicy();

        $account = new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $p): bool { return true; }
            public function getRoles(): array { return ['admin']; }
            public function isAuthenticated(): bool { return true; }
        };

        $result = $policy->createAccess('daily_challenge', '', $account);
        $this->assertTrue($result->isAllowed());
    }
}
