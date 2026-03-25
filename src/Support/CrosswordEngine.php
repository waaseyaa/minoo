<?php

declare(strict_types=1);

namespace Minoo\Support;

final class CrosswordEngine
{
    /**
     * Generate a crossword grid from a list of words.
     *
     * @param list<string> $words Candidate words (lowercase)
     * @param int $gridSize Grid dimension (NxN)
     * @param int $minWords Minimum words required
     * @return array{placements: list<array{word: string, row: int, col: int, direction: string}>, grid: list<list<string|null>>}|null
     */
    public static function generateGrid(array $words, int $gridSize, int $minWords): ?array
    {
        if (count($words) < $minWords) {
            return null;
        }

        usort($words, fn(string $a, string $b) => mb_strlen($b) - mb_strlen($a));

        $words = array_values(array_filter(
            $words,
            fn(string $w) => mb_strlen($w) <= $gridSize && mb_strlen($w) >= 3,
        ));

        if (count($words) < $minWords) {
            return null;
        }

        $grid = array_fill(0, $gridSize, array_fill(0, $gridSize, null));
        $placements = [];

        $firstWord = $words[0];
        $firstLen = mb_strlen($firstWord);
        $startCol = (int) floor(($gridSize - $firstLen) / 2);
        $startRow = (int) floor($gridSize / 2);

        $placements[] = [
            'word' => $firstWord,
            'row' => $startRow,
            'col' => $startCol,
            'direction' => 'across',
        ];
        self::placeWordOnGrid($grid, $firstWord, $startRow, $startCol, 'across');

        $maxAttempts = min(count($words), 20);
        for ($wi = 1; $wi < $maxAttempts; $wi++) {
            $word = $words[$wi];
            $best = self::findBestPlacement($grid, $gridSize, $word);

            if ($best !== null) {
                $placements[] = $best;
                self::placeWordOnGrid($grid, $word, $best['row'], $best['col'], $best['direction']);
            }
        }

        if (count($placements) < $minWords) {
            return null;
        }

        if (!self::areAllWordsConnected($placements)) {
            return null;
        }

        return ['placements' => $placements, 'grid' => $grid];
    }

