<?php

declare(strict_types=1);

namespace Minoo\Support;

final class ShkodaEngine
{
    private const EASY_POS = ['ni', 'na', 'nad', 'nid'];
    private const MEDIUM_POS = ['ni', 'na', 'nad', 'nid', 'vai', 'vii'];

    /** Determine difficulty tier from word length and part of speech. */
    public static function difficultyTier(string $word, string $partOfSpeech): string
    {
        $len = mb_strlen($word);

        // When POS is available, use both length + POS for tier
        if ($partOfSpeech !== '') {
            if ($len <= 5 && in_array($partOfSpeech, self::EASY_POS, true)) {
                return 'easy';
            }
            if ($len <= 8 && in_array($partOfSpeech, self::MEDIUM_POS, true)) {
                return 'medium';
            }
            return 'hard';
        }

        // Fallback: word length only (most OPD entries lack POS — see #533)
        if ($len <= 5) {
            return 'easy';
        }
        if ($len <= 8) {
            return 'medium';
        }
        return 'hard';
    }

    /** Max wrong guesses allowed for a difficulty tier. */
    public static function maxWrongGuesses(string $tier): int
    {
        return match ($tier) {
            'easy' => 7,
            'medium' => 6,
            'hard' => 5,
            default => 6,
        };
    }

    /**
     * Process a single letter guess against the target word.
     *
     * @param string $word Target word (lowercase)
     * @param string $letter Guessed letter
     * @param list<string> $previousGuesses Letters already guessed
     * @return array{correct: bool, positions: list<int>, already_guessed?: bool}
     */
    public static function processGuess(string $word, string $letter, array $previousGuesses): array
    {
        $letter = mb_strtolower($letter);
        $word = mb_strtolower($word);

        if (in_array($letter, $previousGuesses, true)) {
            return ['correct' => false, 'positions' => [], 'already_guessed' => true];
        }

        $positions = [];
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            if (mb_substr($word, $i, 1) === $letter) {
                $positions[] = $i;
            }
        }

        return [
            'correct' => $positions !== [],
            'positions' => $positions,
        ];
    }

    /** Get difficulty tier for a day of the week (0=Sun, 1=Mon, etc.). */
    public static function dailyTier(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1, 3, 5 => 'easy',    // Mon, Wed, Fri
            2, 4 => 'medium',     // Tue, Thu
            default => 'hard',    // Sat, Sun
        };
    }

    /**
     * Generate Wordle-style share text.
     *
     * @param string $word The target word
     * @param list<string> $guesses All guesses in order
     * @param string $direction Game direction
     * @param string $tier Difficulty tier (easy/medium/hard)
     * @param string $date Daily date (YYYY-MM-DD) or empty for practice
     */
    public static function generateShareText(string $word, array $guesses, string $direction, string $tier, string $date = ''): string
    {
        $word = mb_strtolower($word);
        $wordChars = [];
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $wordChars[] = mb_substr($word, $i, 1);
        }

        $emojis = '';
        foreach ($guesses as $letter) {
            $letter = mb_strtolower($letter);
            $emojis .= in_array($letter, $wordChars, true) ? "\xF0\x9F\x94\xA5" : "\xF0\x9F\xAA\xA8";
        }

        $wrongCount = 0;
        foreach ($guesses as $letter) {
            if (!in_array(mb_strtolower($letter), $wordChars, true)) {
                $wrongCount++;
            }
        }

        $totalGuesses = count($guesses);
        $maxWrong = self::maxWrongGuesses($tier);
        $outcome = $wrongCount >= $maxWrong ? 'fire went out' : 'fire still burning';

        $dirLabel = $direction === 'english_to_ojibwe' ? "English \xE2\x86\x92 Ojibwe" : "Ojibwe \xE2\x86\x92 English";
        $dateLabel = $date !== '' ? $date : 'Practice';

        $lines = [
            "\xF0\x9F\x94\xA5 Shkoda \xE2\x80\x94 Daily Challenge",
            "{$dateLabel} \xC2\xB7 {$dirLabel}",
            $emojis,
            "{$totalGuesses} guesses \xC2\xB7 {$outcome}",
            "minoo.live/games/shkoda",
        ];

        return implode("\n", $lines);
    }
}
