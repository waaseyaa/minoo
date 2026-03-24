# Crossword Game Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an Ojibwe crossword puzzle game — the second game on Minoo's games hub after Shkoda.

**Architecture:** Extends the existing game infrastructure (GameSession, GameServiceProvider, GameAccessPolicy). Adds CrosswordPuzzle config entity for pre-generated grids, CrosswordEngine for grid generation + validation, CrosswordController for API endpoints, and a vanilla JS client with Twig template. Follows Shkoda's patterns exactly.

**Tech Stack:** PHP 8.4, Twig 3, vanilla JS (IIFE), vanilla CSS (layers), SQLite

**Spec:** `docs/superpowers/specs/2026-03-24-crossword-game-design.md`

---

### Task 1: Extend GameSession Entity for Multi-Game Support

**Files:**
- Modify: `src/Entity/GameSession.php`
- Modify: `tests/Minoo/Unit/Entity/GameSessionTest.php`

This task makes GameSession game-type-aware so it can serve both Shkoda and Crossword sessions. The key change: `dictionary_entry_id` and `direction` become optional for crossword sessions, and new fields (`game_type`, `puzzle_id`, `grid_state`) are added.

- [ ] **Step 1: Write failing tests for new game_type field**

Add to `tests/Minoo/Unit/Entity/GameSessionTest.php`:

