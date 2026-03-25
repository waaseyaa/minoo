# Matcher Game Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a drag-to-connect word matching game to the Minoo games hub, where players match Ojibwe words to their English definitions.

**Architecture:** New `MatcherController` + `MatcherEngine` following the exact Shkoda/Crossword pattern. Reuses existing `game_session` entity (with `game_type: 'matcher'`), `GameControllerTrait`, and `GameStatsCalculator`. No new entity types or access policies needed.

**Tech Stack:** PHP 8.4, Twig 3, vanilla CSS (`minoo.css`), vanilla JS (inline `<script>`), SVG for connection lines.

**Design spec:** `docs/superpowers/specs/2026-03-25-matcher-game-design.md`

---

### Task 1: Add 'matcher' to GameSession Validation

**Files:**
- Modify: `src/Entity/GameSession.php:19`
- Test: `tests/Minoo/Unit/Entity/GameSessionTest.php`

- [ ] **Step 1: Write the failing test**

Add a test that creating a game session with `game_type: 'matcher'` succeeds. Open `tests/Minoo/Unit/Entity/GameSessionTest.php` and add:

```php
#[Test]
public function matcher_game_type_is_valid(): void
{
    $session = new GameSession([
        'game_type' => 'matcher',
        'mode' => 'daily',
        'direction' => 'ojibwe_to_english',
    ]);

    $this->assertSame('matcher', $session->get('game_type'));
}
```

Note: `matcher` game sessions don't require `dictionary_entry_id` (unlike shkoda). The constructor only enforces that field for `game_type === 'shkoda'`. But the `game_type` validation will reject `'matcher'` because it's not in `VALID_GAME_TYPES`.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter=matcher_game_type_is_valid`
Expected: FAIL with `InvalidArgumentException: Invalid game_type: matcher`

- [ ] **Step 3: Add 'matcher' to VALID_GAME_TYPES**

In `src/Entity/GameSession.php`, change line 19:

```php
public const VALID_GAME_TYPES = ['shkoda', 'crossword', 'matcher'];
```

The `matcher` game type does NOT require `dictionary_entry_id` or `direction` at the entity level (those are optional — the controller provides them). The existing constructor only enforces those fields when `game_type === 'shkoda'`, so no other constructor changes are needed.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter=matcher_game_type_is_valid`
Expected: PASS

- [ ] **Step 5: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (no regressions)

- [ ] **Step 6: Commit**

```bash
git add src/Entity/GameSession.php tests/Minoo/Unit/Entity/GameSessionTest.php
git commit -m "feat(#NNN): add 'matcher' to valid game types"
```

---

### Task 2: Create MatcherEngine

