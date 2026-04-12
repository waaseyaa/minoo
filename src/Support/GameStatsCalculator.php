<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Calculates player statistics for any game type.
 */
final class GameStatsCalculator
{
    /**
     * Build stats for a game type.
     *
     * @param string $gameType Entity game_type value (e.g. 'shkoda', 'crossword')
     * @param list<string> $streakBreakers Status values that break a streak
     * @param list<string> $winStatuses Status values that count as wins
     * @return array<string, mixed>
     */
    public static function build(
        EntityTypeManager $entityTypeManager,
        AccountInterface $account,
        string $gameType,
        array $streakBreakers = ['lost'],
        array $winStatuses = ['won'],
    ): array {
        if (!$account->isAuthenticated()) {
            return ['authenticated' => false];
        }

        $storage = $entityTypeManager->getStorage('game_session');
        $allIds = $storage->getQuery()
            ->condition('user_id', $account->id())
            ->condition('game_type', $gameType)
            ->execute();

        if ($allIds === []) {
            return [
                'authenticated' => true,
                'games_played' => 0,
                'win_rate' => 0.0,
                'current_streak' => 0,
                'best_streak' => 0,
            ];
        }

        $sessions = array_values($storage->loadMultiple($allIds));

        // Sort by created_at DESC for streak calculation
        usort($sessions, fn($a, $b) => (int) $b->get('created_at') - (int) $a->get('created_at'));

        $completed = array_filter($sessions, fn($s) => $s->get('status') !== 'in_progress');
        $wins = array_filter($completed, fn($s) => in_array($s->get('status'), $winStatuses, true));
        $gamesPlayed = count($completed);
        $winRate = $gamesPlayed > 0 ? round(count($wins) / $gamesPlayed, 2) : 0.0;

        // Current streak
        $currentStreak = 0;
        foreach ($sessions as $s) {
            if (in_array($s->get('status'), $winStatuses, true)) {
                $currentStreak++;
            } elseif (in_array($s->get('status'), $streakBreakers, true)) {
                break;
            }
        }

        // Best streak
        $bestStreak = 0;
        $streak = 0;
        foreach ($sessions as $s) {
            if (in_array($s->get('status'), $winStatuses, true)) {
                $streak++;
                $bestStreak = max($bestStreak, $streak);
            } elseif (in_array($s->get('status'), $streakBreakers, true)) {
                $streak = 0;
            }
        }

        $stats = [
            'authenticated' => true,
            'games_played' => $gamesPlayed,
            'win_rate' => $winRate,
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
        ];

        // Average completion time for completed games
        if ($gamesPlayed > 0) {
            $totalTime = 0;
            foreach ($completed as $s) {
                $totalTime += (int) $s->get('updated_at') - (int) $s->get('created_at');
            }
            $stats['avg_time'] = (int) round($totalTime / $gamesPlayed);
        }

        return $stats;
    }
}