    /**
     * Check if all placed words are connected via shared cells.
     *
     * @param list<array{word: string, row: int, col: int, direction: string}> $placements
     */
    public static function areAllWordsConnected(array $placements): bool
    {
        if (count($placements) <= 1) {
            return true;
        }

        $cellToWords = [];
        foreach ($placements as $idx => $p) {
            $len = mb_strlen($p['word']);
            for ($i = 0; $i < $len; $i++) {
                $r = $p['direction'] === 'across' ? $p['row'] : $p['row'] + $i;
                $c = $p['direction'] === 'across' ? $p['col'] + $i : $p['col'];
                $cellToWords["{$r},{$c}"][] = $idx;
            }
        }

        $visited = [0 => true];
        $queue = [0];
        while ($queue !== []) {
            $current = array_shift($queue);
            $p = $placements[$current];
            $len = mb_strlen($p['word']);
            for ($i = 0; $i < $len; $i++) {
                $r = $p['direction'] === 'across' ? $p['row'] : $p['row'] + $i;
                $c = $p['direction'] === 'across' ? $p['col'] + $i : $p['col'];
                foreach ($cellToWords["{$r},{$c}"] as $neighbor) {
                    if (!isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }
        }

        return count($visited) === count($placements);
    }

    /**
     * Score a grid's quality.
     *
     * @param list<array{word: string, row: int, col: int, direction: string}> $placements
     * @return array{passes: bool, word_count: int, fill_ratio: float, connected: bool}
     */
    public static function qualityScore(array $placements, int $gridSize, int $minWords = 4): array
    {
        $filledCells = 0;
        $seen = [];
        foreach ($placements as $p) {
            $len = mb_strlen($p['word']);
            for ($i = 0; $i < $len; $i++) {
                $r = $p['direction'] === 'across' ? $p['row'] : $p['row'] + $i;
                $c = $p['direction'] === 'across' ? $p['col'] + $i : $p['col'];
                $key = "{$r},{$c}";
                if (!isset($seen[$key])) {
                    $filledCells++;
                    $seen[$key] = true;
                }
            }
        }

        $totalCells = $gridSize * $gridSize;
        $fillRatio = $totalCells > 0 ? $filledCells / $totalCells : 0.0;
        $connected = self::areAllWordsConnected($placements);
        $wordCount = count($placements);

        return [
            'passes' => $wordCount >= $minWords && $fillRatio > 0.30 && $connected,
            'word_count' => $wordCount,
            'fill_ratio' => round($fillRatio, 3),
            'connected' => $connected,
        ];
    }

    /** @deprecated Use GameDifficulty::dailyTier() directly. */
    public static function dailyTier(int $dayOfWeek): string
    {
        return GameDifficulty::dailyTier($dayOfWeek);
    }

    /**
     * Validate a word submission against the puzzle solution.
     *
     * @param list<string> $submittedLetters Letters the player typed
     * @param string $correctWord The correct answer
     * @return array{correct: bool, correct_positions: list<int>, wrong_positions: list<int>}
     */
    public static function validateWord(array $submittedLetters, string $correctWord): array
    {
        $correctWord = self::normalizeGlottalStop(mb_strtolower($correctWord));
        $correctPositions = [];
        $wrongPositions = [];
        $len = mb_strlen($correctWord);

        for ($i = 0; $i < $len; $i++) {
            $expected = mb_substr($correctWord, $i, 1);
            $submitted = isset($submittedLetters[$i]) ? self::normalizeGlottalStop(mb_strtolower($submittedLetters[$i])) : '';
            if ($submitted === $expected) {
                $correctPositions[] = $i;
            } else {
                $wrongPositions[] = $i;
            }
        }

        return [
            'correct' => $wrongPositions === [],
            'correct_positions' => $correctPositions,
            'wrong_positions' => $wrongPositions,
        ];
    }

    /**
     * Resolve a clue — prefer Elder-authored, fall back to auto-generated.
     *
     * @param array{auto: string, elder: string|null, elder_author: string|null} $clueData
     * @return array{text: string, author: string|null}
     */
    public static function resolveClue(array $clueData): array
    {
        if (($clueData['elder'] ?? null) !== null && $clueData['elder'] !== '') {
            return ['text' => $clueData['elder'], 'author' => $clueData['elder_author'] ?? null];
        }
        return ['text' => $clueData['auto'] ?? '', 'author' => null];
    }

    /** Max hints allowed per difficulty tier. -1 = unlimited. */
    public static function maxHints(string $tier): int
    {
        return match ($tier) {
            'easy' => -1,
            'medium' => 2,
            'hard' => 0,
            default => 2,
        };
    }

    /** @param list<list<string|null>> $grid */
    private static function placeWordOnGrid(array &$grid, string $word, int $row, int $col, string $direction): void
    {
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_strtolower(mb_substr($word, $i, 1));
            $r = $direction === 'across' ? $row : $row + $i;
            $c = $direction === 'across' ? $col + $i : $col;
            $grid[$r][$c] = $char;
        }
    }

    /**
     * Find the best placement for a word on the grid.
     *
     * @param list<list<string|null>> $grid
     * @return array{word: string, row: int, col: int, direction: string}|null
     */
    private static function findBestPlacement(array $grid, int $gridSize, string $word): ?array
    {
        $wordLen = mb_strlen($word);
        $candidates = [];

        for ($i = 0; $i < $wordLen; $i++) {
            $char = mb_strtolower(mb_substr($word, $i, 1));

            for ($r = 0; $r < $gridSize; $r++) {
                for ($c = 0; $c < $gridSize; $c++) {
                    if ($grid[$r][$c] !== $char) {
                        continue;
                    }

                    $startCol = $c - $i;
                    if ($startCol >= 0 && $startCol + $wordLen <= $gridSize) {
                        if (self::canPlace($grid, $gridSize, $word, $r, $startCol, 'across')) {
                            $candidates[] = [
                                'word' => $word,
                                'row' => $r,
                                'col' => $startCol,
                                'direction' => 'across',
                                'intersections' => self::countIntersections($grid, $word, $r, $startCol, 'across'),
                            ];
                        }
                    }

                    $startRow = $r - $i;
                    if ($startRow >= 0 && $startRow + $wordLen <= $gridSize) {
                        if (self::canPlace($grid, $gridSize, $word, $startRow, $c, 'down')) {
                            $candidates[] = [
                                'word' => $word,
                                'row' => $startRow,
                                'col' => $c,
                                'direction' => 'down',
                                'intersections' => self::countIntersections($grid, $word, $startRow, $c, 'down'),
                            ];
                        }
                    }
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn($a, $b) => $b['intersections'] - $a['intersections']);
        $best = $candidates[0];
        unset($best['intersections']);

        return $best;
    }

    /** Check if a word can be placed without conflicts. */
    private static function canPlace(array $grid, int $gridSize, string $word, int $row, int $col, string $direction): bool
    {
        $len = mb_strlen($word);
        $hasIntersection = false;

        for ($i = 0; $i < $len; $i++) {
            $char = mb_strtolower(mb_substr($word, $i, 1));
            $r = $direction === 'across' ? $row : $row + $i;
            $c = $direction === 'across' ? $col + $i : $col;

            if ($grid[$r][$c] !== null) {
                if ($grid[$r][$c] === $char) {
                    $hasIntersection = true;
                } else {
                    return false;
                }
            } else {
                // Prevent parallel words without spacing
                if ($direction === 'across') {
                    if ($r > 0 && $grid[$r - 1][$c] !== null) {
                        return false;
                    }
                    if ($r < $gridSize - 1 && $grid[$r + 1][$c] !== null) {
                        return false;
                    }
                } else {
                    if ($c > 0 && $grid[$r][$c - 1] !== null) {
                        return false;
                    }
                    if ($c < $gridSize - 1 && $grid[$r][$c + 1] !== null) {
                        return false;
                    }
                }
            }
        }

        if ($direction === 'across') {
            if ($col > 0 && $grid[$row][$col - 1] !== null) {
                return false;
            }
            if ($col + $len < $gridSize && $grid[$row][$col + $len] !== null) {
                return false;
            }
        } else {
            if ($row > 0 && $grid[$row - 1][$col] !== null) {
                return false;
            }
            if ($row + $len < $gridSize && $grid[$row + $len][$col] !== null) {
                return false;
            }
        }

        return $hasIntersection;
    }

    private static function countIntersections(array $grid, string $word, int $row, int $col, string $direction): int
    {
        $len = mb_strlen($word);
        $count = 0;
        for ($i = 0; $i < $len; $i++) {
            $r = $direction === 'across' ? $row : $row + $i;
            $c = $direction === 'across' ? $col + $i : $col;
            if ($grid[$r][$c] !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Normalize glottal stop characters to a single form (ASCII apostrophe).
     * OPD dictionary uses ' (U+0027), keyboard sends ʼ (U+02BC).
     */
    private static function normalizeGlottalStop(string $input): string
    {
        return str_replace(
            ["\xCA\xBC", "\xE2\x80\x99", "\xE2\x80\x98"],
            "'",
            $input,
        );
    }
}