**Files:**
- Create: `src/Support/MatcherEngine.php`
- Create: `tests/Minoo/Unit/Support/MatcherEngineTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Minoo/Unit/Support/MatcherEngineTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\MatcherEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MatcherEngine::class)]
final class MatcherEngineTest extends TestCase
{
    #[Test]
    public function pair_count_for_easy(): void
    {
        $this->assertSame(4, MatcherEngine::pairCount('easy'));
    }

    #[Test]
    public function pair_count_for_medium(): void
    {
        $this->assertSame(6, MatcherEngine::pairCount('medium'));
    }

    #[Test]
    public function pair_count_for_hard(): void
    {
        $this->assertSame(8, MatcherEngine::pairCount('hard'));
    }

    #[Test]
    public function pair_count_defaults_to_easy(): void
    {
        $this->assertSame(4, MatcherEngine::pairCount('invalid'));
    }

    #[Test]
    public function validate_match_correct(): void
    {
        $pairs = [
            ['id' => 'deid_1', 'ojibwe' => 'makwa', 'english' => 'bear'],
            ['id' => 'deid_2', 'ojibwe' => 'nibi', 'english' => 'water'],
        ];

        $result = MatcherEngine::validateMatch('deid_1', 'deid_1', $pairs);
        $this->assertTrue($result['correct']);
    }

    #[Test]
    public function validate_match_incorrect(): void
    {
        $pairs = [
            ['id' => 'deid_1', 'ojibwe' => 'makwa', 'english' => 'bear'],
            ['id' => 'deid_2', 'ojibwe' => 'nibi', 'english' => 'water'],
        ];

        $result = MatcherEngine::validateMatch('deid_1', 'deid_2', $pairs);
        $this->assertFalse($result['correct']);
    }

    #[Test]
    public function daily_seed_is_deterministic(): void
    {
        $seed1 = MatcherEngine::dailySeed('2026-03-25');
        $seed2 = MatcherEngine::dailySeed('2026-03-25');
        $this->assertSame($seed1, $seed2);
    }

    #[Test]
    public function daily_seed_differs_across_dates(): void
    {
        $seed1 = MatcherEngine::dailySeed('2026-03-25');
        $seed2 = MatcherEngine::dailySeed('2026-03-26');
        $this->assertNotSame($seed1, $seed2);
    }

    #[Test]
    public function clean_definition_unwraps_json_array(): void
    {
        $this->assertSame('bear', MatcherEngine::cleanDefinition('["bear"]'));
    }

    #[Test]
    public function clean_definition_joins_multiple_values(): void
    {
        $this->assertSame('bear; grizzly', MatcherEngine::cleanDefinition('["bear", "grizzly"]'));
    }

    #[Test]
    public function clean_definition_expands_abbreviations(): void
    {
        $this->assertSame('she/he walks', MatcherEngine::cleanDefinition('s/he walks'));
    }

    #[Test]
    public function clean_definition_handles_plain_string(): void
    {
        $this->assertSame('bear', MatcherEngine::cleanDefinition('bear'));
    }

    #[Test]
    public function clean_definition_handles_empty_string(): void
    {
        $this->assertSame('', MatcherEngine::cleanDefinition(''));
    }

    #[Test]
    public function is_abbreviation_only_detects_pos_tags(): void
    {
        $this->assertTrue(MatcherEngine::isAbbreviationOnly('na'));
        $this->assertTrue(MatcherEngine::isAbbreviationOnly('vti'));
        $this->assertTrue(MatcherEngine::isAbbreviationOnly('ni'));
        $this->assertFalse(MatcherEngine::isAbbreviationOnly('bear'));
        $this->assertFalse(MatcherEngine::isAbbreviationOnly('a big bear'));
    }

    #[Test]
    public function select_pairs_filters_and_shuffles(): void
    {
        // Build mock entries: array of [id, word, definition]
        $entries = [
            ['id' => 1, 'word' => 'makwa', 'definition' => '["bear"]'],
            ['id' => 2, 'word' => 'nibi', 'definition' => '["water"]'],
            ['id' => 3, 'word' => 'giizis', 'definition' => '["sun"]'],
            ['id' => 4, 'word' => 'waabshki', 'definition' => '["white"]'],
            ['id' => 5, 'word' => 'goon', 'definition' => '["snow"]'],
            ['id' => 6, 'word' => '', 'definition' => '["empty word"]'],     // no word — filtered
            ['id' => 7, 'word' => 'test', 'definition' => ''],               // no def — filtered
            ['id' => 8, 'word' => 'test2', 'definition' => 'na'],            // abbreviation only — filtered
        ];

        $pairs = MatcherEngine::selectPairs($entries, 4);
        $this->assertCount(4, $pairs);

        // Each pair has required keys
        foreach ($pairs as $pair) {
            $this->assertArrayHasKey('id', $pair);
            $this->assertArrayHasKey('ojibwe', $pair);
            $this->assertArrayHasKey('english', $pair);
            $this->assertNotEmpty($pair['ojibwe']);
            $this->assertNotEmpty($pair['english']);
        }
    }

    #[Test]
    public function select_pairs_with_seed_is_deterministic(): void
    {
        $entries = [
            ['id' => 1, 'word' => 'makwa', 'definition' => '["bear"]'],
            ['id' => 2, 'word' => 'nibi', 'definition' => '["water"]'],
            ['id' => 3, 'word' => 'giizis', 'definition' => '["sun"]'],
            ['id' => 4, 'word' => 'waabshki', 'definition' => '["white"]'],
            ['id' => 5, 'word' => 'goon', 'definition' => '["snow"]'],
        ];

        $seed = MatcherEngine::dailySeed('2026-03-25');
        $pairs1 = MatcherEngine::selectPairs($entries, 4, $seed);
        $pairs2 = MatcherEngine::selectPairs($entries, 4, $seed);
        $this->assertSame($pairs1, $pairs2);
    }

    #[Test]
    public function select_pairs_avoids_duplicate_definitions(): void
    {
        $entries = [
            ['id' => 1, 'word' => 'makwa', 'definition' => '["bear"]'],
            ['id' => 2, 'word' => 'mkwa', 'definition' => '["bear"]'],  // duplicate def
            ['id' => 3, 'word' => 'nibi', 'definition' => '["water"]'],
            ['id' => 4, 'word' => 'giizis', 'definition' => '["sun"]'],
            ['id' => 5, 'word' => 'goon', 'definition' => '["snow"]'],
        ];

        $pairs = MatcherEngine::selectPairs($entries, 4);
        $definitions = array_map(fn($p) => $p['english'], $pairs);
        $this->assertSame(count($definitions), count(array_unique($definitions)));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/MatcherEngineTest.php`
Expected: FAIL — class `MatcherEngine` not found

- [ ] **Step 3: Implement MatcherEngine**

Create `src/Support/MatcherEngine.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

final class MatcherEngine
{
    private const POS_ABBREVIATIONS = [
        'na', 'nad', 'ni', 'nid', 'vai', 'vii', 'vta', 'vti',
        'pc', 'adv', 'pron', 'conj', 'interj', 'num',
    ];

    public static function pairCount(string $difficulty): int
    {
        return match ($difficulty) {
            'easy' => 4,
            'medium' => 6,
            'hard' => 8,
            default => 4,
        };
    }

    /**
     * Validate whether two IDs form a correct match.
     *
     * Both left and right refer to the same pair's `id` field when correct.
     * The frontend sends the dictionary entry ID from each side.
     *
     * @param string $leftId  The ID selected on the Ojibwe side
     * @param string $rightId The ID selected on the English side
     * @param list<array{id: string, ojibwe: string, english: string}> $pairs
     * @return array{correct: bool}
     */
    public static function validateMatch(string $leftId, string $rightId, array $pairs): array
    {
        return ['correct' => $leftId === $rightId];
    }

    public static function dailySeed(string $date): int
    {
        return crc32("matcher-{$date}");
    }

    /**
     * Extract a clean definition string from a field that may be JSON-encoded.
     *
     * Replicates GameControllerTrait::cleanDefinition() as a static method
     * so the engine can be used without a controller instance.
     */
    public static function cleanDefinition(string $raw): string
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

    /**
     * Check if a definition string is only a linguistic part-of-speech abbreviation.
     */
    public static function isAbbreviationOnly(string $definition): bool
    {
        return in_array(strtolower(trim($definition)), self::POS_ABBREVIATIONS, true);
    }

    /**
     * Select pairs from a list of raw dictionary entry data.
     *
     * @param list<array{id: int, word: string, definition: string}> $entries Raw entry data
     * @param int $count Number of pairs to select
     * @param int|null $seed Deterministic seed for daily mode (null = random)
     * @return list<array{id: int, ojibwe: string, english: string}>
     */
    public static function selectPairs(array $entries, int $count, ?int $seed = null): array
    {
        // Filter: must have word, must have definition, definition must not be abbreviation-only
        $valid = [];
        $seenDefinitions = [];
        foreach ($entries as $entry) {
            if ($entry['word'] === '') {
                continue;
            }
            if ($entry['definition'] === '') {
                continue;
            }

            $cleaned = self::cleanDefinition($entry['definition']);
            if ($cleaned === '' || self::isAbbreviationOnly($cleaned)) {
                continue;
            }

            // Avoid duplicate definitions
            $defKey = strtolower($cleaned);
            if (isset($seenDefinitions[$defKey])) {
                continue;
            }
            $seenDefinitions[$defKey] = true;

            $valid[] = [
                'id' => $entry['id'],
                'ojibwe' => $entry['word'],
                'english' => $cleaned,
            ];
        }

        // Seed-based or random shuffle
        if ($seed !== null) {
            mt_srand($seed);
            usort($valid, fn() => mt_rand(-1, 1));
            mt_srand();
        } else {
            shuffle($valid);
        }

        return array_slice($valid, 0, $count);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/MatcherEngineTest.php`
