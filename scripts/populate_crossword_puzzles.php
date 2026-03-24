#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate crossword puzzles from dictionary entries.
 * Run: php scripts/populate_crossword_puzzles.php
 *
 * Creates:
 * - One daily puzzle: daily-{today} (7x7, tier based on day of week)
 * - Five practice puzzles: practice-001 through practice-005 (7x7, mixed tiers)
 * - Themed puzzles if enough categorizable words exist
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Minoo\Support\CrosswordEngine;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();
$puzzleStorage = $entityTypeManager->getStorage('crossword_puzzle');
$dictStorage = $entityTypeManager->getStorage('dictionary_entry');

// --- Load candidate words ---

echo "Loading dictionary entries...\n";

$ids = $dictStorage->getQuery()
    ->condition('status', 1)
    ->range(0, 200)
    ->execute();

$candidateWords = [];
$wordMeta = [];
$entries = $dictStorage->loadMultiple($ids);

foreach ($entries as $entry) {
    $word = mb_strtolower((string) $entry->get('word'));
    $def = (string) $entry->get('definition');
    $len = mb_strlen($word);

    if ($def === '' || $len < 3 || $len > 7 || str_contains($word, '-')) {
        continue;
    }

    $candidateWords[] = $word;
    $wordMeta[$word] = [
        'dictionary_entry_id' => (int) $entry->id(),
        'definition' => $def,
        'part_of_speech' => (string) $entry->get('part_of_speech'),
    ];
}

echo sprintf("Found %d usable words (3-7 chars, no hyphens, with definitions).\n", count($candidateWords));

if (count($candidateWords) < 4) {
    echo "Error: Not enough dictionary entries to generate puzzles. Need at least 4.\n";
    exit(1);
}

$created = 0;

// --- Helper: build puzzle entity from grid result ---

function buildPuzzle(
    object $puzzleStorage,
    string $puzzleId,
    array $result,
    array $wordMeta,
    string $tier,
    ?string $theme = null,
): ?object {
    $existing = $puzzleStorage->getQuery()
        ->condition('id', $puzzleId)
        ->execute();

    if ($existing !== []) {
        echo "  Skipping {$puzzleId} — already exists.\n";
        return null;
    }

    $puzzleWords = [];
    $clues = [];
    foreach ($result['placements'] as $idx => $p) {
        $meta = $wordMeta[$p['word']] ?? null;
        $puzzleWords[] = [
            'dictionary_entry_id' => $meta['dictionary_entry_id'] ?? null,
            'row' => $p['row'],
            'col' => $p['col'],
            'direction' => $p['direction'],
            'word' => $p['word'],
        ];
        $clues[(string) $idx] = [
            'auto' => $meta !== null ? cleanDefinition($meta['definition']) : $p['word'],
            'elder' => null,
            'elder_author' => null,
        ];
    }

    $values = [
        'id' => $puzzleId,
        'grid_size' => 7,
        'words' => json_encode($puzzleWords),
        'clues' => json_encode($clues),
        'difficulty_tier' => $tier,
    ];

    if ($theme !== null) {
        $values['theme'] = $theme;
    }

    $puzzle = $puzzleStorage->create($values);
    $puzzleStorage->save($puzzle);

    return $puzzle;
}

function cleanDefinition(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $raw = implode('; ', array_filter(array_map('trim', $decoded)));
    }
    $raw = str_replace(
        ['h/self', 's/he', 'h/', 's.t.', 's.o.'],
        ['himself/herself', 'she/he', 'him/her', 'something', 'someone'],
        $raw,
    );
    return $raw;
}

// --- 1. Daily puzzle ---

echo "\n--- Daily Puzzle ---\n";
$today = date('Y-m-d');
$dayOfWeek = (int) date('w');
$dailyTier = CrosswordEngine::dailyTier($dayOfWeek);
$dailyId = "daily-{$today}";

// Shuffle for variety then generate
$dailyWords = $candidateWords;
shuffle($dailyWords);

$result = CrosswordEngine::generateGrid($dailyWords, 7, 4);
if ($result !== null) {
    $puzzle = buildPuzzle($puzzleStorage, $dailyId, $result, $wordMeta, $dailyTier);
    if ($puzzle !== null) {
        $wordCount = count($result['placements']);
        echo "  Created {$dailyId} ({$dailyTier}, {$wordCount} words)\n";
        $created++;
    }
} else {
    echo "  Failed to generate daily puzzle grid.\n";
}

// --- 2. Practice puzzles ---

echo "\n--- Practice Puzzles ---\n";
$practiceTiers = ['easy', 'easy', 'medium', 'medium', 'hard'];

for ($i = 1; $i <= 5; $i++) {
    $practiceId = sprintf('practice-%03d', $i);
    $tier = $practiceTiers[$i - 1];

    $practiceWords = $candidateWords;
    shuffle($practiceWords);

    $result = CrosswordEngine::generateGrid($practiceWords, 7, 4);
    if ($result !== null) {
        $puzzle = buildPuzzle($puzzleStorage, $practiceId, $result, $wordMeta, $tier);
        if ($puzzle !== null) {
            $wordCount = count($result['placements']);
            echo "  Created {$practiceId} ({$tier}, {$wordCount} words)\n";
            $created++;
        }
    } else {
        echo "  Failed to generate {$practiceId} grid.\n";
    }
}

// --- 3. Themed puzzles (animals) ---

echo "\n--- Themed Puzzles ---\n";

$animalKeywords = ['bear', 'wolf', 'eagle', 'fish', 'deer', 'moose', 'turtle', 'rabbit', 'bird', 'fox', 'owl', 'beaver', 'otter'];
$animalWords = [];

foreach ($candidateWords as $word) {
    $meta = $wordMeta[$word] ?? null;
    if ($meta === null) {
        continue;
    }
    $defLower = mb_strtolower($meta['definition']);
    foreach ($animalKeywords as $keyword) {
        if (str_contains($defLower, $keyword)) {
            $animalWords[] = $word;
            break;
        }
    }
}

if (count($animalWords) >= 4) {
    echo sprintf("  Found %d animal-related words.\n", count($animalWords));

    // Pad with random words if needed for better grid generation
    $padded = $animalWords;
    if (count($padded) < 10) {
        $extras = array_diff($candidateWords, $animalWords);
        shuffle($extras);
        $padded = array_merge($padded, array_slice($extras, 0, 10 - count($padded)));
    }

    for ($i = 1; $i <= min(3, (int) floor(count($animalWords) / 4)); $i++) {
        $themeId = sprintf('animals-%03d', $i);
        shuffle($padded);

        $result = CrosswordEngine::generateGrid($padded, 7, 4);
        if ($result !== null) {
            $puzzle = buildPuzzle($puzzleStorage, $themeId, $result, $wordMeta, 'easy', 'animals');
            if ($puzzle !== null) {
                $wordCount = count($result['placements']);
                echo "  Created {$themeId} (animals theme, {$wordCount} words)\n";
                $created++;
            }
        } else {
            echo "  Failed to generate {$themeId} grid.\n";
        }
    }
} else {
    echo "  Not enough animal-related words for themed puzzles (found " . count($animalWords) . ", need 4).\n";
}

// --- Summary ---

echo "\n=== Summary ===\n";
echo "Created {$created} crossword puzzle(s).\n";

$allIds = $puzzleStorage->getQuery()->execute();
echo sprintf("Total puzzles in database: %d\n", count($allIds));

echo "\nDone. Puzzles available at /games/crossword.\n";
