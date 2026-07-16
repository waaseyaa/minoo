<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Games;

use App\Domain\Games\GameStatsCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

/**
 * Streak semantics live here for every game's complete() response — restored
 * coverage after the per-game stats endpoints were retired (#920 review).
 */
#[CoversClass(GameStatsCalculator::class)]
final class GameStatsCalculatorTest extends TestCase
{
    #[Test]
    public function abandoned_session_breaks_the_current_streak(): void
    {
        // Newest session abandoned — must break the current streak; the older
        // completed win must not count toward it once broken.
        $abandoned = $this->makeSession(['status' => 'abandoned', 'created_at' => '300']);
        $won = $this->makeSession(['status' => 'won', 'created_at' => '100']);

        $stats = GameStatsCalculator::build(
            $this->entityTypeManagerWith([2 => $abandoned, 1 => $won]),
            $this->authenticatedAccount(),
            'agim',
            streakBreakers: ['abandoned', 'lost'],
        );

        self::assertSame(0, $stats['current_streak']);
        self::assertSame(2, $stats['games_played']);
    }

    #[Test]
    public function consecutive_wins_accumulate_the_current_streak(): void
    {
        $newerWin = $this->makeSession(['status' => 'won', 'created_at' => '300']);
        $olderWin = $this->makeSession(['status' => 'won', 'created_at' => '100']);

        $stats = GameStatsCalculator::build(
            $this->entityTypeManagerWith([2 => $newerWin, 1 => $olderWin]),
            $this->authenticatedAccount(),
            'agim',
        );

        self::assertSame(2, $stats['current_streak']);
        self::assertSame(1.0, $stats['win_rate']);
    }

    private function entityTypeManagerWith(array $sessions): EntityTypeManager
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('setAccount')->willReturnSelf();
        $query->method('accessCheck')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn(array_keys($sessions));

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->willReturn($sessions);

        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $entityTypeManager->method('getStorage')->willReturn($storage);

        return $entityTypeManager;
    }

    private function authenticatedAccount(): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('isAuthenticated')->willReturn(true);
        $account->method('id')->willReturn(42);

        return $account;
    }

    private function makeSession(array $fields): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('get')->willReturnCallback(static fn (string $f) => $fields[$f] ?? null);

        return $mock;
    }
}