Expected: All 16 tests PASS

- [ ] **Step 5: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add src/Support/MatcherEngine.php tests/Minoo/Unit/Support/MatcherEngineTest.php
git commit -m "feat(#NNN): add MatcherEngine with word selection and validation"
```

---

### Task 3: Create MatcherController

**Files:**
- Create: `src/Controller/MatcherController.php`

- [ ] **Step 1: Create the controller**

Create `src/Controller/MatcherController.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\GameStatsCalculator;
use Minoo\Support\MatcherEngine;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class MatcherController
{
    use GameControllerTrait;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly GateInterface $gate,
    ) {}

    private function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    /** Render the game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('matcher.html.twig', [
            'path' => '/games/matcher',
        ]);

        return new SsrResponse(content: $html);
    }

    /** GET /api/games/matcher/daily — today's matching pairs. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $today = date('Y-m-d');
        $difficulty = 'easy';
        $direction = 'ojibwe_to_english';
        $count = MatcherEngine::pairCount($difficulty);

        $entries = $this->loadDictionaryEntries(500);
        if ($entries === []) {
            return $this->json(['error' => 'No words available'], 503);
        }

        $seed = MatcherEngine::dailySeed($today);
        $pairs = MatcherEngine::selectPairs($entries, $count, $seed);

        if (count($pairs) < $count) {
            return $this->json(['error' => 'Not enough words available'], 503);
        }

        $session = $this->createMatcherSession($account, 'daily', $direction, $difficulty, $today, $pairs);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'pairs' => $this->shuffledPairSides($pairs, $direction),
            'difficulty' => $difficulty,
            'direction' => $direction,
            'date' => $today,
        ]);
    }

    /** GET /api/games/matcher/practice — random pairs by difficulty. */
    public function practice(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $difficulty = $query['difficulty'] ?? 'easy';
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'easy';
        }

        $direction = $query['direction'] ?? 'ojibwe_to_english';
        if (!in_array($direction, ['ojibwe_to_english', 'english_to_ojibwe'], true)) {
            $direction = 'ojibwe_to_english';
        }

        $count = MatcherEngine::pairCount($difficulty);

        $entries = $this->loadDictionaryEntries(500);
        if ($entries === []) {
            return $this->json(['error' => 'No words available'], 503);
        }

        $pairs = MatcherEngine::selectPairs($entries, $count);

        if (count($pairs) < $count) {
            return $this->json(['error' => 'Not enough words available'], 503);
        }

        $session = $this->createMatcherSession($account, 'practice', $direction, $difficulty, null, $pairs);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'pairs' => $this->shuffledPairSides($pairs, $direction),
            'difficulty' => $difficulty,
            'direction' => $direction,
        ]);
    }

    /** POST /api/games/matcher/match — validate a single match attempt. */
    public function match(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $leftId = $data['left_id'] ?? '';
        $rightId = $data['right_id'] ?? '';

        if ($token === '' || $leftId === '' || $rightId === '') {
            return $this->json(['error' => 'Missing required fields'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Game already finished'], 400);
        }

        $pairs = json_decode((string) $session->get('guesses'), true) ?: [];
        $result = MatcherEngine::validateMatch($leftId, $rightId, $pairs);

        // Track attempt in session
        $matches = json_decode((string) ($session->get('grid_state') ?? '[]'), true) ?: [];
        $matches[] = ['left_id' => $leftId, 'right_id' => $rightId, 'correct' => $result['correct']];

        $wrongCount = (int) $session->get('wrong_count');
        if (!$result['correct']) {
            $wrongCount++;
        }

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session->set('grid_state', json_encode($matches));
        $session->set('wrong_count', $wrongCount);
        $sessionStorage->save($session);

        return $this->json($result);
    }

    /** POST /api/games/matcher/complete — finish game, record stats. */
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

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session->set('status', 'completed');
        $sessionStorage->save($session);

        $matches = json_decode((string) ($session->get('grid_state') ?? '[]'), true) ?: [];
        $totalAttempts = count($matches);
        $wrongCount = (int) $session->get('wrong_count');
        $correctCount = $totalAttempts - $wrongCount;
        $accuracy = $totalAttempts > 0 ? round(($correctCount / $totalAttempts) * 100, 1) : 100.0;

        $timeSeconds = (int) $session->get('updated_at') - (int) $session->get('created_at');

        $stats = GameStatsCalculator::build(
            $this->entityTypeManager,
            $account,
            'matcher',
            streakBreakers: [],
            winStatuses: ['completed'],
        );

        return $this->json([
            'time_seconds' => $timeSeconds,
            'attempts' => $totalAttempts,
            'wrong_count' => $wrongCount,
            'accuracy' => $accuracy,
            'pairs_count' => $correctCount,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/matcher/stats — player stats. */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->json(GameStatsCalculator::build(
            $this->entityTypeManager,
            $account,
            'matcher',
            streakBreakers: [],
            winStatuses: ['completed'],
        ));
    }

    // --- Private helpers ---

    /**
     * Load raw dictionary entry data for pair selection.
     *
     * @return list<array{id: int, word: string, definition: string}>
     */
    private function loadDictionaryEntries(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->range(0, $limit)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $entries = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            $entries[] = [
                'id' => $entity->id(),
                'word' => (string) $entity->get('word'),
                'definition' => (string) $entity->get('definition'),
            ];
        }

        return $entries;
    }

    /**
     * Create a matcher game session.
     *
     * @param list<array{id: int, ojibwe: string, english: string}> $pairs
     */
    private function createMatcherSession(
        AccountInterface $account,
        string $mode,
        string $direction,
        string $difficulty,
        ?string $dailyDate,
        array $pairs,
    ): \Minoo\Entity\GameSession {
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'matcher',
            'mode' => $mode,
            'direction' => $direction,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $dailyDate,
            'difficulty_tier' => $difficulty,
            'guesses' => json_encode($pairs),
            'grid_state' => '[]',
        ]);
        $sessionStorage->save($session);

        return $session;
    }

    /**
     * Return pairs with shuffled left/right columns for the frontend.
     *
     * Each side is independently shuffled so positions don't hint at matches.
     *
     * @param list<array{id: int, ojibwe: string, english: string}> $pairs
     * @return array{left: list<array{id: int, text: string}>, right: list<array{id: int, text: string}>}
     */
    private function shuffledPairSides(array $pairs, string $direction): array
    {
        $left = [];
        $right = [];

        foreach ($pairs as $pair) {
            if ($direction === 'ojibwe_to_english') {
                $left[] = ['id' => $pair['id'], 'text' => $pair['ojibwe']];
                $right[] = ['id' => $pair['id'], 'text' => $pair['english']];
            } else {
                $left[] = ['id' => $pair['id'], 'text' => $pair['english']];
                $right[] = ['id' => $pair['id'], 'text' => $pair['ojibwe']];
            }
        }

        shuffle($left);
        shuffle($right);

        return ['left' => $left, 'right' => $right];
    }
}
```

- [ ] **Step 2: Verify file compiles**

Run: `php -l src/Controller/MatcherController.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Controller/MatcherController.php
git commit -m "feat(#NNN): add MatcherController with daily, practice, match, complete, stats endpoints"
```

---

### Task 4: Register Matcher Routes in GameServiceProvider

**Files:**
- Modify: `src/Provider/GameServiceProvider.php`

- [ ] **Step 1: Add matcher routes**

At the end of the `routes()` method in `src/Provider/GameServiceProvider.php`, before the closing `}`, add:

```php
        // --- Matcher routes ---

        $router->addRoute(
            'games.matcher',
            RouteBuilder::create('/games/matcher')
                ->controller('Minoo\\Controller\\MatcherController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.daily',
            RouteBuilder::create('/api/games/matcher/daily')
                ->controller('Minoo\\Controller\\MatcherController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.practice',
            RouteBuilder::create('/api/games/matcher/practice')
                ->controller('Minoo\\Controller\\MatcherController::practice')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.match',
            RouteBuilder::create('/api/games/matcher/match')
                ->controller('Minoo\\Controller\\MatcherController::match')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.complete',
            RouteBuilder::create('/api/games/matcher/complete')
                ->controller('Minoo\\Controller\\MatcherController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.stats',
            RouteBuilder::create('/api/games/matcher/stats')
                ->controller('Minoo\\Controller\\MatcherController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
```

- [ ] **Step 2: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

This ensures the new routes are discovered on next request.

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add src/Provider/GameServiceProvider.php
git commit -m "feat(#NNN): register matcher routes in GameServiceProvider"
```

---

### Task 5: Create Matcher Template

**Files:**
- Create: `templates/matcher.html.twig`

- [ ] **Step 1: Create the template**

Create `templates/matcher.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}Word Match — Minoo{% endblock %}

{% block content %}
<div class="matcher" data-game="matcher">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="/games">Games</a> <span aria-hidden="true">›</span> <span>Word Match</span>
    </nav>

    {# Mode tabs #}
    <div class="matcher__tabs" role="tablist" aria-label="Game mode">
        <button role="tab" class="game-btn game-btn--tab" data-mode="daily" aria-selected="true">Daily</button>
        <button role="tab" class="game-btn game-btn--tab" data-mode="practice" aria-selected="false">Practice</button>
    </div>

    {# Practice controls (hidden in daily mode) #}
    <div class="matcher__controls" data-show-mode="practice" hidden>
        <div class="matcher__difficulty" role="group" aria-label="Difficulty">
            <button class="game-btn game-btn--sm" data-difficulty="easy" aria-pressed="true">Easy (4)</button>
            <button class="game-btn game-btn--sm" data-difficulty="medium" aria-pressed="false">Medium (6)</button>
            <button class="game-btn game-btn--sm" data-difficulty="hard" aria-pressed="false">Hard (8)</button>
        </div>
        <div class="matcher__direction" role="group" aria-label="Direction">
            <button class="game-btn game-btn--sm" data-direction="ojibwe_to_english" aria-pressed="true">Ojibwe → English</button>
            <button class="game-btn game-btn--sm" data-direction="english_to_ojibwe" aria-pressed="false">English → Ojibwe</button>
        </div>
    </div>

    {# Game board #}
    <div class="matcher__board" aria-label="Matching game board">
        <div class="matcher__column matcher__column--left" data-side="left"></div>
        <svg class="matcher__svg" aria-hidden="true"></svg>
        <div class="matcher__column matcher__column--right" data-side="right"></div>
    </div>

    {# Stats row #}
    <div class="matcher__status">
        <div class="game-stat">
            <span class="game-stat__value" data-stat="time">0:00</span>
            <span class="game-stat__label">Time</span>
        </div>
        <div class="game-stat">
            <span class="game-stat__value" data-stat="matched">0</span>
            <span class="game-stat__label">Matched</span>
        </div>
        <div class="game-stat">
            <span class="game-stat__value" data-stat="wrong">0</span>
            <span class="game-stat__label">Wrong</span>
        </div>
    </div>

    {# Completion overlay #}
    <div class="matcher__complete" hidden>
        <div class="matcher__complete-card">
            <h2 class="matcher__complete-title">All matched!</h2>
            <div class="matcher__complete-stats">
                <div class="game-stat">
                    <span class="game-stat__value" data-result="time"></span>
                    <span class="game-stat__label">Time</span>
                </div>
                <div class="game-stat">
                    <span class="game-stat__value" data-result="attempts"></span>
                    <span class="game-stat__label">Attempts</span>
                </div>
                <div class="game-stat">
                    <span class="game-stat__value" data-result="accuracy"></span>
                    <span class="game-stat__label">Accuracy</span>
                </div>
            </div>
            <div class="matcher__complete-actions">
                <button class="game-btn game-btn--primary" data-action="play-again">Play Again</button>
                <button class="game-btn" data-action="share">Share</button>
            </div>
        </div>
    </div>

    {# Toast for feedback #}
    <div class="game-toast" role="status" aria-live="polite" hidden></div>

    {# Screen reader announcements #}
    <div class="visually-hidden" aria-live="polite" data-sr-announce></div>
</div>

<script>
(function() {
    'use strict';

    const root = document.querySelector('[data-game="matcher"]');
    const board = root.querySelector('.matcher__board');
    const leftCol = root.querySelector('[data-side="left"]');
    const rightCol = root.querySelector('[data-side="right"]');
    const svg = root.querySelector('.matcher__svg');
    const completeOverlay = root.querySelector('.matcher__complete');
    const toast = root.querySelector('.game-toast');
    const srAnnounce = root.querySelector('[data-sr-announce]');

    let state = {
        token: null,
        pairs: null,       // {left: [{id, text}], right: [{id, text}]}
        mode: 'daily',
        difficulty: 'easy',
        direction: 'ojibwe_to_english',
        selected: null,     // {side, id, el} — currently selected word
        matched: new Set(),  // Set of matched IDs
        wrongCount: 0,
        matchCount: 0,
        totalPairs: 0,
        timerStart: null,
        timerInterval: null,
        dragLine: null,      // active SVG line during drag
        isDragging: false,
        isMobile: 'ontouchstart' in window,
    };

    // --- Init ---

    function init() {
        bindTabs();
        bindControls();
        loadGame();
    }

    // --- Tab / control handlers ---

    function bindTabs() {
        root.querySelectorAll('[data-mode]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.mode = btn.dataset.mode;
                root.querySelectorAll('[data-mode]').forEach(b => b.setAttribute('aria-selected', 'false'));
                btn.setAttribute('aria-selected', 'true');

                const practiceControls = root.querySelector('[data-show-mode="practice"]');
                practiceControls.hidden = state.mode !== 'practice';

                loadGame();
            });
        });
    }

    function bindControls() {
        root.querySelectorAll('[data-difficulty]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.difficulty = btn.dataset.difficulty;
                root.querySelectorAll('[data-difficulty]').forEach(b => b.setAttribute('aria-pressed', 'false'));
                btn.setAttribute('aria-pressed', 'true');
                loadGame();
            });
        });

        root.querySelectorAll('[data-direction]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.direction = btn.dataset.direction;
                root.querySelectorAll('[data-direction]').forEach(b => b.setAttribute('aria-pressed', 'false'));
                btn.setAttribute('aria-pressed', 'true');
                loadGame();
            });
        });

        root.querySelector('[data-action="play-again"]').addEventListener('click', loadGame);
        root.querySelector('[data-action="share"]').addEventListener('click', shareResult);
    }

    // --- API ---

    async function loadGame() {
        resetState();
        completeOverlay.hidden = true;

        try {
            const url = state.mode === 'daily'
                ? '/api/games/matcher/daily'
                : `/api/games/matcher/practice?difficulty=${state.difficulty}&direction=${state.direction}`;

            const res = await fetch(url);
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                showToast(err.error || 'Failed to load game');
                return;
            }

            const data = await res.json();
            state.token = data.session_token;
            state.pairs = data.pairs;
            state.totalPairs = data.pairs.left.length;
            state.direction = data.direction;
            state.difficulty = data.difficulty;

            renderBoard();
            announce('Game loaded. Match the words.');
        } catch (e) {
            showToast('Failed to load game');
        }
    }

    async function submitMatch(leftId, rightId) {
        try {
            const res = await fetch('/api/games/matcher/match', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_token: state.token,
                    left_id: String(leftId),
                    right_id: String(rightId),
                }),
            });

            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                showToast(err.error || 'Error');
                return null;
            }

            return await res.json();
        } catch (e) {
            showToast('Connection error');
            return null;
        }
    }

    async function submitComplete() {
        try {
            const res = await fetch('/api/games/matcher/complete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_token: state.token }),
            });
            if (res.ok) return await res.json();
        } catch (e) { /* ignore */ }
        return null;
    }

    // --- Rendering ---

    function renderBoard() {
        leftCol.innerHTML = '';
        rightCol.innerHTML = '';
        svg.innerHTML = '';

        state.pairs.left.forEach(item => {
            leftCol.appendChild(createWordEl(item, 'left'));
        });

        state.pairs.right.forEach(item => {
            rightCol.appendChild(createWordEl(item, 'right'));
        });

        updateStats();
    }

    function createWordEl(item, side) {
        const el = document.createElement('button');
        el.className = 'matcher-word';
        el.textContent = item.text;
        el.dataset.id = item.id;
        el.dataset.side = side;
        el.setAttribute('type', 'button');

        if (state.isMobile) {
            el.addEventListener('click', () => handleTap(el, side, item.id));
        } else {
            el.addEventListener('mousedown', (e) => handleDragStart(e, el, side, item.id));
        }

        return el;
    }

    // --- Interaction: Tap (mobile) ---

    function handleTap(el, side, id) {
        if (state.matched.has(id + '-' + side)) return;

        if (!state.selected) {
            state.selected = { side, id, el };
            el.classList.add('matcher-word--active');
            startTimer();
            return;
        }

        if (state.selected.side === side) {
            // Same side — swap selection
            state.selected.el.classList.remove('matcher-word--active');
            state.selected = { side, id, el };
            el.classList.add('matcher-word--active');
            return;
        }

        // Different side — attempt match
        const leftId = side === 'left' ? id : state.selected.id;
        const rightId = side === 'right' ? id : state.selected.id;
        const leftEl = side === 'left' ? el : state.selected.el;
        const rightEl = side === 'right' ? el : state.selected.el;

        state.selected.el.classList.remove('matcher-word--active');
        state.selected = null;

        attemptMatch(leftId, rightId, leftEl, rightEl);
    }

    // --- Interaction: Drag (desktop) ---

    function handleDragStart(e, el, side, id) {
        if (state.matched.has(id + '-' + side)) return;
        e.preventDefault();
        startTimer();

        state.isDragging = true;
        state.selected = { side, id, el };
        el.classList.add('matcher-word--active');

        const rect = el.getBoundingClientRect();
        const boardRect = board.getBoundingClientRect();
        const startX = rect.left + rect.width / 2 - boardRect.left;
        const startY = rect.top + rect.height / 2 - boardRect.top;

        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', startX);
        line.setAttribute('y1', startY);
        line.setAttribute('x2', startX);
        line.setAttribute('y2', startY);
        line.classList.add('matcher-line', 'matcher-line--drawing');
        svg.appendChild(line);
        state.dragLine = line;

        function onMove(e2) {
            const x = (e2.clientX || e2.touches?.[0]?.clientX) - boardRect.left;
            const y = (e2.clientY || e2.touches?.[0]?.clientY) - boardRect.top;
            line.setAttribute('x2', x);
            line.setAttribute('y2', y);
        }

        function onUp(e2) {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            state.isDragging = false;
            el.classList.remove('matcher-word--active');

            // Find target
            const target = document.elementFromPoint(e2.clientX, e2.clientY);
            const targetWord = target?.closest?.('.matcher-word');

            if (targetWord && targetWord.dataset.side !== side) {
                const targetId = parseInt(targetWord.dataset.id);
                const leftId = side === 'left' ? id : targetId;
                const rightId = side === 'right' ? id : targetId;
                const leftEl2 = side === 'left' ? el : targetWord;
                const rightEl2 = side === 'right' ? el : targetWord;

                line.remove();
                state.dragLine = null;
                state.selected = null;
                attemptMatch(leftId, rightId, leftEl2, rightEl2);
            } else {
                line.remove();
                state.dragLine = null;
                state.selected = null;
            }
        }

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    // --- Match logic ---

    async function attemptMatch(leftId, rightId, leftEl, rightEl) {
        const result = await submitMatch(leftId, rightId);
        if (!result) return;

        if (result.correct) {
            state.matched.add(leftId + '-left');
            state.matched.add(rightId + '-right');
            state.matchCount++;

            leftEl.classList.add('matcher-word--matched');
            rightEl.classList.add('matcher-word--matched');
            leftEl.disabled = true;
            rightEl.disabled = true;

            drawLockedLine(leftEl, rightEl);
            announce(`Correct! ${state.matchCount} of ${state.totalPairs} matched.`);

            if (state.matchCount === state.totalPairs) {
                stopTimer();
                const result2 = await submitComplete();
                showComplete(result2);
            }
        } else {
            state.wrongCount++;
            leftEl.classList.add('matcher-word--wrong');
            rightEl.classList.add('matcher-word--wrong');
            announce('Incorrect match. Try again.');

            setTimeout(() => {
                leftEl.classList.remove('matcher-word--wrong');
                rightEl.classList.remove('matcher-word--wrong');
            }, 600);
        }

        updateStats();
    }

    function drawLockedLine(leftEl, rightEl) {
        const boardRect = board.getBoundingClientRect();
        const lr = leftEl.getBoundingClientRect();
        const rr = rightEl.getBoundingClientRect();

        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', lr.left + lr.width / 2 - boardRect.left);
        line.setAttribute('y1', lr.top + lr.height / 2 - boardRect.top);
        line.setAttribute('x2', rr.left + rr.width / 2 - boardRect.left);
        line.setAttribute('y2', rr.top + rr.height / 2 - boardRect.top);
        line.classList.add('matcher-line', 'matcher-line--locked');
        svg.appendChild(line);
    }

    // --- Timer ---

    function startTimer() {
        if (state.timerStart) return;
        state.timerStart = Date.now();
        state.timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - state.timerStart) / 1000);
            const min = Math.floor(elapsed / 60);
            const sec = String(elapsed % 60).padStart(2, '0');
            root.querySelector('[data-stat="time"]').textContent = `${min}:${sec}`;
        }, 1000);
    }

    function stopTimer() {
        if (state.timerInterval) {
            clearInterval(state.timerInterval);
            state.timerInterval = null;
        }
    }

    // --- Completion ---

    function showComplete(data) {
        if (data) {
            root.querySelector('[data-result="time"]').textContent = formatTime(data.time_seconds);
            root.querySelector('[data-result="attempts"]').textContent = data.attempts;
            root.querySelector('[data-result="accuracy"]').textContent = data.accuracy + '%';
        }
        completeOverlay.hidden = false;
        announce('All pairs matched! Game complete.');
    }

    function shareResult() {
        const time = root.querySelector('[data-result="time"]').textContent;
        const accuracy = root.querySelector('[data-result="accuracy"]').textContent;
        const text = [
            '\u{1F517} Word Match — Minoo',
            `${state.mode === 'daily' ? new Date().toISOString().slice(0, 10) : 'Practice'} \u00B7 ${state.difficulty}`,
            `${time} \u00B7 ${accuracy} accuracy`,
            'minoo.live/games/matcher',
        ].join('\n');

        if (navigator.share) {
            navigator.share({ text }).catch(() => {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => showToast('Copied to clipboard'));
        }
    }

    // --- Utilities ---

    function resetState() {
        stopTimer();
        state.token = null;
        state.pairs = null;
        state.selected = null;
        state.matched = new Set();
        state.wrongCount = 0;
        state.matchCount = 0;
        state.totalPairs = 0;
        state.timerStart = null;
        leftCol.innerHTML = '';
        rightCol.innerHTML = '';
        svg.innerHTML = '';
        updateStats();
    }

    function updateStats() {
        root.querySelector('[data-stat="matched"]').textContent = state.matchCount;
        root.querySelector('[data-stat="wrong"]').textContent = state.wrongCount;
    }

    function formatTime(seconds) {
        const min = Math.floor(seconds / 60);
        const sec = String(seconds % 60).padStart(2, '0');
        return `${min}:${sec}`;
    }

    function showToast(msg) {
        toast.textContent = msg;
        toast.hidden = false;
        setTimeout(() => { toast.hidden = true; }, 3700);
    }

    function announce(msg) {
        srAnnounce.textContent = msg;
    }

    init();
})();
</script>
{% endblock %}
```

- [ ] **Step 2: Verify template renders without errors**

Run: `php -S localhost:8081 -t public &` then visit `http://localhost:8081/games/matcher` in a browser.
Expected: Page loads with game layout (no word data until API is wired, but no PHP/Twig errors).

- [ ] **Step 3: Commit**

```bash
git add templates/matcher.html.twig
git commit -m "feat(#NNN): add matcher game template with drag-to-connect UI"
```

---

### Task 6: Add Matcher CSS

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add matcher styles to `@layer components`**

Find the end of the crossword CSS section in `public/css/minoo.css` (after the last `.crossword` rule) and add:

```css
/* ── Matcher ── */

.matcher {
    --matcher-accent: oklch(0.75 0.15 280);
    max-width: 48rem;
    margin: 0 auto;
    padding: var(--space-m);
}

.matcher__tabs {
    display: flex;
    gap: var(--space-xs);
    margin-block-end: var(--space-m);
}

.matcher__controls {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-s);
    margin-block-end: var(--space-m);
}

.matcher__difficulty,
.matcher__direction {
    display: flex;
    gap: var(--space-xs);
}

.matcher__board {
    position: relative;
    display: flex;
    gap: var(--space-l);
    justify-content: center;
    align-items: flex-start;
    min-height: 20rem;
    margin-block-end: var(--space-m);
}

.matcher__column {
    display: flex;
    flex-direction: column;
    gap: var(--space-s);
    flex: 0 1 14rem;
    z-index: 1;
}

.matcher__svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.matcher-word {
    padding: var(--space-xs) var(--space-s);
    background: var(--surface-2);
    border: 2px solid var(--border);
    border-radius: var(--radius-m);
    color: var(--text-1);
    font-size: var(--step-0);
    text-align: center;
    cursor: grab;
    transition: border-color 0.15s, opacity 0.3s, transform 0.15s;
    user-select: none;
}

.matcher-word:hover:not(:disabled) {
    border-color: var(--matcher-accent);
}

.matcher-word--active {
    border-color: var(--matcher-accent);
    box-shadow: 0 0 0 3px oklch(0.75 0.15 280 / 0.3);
}

.matcher-word--matched {
    border-color: var(--color-correct);
    opacity: 0.6;
    cursor: default;
}

.matcher-word--wrong {
    border-color: var(--color-wrong);
    animation: matcher-shake 0.4s ease-in-out;
}

@keyframes matcher-shake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-6px); }
    40% { transform: translateX(6px); }
    60% { transform: translateX(-4px); }
    80% { transform: translateX(4px); }
}

.matcher-line--drawing {
    stroke: var(--matcher-accent);
    stroke-width: 2;
    stroke-dasharray: 6 4;
    opacity: 0.7;
}

.matcher-line--locked {
    stroke: var(--color-correct);
    stroke-width: 2.5;
    opacity: 0.5;
}

.matcher__status {
    display: flex;
    justify-content: center;
    gap: var(--space-l);
}

.matcher__complete {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: oklch(0 0 0 / 0.6);
    z-index: 100;
}

.matcher__complete-card {
    background: var(--surface-1);
    border: 1px solid var(--border);
    border-radius: var(--radius-l);
    padding: var(--space-l);
    text-align: center;
    max-width: 24rem;
    width: 90%;
}

.matcher__complete-title {
    font-size: var(--step-2);
    margin-block-end: var(--space-m);
}

.matcher__complete-stats {
    display: flex;
    justify-content: center;
    gap: var(--space-l);
    margin-block-end: var(--space-m);
}

.matcher__complete-actions {
    display: flex;
    gap: var(--space-s);
    justify-content: center;
}

/* Matcher mobile */
@media (max-width: 40em) {
    .matcher__board {
        gap: var(--space-s);
    }

    .matcher__column {
        flex: 1;
    }

    .matcher-word {
        font-size: var(--step--1);
        padding: var(--space-xs);
    }
}
```

- [ ] **Step 2: Bump CSS cache version**

In `templates/base.html.twig`, find the CSS `<link>` tag and bump the `?v=N` query parameter by 1.

- [ ] **Step 3: Verify styles render**

Visit `http://localhost:8081/games/matcher` and confirm the layout looks correct.

- [ ] **Step 4: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat(#NNN): add matcher game CSS with drag, shake, and completion styles"
```

---

### Task 7: Update Games Hub

**Files:**
- Modify: `templates/games.html.twig`

- [ ] **Step 1: Replace "Word Match" placeholder with active game card**

In `templates/games.html.twig`, add a new featured card for Matcher after the Crossword card (before `</section>` closing the `games-hub__featured` section), and remove the "Word Match" placeholder from the "coming soon" grid.

After the Crossword `</a>` tag (line 57) and before `</section>` (line 58), add:

```twig
        <a href="/games/matcher" class="game-card game-card--featured">
            <div class="game-card__icon">
                <svg viewBox="0 0 80 80" width="80" height="80" aria-hidden="true">
                    <circle cx="20" cy="24" r="8" fill="currentColor" opacity="0.8"/>
                    <circle cx="60" cy="24" r="8" fill="currentColor" opacity="0.8"/>
                    <circle cx="20" cy="48" r="8" fill="currentColor" opacity="0.8"/>
                    <circle cx="60" cy="48" r="8" fill="currentColor" opacity="0.8"/>
                    <line x1="28" y1="24" x2="52" y2="48" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                    <line x1="28" y1="48" x2="52" y2="24" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                </svg>
            </div>
            <div class="game-card__content">
                <span class="game-card__badge">Word Game</span>
                <h3 class="game-card__title">Word Match</h3>
                <p class="game-card__description">Draw lines between Ojibwe words and English definitions. Daily challenges and practice mode.</p>
                <span class="game-card__cta">Play now</span>
            </div>
        </a>
```

Then remove the "Word Match" placeholder `<div>` from the `games-hub__coming-grid` section (lines 63-67):

```twig
            <div class="game-card game-card--placeholder">
                <div class="game-card__placeholder-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12h8M12 8v8"/></svg>
                </div>
                <span class="game-card__placeholder-label">Word Match</span>
            </div>
```

Remove that block entirely — "Word Match" is now a real game.

- [ ] **Step 2: Verify the games hub**

Visit `http://localhost:8081/games` and confirm: 3 featured game cards (Shkoda, Crossword, Word Match), 2 remaining "coming soon" placeholders (Listening Quiz, Sentence Builder).

- [ ] **Step 3: Commit**

```bash
git add templates/games.html.twig
git commit -m "feat(#NNN): promote Word Match from placeholder to featured game on games hub"
```

---

### Task 8: Integration Test

**Files:**
- Create: `tests/Minoo/Integration/Controller/MatcherControllerTest.php`

- [ ] **Step 1: Write integration tests**

Create `tests/Minoo/Integration/Controller/MatcherControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\HttpKernel;

#[CoversNothing]
final class MatcherControllerTest extends TestCase
{
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        putenv('WAASEYAA_DB=:memory:');
        $projectRoot = dirname(__DIR__, 3);

        self::$kernel = new HttpKernel($projectRoot);
        $ref = new \ReflectionMethod(self::$kernel, 'boot');
        $ref->invoke(self::$kernel);
    }

    #[Test]
    public function matcher_page_renders(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $this->assertNotNull($etm->getDefinition('game_session'));

        // Verify matcher game_type is accepted
        $storage = $etm->getStorage('game_session');
        $session = $storage->create([
            'game_type' => 'matcher',
            'mode' => 'practice',
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'easy',
        ]);
        $this->assertSame('matcher', $session->get('game_type'));
        $this->assertSame('in_progress', $session->get('status'));
    }

    #[Test]
    public function matcher_session_saves_and_loads(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('game_session');

        $session = $storage->create([
            'game_type' => 'matcher',
            'mode' => 'daily',
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'easy',
            'guesses' => json_encode([['id' => 1, 'ojibwe' => 'makwa', 'english' => 'bear']]),
            'grid_state' => json_encode([['left_id' => '1', 'right_id' => '1', 'correct' => true]]),
        ]);
        $storage->save($session);

        $loaded = $storage->loadByKey('uuid', $session->get('uuid'));
        $this->assertNotNull($loaded);
        $this->assertSame('matcher', $loaded->get('game_type'));

        $pairs = json_decode((string) $loaded->get('guesses'), true);
        $this->assertCount(1, $pairs);
        $this->assertSame('makwa', $pairs[0]['ojibwe']);
    }

    #[Test]
    public function matcher_session_completes(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('game_session');

        $session = $storage->create([
            'game_type' => 'matcher',
            'mode' => 'practice',
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'easy',
        ]);
        $storage->save($session);

        $session->set('status', 'completed');
        $storage->save($session);

        $loaded = $storage->load($session->id());
        $this->assertSame('completed', $loaded->get('status'));
    }
}
```

- [ ] **Step 2: Run integration tests**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/Controller/MatcherControllerTest.php`
Expected: All 3 tests PASS

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (no regressions)

- [ ] **Step 4: Commit**

```bash
git add tests/Minoo/Integration/Controller/MatcherControllerTest.php
git commit -m "test(#NNN): add matcher game integration tests"
```

---

### Task 9: Smoke Test in Browser

**Files:** None (manual verification)

- [ ] **Step 1: Start dev server**

Run: `php -S localhost:8081 -t public`

- [ ] **Step 2: Verify games hub**

Visit `http://localhost:8081/games`. Confirm:
- 3 featured game cards: Shkoda, Crossword, Word Match
- Word Match card links to `/games/matcher`
- 2 "coming soon" placeholders remain (Listening Quiz, Sentence Builder)

- [ ] **Step 3: Verify matcher page loads**

Visit `http://localhost:8081/games/matcher`. Confirm:
- Page renders with breadcrumb, mode tabs, game board area
- No PHP errors or blank page

- [ ] **Step 4: Verify daily API**

Visit `http://localhost:8081/api/games/matcher/daily`. Confirm:
- Returns JSON with `session_token`, `pairs.left`, `pairs.right`, `difficulty`, `direction`
- Or returns `{"error": "No words available"}` if no dictionary entries exist (expected in empty dev DB)

- [ ] **Step 5: Test gameplay (if dictionary data exists)**

On the matcher page:
- Words appear in two columns
- Desktop: drag from left word to right word, line follows cursor
- Correct match: line locks green, words dim
- Wrong match: words shake, line disappears
- All matched: completion overlay appears with time/attempts/accuracy

- [ ] **Step 6: Document any issues found**

Create GitHub issues for any bugs discovered during smoke testing (per workflow spec).
