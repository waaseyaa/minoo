# Ishkode Word Game Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build "Ishkode," an Ojibwe word guessing game with campfire metaphor, three play modes (daily/practice/streak), and adaptive difficulty — pulling words dynamically from Minoo's dictionary database.

**Architecture:** Hybrid client/server — JS game engine for snappy play, server API for word delivery and score validation. Two new entities (GameSession content entity, DailyChallenge config entity), one controller, one service provider, one access policy, one game engine support class. Frontend is a Twig template + vanilla JS + CSS added to minoo.css.

**Tech Stack:** PHP 8.4, Waaseyaa entity system, SQLite, Twig 3, vanilla JS, CSS (oklch design tokens)

**Spec:** `docs/superpowers/specs/2026-03-23-ishkode-word-game-design.md`

---

### Task 1: GameSession Entity

**Files:**
- Create: `src/Entity/GameSession.php`
- Create: `tests/Minoo/Unit/Entity/GameSessionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\GameSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GameSession::class)]
final class GameSessionTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();
        $session = new GameSession([
            'mode' => 'daily',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 42,
        ]);
        $after = time();

        $this->assertSame('game_session', $session->getEntityTypeId());
        $this->assertSame('daily', $session->get('mode'));
        $this->assertSame('english_to_ojibwe', $session->get('direction'));
        $this->assertSame(42, $session->get('dictionary_entry_id'));
        $this->assertSame('in_progress', $session->get('status'));
        $this->assertSame(0, $session->get('wrong_count'));
        $this->assertSame('[]', $session->get('guesses'));
        $this->assertSame('easy', $session->get('difficulty_tier'));
        $this->assertNull($session->get('user_id'));
        $this->assertNull($session->get('daily_date'));
        $this->assertGreaterThanOrEqual($before, $session->get('created_at'));
        $this->assertLessThanOrEqual($after, $session->get('updated_at'));
    }

    #[Test]
    public function it_requires_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession(['direction' => 'english_to_ojibwe', 'dictionary_entry_id' => 1]);
    }

    #[Test]
    public function it_requires_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession(['mode' => 'daily', 'dictionary_entry_id' => 1]);
    }

    #[Test]
    public function it_requires_dictionary_entry_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession(['mode' => 'daily', 'direction' => 'english_to_ojibwe']);
    }

    #[Test]
    public function it_validates_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession([
            'mode' => 'invalid',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 1,
        ]);
    }

    #[Test]
    public function it_validates_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession([
            'mode' => 'daily',
            'direction' => 'invalid',
            'dictionary_entry_id' => 1,
        ]);
    }

    #[Test]
    public function it_accepts_all_fields(): void
    {
        $session = new GameSession([
            'mode' => 'streak',
            'direction' => 'ojibwe_to_english',
            'dictionary_entry_id' => 99,
            'user_id' => 7,
            'daily_date' => '2026-03-23',
            'difficulty_tier' => 'hard',
            'guesses' => '["a","b"]',
            'wrong_count' => 2,
            'status' => 'won',
        ]);

        $this->assertSame(7, $session->get('user_id'));
        $this->assertSame('2026-03-23', $session->get('daily_date'));
        $this->assertSame('hard', $session->get('difficulty_tier'));
        $this->assertSame('["a","b"]', $session->get('guesses'));
        $this->assertSame(2, $session->get('wrong_count'));
        $this->assertSame('won', $session->get('status'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GameSessionTest.php`
Expected: FAIL — class `Minoo\Entity\GameSession` not found