```php
#[Test]
public function it_defaults_game_type_to_shkoda(): void
{
    $session = new GameSession([
        'mode' => 'daily',
        'direction' => 'english_to_ojibwe',
        'dictionary_entry_id' => 42,
    ]);
    $this->assertSame('shkoda', $session->get('game_type'));
}

#[Test]
public function it_accepts_crossword_game_type(): void
{
    $session = new GameSession([
        'game_type' => 'crossword',
        'mode' => 'daily',
    ]);
    $this->assertSame('crossword', $session->get('game_type'));
    $this->assertNull($session->get('dictionary_entry_id'));
    $this->assertNull($session->get('direction'));
}

#[Test]
public function it_validates_game_type(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new GameSession([
        'game_type' => 'invalid',
        'mode' => 'daily',
        'direction' => 'english_to_ojibwe',
        'dictionary_entry_id' => 1,
    ]);
}

#[Test]
public function crossword_accepts_themed_mode(): void
{
    $session = new GameSession([
        'game_type' => 'crossword',
        'mode' => 'themed',
        'puzzle_id' => 'animals-003',
    ]);
    $this->assertSame('themed', $session->get('mode'));
    $this->assertSame('animals-003', $session->get('puzzle_id'));
}

#[Test]
public function crossword_accepts_abandoned_status(): void
{
    $session = new GameSession([
        'game_type' => 'crossword',
        'mode' => 'daily',
        'status' => 'abandoned',
    ]);
    $this->assertSame('abandoned', $session->get('status'));
}

#[Test]
public function crossword_accepts_completed_status(): void
{
    $session = new GameSession([
        'game_type' => 'crossword',
        'mode' => 'daily',
        'status' => 'completed',
    ]);
    $this->assertSame('completed', $session->get('status'));
}

#[Test]
public function shkoda_still_requires_direction_and_entry(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new GameSession(['game_type' => 'shkoda', 'mode' => 'daily']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GameSessionTest.php -v`
Expected: 7 new tests FAIL (game_type field doesn't exist, crossword constructor validation rejects missing direction/entry)

- [ ] **Step 3: Update GameSession constructor**

In `src/Entity/GameSession.php`, replace the constructor with game-type-aware validation:

```php
private const VALID_GAME_TYPES = ['shkoda', 'crossword'];
private const VALID_MODES = ['daily', 'practice', 'streak', 'themed'];
private const VALID_DIRECTIONS = ['ojibwe_to_english', 'english_to_ojibwe'];
private const VALID_STATUSES = ['in_progress', 'won', 'lost', 'completed', 'abandoned'];
private const VALID_TIERS = ['easy', 'medium', 'hard'];

/** @param array<string, mixed> $values */
public function __construct(array $values = [])
{
    // Default game_type to shkoda for backward compatibility
    if (!array_key_exists('game_type', $values)) {
        $values['game_type'] = 'shkoda';
    }

    if (!in_array($values['game_type'], self::VALID_GAME_TYPES, true)) {
        throw new \InvalidArgumentException("Invalid game_type: {$values['game_type']}");
    }

    if (!isset($values['mode'])) {
        throw new \InvalidArgumentException('Missing required field: mode');
    }
    if (!in_array($values['mode'], self::VALID_MODES, true)) {
        throw new \InvalidArgumentException("Invalid mode: {$values['mode']}");
    }

    // Shkoda requires direction and dictionary_entry_id; crossword does not
    if ($values['game_type'] === 'shkoda') {
        foreach (['direction', 'dictionary_entry_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        if (!in_array($values['direction'], self::VALID_DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid direction: {$values['direction']}");
        }
    } else {
        // Crossword: these fields are optional
        if (!array_key_exists('direction', $values)) {
            $values['direction'] = null;
        }
        if (!array_key_exists('dictionary_entry_id', $values)) {
            $values['dictionary_entry_id'] = null;
        }
    }

    if (isset($values['status']) && !in_array($values['status'], self::VALID_STATUSES, true)) {
        throw new \InvalidArgumentException("Invalid status: {$values['status']}");
    }
    if (isset($values['difficulty_tier']) && !in_array($values['difficulty_tier'], self::VALID_TIERS, true)) {
        throw new \InvalidArgumentException("Invalid difficulty_tier: {$values['difficulty_tier']}");
    }

    if (!array_key_exists('user_id', $values)) {
        $values['user_id'] = null;
    }
    if (!array_key_exists('guesses', $values)) {
        $values['guesses'] = '[]';
    }
    if (!array_key_exists('wrong_count', $values)) {
        $values['wrong_count'] = 0;
    }
    if (!array_key_exists('status', $values)) {
        $values['status'] = 'in_progress';
    }
    if (!array_key_exists('daily_date', $values)) {
        $values['daily_date'] = null;
    }
    if (!array_key_exists('difficulty_tier', $values)) {
        $values['difficulty_tier'] = 'easy';
    }
    if (!array_key_exists('puzzle_id', $values)) {
        $values['puzzle_id'] = null;
    }
    if (!array_key_exists('grid_state', $values)) {
        $values['grid_state'] = null;
    }
    if (!array_key_exists('hints_used', $values)) {
        $values['hints_used'] = 0;
    }
    if (!array_key_exists('created_at', $values)) {
        $values['created_at'] = time();
    }
    if (!array_key_exists('updated_at', $values)) {
        $values['updated_at'] = time();
    }

    parent::__construct($values, $this->entityTypeId, $this->entityKeys);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GameSessionTest.php -v`
Expected: ALL tests PASS (both old and new)

- [ ] **Step 5: Commit**

```bash
git add src/Entity/GameSession.php tests/Minoo/Unit/Entity/GameSessionTest.php
git commit -m "feat: extend GameSession for multi-game support (shkoda + crossword)"
```

---

### Task 2: Add GameSession Field Definitions and Migration

**Files:**
- Modify: `src/Provider/GameServiceProvider.php`
- Create: `migrations/20260324_100000_add_crossword_fields_to_game_session.php`

- [ ] **Step 1: Add field definitions to GameServiceProvider**

In `src/Provider/GameServiceProvider.php`, add these fields to the `game_session` entity type's `fieldDefinitions` array, after the existing `difficulty_tier` entry (line 33) and before `created_at`:

```php
'game_type' => ['type' => 'string', 'label' => 'Game Type', 'weight' => 18, 'default' => 'shkoda'],
'puzzle_id' => ['type' => 'string', 'label' => 'Puzzle ID', 'weight' => 19],
'grid_state' => ['type' => 'text_long', 'label' => 'Grid State', 'description' => 'JSON crossword grid fill state.', 'weight' => 20],
'hints_used' => ['type' => 'integer', 'label' => 'Hints Used', 'weight' => 21, 'default' => 0],
```

- [ ] **Step 2: Create migration file**

Create `migrations/20260324_100000_add_crossword_fields_to_game_session.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add crossword game fields to game_session table.
 *
 * Note: Fields are stored in the _data JSON blob (Waaseyaa entity pattern),
 * so no ALTER TABLE is needed. This migration is a no-op placeholder for
 * version tracking — the field definitions in GameServiceProvider handle storage.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        // Fields live in _data JSON blob — no schema change needed.
        // This migration exists for version tracking only.
    }

    public function down(SchemaBuilder $schema): void
    {
        // No schema to revert.
    }
};
```

- [ ] **Step 3: Run migration and verify**

Run: `bin/waaseyaa migrate`
Then: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GameSessionTest.php -v`
Expected: Migration succeeds, all tests still pass.

- [ ] **Step 4: Commit**

```bash
git add src/Provider/GameServiceProvider.php migrations/20260324_100000_add_crossword_fields_to_game_session.php
git commit -m "feat: add crossword field definitions and migration placeholder"
```

---

### Task 3: Create CrosswordPuzzle Config Entity

**Files:**
- Create: `src/Entity/CrosswordPuzzle.php`
- Create: `tests/Minoo/Unit/Entity/CrosswordPuzzleTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Minoo/Unit/Entity/CrosswordPuzzleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\CrosswordPuzzle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrosswordPuzzle::class)]
final class CrosswordPuzzleTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $words = [
            ['dictionary_entry_id' => 1, 'row' => 0, 'col' => 0, 'direction' => 'across', 'word' => 'shkoda'],
            ['dictionary_entry_id' => 2, 'row' => 0, 'col' => 3, 'direction' => 'down', 'word' => 'nibi'],
        ];
        $clues = [
            '0' => ['auto' => 'fire', 'elder' => null, 'elder_author' => null],
            '1' => ['auto' => 'water', 'elder' => null, 'elder_author' => null],
        ];

        $puzzle = new CrosswordPuzzle([
            'id' => 'daily-2026-03-25',
            'grid_size' => 7,
            'words' => json_encode($words),
            'clues' => json_encode($clues),
            'difficulty_tier' => 'easy',
        ]);

        $this->assertSame('crossword_puzzle', $puzzle->getEntityTypeId());
        $this->assertSame('daily-2026-03-25', $puzzle->id());
        $this->assertSame(7, $puzzle->get('grid_size'));
        $this->assertNull($puzzle->get('theme'));
        $this->assertSame('easy', $puzzle->get('difficulty_tier'));
    }

    #[Test]
    public function it_requires_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['grid_size' => 7, 'words' => '[]', 'clues' => '{}']);
    }

    #[Test]
    public function it_requires_grid_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['id' => 'test-1', 'words' => '[]', 'clues' => '{}']);
    }

    #[Test]
    public function it_requires_words(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['id' => 'test-1', 'grid_size' => 7, 'clues' => '{}']);
    }

    #[Test]
    public function it_requires_clues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['id' => 'test-1', 'grid_size' => 7, 'words' => '[]']);
    }

    #[Test]
    public function it_validates_difficulty_tier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle([
            'id' => 'test-1',
            'grid_size' => 7,
            'words' => '[]',
            'clues' => '{}',
            'difficulty_tier' => 'impossible',
        ]);
    }

    #[Test]
    public function it_accepts_theme(): void
    {
        $puzzle = new CrosswordPuzzle([
            'id' => 'animals-003',
            'grid_size' => 10,
            'words' => '[]',
            'clues' => '{}',
            'theme' => 'animals',
            'difficulty_tier' => 'medium',
        ]);

        $this->assertSame('animals', $puzzle->get('theme'));
        $this->assertSame(10, $puzzle->get('grid_size'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CrosswordPuzzleTest.php -v`
Expected: FAIL — class `Minoo\Entity\CrosswordPuzzle` does not exist.

- [ ] **Step 3: Create CrosswordPuzzle entity**

Create `src/Entity/CrosswordPuzzle.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class CrosswordPuzzle extends ConfigEntityBase
{
    protected string $entityTypeId = 'crossword_puzzle';

    protected array $entityKeys = ['id' => 'id', 'label' => 'id'];

    private const VALID_TIERS = ['easy', 'medium', 'hard'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['id', 'grid_size', 'words', 'clues'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('theme', $values)) {
            $values['theme'] = null;
        }
        if (!array_key_exists('difficulty_tier', $values)) {
            $values['difficulty_tier'] = 'easy';
        }

        if (!in_array($values['difficulty_tier'], self::VALID_TIERS, true)) {
            throw new \InvalidArgumentException("Invalid difficulty_tier: {$values['difficulty_tier']}");
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CrosswordPuzzleTest.php -v`
Expected: ALL 7 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Entity/CrosswordPuzzle.php tests/Minoo/Unit/Entity/CrosswordPuzzleTest.php
git commit -m "feat: add CrosswordPuzzle config entity"
```

---

### Task 4: Register CrosswordPuzzle Entity and Migration

**Files:**
- Modify: `src/Provider/GameServiceProvider.php`
- Create: `migrations/20260324_100100_create_crossword_puzzle_table.php`

- [ ] **Step 1: Register entity type in GameServiceProvider**

Add after the `daily_challenge` entity type registration (after line 51) in `src/Provider/GameServiceProvider.php`:

```php
$this->entityType(new EntityType(
    id: 'crossword_puzzle',
    label: 'Crossword Puzzle',
    class: \Minoo\Entity\CrosswordPuzzle::class,
    keys: ['id' => 'id', 'label' => 'id'],
    group: 'games',
    fieldDefinitions: [
        'grid_size' => ['type' => 'integer', 'label' => 'Grid Size', 'weight' => 0],
        'words' => ['type' => 'text_long', 'label' => 'Words', 'description' => 'JSON array of word placements.', 'weight' => 5],
        'clues' => ['type' => 'text_long', 'label' => 'Clues', 'description' => 'JSON map of word index to clue data.', 'weight' => 10],
        'theme' => ['type' => 'string', 'label' => 'Theme', 'weight' => 15],
        'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 20, 'default' => 'easy'],
    ],
));
```

Also add the import at the top: `use Minoo\Entity\CrosswordPuzzle;`

- [ ] **Step 2: Create migration**

Create `migrations/20260324_100100_create_crossword_puzzle_table.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the crossword_puzzle table (config entity schema).
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('crossword_puzzle')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE crossword_puzzle (
                id TEXT PRIMARY KEY,
                bundle CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('crossword_puzzle')) {
            $schema->getConnection()->executeStatement('DROP TABLE crossword_puzzle');
        }
    }
};
```

- [ ] **Step 3: Update GameAccessPolicy**

In `src/Access/GameAccessPolicy.php`, add `crossword_puzzle` to the entity types:

```php
#[PolicyAttribute(entityType: ['game_session', 'daily_challenge', 'crossword_puzzle'])]
// ...
private const ENTITY_TYPES = ['game_session', 'daily_challenge', 'crossword_puzzle'];
```

- [ ] **Step 4: Delete stale manifest and run migration**

Run: `rm -f storage/framework/packages.php && bin/waaseyaa migrate`
Then: `./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: Migration succeeds, all tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Provider/GameServiceProvider.php src/Access/GameAccessPolicy.php migrations/20260324_100100_create_crossword_puzzle_table.php
git commit -m "feat: register CrosswordPuzzle entity type and create table migration"
```

---

### Task 5: Build CrosswordEngine — Grid Generation

**Files:**
- Create: `src/Support/CrosswordEngine.php`
- Create: `tests/Minoo/Unit/Support/CrosswordEngineTest.php`

The engine is the algorithmic core. It has three responsibilities: generating grids, scoring quality, and resolving clues. This task covers grid generation and quality scoring. Clue resolution comes in Task 6.

- [ ] **Step 1: Write failing tests for grid generation**

Create `tests/Minoo/Unit/Support/CrosswordEngineTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\CrosswordEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrosswordEngine::class)]
final class CrosswordEngineTest extends TestCase
{
    #[Test]
    public function generate_grid_produces_valid_placement(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig'];
        $result = CrosswordEngine::generateGrid($words, 7, 4);

        $this->assertNotNull($result, 'Should produce a grid');
        $this->assertArrayHasKey('placements', $result);
        $this->assertArrayHasKey('grid', $result);
        $this->assertGreaterThanOrEqual(4, count($result['placements']));
    }

    #[Test]
    public function generate_grid_returns_null_when_too_few_words(): void
    {
        $result = CrosswordEngine::generateGrid(['hi'], 7, 4);
        $this->assertNull($result);
    }

    #[Test]
    public function placements_have_no_letter_conflicts(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig', 'dewe'];
        $result = CrosswordEngine::generateGrid($words, 7, 4);

        if ($result === null) {
            $this->markTestSkipped('Grid generation did not produce a result for this word set');
        }

        // Verify no two words place different letters in the same cell
        $cells = [];
        foreach ($result['placements'] as $p) {
            $word = $p['word'];
            $len = mb_strlen($word);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_strtolower(mb_substr($word, $i, 1));
                $r = $p['direction'] === 'across' ? $p['row'] : $p['row'] + $i;
                $c = $p['direction'] === 'across' ? $p['col'] + $i : $p['col'];
                $key = "{$r},{$c}";
                if (isset($cells[$key])) {
                    $this->assertSame($cells[$key], $char, "Conflict at {$key}");
                }
                $cells[$key] = $char;
            }
        }
    }

    #[Test]
    public function placements_stay_within_grid_bounds(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi'];
        $result = CrosswordEngine::generateGrid($words, 7, 3);

        if ($result === null) {
            $this->markTestSkipped('Grid generation did not produce a result');
        }

        foreach ($result['placements'] as $p) {
            $len = mb_strlen($p['word']);
            if ($p['direction'] === 'across') {
                $this->assertLessThanOrEqual(7, $p['col'] + $len, "Word '{$p['word']}' overflows grid horizontally");
            } else {
                $this->assertLessThanOrEqual(7, $p['row'] + $len, "Word '{$p['word']}' overflows grid vertically");
            }
            $this->assertGreaterThanOrEqual(0, $p['row']);
            $this->assertGreaterThanOrEqual(0, $p['col']);
        }
    }

    #[Test]
    public function all_words_are_connected(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig'];
        $result = CrosswordEngine::generateGrid($words, 7, 4);

        if ($result === null) {
            $this->markTestSkipped('Grid generation did not produce a result');
        }

        $this->assertTrue(
            CrosswordEngine::areAllWordsConnected($result['placements']),
            'All placed words must share at least one intersection'
        );
    }

    #[Test]
    public function quality_score_rejects_sparse_grid(): void
    {
        // A single word on a 10x10 grid is too sparse
        $placements = [
            ['word' => 'nibi', 'row' => 0, 'col' => 0, 'direction' => 'across'],
        ];
        $score = CrosswordEngine::qualityScore($placements, 10);
        $this->assertFalse($score['passes']);
    }

    #[Test]
    public function daily_tier_matches_shkoda_pattern(): void
    {
        $this->assertSame('easy', CrosswordEngine::dailyTier(1));   // Mon
        $this->assertSame('medium', CrosswordEngine::dailyTier(2)); // Tue
        $this->assertSame('hard', CrosswordEngine::dailyTier(0));   // Sun
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/CrosswordEngineTest.php -v`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement CrosswordEngine**

Create `src/Support/CrosswordEngine.php`:

```php
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

        // Sort by length descending — longer words are easier to intersect
        usort($words, fn(string $a, string $b) => mb_strlen($b) - mb_strlen($a));

        // Filter out words that don't fit in the grid
        $words = array_values(array_filter(
            $words,
            fn(string $w) => mb_strlen($w) <= $gridSize && mb_strlen($w) >= 3,
        ));

        if (count($words) < $minWords) {
            return null;
        }

        $grid = array_fill(0, $gridSize, array_fill(0, $gridSize, null));
        $placements = [];

        // Place first word horizontally near center
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

        // Try to place remaining words
        $maxAttempts = min(count($words), 20);
        for ($wi = 1; $wi < $maxAttempts; $wi++) {
            $word = $words[$wi];
            $best = self::findBestPlacement($grid, $gridSize, $word, $placements);

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

        // Build cell-to-word-index map
        $cellToWords = [];
        foreach ($placements as $idx => $p) {
            $len = mb_strlen($p['word']);
            for ($i = 0; $i < $len; $i++) {
                $r = $p['direction'] === 'across' ? $p['row'] : $p['row'] + $i;
                $c = $p['direction'] === 'across' ? $p['col'] + $i : $p['col'];
                $cellToWords["{$r},{$c}"][] = $idx;
            }
        }

        // BFS from word 0
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

    /** Reuse Shkoda's day-of-week difficulty pattern. */
    public static function dailyTier(int $dayOfWeek): string
    {
        return ShkodaEngine::dailyTier($dayOfWeek);
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
        $correctWord = mb_strtolower($correctWord);
        $correctPositions = [];
        $wrongPositions = [];
        $len = mb_strlen($correctWord);

        for ($i = 0; $i < $len; $i++) {
            $expected = mb_substr($correctWord, $i, 1);
            $submitted = isset($submittedLetters[$i]) ? mb_strtolower($submittedLetters[$i]) : '';
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

    // --- Private helpers ---

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
     * @param list<array{word: string, row: int, col: int, direction: string}> $existing
     * @return array{word: string, row: int, col: int, direction: string}|null
     */
    private static function findBestPlacement(array $grid, int $gridSize, string $word, array $existing): ?array
    {
        $wordLen = mb_strlen($word);
        $candidates = [];

        // Try each cell in the grid for intersections
        for ($i = 0; $i < $wordLen; $i++) {
            $char = mb_strtolower(mb_substr($word, $i, 1));

            for ($r = 0; $r < $gridSize; $r++) {
                for ($c = 0; $c < $gridSize; $c++) {
                    if ($grid[$r][$c] !== $char) {
                        continue;
                    }

                    // Try placing horizontally (word char $i at position $r, $c)
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

                    // Try placing vertically
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

        // Pick candidate with most intersections
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
                    return false; // Letter conflict
                }
            } else {
                // Check adjacent cells to avoid words running parallel without spacing
                if ($direction === 'across') {
                    // Check above and below for non-intersection cells
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

        // Check cells before and after the word are empty
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
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/CrosswordEngineTest.php -v`
Expected: ALL tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/CrosswordEngine.php tests/Minoo/Unit/Support/CrosswordEngineTest.php
git commit -m "feat: add CrosswordEngine with grid generation and quality scoring"
```

---

### Task 6: Add Clue Resolution to CrosswordEngine

**Files:**
- Modify: `src/Support/CrosswordEngine.php`
- Modify: `tests/Minoo/Unit/Support/CrosswordEngineTest.php`

- [ ] **Step 1: Write failing tests for clue resolution and word validation**

Add to `tests/Minoo/Unit/Support/CrosswordEngineTest.php`:

```php
#[Test]
public function resolve_clue_prefers_elder_over_auto(): void
{
    $clueData = [
        'auto' => 'the Ojibwe word for fire',
        'elder' => 'It keeps you warm at night in the bush',
        'elder_author' => 'Elder Name',
    ];
    $resolved = CrosswordEngine::resolveClue($clueData);
    $this->assertSame('It keeps you warm at night in the bush', $resolved['text']);
    $this->assertSame('Elder Name', $resolved['author']);
}

#[Test]
public function resolve_clue_falls_back_to_auto(): void
{
    $clueData = [
        'auto' => 'the Ojibwe word for fire',
        'elder' => null,
        'elder_author' => null,
    ];
    $resolved = CrosswordEngine::resolveClue($clueData);
    $this->assertSame('the Ojibwe word for fire', $resolved['text']);
    $this->assertNull($resolved['author']);
}

#[Test]
public function validate_word_correct(): void
{
    $result = CrosswordEngine::validateWord(['s', 'h', 'k', 'o', 'd', 'a'], 'shkoda');
    $this->assertTrue($result['correct']);
    $this->assertSame([0, 1, 2, 3, 4, 5], $result['correct_positions']);
    $this->assertSame([], $result['wrong_positions']);
}

#[Test]
public function validate_word_partial(): void
{
    $result = CrosswordEngine::validateWord(['s', 'h', 'k', 'x', 'd', 'a'], 'shkoda');
    $this->assertFalse($result['correct']);
    $this->assertSame([0, 1, 2, 4, 5], $result['correct_positions']);
    $this->assertSame([3], $result['wrong_positions']);
}

#[Test]
public function validate_word_is_case_insensitive(): void
{
    $result = CrosswordEngine::validateWord(['S', 'H', 'K', 'O', 'D', 'A'], 'shkoda');
    $this->assertTrue($result['correct']);
}

#[Test]
public function max_hints_per_tier(): void
{
    $this->assertSame(-1, CrosswordEngine::maxHints('easy'));    // unlimited
    $this->assertSame(2, CrosswordEngine::maxHints('medium'));
    $this->assertSame(0, CrosswordEngine::maxHints('hard'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/CrosswordEngineTest.php -v`
Expected: New tests FAIL (methods don't exist yet).

- [ ] **Step 3: Add resolveClue and maxHints methods**

Add to `src/Support/CrosswordEngine.php`:

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/CrosswordEngineTest.php -v`
Expected: ALL tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/CrosswordEngine.php tests/Minoo/Unit/Support/CrosswordEngineTest.php
git commit -m "feat: add clue resolution and word validation to CrosswordEngine"
```

---

### Task 7: Build CrosswordController — Page and Daily Endpoint

**Files:**
- Create: `src/Controller/CrosswordController.php`
- Modify: `src/Provider/GameServiceProvider.php`

The controller follows ShkodaController's exact patterns: constructor DI with EntityTypeManager + Twig, private `json()` and `jsonBody()` helpers, SsrResponse returns.

- [ ] **Step 1: Create CrosswordController with page and daily endpoints**

Create `src/Controller/CrosswordController.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CrosswordEngine;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CrosswordController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** Render the crossword game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('crossword.html.twig', [
            'path' => '/games/crossword',
        ]);
        return new SsrResponse(content: $html);
    }

    /** GET /api/games/crossword/daily — today's puzzle. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $today = date('Y-m-d');
        $puzzleId = "daily-{$today}";

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $puzzle = $puzzleStorage->load($puzzleId);

        if ($puzzle === null) {
            // Fallback: generate on-the-fly when cron missed a run
            $puzzle = $this->generateFallbackDaily($puzzleId, $today);
            if ($puzzle === null) {
                return $this->json(['error' => 'No words available to generate puzzle'], 503);
            }
        }

        $tier = (string) $puzzle->get('difficulty_tier');
        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $cluesData = json_decode((string) $puzzle->get('clues'), true) ?: [];

        // Resolve clues (elder preferred over auto)
        $clues = [];
        foreach ($cluesData as $idx => $clueData) {
            $resolved = CrosswordEngine::resolveClue($clueData);
            $clues[$idx] = $resolved;
        }

        // Build word bank (Ojibwe word + English meaning from dictionary)
        $wordBank = $this->buildWordBank($words, $tier);

        // Create game session
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'crossword',
            'mode' => 'daily',
            'puzzle_id' => $puzzleId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $today,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        // Strip answers from placements before sending to client
        $clientPlacements = array_map(fn($w) => [
            'row' => $w['row'],
            'col' => $w['col'],
            'direction' => $w['direction'],
            'length' => mb_strlen($w['word']),
        ], $words);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'puzzle_id' => $puzzleId,
            'grid_size' => (int) $puzzle->get('grid_size'),
            'placements' => $clientPlacements,
            'clues' => $clues,
            'word_bank' => $wordBank,
            'difficulty' => $tier,
            'max_hints' => CrosswordEngine::maxHints($tier),
            'date' => $today,
        ]);
    }

    /** GET /api/games/crossword/random — random practice puzzle. */
    public function random(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $tier = $query['tier'] ?? 'easy';
        if (!in_array($tier, ['easy', 'medium', 'hard'], true)) {
            $tier = 'easy';
        }

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $ids = $puzzleStorage->getQuery()
            ->condition('difficulty_tier', $tier)
            ->execute();

        // Exclude daily puzzles and filter for non-themed practice grids
        $practiceIds = array_filter($ids, fn($id) => !str_starts_with((string) $id, 'daily-'));

        if ($practiceIds === []) {
            // Fallback: use any puzzle at this tier
            $practiceIds = $ids;
        }

        if ($practiceIds === []) {
            return $this->json(['error' => 'No puzzles available'], 503);
        }

        $puzzleId = $practiceIds[array_rand($practiceIds)];
        $puzzle = $puzzleStorage->load($puzzleId);

        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 503);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $cluesData = json_decode((string) $puzzle->get('clues'), true) ?: [];

        $clues = [];
        foreach ($cluesData as $idx => $clueData) {
            $clues[$idx] = CrosswordEngine::resolveClue($clueData);
        }

        $wordBank = $this->buildWordBank($words, $tier);

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'crossword',
            'mode' => 'practice',
            'puzzle_id' => (string) $puzzleId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $clientPlacements = array_map(fn($w) => [
            'row' => $w['row'],
            'col' => $w['col'],
            'direction' => $w['direction'],
            'length' => mb_strlen($w['word']),
        ], $words);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'puzzle_id' => (string) $puzzleId,
            'grid_size' => (int) $puzzle->get('grid_size'),
            'placements' => $clientPlacements,
            'clues' => $clues,
            'word_bank' => $wordBank,
            'difficulty' => $tier,
            'max_hints' => CrosswordEngine::maxHints($tier),
        ]);
    }

    /** GET /api/games/crossword/themes — list theme packs with progress. */
    public function themes(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $allIds = $puzzleStorage->getQuery()->execute();

        // Group by theme field (not by parsing IDs)
        $themeCounts = [];
        $puzzles = $puzzleStorage->loadMultiple($allIds);
        foreach ($puzzles as $puzzle) {
            $theme = (string) $puzzle->get('theme');
            if ($theme === '') {
                continue;
            }
            $themeCounts[$theme] = ($themeCounts[$theme] ?? 0) + 1;
        }

        // Load user's completed crossword sessions once (not per-theme)
        $completedByTheme = [];
        if ($account->isAuthenticated()) {
            $sessionIds = $this->entityTypeManager->getStorage('game_session')->getQuery()
                ->condition('game_type', 'crossword')
                ->condition('user_id', $account->id())
                ->condition('status', 'completed')
                ->execute();
            $sessions = $this->entityTypeManager->getStorage('game_session')->loadMultiple($sessionIds);
            foreach ($sessions as $s) {
                $pid = (string) $s->get('puzzle_id');
                // Match puzzle_id to theme by loading the puzzle's theme
                foreach ($puzzles as $p) {
                    if ((string) $p->id() === $pid) {
                        $t = (string) $p->get('theme');
                        if ($t !== '') {
                            $completedByTheme[$t] = ($completedByTheme[$t] ?? 0) + 1;
                        }
                        break;
                    }
                }
            }
        }

        $themes = [];
        foreach ($themeCounts as $slug => $total) {
            $entry = ['slug' => $slug, 'name' => ucfirst($slug), 'total' => $total];
            if ($account->isAuthenticated()) {
                $entry['completed'] = $completedByTheme[$slug] ?? 0;
            }
            $themes[] = $entry;
        }

        return $this->json(['themes' => $themes]);
    }

    /** GET /api/games/crossword/theme/{slug} — next unsolved puzzle in theme. */
    public function theme(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        if ($slug === '') {
            return $this->json(['error' => 'Missing theme slug'], 400);
        }

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $allIds = $puzzleStorage->getQuery()
            ->condition('theme', $slug)
            ->execute();

        if ($allIds === []) {
            return $this->json(['error' => 'Theme not found'], 404);
        }

        // Determine completed puzzles
        $completedPuzzleIds = [];
        if ($account->isAuthenticated()) {
            $sessionIds = $this->entityTypeManager->getStorage('game_session')->getQuery()
                ->condition('game_type', 'crossword')
                ->condition('user_id', $account->id())
                ->condition('status', 'completed')
                ->execute();
            $sessions = $this->entityTypeManager->getStorage('game_session')->loadMultiple($sessionIds);
            foreach ($sessions as $s) {
                $completedPuzzleIds[] = (string) $s->get('puzzle_id');
            }
        } else {
            // Anonymous: client sends completed IDs as query param
            $completed = $query['completed'] ?? '';
            if ($completed !== '') {
                $completedPuzzleIds = explode(',', $completed);
            }
        }

        // Find first unsolved
        sort($allIds);
        $nextId = null;
        foreach ($allIds as $id) {
            if (!in_array((string) $id, $completedPuzzleIds, true)) {
                $nextId = $id;
                break;
            }
        }

        if ($nextId === null) {
            return $this->json(['error' => 'All puzzles in this theme completed', 'theme_complete' => true], 200);
        }

        $puzzle = $puzzleStorage->load($nextId);
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 503);
        }

        $tier = (string) $puzzle->get('difficulty_tier');
        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $cluesData = json_decode((string) $puzzle->get('clues'), true) ?: [];

        $clues = [];
        foreach ($cluesData as $idx => $clueData) {
            $clues[$idx] = CrosswordEngine::resolveClue($clueData);
        }

        $wordBank = $this->buildWordBank($words, $tier);

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'crossword',
            'mode' => 'themed',
            'puzzle_id' => (string) $nextId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $clientPlacements = array_map(fn($w) => [
            'row' => $w['row'],
            'col' => $w['col'],
            'direction' => $w['direction'],
            'length' => mb_strlen($w['word']),
        ], $words);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'puzzle_id' => (string) $nextId,
            'grid_size' => (int) $puzzle->get('grid_size'),
            'placements' => $clientPlacements,
            'clues' => $clues,
            'word_bank' => $wordBank,
            'difficulty' => $tier,
            'max_hints' => CrosswordEngine::maxHints($tier),
            'theme' => $slug,
        ]);
    }

    /** POST /api/games/crossword/check — validate a word. */
    public function check(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $wordIndex = $data['word_index'] ?? null;
        $letters = $data['letters'] ?? [];

        if ($token === '' || $wordIndex === null || !is_array($letters)) {
            return $this->json(['error' => 'Missing session_token, word_index, or letters'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        $puzzleId = (string) $session->get('puzzle_id');
        $puzzle = $this->entityTypeManager->getStorage('crossword_puzzle')->load($puzzleId);
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 500);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $wordIndex = (int) $wordIndex;
        if (!isset($words[$wordIndex])) {
            return $this->json(['error' => 'Invalid word index'], 400);
        }

        $correctWord = $words[$wordIndex]['word'];
        $result = CrosswordEngine::validateWord($letters, $correctWord);

        // Update grid state
        $gridState = json_decode((string) ($session->get('grid_state') ?? '{}'), true) ?: [];
        if ($result['correct']) {
            $gridState["word_{$wordIndex}"] = 'completed';
        }
        $session->set('grid_state', json_encode($gridState));
        $session->set('updated_at', time());

        // Check if all words completed
        $allComplete = true;
        foreach (array_keys($words) as $idx) {
            if (($gridState["word_{$idx}"] ?? '') !== 'completed') {
                $allComplete = false;
                break;
            }
        }

        if ($allComplete) {
            $session->set('status', 'completed');
        }

        $this->entityTypeManager->getStorage('game_session')->save($session);

        $response = [
            'correct' => $result['correct'],
            'word_index' => $wordIndex,
            'correct_positions' => $result['correct_positions'],
            'wrong_positions' => $result['wrong_positions'],
            'puzzle_complete' => $allComplete,
        ];

        // Include teaching data for correct words
        if ($result['correct'] && isset($words[$wordIndex]['dictionary_entry_id'])) {
            $entry = $this->entityTypeManager->getStorage('dictionary_entry')
                ->load((int) $words[$wordIndex]['dictionary_entry_id']);
            if ($entry !== null) {
                $response['teaching'] = [
                    'word' => (string) $entry->get('word'),
                    'meaning' => $this->cleanDefinition((string) $entry->get('definition')),
                    'pos' => (string) $entry->get('part_of_speech'),
                ];
            }
        }

        return $this->json($response);
    }

    /** POST /api/games/crossword/complete — submit finished puzzle. */
    public function complete(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';

        if ($token === '') {
            return $this->json(['error' => 'Missing session_token'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($session->get('status') !== 'completed') {
            return $this->json(['error' => 'Puzzle not yet completed'], 400);
        }

        $puzzleId = (string) $session->get('puzzle_id');
        $puzzle = $this->entityTypeManager->getStorage('crossword_puzzle')->load($puzzleId);
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 500);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $cluesData = json_decode((string) $puzzle->get('clues'), true) ?: [];

        // Build teaching data for each word
        $wordTeachings = [];
        foreach ($words as $idx => $w) {
            $teaching = ['word' => $w['word']];

            if (isset($w['dictionary_entry_id'])) {
                $entry = $this->entityTypeManager->getStorage('dictionary_entry')
                    ->load((int) $w['dictionary_entry_id']);
                if ($entry !== null) {
                    $teaching['meaning'] = $this->cleanDefinition((string) $entry->get('definition'));
                    $teaching['pos'] = (string) $entry->get('part_of_speech');

                    // Load example sentence
                    $exampleIds = $this->entityTypeManager->getStorage('example_sentence')->getQuery()
                        ->condition('dictionary_entry_id', $entry->id())
                        ->condition('status', 1)
                        ->range(0, 1)
                        ->execute();
                    $example = $exampleIds !== []
                        ? $this->entityTypeManager->getStorage('example_sentence')->load(reset($exampleIds))
                        : null;
                    if ($example !== null) {
                        $teaching['example_ojibwe'] = (string) $example->get('ojibwe_text');
                        $teaching['example_english'] = (string) $example->get('english_text');
                    }
                }
            }

            // Include elder clue attribution
            $clueData = $cluesData[(string) $idx] ?? null;
            if ($clueData !== null && ($clueData['elder'] ?? null) !== null) {
                $teaching['elder_clue'] = $clueData['elder'];
                $teaching['elder_author'] = $clueData['elder_author'] ?? null;
            }

            $wordTeachings[] = $teaching;
        }

        $stats = $this->buildStats($account);

        // Compute time from session creation to now
        $timeSeconds = time() - (int) $session->get('created_at');

        return $this->json([
            'completed' => true,
            'time_seconds' => $timeSeconds,
            'hints_used' => (int) $session->get('hints_used'),
            'words' => $wordTeachings,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/crossword/stats — player stats (auth required). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->json($this->buildStats($account));
    }

    /** POST /api/games/crossword/hint — reveal a letter (tracked server-side). */
    public function hint(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $wordIndex = $data['word_index'] ?? null;
        $position = $data['position'] ?? null;

        if ($token === '' || $wordIndex === null || $position === null) {
            return $this->json(['error' => 'Missing required fields'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        $tier = (string) $session->get('difficulty_tier');
        $maxHints = CrosswordEngine::maxHints($tier);
        $hintsUsed = (int) $session->get('hints_used');

        if ($maxHints !== -1 && $hintsUsed >= $maxHints) {
            return $this->json(['error' => 'No hints remaining'], 400);
        }

        $puzzle = $this->entityTypeManager->getStorage('crossword_puzzle')
            ->load((string) $session->get('puzzle_id'));
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 500);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $wordIndex = (int) $wordIndex;
        if (!isset($words[$wordIndex])) {
            return $this->json(['error' => 'Invalid word index'], 400);
        }

        $word = $words[$wordIndex]['word'];
        $position = (int) $position;
        $letter = mb_substr(mb_strtolower($word), $position, 1);

        $session->set('hints_used', $hintsUsed + 1);
        $session->set('updated_at', time());
        $this->entityTypeManager->getStorage('game_session')->save($session);

        return $this->json([
            'letter' => $letter,
            'position' => $position,
            'word_index' => $wordIndex,
            'hints_remaining' => $maxHints === -1 ? -1 : $maxHints - $hintsUsed - 1,
        ]);
    }

    /** POST /api/games/crossword/abandon — give up on current puzzle. */
    public function abandon(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';

        if ($token === '') {
            return $this->json(['error' => 'Missing session_token'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Game already finished'], 400);
        }

        $session->set('status', 'abandoned');
        $session->set('updated_at', time());
        $this->entityTypeManager->getStorage('game_session')->save($session);

        return $this->json(['abandoned' => true]);
    }

    // --- Private helpers ---

    /**
     * Generate a fallback daily puzzle when cron missed a run.
     * Uses dictionary entries to build a quick grid on the fly.
     */
    private function generateFallbackDaily(string $puzzleId, string $today): ?object
    {
        $dayOfWeek = (int) date('w', strtotime($today));
        $tier = CrosswordEngine::dailyTier($dayOfWeek);

        // Load dictionary words with definitions
        $dictStorage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $dictStorage->getQuery()
            ->condition('status', 1)
            ->range(0, 200)
            ->execute();

        $words = [];
        $wordMeta = [];
        $entries = $dictStorage->loadMultiple($ids);
        foreach ($entries as $entry) {
            $word = mb_strtolower((string) $entry->get('word'));
            $def = (string) $entry->get('definition');
            $len = mb_strlen($word);
            if ($def === '' || $len < 3 || $len > 7 || str_contains($word, '-')) {
                continue;
            }
            $words[] = $word;
            $wordMeta[$word] = [
                'dictionary_entry_id' => (int) $entry->id(),
                'definition' => $def,
            ];
        }

        $result = CrosswordEngine::generateGrid($words, 7, 4);
        if ($result === null) {
            return null;
        }

        // Build puzzle data
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
                'auto' => $meta !== null ? $this->cleanDefinition($meta['definition']) : $p['word'],
                'elder' => null,
                'elder_author' => null,
            ];
        }

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $puzzle = $puzzleStorage->create([
            'id' => $puzzleId,
            'grid_size' => 7,
            'words' => json_encode($puzzleWords),
            'clues' => json_encode($clues),
            'difficulty_tier' => $tier,
        ]);
        $puzzleStorage->save($puzzle);

        return $puzzle;
    }

    /**
     * Build word bank based on difficulty tier.
     *
     * @param list<array{dictionary_entry_id?: int, word: string}> $words
     * @return list<array{word: string, meaning?: string}>|null
     */
    private function buildWordBank(array $words, string $tier): ?array
    {
        if ($tier === 'hard') {
            return null; // No word bank on hard
        }

        $bank = [];
        foreach ($words as $w) {
            $entry = ['word' => $w['word']];
            if ($tier === 'easy' && isset($w['dictionary_entry_id'])) {
                $dictEntry = $this->entityTypeManager->getStorage('dictionary_entry')
                    ->load((int) $w['dictionary_entry_id']);
                if ($dictEntry !== null) {
                    $entry['meaning'] = $this->cleanDefinition((string) $dictEntry->get('definition'));
                }
            }
            $bank[] = $entry;
        }

        // Shuffle so word bank order doesn't match clue order
        shuffle($bank);
        return $bank;
    }

    private function loadSessionByToken(string $uuid): ?object
    {
        $storage = $this->entityTypeManager->getStorage('game_session');
        $ids = $storage->getQuery()
            ->condition('uuid', $uuid)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        return $storage->load(reset($ids));
    }

    /** @return array<string, mixed> */
    private function buildStats(AccountInterface $account): array
    {
        if (!$account->isAuthenticated()) {
            return ['authenticated' => false];
        }

        $storage = $this->entityTypeManager->getStorage('game_session');
        $allIds = $storage->getQuery()
            ->condition('user_id', $account->id())
            ->condition('game_type', 'crossword')
            ->execute();

        if ($allIds === []) {
            return [
                'authenticated' => true,
                'puzzles_completed' => 0,
                'current_streak' => 0,
                'best_streak' => 0,
            ];
        }

        $sessions = array_values($storage->loadMultiple($allIds));
        usort($sessions, fn($a, $b) => (int) $b->get('created_at') - (int) $a->get('created_at'));

        $completed = array_filter($sessions, fn($s) => $s->get('status') === 'completed');

        // Streak = consecutive daily completions
        $currentStreak = 0;
        foreach ($sessions as $s) {
            if ($s->get('status') === 'completed') {
                $currentStreak++;
            } else {
                break;
            }
        }

        $bestStreak = 0;
        $streak = 0;
        foreach ($sessions as $s) {
            if ($s->get('status') === 'completed') {
                $streak++;
                $bestStreak = max($bestStreak, $streak);
            } elseif ($s->get('status') === 'abandoned') {
                $streak = 0;
            }
        }

        // Average completion time
        $totalTime = 0;
        foreach ($completed as $s) {
            $totalTime += (int) $s->get('updated_at') - (int) $s->get('created_at');
        }
        $avgTime = count($completed) > 0 ? (int) round($totalTime / count($completed)) : 0;

        return [
            'authenticated' => true,
            'puzzles_completed' => count($completed),
            'avg_time' => $avgTime,
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
        ];
    }

    private function cleanDefinition(string $raw): string
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

    /** @return array<string, mixed> */
    private function jsonBody(HttpRequest $request): array
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return [];
        }
        try {
            return (array) json_decode((string) $content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
```

- [ ] **Step 2: Register routes in GameServiceProvider**

Add to the `routes()` method in `src/Provider/GameServiceProvider.php`, after the existing Shkoda routes:

```php
// --- Crossword routes ---

$router->addRoute(
    'games.crossword',
    RouteBuilder::create('/games/crossword')
        ->controller('Minoo\\Controller\\CrosswordController::page')
        ->allowAll()
        ->render()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.daily',
    RouteBuilder::create('/api/games/crossword/daily')
        ->controller('Minoo\\Controller\\CrosswordController::daily')
        ->allowAll()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.random',
    RouteBuilder::create('/api/games/crossword/random')
        ->controller('Minoo\\Controller\\CrosswordController::random')
        ->allowAll()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.themes',
    RouteBuilder::create('/api/games/crossword/themes')
        ->controller('Minoo\\Controller\\CrosswordController::themes')
        ->allowAll()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.theme',
    RouteBuilder::create('/api/games/crossword/theme/{slug}')
        ->controller('Minoo\\Controller\\CrosswordController::theme')
        ->allowAll()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.check',
    RouteBuilder::create('/api/games/crossword/check')
        ->controller('Minoo\\Controller\\CrosswordController::check')
        ->allowAll()
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.complete',
    RouteBuilder::create('/api/games/crossword/complete')
        ->controller('Minoo\\Controller\\CrosswordController::complete')
        ->allowAll()
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.hint',
    RouteBuilder::create('/api/games/crossword/hint')
        ->controller('Minoo\\Controller\\CrosswordController::hint')
        ->allowAll()
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.abandon',
    RouteBuilder::create('/api/games/crossword/abandon')
        ->controller('Minoo\\Controller\\CrosswordController::abandon')
        ->allowAll()
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'api.games.crossword.stats',
    RouteBuilder::create('/api/games/crossword/stats')
        ->controller('Minoo\\Controller\\CrosswordController::stats')
        ->requireAuthentication()
        ->methods('GET')
        ->build(),
);
```

- [ ] **Step 3: Delete stale manifest and run tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: ALL tests PASS.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/CrosswordController.php src/Provider/GameServiceProvider.php
git commit -m "feat: add CrosswordController with all API endpoints and routes"
```

---

### Task 8: Fix Shkoda Stats to Filter by Game Type

**Files:**
- Modify: `src/Controller/ShkodaController.php`

Per the spec: existing Shkoda stats queries must filter by `game_type` to avoid cross-contamination once crossword sessions exist.

- [ ] **Step 1: Add game_type filter to ShkodaController::buildStats()**

In `src/Controller/ShkodaController.php`, update the `buildStats()` method. Change the query at line 447-449 from:

```php
$allIds = $storage->getQuery()
    ->condition('user_id', $account->id())
    ->execute();
```

to:

```php
$allIds = $storage->getQuery()
    ->condition('user_id', $account->id())
    ->condition('game_type', 'shkoda')
    ->execute();
```

- [ ] **Step 2: Run tests to verify no regression**

Run: `./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: ALL tests PASS.

- [ ] **Step 3: Commit**

```bash
git add src/Controller/ShkodaController.php
git commit -m "fix: filter Shkoda stats by game_type to avoid crossword cross-contamination"
```

---

### Task 9: Create Crossword Template

**Files:**
- Create: `templates/crossword.html.twig`

Follow the exact same pattern as `shkoda.html.twig` — extends `base.html.twig`, defines title and content blocks, loads the JS file.

- [ ] **Step 1: Create the template**

Create `templates/crossword.html.twig`. This template defines the game shell — the JS will populate it dynamically. Key sections: breadcrumb, mode tabs, three-column layout (clues | grid | word bank), active clue bar, keyboard, reveal/completion screen.

The template structure should match the page layout from the design spec (Section 2). Use CSS class prefix `.crossword__` (BEM, matching Shkoda's `.shkoda__` pattern). Include `public/js/crossword.js` at the bottom.

```twig
{% extends "base.html.twig" %}

{% block title %}Crossword — Minoo Games{% endblock %}

{% block content %}
<div class="crossword" data-game="crossword">
    {# Breadcrumb #}
    <nav class="crossword__breadcrumb" aria-label="Breadcrumb">
        <a href="/games">Games</a> › <span>Crossword</span>
    </nav>

    {# Mode tabs #}
    <div class="crossword__tabs" role="tablist">
        <button class="crossword__tab crossword__tab--active" role="tab" data-mode="daily" aria-selected="true">Daily</button>
        <button class="crossword__tab" role="tab" data-mode="practice" aria-selected="false">Practice</button>
        <button class="crossword__tab" role="tab" data-mode="themes" aria-selected="false">Themes</button>
    </div>

    {# Difficulty selector (practice mode) #}
    <div class="crossword__difficulty" hidden>
        <button class="crossword__tier crossword__tier--active" data-tier="easy">Easy</button>
        <button class="crossword__tier" data-tier="medium">Medium</button>
        <button class="crossword__tier" data-tier="hard">Hard</button>
    </div>

    {# Theme selector (themes mode) #}
    <div class="crossword__themes" hidden>
        <div class="crossword__themes-list"></div>
    </div>

    {# Game area #}
    <div class="crossword__game" hidden>
        <div class="crossword__layout">
            {# Left: Clues #}
            <div class="crossword__clues">
                <div class="crossword__clues-across">
                    <h4 class="crossword__clues-heading">Across</h4>
                    <div class="crossword__clues-list" data-direction="across"></div>
                </div>
                <div class="crossword__clues-down">
                    <h4 class="crossword__clues-heading">Down</h4>
                    <div class="crossword__clues-list" data-direction="down"></div>
                </div>
            </div>

            {# Center: Grid #}
            <div class="crossword__grid-area">
                <div class="crossword__grid" role="grid" aria-label="Crossword grid"></div>
                <div class="crossword__active-clue"></div>
            </div>

            {# Right: Word bank #}
            <div class="crossword__word-bank">
                <h4 class="crossword__word-bank-heading">Word Bank</h4>
                <div class="crossword__word-bank-list"></div>
                <div class="crossword__difficulty-badge"></div>
            </div>
        </div>

        {# Keyboard #}
        <div class="crossword__keyboard">
            <div class="crossword__keyboard-row" data-row="1"></div>
            <div class="crossword__keyboard-row" data-row="2"></div>
            <div class="crossword__keyboard-row" data-row="3"></div>
        </div>
    </div>

    {# Completion screen #}
    <div class="crossword__complete" hidden>
        <h2 class="crossword__complete-title"></h2>
        <div class="crossword__complete-stats"></div>
        <div class="crossword__complete-teachings"></div>
        <div class="crossword__complete-actions">
            <button class="crossword__btn crossword__btn--primary" data-action="next">Next Puzzle</button>
            <button class="crossword__btn" data-action="share">Share</button>
        </div>
    </div>

    {# Loading #}
    <div class="crossword__loading">
        <p>Loading puzzle...</p>
    </div>
</div>

<script src="/js/crossword.js"></script>
{% endblock %}
```

- [ ] **Step 2: Verify template renders**

Run: `php -S localhost:8081 -t public` then visit `http://localhost:8081/games/crossword`
Expected: Page renders with the game shell (empty grid area, tabs visible).

- [ ] **Step 3: Commit**

```bash
git add templates/crossword.html.twig
git commit -m "feat: add crossword game template"
```

---

### Task 10: Add Crossword CSS

**Files:**
- Modify: `public/css/minoo.css`

Add crossword component styles in the `@layer components` section, following the existing Shkoda pattern. Use `.crossword__` BEM prefix.

- [ ] **Step 1: Add CSS custom properties for crossword**

Add crossword tokens alongside the existing Shkoda tokens in the `:root` / tokens layer:

```css
/* Crossword game tokens */
--crossword-cell-size: 2.5rem;
--crossword-cell-size-mobile: 2rem;
--crossword-active: oklch(0.75 0.15 55);
--crossword-correct: var(--color-language);
--crossword-wrong: var(--color-events);
--crossword-cell-bg: oklch(0.18 0.01 260);
--crossword-cell-border: oklch(0.35 0.02 260);
--crossword-black: oklch(0.08 0 0);
```

- [ ] **Step 2: Add crossword component styles**

Add in `@layer components` section of `minoo.css`. **Reference the existing Shkoda styles in `minoo.css`** (search for `.shkoda`) as the pattern — same token usage, same nesting conventions, same responsive approach.

Key components to implement (all use `.crossword__` BEM prefix):

```css
/* Container and layout */
.crossword { /* max-width, padding, margin-auto */ }
.crossword__layout {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) auto minmax(160px, 1fr);
  gap: 1.5rem;
  align-items: start;
}

/* Grid — CSS Grid where grid-template-columns uses var(--crossword-cell-size) */
.crossword__grid { display: inline-grid; gap: 2px; }
.crossword__cell { /* cell-size square, border, bg, flex center */ }
.crossword__cell--black { background: var(--crossword-black); }
.crossword__cell--active { border-color: var(--crossword-active); box-shadow glow; }
.crossword__cell--correct { color: var(--crossword-correct); }
.crossword__cell--cursor { /* blinking cursor animation */ }
.crossword__cell-number { /* absolute top-left, small font */ }

/* Clues panel */
.crossword__clues-heading { /* uppercase, letter-spacing, colored per direction */ }
.crossword__clue { /* padding, hover bg, active highlight */ }
.crossword__clue--active { /* border-left accent, brighter bg */ }

/* Word bank */
.crossword__word-bank-item { /* card with ojibwe word + meaning */ }
.crossword__word-bank-item--used { opacity: 0.4; text-decoration: line-through; }

/* Keyboard — reuse Shkoda keyboard sizing and layout */
.crossword__keyboard { /* border-top, padding, center */ }
.crossword__button { /* same dimensions as .shkoda__button */ }

/* Active clue bar below grid */
.crossword__active-clue { /* border-left accent, bg, margin-top */ }

/* Tabs and difficulty — same pattern as Shkoda tabs */
/* Completion screen — same pattern as Shkoda reveal */
/* Theme browser — card grid for theme selection */

/* Mobile: stack layout, hide clues/word-bank behind slide-out panels */
@media (max-width: 768px) {
  .crossword__layout { grid-template-columns: 1fr; }
  .crossword__cell { width: var(--crossword-cell-size-mobile); height: var(--crossword-cell-size-mobile); }
}
```

- [ ] **Step 3: Bump CSS cache version**

In `templates/base.html.twig`, bump `?v=N` on the CSS link.

- [ ] **Step 4: Verify visually**

Visit `http://localhost:8081/games/crossword` and confirm the styled shell renders correctly.

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat: add crossword game CSS components"
```

---

### Task 11: Build Crossword Client-Side JS

**Files:**
- Create: `public/js/crossword.js`

Vanilla JS, IIFE pattern matching `shkoda.js`. ~600-800 lines. Key responsibilities: tab switching, puzzle loading (fetch API), grid rendering, cell selection + direction toggle, keyboard input (on-screen + physical), word checking via API, completion flow, stats display, share text.

- [ ] **Step 1: Create the JS file**

Create `public/js/crossword.js` as an IIFE with these sections:

```javascript
(function() {
    'use strict';

    // --- State ---
    const state = {
        mode: 'daily',        // daily | practice | themes
        tier: 'easy',
        sessionToken: null,
        puzzleId: null,
        gridSize: 0,
        placements: [],       // {row, col, direction, length}
        clues: {},
        wordBank: null,
        grid: [],             // 2D array of {letter, wordIndices}
        selectedCell: null,   // {row, col}
        selectedDirection: 'across',
        selectedWordIndex: null,
        completedWords: new Set(),
        hintsUsed: 0,
        maxHints: 0,
        startTime: null,
    };

    // --- Init ---
    // --- Tab switching ---
    // --- Puzzle loading (daily, random, themed) ---
    // --- Grid rendering ---
    // --- Cell selection + direction toggle ---
    // --- Keyboard input (on-screen + physical) ---
    // --- Word checking via POST /api/games/crossword/check ---
    // --- Hint system ---
    // --- Completion flow via POST /api/games/crossword/complete ---
    // --- Stats display ---
    // --- Share text generation ---
    // --- Theme browser ---
    // --- localStorage for anonymous stats ---

    document.addEventListener('DOMContentLoaded', init);
})();
```

This is the largest single file. **Reference `public/js/shkoda.js` as the pattern** — same IIFE structure, same fetch patterns, same localStorage approach. Key implementation details:

**Grid rendering:**
- Render as CSS Grid with cells as `<div>` elements — one per grid square
- Black (unused) cells get `.crossword__cell--black`, active cells get a border highlight
- Number labels in top-left corner of starting cells (like newspaper crosswords)

**Cell selection + navigation:**
- Click cell → select the word it belongs to, highlight all cells in that word
- Click same cell again → toggle between across/down (at intersection cells)
- Arrow keys navigate between cells
- Tab/Shift-Tab move to next/previous word
- When typing, cursor advances to next empty cell in the word

**Keyboard input:**
- Physical keyboard: letter keys type, Backspace deletes, Enter triggers CHECK
- On-screen keyboard: same layout as Shkoda (`shkoda.js` `initPhysicalKeyboard()` pattern) + HINT/DEL/CHECK buttons
- Glottal stop ʼ key for Ojibwe

**API integration:**
- Mode switching: fetch `/api/games/crossword/daily`, `/random`, or `/theme/{slug}`
- CHECK button: `POST /api/games/crossword/check` with `{session_token, word_index, letters}`
- HINT button: `POST /api/games/crossword/hint` with `{session_token, word_index, position}`
- Abandon: `POST /api/games/crossword/abandon`
- On all complete: `POST /api/games/crossword/complete`, show teaching + stats

**Share text generation (client-side):**
- Build a grid emoji pattern from completed state: `🟩` for filled cells, `⬛` for black cells
- Format: "📝 Crossword — Daily Challenge\n{date}\n{emoji grid}\n{word_count} words · {time}\nminoo.live/games/crossword"

**localStorage keys:**
- `crossword-stats`: {puzzles_completed, current_streak, best_streak}
- `crossword-daily-{YYYY-MM-DD}`: completion flag (prevent replay)
- `crossword-theme-{slug}`: array of completed puzzle IDs (anonymous progress)

- [ ] **Step 2: Test in browser**

Run dev server, visit `/games/crossword`. Verify:
- Tabs switch between daily/practice/themes
- Loading state shows
- (Requires puzzles in DB to fully test — see Task 12)

- [ ] **Step 3: Commit**

```bash
git add public/js/crossword.js
git commit -m "feat: add crossword client-side game engine"
```

---

### Task 12: Update Games Hub and Sidebar Navigation

**Files:**
- Modify: `templates/games.html.twig`
- Modify: `templates/components/sidebar-nav.html.twig`

- [ ] **Step 1: Add crossword card to games hub**

In `templates/games.html.twig`, add a second featured game card for the crossword alongside Shkoda. Use a placeholder grid SVG icon instead of the campfire. Card links to `/games/crossword`.

- [ ] **Step 2: Add crossword link to sidebar**

In `templates/components/sidebar-nav.html.twig`, add "Crossword" link in the Games section alongside Shkoda.

- [ ] **Step 3: Verify visually**

Visit `/games` and confirm both game cards appear with equal prominence. Check sidebar nav has both links.

- [ ] **Step 4: Commit**

```bash
git add templates/games.html.twig templates/components/sidebar-nav.html.twig
git commit -m "feat: add crossword to games hub and sidebar navigation"
```

---

### Task 13: Seed Sample Puzzles for Testing

**Files:**
- Create: `scripts/populate_crossword_puzzles.php`

A one-time script (following `scripts/populate_featured.php` pattern) that generates a few sample crossword puzzles from the dictionary for testing.

- [ ] **Step 1: Create the seeding script**

Create `scripts/populate_crossword_puzzles.php` that:
1. Boots HttpKernel via reflection (same pattern as existing scripts)
2. Loads dictionary entries with definitions
3. Uses `CrosswordEngine::generateGrid()` to build a few grids
4. Stores them as CrosswordPuzzle entities
5. Creates a daily puzzle for today and a few practice puzzles

- [ ] **Step 2: Run the script**

Run: `php scripts/populate_crossword_puzzles.php`
Expected: Output shows puzzles created, no errors.

- [ ] **Step 3: End-to-end test**

Visit `/games/crossword`, play the daily puzzle through to completion. Verify: grid renders, clues display, keyboard works, CHECK validates words, completion screen shows teaching data.

- [ ] **Step 4: Commit**

```bash
git add scripts/populate_crossword_puzzles.php
git commit -m "feat: add crossword puzzle seeding script for testing"
```

---

### Task 14: Add CrosswordController Integration Test

**Files:**
- Create: `tests/Minoo/Integration/Controller/CrosswordControllerTest.php`

Smoke test the check and complete endpoints — the most complex controller logic (grid state mutation, completion detection).

- [ ] **Step 1: Write integration test**

Create `tests/Minoo/Integration/Controller/CrosswordControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Controller;

use Minoo\Entity\CrosswordPuzzle;
use Minoo\Entity\GameSession;
use Minoo\Support\CrosswordEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CrosswordControllerTest extends TestCase
{
    /**
     * Smoke test: CrosswordEngine::validateWord correctly identifies
     * right and wrong answers, which is the core of the check endpoint.
     */
    #[Test]
    public function validate_word_identifies_correct_answer(): void
    {
        $result = CrosswordEngine::validateWord(
            ['s', 'h', 'k', 'o', 'd', 'a'],
            'shkoda'
        );
        $this->assertTrue($result['correct']);
    }

    #[Test]
    public function validate_word_identifies_partial_answer(): void
    {
        $result = CrosswordEngine::validateWord(
            ['s', 'h', 'k', 'x', 'x', 'x'],
            'shkoda'
        );
        $this->assertFalse($result['correct']);
        $this->assertSame([0, 1, 2], $result['correct_positions']);
        $this->assertSame([3, 4, 5], $result['wrong_positions']);
    }

    #[Test]
    public function crossword_session_tracks_grid_state(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'daily',
            'puzzle_id' => 'daily-2026-03-25',
        ]);

        // Simulate completing words
        $gridState = ['word_0' => 'completed', 'word_1' => 'completed'];
        $session->set('grid_state', json_encode($gridState));

        $decoded = json_decode((string) $session->get('grid_state'), true);
        $this->assertSame('completed', $decoded['word_0']);
        $this->assertSame('completed', $decoded['word_1']);
    }

    #[Test]
    public function crossword_puzzle_stores_and_retrieves_words(): void
    {
        $words = [
            ['dictionary_entry_id' => 1, 'row' => 0, 'col' => 0, 'direction' => 'across', 'word' => 'shkoda'],
        ];
        $clues = ['0' => ['auto' => 'fire', 'elder' => null, 'elder_author' => null]];

        $puzzle = new CrosswordPuzzle([
            'id' => 'test-1',
            'grid_size' => 7,
            'words' => json_encode($words),
            'clues' => json_encode($clues),
        ]);

        $decoded = json_decode((string) $puzzle->get('words'), true);
        $this->assertCount(1, $decoded);
        $this->assertSame('shkoda', $decoded[0]['word']);
    }
}
```

- [ ] **Step 2: Run test**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/Controller/CrosswordControllerTest.php -v`
Expected: ALL tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Minoo/Integration/Controller/CrosswordControllerTest.php
git commit -m "test: add CrosswordController integration smoke tests"
```

---

### Task 15: Run Full Test Suite and Cleanup

**Files:**
- Various (fixes only)

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: ALL tests pass. Fix any failures.

- [ ] **Step 2: Delete stale manifest**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 3: Run PHPStan if baseline exists**

Run: `./vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon` (if new files trigger PHPStan errors on `EntityInterface::get()`)

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: clean up after crossword game implementation"
```
