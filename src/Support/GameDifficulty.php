<?php

declare(strict_types=1);

namespace Minoo\Support;

/**
 * Shared difficulty tier logic for all games.
 */
final class GameDifficulty
{
    /** Get difficulty tier for a day of the week (0=Sun, 1=Mon, etc.). */
    public static function dailyTier(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1, 3, 5 => 'easy',    // Mon, Wed, Fri
            2, 4 => 'medium',     // Tue, Thu
            default => 'hard',    // Sat, Sun
        };
    }
}