- [ ] **Step 3: Write the entity class**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class GameSession extends ContentEntityBase
{
    protected string $entityTypeId = 'game_session';

    protected array $entityKeys = [
        'id' => 'gsid',
        'uuid' => 'uuid',
        'label' => 'mode',
    ];

    private const VALID_MODES = ['daily', 'practice', 'streak'];
    private const VALID_DIRECTIONS = ['ojibwe_to_english', 'english_to_ojibwe'];
    private const VALID_STATUSES = ['in_progress', 'won', 'lost'];
    private const VALID_TIERS = ['easy', 'medium', 'hard'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['mode', 'direction', 'dictionary_entry_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!in_array($values['mode'], self::VALID_MODES, true)) {
            throw new \InvalidArgumentException("Invalid mode: {$values['mode']}");
        }
        if (!in_array($values['direction'], self::VALID_DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid direction: {$values['direction']}");
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
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GameSessionTest.php`
Expected: 7 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/GameSession.php tests/Minoo/Unit/Entity/GameSessionTest.php
git commit -m "feat(#N): add GameSession entity with validation"
```

---

### Task 2: DailyChallenge Config Entity

**Files:**
- Create: `src/Entity/DailyChallenge.php`
- Create: `tests/Minoo/Unit/Entity/DailyChallengeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\DailyChallenge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DailyChallenge::class)]
final class DailyChallengeTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $challenge = new DailyChallenge([
            'date' => '2026-03-23',
            'dictionary_entry_id' => 42,
        ]);

        $this->assertSame('daily_challenge', $challenge->getEntityTypeId());
        $this->assertSame('2026-03-23', $challenge->get('date'));
        $this->assertSame(42, $challenge->get('dictionary_entry_id'));
        $this->assertSame('english_to_ojibwe', $challenge->get('direction'));
        $this->assertSame('easy', $challenge->get('difficulty_tier'));
    }

    #[Test]
    public function it_accepts_all_fields(): void
    {
        $challenge = new DailyChallenge([
            'date' => '2026-03-24',
            'dictionary_entry_id' => 99,
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'hard',
        ]);

        $this->assertSame('ojibwe_to_english', $challenge->get('direction'));
        $this->assertSame('hard', $challenge->get('difficulty_tier'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/DailyChallengeTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write the config entity class**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class DailyChallenge extends ConfigEntityBase
{
    protected string $entityTypeId = 'daily_challenge';

    protected array $entityKeys = ['id' => 'date', 'label' => 'date'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('direction', $values)) {
            $values['direction'] = 'english_to_ojibwe';
        }
        if (!array_key_exists('difficulty_tier', $values)) {
            $values['difficulty_tier'] = 'easy';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/DailyChallengeTest.php`
Expected: 2 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/DailyChallenge.php tests/Minoo/Unit/Entity/DailyChallengeTest.php
git commit -m "feat(#N): add DailyChallenge config entity"
```

---

### Task 3: GameAccessPolicy

**Files:**
- Create: `src/Access/GameAccessPolicy.php`
- Create: `tests/Minoo/Unit/Access/GameAccessPolicyTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\GameAccessPolicy;
use Minoo\Entity\GameSession;
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/GameAccessPolicyTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write the access policy**

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['game_session', 'daily_challenge'])]
final class GameAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['game_session', 'daily_challenge'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::ENTITY_TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::allowed('Game data is publicly viewable.'),
            default => AccessResult::neutral('Cannot modify game data.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        // Anyone can create game sessions (anonymous play)
        if ($entityTypeId === 'game_session') {
            return AccessResult::allowed('Public game play.');
        }

        return AccessResult::neutral('Only admins can create daily challenges.');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/GameAccessPolicyTest.php`
Expected: 5 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Access/GameAccessPolicy.php tests/Minoo/Unit/Access/GameAccessPolicyTest.php
git commit -m "feat(#N): add GameAccessPolicy for public play + admin daily challenges"
```

---

### Task 4: IshkodeEngine (Game Logic)

**Files:**
- Create: `src/Support/IshkodeEngine.php`
- Create: `tests/Minoo/Unit/Support/IshkodeEngineTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\IshkodeEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IshkodeEngine::class)]
final class IshkodeEngineTest extends TestCase
{
    #[Test]
    public function difficulty_tier_for_short_noun(): void
    {
        $this->assertSame('easy', IshkodeEngine::difficultyTier('makwa', 'na'));
    }

    #[Test]
    public function difficulty_tier_for_medium_verb(): void
    {
        $this->assertSame('medium', IshkodeEngine::difficultyTier('bimosed', 'vai'));
    }

    #[Test]
    public function difficulty_tier_for_long_word(): void
    {
        $this->assertSame('hard', IshkodeEngine::difficultyTier('ishkodewaaboo', 'ni'));
    }

    #[Test]
    public function max_wrong_guesses_per_tier(): void
    {
        $this->assertSame(7, IshkodeEngine::maxWrongGuesses('easy'));
        $this->assertSame(6, IshkodeEngine::maxWrongGuesses('medium'));
        $this->assertSame(5, IshkodeEngine::maxWrongGuesses('hard'));
    }

    #[Test]
    public function process_correct_guess(): void
    {
        $result = IshkodeEngine::processGuess('ishkode', 'i', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([0], $result['positions']);
    }

    #[Test]
    public function process_wrong_guess(): void
    {
        $result = IshkodeEngine::processGuess('ishkode', 'z', []);
        $this->assertFalse($result['correct']);
        $this->assertSame([], $result['positions']);
    }

    #[Test]
    public function process_guess_finds_multiple_positions(): void
    {
        // "baabaa" has 'a' at positions 1, 2, 4, 5 (0-indexed)
        $result = IshkodeEngine::processGuess('baabaa', 'a', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([1, 2, 4, 5], $result['positions']);
    }

    #[Test]
    public function process_guess_is_case_insensitive(): void
    {
        $result = IshkodeEngine::processGuess('Makwa', 'm', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([0], $result['positions']);
    }

    #[Test]
    public function duplicate_guess_returns_already_guessed(): void
    {
        $result = IshkodeEngine::processGuess('ishkode', 'i', ['i']);
        $this->assertArrayHasKey('already_guessed', $result);
        $this->assertTrue($result['already_guessed']);
    }

    #[Test]
    public function daily_tier_for_day_of_week(): void
    {
        // Monday = easy
        $this->assertSame('easy', IshkodeEngine::dailyTier(1));
        // Tuesday = medium
        $this->assertSame('medium', IshkodeEngine::dailyTier(2));
        // Saturday = hard
        $this->assertSame('hard', IshkodeEngine::dailyTier(6));
        // Sunday = hard
        $this->assertSame('hard', IshkodeEngine::dailyTier(0));
    }

    #[Test]
    public function generate_share_text(): void
    {
        $guesses = ['i', 's', 'r', 'h', 'k', 'l', 'o', 'd', 'e'];
        $word = 'ishkode';
        $text = IshkodeEngine::generateShareText($word, $guesses, 'english_to_ojibwe', 'easy', '2026-03-23');

        $this->assertStringContainsString('Ishkode', $text);
        $this->assertStringContainsString('2026-03-23', $text);
        $this->assertStringContainsString('🔥', $text);
        $this->assertStringContainsString('🪨', $text);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/IshkodeEngineTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write the engine**

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

final class IshkodeEngine
{
    private const EASY_POS = ['ni', 'na', 'nad', 'nid'];
    private const MEDIUM_POS = ['ni', 'na', 'nad', 'nid', 'vai', 'vii'];

    /** Determine difficulty tier from word length and part of speech. */
    public static function difficultyTier(string $word, string $partOfSpeech): string
    {
        $len = mb_strlen($word);

        if ($len <= 5 && in_array($partOfSpeech, self::EASY_POS, true)) {
            return 'easy';
        }
        if ($len <= 8 && in_array($partOfSpeech, self::MEDIUM_POS, true)) {
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
            $emojis .= in_array($letter, $wordChars, true) ? '🔥' : '🪨';
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

        $dirLabel = $direction === 'english_to_ojibwe' ? 'English → Ojibwe' : 'Ojibwe → English';
        $dateLabel = $date !== '' ? $date : 'Practice';

        $lines = [
            "🔥 Ishkode — Daily Challenge",
            "{$dateLabel} · {$dirLabel}",
            $emojis,
            "{$totalGuesses} guesses · {$outcome}",
            "minoo.live/games/ishkode",
        ];

        return implode("\n", $lines);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/IshkodeEngineTest.php`
Expected: 11 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Support/IshkodeEngine.php tests/Minoo/Unit/Support/IshkodeEngineTest.php
git commit -m "feat(#N): add IshkodeEngine game logic (guess processing, difficulty, share text)"
```

---

### Task 5: GameServiceProvider (Entity Registration + Routes)

**Files:**
- Create: `src/Provider/GameServiceProvider.php`

- [ ] **Step 1: Write the service provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\DailyChallenge;
use Minoo\Entity\GameSession;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'game_session',
            label: 'Game Session',
            class: GameSession::class,
            keys: ['id' => 'gsid', 'uuid' => 'uuid', 'label' => 'mode'],
            group: 'games',
            fieldDefinitions: [
                'mode' => ['type' => 'string', 'label' => 'Mode', 'weight' => 0],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 1],
                'dictionary_entry_id' => ['type' => 'integer', 'label' => 'Dictionary Entry', 'weight' => 5],
                'user_id' => ['type' => 'integer', 'label' => 'User', 'weight' => 6],
                'guesses' => ['type' => 'text_long', 'label' => 'Guesses', 'description' => 'JSON array of letters guessed.', 'weight' => 10],
                'wrong_count' => ['type' => 'integer', 'label' => 'Wrong Count', 'weight' => 11, 'default' => 0],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 15, 'default' => 'in_progress'],
                'daily_date' => ['type' => 'string', 'label' => 'Daily Date', 'weight' => 16],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 17, 'default' => 'easy'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'daily_challenge',
            label: 'Daily Challenge',
            class: DailyChallenge::class,
            keys: ['id' => 'date', 'label' => 'date'],
            group: 'games',
            fieldDefinitions: [
                'date' => ['type' => 'string', 'label' => 'Date', 'weight' => 0],
                'dictionary_entry_id' => ['type' => 'integer', 'label' => 'Dictionary Entry', 'weight' => 5],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 10, 'default' => 'english_to_ojibwe'],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 15, 'default' => 'easy'],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        // Game page
        $router->addRoute(
            'games.ishkode',
            RouteBuilder::create('/games/ishkode')
                ->controller('Minoo\\Controller\\IshkodeController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // API: get daily challenge
        $router->addRoute(
            'api.games.ishkode.daily',
            RouteBuilder::create('/api/games/ishkode/daily')
                ->controller('Minoo\\Controller\\IshkodeController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: get random word for practice/streak
        $router->addRoute(
            'api.games.ishkode.word',
            RouteBuilder::create('/api/games/ishkode/word')
                ->controller('Minoo\\Controller\\IshkodeController::word')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: submit guess (daily challenge only)
        $router->addRoute(
            'api.games.ishkode.guess',
            RouteBuilder::create('/api/games/ishkode/guess')
                ->controller('Minoo\\Controller\\IshkodeController::guess')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: complete game
        $router->addRoute(
            'api.games.ishkode.complete',
            RouteBuilder::create('/api/games/ishkode/complete')
                ->controller('Minoo\\Controller\\IshkodeController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: player stats (auth required)
        $router->addRoute(
            'api.games.ishkode.stats',
            RouteBuilder::create('/api/games/ishkode/stats')
                ->controller('Minoo\\Controller\\IshkodeController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}
```

- [ ] **Step 2: Delete stale manifest cache and run full test suite**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All existing tests pass + new entity/access/engine tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Provider/GameServiceProvider.php
git commit -m "feat(#N): add GameServiceProvider with entity types and routes"
```

---

### Task 6: Database Migrations

**Files:**
- Create: `migrations/20260323_200000_create_game_session_table.php`
- Create: `migrations/20260323_200100_create_daily_challenge_table.php`

- [ ] **Step 1: Create game_session migration**

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the game_session table for Ishkode word game.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('game_session')) {
            return;
        }

        $schema->getConnection()->executeStatement('
            CREATE TABLE game_session (
                gsid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                mode TEXT NOT NULL,
                direction TEXT NOT NULL,
                dictionary_entry_id INTEGER NOT NULL,
                user_id INTEGER DEFAULT NULL,
                guesses TEXT DEFAULT "[]",
                wrong_count INTEGER DEFAULT 0,
                status TEXT DEFAULT "in_progress",
                daily_date TEXT DEFAULT NULL,
                difficulty_tier TEXT DEFAULT "easy",
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ');

        $schema->getConnection()->executeStatement(
            'CREATE INDEX idx_game_session_user ON game_session (user_id)',
        );
        $schema->getConnection()->executeStatement(
            'CREATE INDEX idx_game_session_daily ON game_session (daily_date, user_id)',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('game_session')) {
            $schema->getConnection()->executeStatement('DROP TABLE game_session');
        }
    }
};
```

- [ ] **Step 2: Create daily_challenge migration**

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the daily_challenge table for Ishkode word game.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('daily_challenge')) {
            return;
        }

        $schema->getConnection()->executeStatement('
            CREATE TABLE daily_challenge (
                date TEXT PRIMARY KEY,
                dictionary_entry_id INTEGER NOT NULL,
                direction TEXT DEFAULT "english_to_ojibwe",
                difficulty_tier TEXT DEFAULT "easy"
            )
        ');
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('daily_challenge')) {
            $schema->getConnection()->executeStatement('DROP TABLE daily_challenge');
        }
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `bin/waaseyaa migrate`
Expected: 2 new migrations applied

- [ ] **Step 4: Verify schema**

Run: `bin/waaseyaa schema:check`
Expected: No drift detected

- [ ] **Step 5: Commit**

```bash
git add migrations/
git commit -m "feat(#N): add migrations for game_session and daily_challenge tables"
```

---

### Task 7: IshkodeController (API Endpoints)

**Files:**
- Create: `src/Controller/IshkodeController.php`

This is the largest file. It handles the game page + all 5 API endpoints.

- [ ] **Step 1: Write the controller**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Entity\GameSession;
use Minoo\Support\IshkodeEngine;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class IshkodeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** Render the game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('ishkode.html.twig', [
            'path' => '/games/ishkode',
        ]);

        return new SsrResponse(content: $html);
    }

    /** GET /api/games/ishkode/daily — today's challenge metadata. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $today = date('Y-m-d');
        $dayOfWeek = (int) date('w');

        // Try pre-generated challenge
        $challengeStorage = $this->entityTypeManager->getStorage('daily_challenge');
        $challenge = $challengeStorage->load($today);

        if ($challenge !== null) {
            $entryId = (int) $challenge->get('dictionary_entry_id');
            $direction = (string) $challenge->get('direction');
            $tier = (string) $challenge->get('difficulty_tier');
        } else {
            // Fallback: deterministic random selection seeded by date
            $tier = IshkodeEngine::dailyTier($dayOfWeek);
            $direction = $dayOfWeek % 2 === 0 ? 'english_to_ojibwe' : 'ojibwe_to_english';
            $entryId = $this->selectRandomWord($tier, $today);
            if ($entryId === null) {
                return $this->json(['error' => 'No words available for today'], 503);
            }
        }

        $entry = $this->entityTypeManager->getStorage('dictionary_entry')->load($entryId);
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 503);
        }

        $word = (string) $entry->get('word');

        // Create server-side session for validation
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'mode' => 'daily',
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $today,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $clue = $direction === 'english_to_ojibwe'
            ? (string) $entry->get('definition')
            : $word;

        return $this->json([
            'session_token' => $session->get('uuid'),
            'word_length' => mb_strlen($word),
            'clue' => $clue,
            'clue_detail' => (string) $entry->get('part_of_speech'),
            'direction' => $direction,
            'difficulty' => $tier,
            'max_wrong' => IshkodeEngine::maxWrongGuesses($tier),
            'date' => $today,
        ]);
    }

    /** GET /api/games/ishkode/word — random word for practice/streak. */
    public function word(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $mode = ($query['mode'] ?? 'practice') === 'streak' ? 'streak' : 'practice';
        $tier = $query['tier'] ?? 'easy';
        if (!in_array($tier, ['easy', 'medium', 'hard'], true)) {
            $tier = 'easy';
        }

        $entryId = $this->selectRandomWord($tier);
        if ($entryId === null) {
            return $this->json(['error' => 'No words available'], 503);
        }

        $entry = $this->entityTypeManager->getStorage('dictionary_entry')->load($entryId);
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 503);
        }

        $direction = ($query['direction'] ?? 'english_to_ojibwe');
        if (!in_array($direction, ['english_to_ojibwe', 'ojibwe_to_english'], true)) {
            $direction = 'english_to_ojibwe';
        }

        $word = (string) $entry->get('word');

        // For practice/streak, include word (base64 obfuscated, client-side validation)
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'mode' => $mode,
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $clue = $direction === 'english_to_ojibwe'
            ? (string) $entry->get('definition')
            : $word;

        return $this->json([
            'session_token' => $session->get('uuid'),
            'word_length' => mb_strlen($word),
            'word_data' => base64_encode($word),
            'clue' => $clue,
            'clue_detail' => (string) $entry->get('part_of_speech'),
            'direction' => $direction,
            'difficulty' => $tier,
            'max_wrong' => IshkodeEngine::maxWrongGuesses($tier),
        ]);
    }

    /** POST /api/games/ishkode/guess — validate a letter (daily mode only). */
    public function guess(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $letter = $data['letter'] ?? '';

        if ($token === '' || $letter === '') {
            return $this->json(['error' => 'Missing session_token or letter'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Game already finished'], 400);
        }

        // Load the word
        $entry = $this->entityTypeManager->getStorage('dictionary_entry')
            ->load((int) $session->get('dictionary_entry_id'));
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 500);
        }

        $word = (string) $entry->get('word');
        $previousGuesses = json_decode((string) $session->get('guesses'), true) ?: [];

        $result = IshkodeEngine::processGuess($word, $letter, $previousGuesses);

        if (!empty($result['already_guessed'])) {
            return $this->json(['error' => 'Letter already guessed', 'already_guessed' => true], 400);
        }

        // Update session
        $previousGuesses[] = mb_strtolower($letter);
        $wrongCount = (int) $session->get('wrong_count');
        if (!$result['correct']) {
            $wrongCount++;
        }

        $maxWrong = IshkodeEngine::maxWrongGuesses((string) $session->get('difficulty_tier'));
        $allRevealed = $this->isWordFullyRevealed($word, $previousGuesses);
        $gameOver = $wrongCount >= $maxWrong || $allRevealed;

        $status = 'in_progress';
        if ($allRevealed) {
            $status = 'won';
        } elseif ($wrongCount >= $maxWrong) {
            $status = 'lost';
        }

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session->set('guesses', json_encode($previousGuesses));
        $session->set('wrong_count', $wrongCount);
        $session->set('status', $status);
        $session->set('updated_at', time());
        $sessionStorage->save($session);

        $response = [
            'correct' => $result['correct'],
            'positions' => $result['positions'],
            'remaining_wrong' => $maxWrong - $wrongCount,
            'game_over' => $gameOver,
            'status' => $status,
        ];

        // Reveal word on game over
        if ($gameOver) {
            $response['word'] = $word;
        }

        return $this->json($response);
    }

    /** POST /api/games/ishkode/complete — submit completed game, get teaching data + stats. */
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

        // For practice/streak, accept client-reported result
        if ($session->get('mode') !== 'daily' && isset($data['result'])) {
            $result = $data['result'] === 'won' ? 'won' : 'lost';
            $guesses = $data['guesses'] ?? [];
            $wrongCount = (int) ($data['wrong_count'] ?? 0);

            $sessionStorage = $this->entityTypeManager->getStorage('game_session');
            $session->set('status', $result);
            $session->set('guesses', json_encode($guesses));
            $session->set('wrong_count', $wrongCount);
            $session->set('updated_at', time());
            $sessionStorage->save($session);
        }

        // Load word data for teaching moment
        $entry = $this->entityTypeManager->getStorage('dictionary_entry')
            ->load((int) $session->get('dictionary_entry_id'));

        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 500);
        }

        $word = (string) $entry->get('word');
        $slug = (string) $entry->get('slug');

        // Load example sentence if available
        $exampleStorage = $this->entityTypeManager->getStorage('example_sentence');
        $exampleIds = $exampleStorage->getQuery()
            ->condition('dictionary_entry_id', $entry->id())
            ->condition('status', 1)
            ->range(0, 1)
            ->execute();
        $example = $exampleIds !== [] ? $exampleStorage->load(reset($exampleIds)) : null;

        // Build stats for authenticated users
        $stats = $this->buildStats($account);

        return $this->json([
            'word' => $word,
            'definition' => (string) $entry->get('definition'),
            'part_of_speech' => (string) $entry->get('part_of_speech'),
            'stem' => (string) $entry->get('stem'),
            'slug' => $slug,
            'example_ojibwe' => $example !== null ? (string) $example->get('ojibwe_text') : null,
            'example_english' => $example !== null ? (string) $example->get('english_text') : null,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/ishkode/stats — player stats (auth required). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->json($this->buildStats($account));
    }

    // --- Private helpers ---

    private function selectRandomWord(string $tier, string $seed = ''): ?int
    {
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1);

        $ids = $query->execute();

        if ($ids === []) {
            return null;
        }

        // Filter by word length for tier
        $filtered = [];
        $entries = $storage->loadMultiple($ids);
        foreach ($entries as $entry) {
            $word = (string) $entry->get('word');
            $pos = (string) $entry->get('part_of_speech');
            $def = (string) $entry->get('definition');

            if ($def === '') {
                continue;
            }

            $entryTier = IshkodeEngine::difficultyTier($word, $pos);
            if ($entryTier === $tier) {
                $filtered[] = $entry->id();
            }
        }

        if ($filtered === []) {
            // Fallback: any word with a definition
            $filtered = [];
            foreach ($entries as $entry) {
                if ((string) $entry->get('definition') !== '') {
                    $filtered[] = $entry->id();
                }
            }
        }

        if ($filtered === []) {
            return null;
        }

        // Deterministic selection for daily, random for practice
        if ($seed !== '') {
            $index = crc32($seed) % count($filtered);
            if ($index < 0) {
                $index += count($filtered);
            }
            return $filtered[$index];
        }

        return $filtered[array_rand($filtered)];
    }

    private function loadSessionByToken(string $uuid): ?GameSession
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

    private function isWordFullyRevealed(string $word, array $guesses): bool
    {
        $word = mb_strtolower($word);
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($word, $i, 1);
            if (!in_array($char, $guesses, true)) {
                return false;
            }
        }
        return true;
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
        $wins = array_filter($completed, fn($s) => $s->get('status') === 'won');
        $gamesPlayed = count($completed);
        $winRate = $gamesPlayed > 0 ? round(count($wins) / $gamesPlayed, 2) : 0.0;

        // Current streak
        $currentStreak = 0;
        foreach ($sessions as $s) {
            if ($s->get('status') === 'won') {
                $currentStreak++;
            } elseif ($s->get('status') === 'lost') {
                break;
            }
        }

        // Best streak
        $bestStreak = 0;
        $streak = 0;
        foreach ($sessions as $s) {
            if ($s->get('status') === 'won') {
                $streak++;
                $bestStreak = max($bestStreak, $streak);
            } elseif ($s->get('status') === 'lost') {
                $streak = 0;
            }
        }

        return [
            'authenticated' => true,
            'games_played' => $gamesPlayed,
            'win_rate' => $winRate,
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
        ];
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

- [ ] **Step 2: Delete stale manifest cache, run full test suite**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Controller/IshkodeController.php
git commit -m "feat(#N): add IshkodeController with game page + 5 API endpoints"
```

---

### Task 8: Twig Template (Game Page Shell)

**Files:**
- Create: `templates/ishkode.html.twig`

- [ ] **Step 1: Write the template**

```twig
{% extends "base.html.twig" %}

{% block title %}Ishkode — Word Game{% endblock %}

{% block content %}
<div class="ishkode" id="ishkode-game" data-api-base="/api/games/ishkode">
    {# Mode tabs #}
    <nav class="ishkode__tabs" role="tablist">
        <button class="ishkode__tab ishkode__tab--active" role="tab" data-mode="daily" aria-selected="true">Daily Challenge</button>
        <button class="ishkode__tab" role="tab" data-mode="practice" aria-selected="false">Practice</button>
        <button class="ishkode__tab" role="tab" data-mode="streak" aria-selected="false">Streak</button>
    </nav>

    {# Direction toggle #}
    <div class="ishkode__direction">
        <button class="ishkode__dir-btn ishkode__dir-btn--active" data-direction="english_to_ojibwe">English → Ojibwe</button>
        <button class="ishkode__dir-btn" data-direction="ojibwe_to_english">Ojibwe → English</button>
    </div>

    {# Campfire SVG #}
    <div class="ishkode__fire" id="ishkode-fire" data-fire-state="7">
        <svg viewBox="0 0 120 120" width="120" height="120" aria-label="Campfire" role="img">
            {# Glow #}
            <ellipse class="ishkode__glow" cx="60" cy="100" rx="40" ry="15" />
            {# Flames — 3 layers #}
            <path class="ishkode__flame ishkode__flame--outer" d="M60 20 C40 50, 30 80, 40 95 Q50 105, 60 100 Q70 105, 80 95 C90 80, 80 50, 60 20Z" />
            <path class="ishkode__flame ishkode__flame--mid" d="M60 35 C45 55, 38 78, 45 92 Q52 98, 60 95 Q68 98, 75 92 C82 78, 75 55, 60 35Z" />
            <path class="ishkode__flame ishkode__flame--inner" d="M60 50 C50 65, 46 80, 50 90 Q55 95, 60 92 Q65 95, 70 90 C74 80, 70 65, 60 50Z" />
            {# Logs #}
            <rect class="ishkode__log" x="30" y="98" width="60" height="8" rx="4" />
            <rect class="ishkode__log ishkode__log--cross" x="35" y="95" width="50" height="6" rx="3" transform="rotate(-8, 60, 98)" />
            {# Embers (visible on loss) #}
            <circle class="ishkode__ember" cx="50" cy="90" r="2" />
            <circle class="ishkode__ember" cx="65" cy="88" r="1.5" />
            <circle class="ishkode__ember" cx="55" cy="85" r="1" />
        </svg>
        <div class="ishkode__remaining" id="ishkode-remaining">7 guesses remaining</div>
    </div>

    {# Clue box #}
    <div class="ishkode__clue" id="ishkode-clue">
        <div class="ishkode__clue-label">Guess the Ojibwe word for:</div>
        <div class="ishkode__clue-word" id="ishkode-clue-word"></div>
        <div class="ishkode__clue-detail" id="ishkode-clue-detail"></div>
    </div>

    {# Word blanks #}
    <div class="ishkode__blanks" id="ishkode-blanks" aria-live="polite"></div>

    {# Wrong guesses #}
    <div class="ishkode__wrong" id="ishkode-wrong">
        <span class="ishkode__wrong-label">Wrong guesses:</span>
        <div class="ishkode__wrong-letters" id="ishkode-wrong-letters"></div>
    </div>

    {# On-screen keyboard #}
    <div class="ishkode__keyboard" id="ishkode-keyboard"></div>

    {# Reveal screen (hidden by default) #}
    <div class="ishkode__reveal" id="ishkode-reveal" hidden>
        <div class="ishkode__reveal-message" id="ishkode-reveal-message"></div>
        <div class="ishkode__reveal-word" id="ishkode-reveal-word"></div>
        <div class="ishkode__teaching" id="ishkode-teaching"></div>
        <div class="ishkode__stats" id="ishkode-stats"></div>
        <div class="ishkode__actions" id="ishkode-actions"></div>
    </div>

    {# Loading state #}
    <div class="ishkode__loading" id="ishkode-loading">Loading...</div>
</div>

<script src="/js/ishkode.js" defer></script>
{% endblock %}
```

- [ ] **Step 2: Verify page loads**

Run: `php -S localhost:8081 -t public` then visit `http://localhost:8081/games/ishkode`
Expected: Page renders with game shell (no JS functionality yet)

- [ ] **Step 3: Commit**

```bash
git add templates/ishkode.html.twig
git commit -m "feat(#N): add ishkode.html.twig game page template"
```

---

### Task 9: CSS (Game Styles in minoo.css)

**Files:**
- Modify: `public/css/minoo.css` — add to `@layer tokens` and `@layer components`

- [ ] **Step 1: Add game design tokens to `@layer tokens`**

Add after the existing language domain color variables:

```css
/* Ishkode game tokens */
--ishkode-fire-full: oklch(0.75 0.15 55);
--ishkode-fire-mid: oklch(0.65 0.18 40);
--ishkode-fire-low: oklch(0.50 0.15 25);
--ishkode-fire-dead: oklch(0.35 0.02 60);
--ishkode-ember: oklch(0.55 0.12 30);
--ishkode-correct: var(--color-language);
--ishkode-wrong: var(--color-events);
--ishkode-key-bg: oklch(0.25 0.01 250);
--ishkode-key-hover: oklch(0.30 0.01 250);
```

- [ ] **Step 2: Add game component styles to `@layer components`**

Add Ishkode component styles to the components layer — covering: game container, tabs, direction toggle, campfire SVG with 7 fire states, clue box, letter blanks, wrong guesses, keyboard, reveal screen, and loading state. All animations use pure CSS transitions and `@keyframes`. Fire states are driven by `[data-fire-state]` attribute selectors.

The CSS should be approximately 200-300 lines covering all game UI elements. Key patterns:
- `.ishkode` container with `max-width: 600px; margin: 0 auto`
- `.ishkode__tabs` with flex row, active state using `--color-language`
- `.ishkode__fire` with transition on `opacity`, `transform`, `filter` for flame paths
- `[data-fire-state="7"]` through `[data-fire-state="0"]` controlling flame `scaleY()`, glow `opacity`, color fill
- `.ishkode__blanks` with flex row, individual letter cells with bottom border
- `.ishkode__keyboard` with grid layout, key states (correct/wrong/unused)
- `.ishkode__reveal` with fade-in animation
- `@keyframes ishkode-spark` for win celebration particles
- `@keyframes ishkode-smoke` for loss smoke wisps

- [ ] **Step 3: Verify visual rendering**

Visit `http://localhost:8081/games/ishkode` and confirm game shell renders with correct styling, campfire SVG visible, tabs styled.

- [ ] **Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#N): add Ishkode game styles to minoo.css components layer"
```

---

### Task 10: Client-Side Game Engine (ishkode.js)

**Files:**
- Create: `public/js/ishkode.js`

- [ ] **Step 1: Write the JS game engine**

The JS file should be approximately 400-500 lines of vanilla JS (no framework) implementing:

```javascript
/**
 * Ishkode — Ojibwe Word Game
 *
 * Client-side game engine. Handles:
 * - Mode switching (daily/practice/streak)
 * - Direction toggle (english_to_ojibwe / ojibwe_to_english)
 * - Keyboard rendering and input (on-screen + physical)
 * - Campfire state management (data-fire-state attribute)
 * - Letter blank rendering and reveal
 * - Daily mode: server-validated per guess (POST /api/games/ishkode/guess)
 * - Practice/streak: client-validated (word decoded from base64)
 * - Game completion (POST /api/games/ishkode/complete)
 * - Reveal screen with teaching data
 * - Share text generation (clipboard)
 * - localStorage for anonymous stats + daily completion tracking
 */
```

Key implementation details:
- **IIFE pattern** — no global pollution, matches existing JS patterns in Minoo
- **Keyboard layout** — 3 rows: `ABCDEGHIJ` / `KLMNOPQR` / `STWYZʼ` (Ojibwe-relevant subset + glottal stop)
- **Physical keyboard support** — `keydown` listener, filter to valid letters
- **API calls** — `fetch()` with JSON headers
- **Daily dedup** — check `localStorage.getItem('ishkode-daily-' + date)` before allowing play
- **Stats in localStorage** — `ishkode-stats` key with `{games_played, wins, current_streak, best_streak}`
- **Campfire updates** — `document.getElementById('ishkode-fire').dataset.fireState = remaining`
- **Share** — `navigator.share()` with `navigator.clipboard.writeText()` fallback

- [ ] **Step 2: Test full game flow manually**

1. Start dev server: `php -S localhost:8081 -t public`
2. Visit `http://localhost:8081/games/ishkode`
3. Verify: daily challenge loads, keyboard works, guesses validate, campfire animates, reveal screen shows
4. Test practice mode, streak mode, direction toggle
5. Test share button

- [ ] **Step 3: Commit**

```bash
git add public/js/ishkode.js
git commit -m "feat(#N): add ishkode.js client-side game engine"
```

---

### Task 11: Word Pool Validation + Smoke Test

**Files:**
- No new files — validation queries and manual testing

- [ ] **Step 1: Validate word pool size per tier**

Run the SQL query from the spec against the production database (or local copy):

```bash
bin/waaseyaa db:query "SELECT CASE WHEN LENGTH(word) <= 5 THEN 'easy' WHEN LENGTH(word) <= 8 THEN 'medium' ELSE 'hard' END AS tier, COUNT(*) AS count FROM dictionary_entry WHERE status = 1 AND consent_public = 1 AND definition != '' GROUP BY tier"
```

Expected: At least 20 words per tier. If any tier is below threshold, note it and adjust tier boundaries.

- [ ] **Step 2: Regenerate PHPStan baseline**

Run: `./vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`
Expected: Baseline regenerated to account for new files calling `EntityInterface::get()`

- [ ] **Step 3: Run full test suite**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All tests pass including new GameSession, DailyChallenge, GameAccessPolicy, and IshkodeEngine tests

- [ ] **Step 4: Manual smoke test**

1. Start dev server
2. Play a daily challenge through to completion
3. Play a practice round
4. Start a streak and lose
5. Verify share text copies correctly
6. Verify "View Entry" links to correct `/language/{slug}` page
7. Verify campfire animates through all states

- [ ] **Step 5: Commit any fixes from smoke testing**

```bash
git add -A
git commit -m "fix(#N): smoke test fixes for Ishkode game"
```
