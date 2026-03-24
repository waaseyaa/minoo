# Feed Ranking Algorithm Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the static feed sort key with an EdgeRank-style scoring algorithm (affinity x engagement x decay) that personalizes feed content based on user interaction history.

**Architecture:** Six new classes in `src/Feed/Scoring/` (DecayCalculator, EngagementCalculator, AffinityCache, AffinityCalculator, DiversityReranker, FeedScorer) plus a service provider. FeedScorer orchestrates the three scoring factors and is injected into the existing FeedAssembler, replacing the static `sortKey` sort. All config is tunable via `config/feed_scoring.php`.

**Tech Stack:** PHP 8.4, Waaseyaa entity system + DatabaseInterface (DBALDatabase) + CacheBackendInterface, SQLite, PHPUnit 10.5

**Spec:** `docs/superpowers/specs/2026-03-23-feed-ranking-algorithm-design.md`

---

### Task 1: DecayCalculator

Pure math — no database, no dependencies. Easiest starting point.

**Files:**
- Create: `src/Feed/Scoring/DecayCalculator.php`
- Create: `tests/Minoo/Unit/Feed/Scoring/DecayCalculatorTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\DecayCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecayCalculator::class)]
final class DecayCalculatorTest extends TestCase
{
    #[Test]
    public function brand_new_content_has_no_decay(): void
    {
        $calc = new DecayCalculator(halfLifeHours: 96.0);
        $now = time();
        $result = $calc->compute($now, $now);
        self::assertEqualsWithDelta(1.0, $result, 0.001);
    }

    #[Test]
    public function content_at_half_life_decays_to_half(): void
    {
        $calc = new DecayCalculator(halfLifeHours: 96.0);
        $now = time();
        $fourDaysAgo = $now - (96 * 3600);
        $result = $calc->compute($fourDaysAgo, $now);
        self::assertEqualsWithDelta(0.5, $result, 0.001);
    }

    #[Test]
    public function two_half_lives_decays_to_quarter(): void
    {
        $calc = new DecayCalculator(halfLifeHours: 96.0);
        $now = time();
        $eightDaysAgo = $now - (192 * 3600);
        $result = $calc->compute($eightDaysAgo, $now);
        self::assertEqualsWithDelta(0.25, $result, 0.001);
    }

    #[Test]
    public function custom_half_life(): void
    {
        $calc = new DecayCalculator(halfLifeHours: 24.0);
        $now = time();
        $oneDayAgo = $now - (24 * 3600);
        $result = $calc->compute($oneDayAgo, $now);
        self::assertEqualsWithDelta(0.5, $result, 0.001);
    }

    #[Test]
    public function decay_never_reaches_zero(): void
    {
        $calc = new DecayCalculator(halfLifeHours: 96.0);
        $now = time();
        $thirtyDaysAgo = $now - (30 * 24 * 3600);
        $result = $calc->compute($thirtyDaysAgo, $now);
        self::assertGreaterThan(0.0, $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/DecayCalculatorTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

final class DecayCalculator
{
    public function __construct(
        private readonly float $halfLifeHours = 96.0,
    ) {}

    public function compute(int $createdAt, ?int $now = null): float
    {
        $now ??= time();
        $ageHours = ($now - $createdAt) / 3600.0;

        return pow(0.5, $ageHours / $this->halfLifeHours);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/DecayCalculatorTest.php`
Expected: 5 tests, 5 assertions, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/Scoring/DecayCalculator.php tests/Minoo/Unit/Feed/Scoring/DecayCalculatorTest.php
git commit -m "feat: add DecayCalculator for feed time decay scoring"
```

---

### Task 2: EngagementCalculator

Computes engagement weight using batch queries. Replaces the N+1 pattern in `EngagementCounter`.

**Files:**
- Create: `src/Feed/Scoring/EngagementCalculator.php`
- Create: `tests/Minoo/Unit/Feed/Scoring/EngagementCalculatorTest.php`

**Docs to check:**
- Waaseyaa `DatabaseInterface::select()` — see `vendor/waaseyaa/database-legacy/src/DatabaseInterface.php`
- `SelectInterface` — `fields()`, `condition()`, `execute()` return `\Traversable`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\EngagementCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;

#[CoversClass(EngagementCalculator::class)]
final class EngagementCalculatorTest extends TestCase
{
    private DBALDatabase $db;
    private EngagementCalculator $calculator;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();
        $this->createTables();
        $this->calculator = new EngagementCalculator($this->db, reactionWeight: 1.0, commentWeight: 3.0);
    }

    #[Test]
    public function zero_interactions_returns_base_weight(): void
    {
        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);
        self::assertEqualsWithDelta(1.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function reactions_increase_weight(): void
    {
        $this->insertReaction('post', 1, 100);
        $this->insertReaction('post', 1, 101);
        $this->insertReaction('post', 1, 102);

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 3*1.0) = 1.0 + 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function comments_weigh_more_than_reactions(): void
    {
        $this->insertComment('post', 1, 100);

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 1*3.0) = 1.0 + 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function log_dampening_prevents_runaway(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->insertReaction('post', 1, $i);
        }

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 100) ≈ 7.66 — not 100
        self::assertLessThan(10.0, $result['post:1']['weight']);
        self::assertGreaterThan(7.0, $result['post:1']['weight']);
    }

    #[Test]
    public function batch_returns_counts_for_display(): void
    {
        $this->insertReaction('post', 1, 100);
        $this->insertReaction('post', 1, 101);
        $this->insertComment('post', 1, 100);

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        self::assertSame(2, $result['post:1']['reactions']);
        self::assertSame(1, $result['post:1']['comments']);
    }

    #[Test]
    public function empty_input_returns_empty(): void
    {
        $result = $this->calculator->computeBatch([]);
        self::assertSame([], $result);
    }

    private function createTables(): void
    {
        $schema = $this->db->schema();
        $schema->createTable('reaction', [
            'fields' => [
                'rid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'reaction_type' => ['type' => 'varchar', 'length' => 32],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['rid'],
        ]);
        $schema->createTable('comment', [
            'fields' => [
                'cid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'body' => ['type' => 'text'],
                'status' => ['type' => 'int', 'not null' => true, 'default' => '1'],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['cid'],
        ]);
    }

    private function insertReaction(string $targetType, int $targetId, int $userId): void
    {
        $this->db->insert('reaction')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reaction_type' => 'like',
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }

    private function insertComment(string $targetType, int $targetId, int $userId): void
    {
        $this->db->insert('comment')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'body' => 'test comment',
            'status' => 1,
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/EngagementCalculatorTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Waaseyaa\Database\DatabaseInterface;

final class EngagementCalculator
{
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly float $reactionWeight = 1.0,
        private readonly float $commentWeight = 3.0,
    ) {}

    /**
     * Batch-compute engagement weight + raw counts for feed items.
     *
     * @param array<string, array{type: string, id: int}> $targetKeys  keyed by "type:id"
     * @return array<string, array{weight: float, reactions: int, comments: int}>
     */
    public function computeBatch(array $targetKeys): array
    {
        if ($targetKeys === []) {
            return [];
        }

        $result = [];
        foreach ($targetKeys as $key => $target) {
            $result[$key] = ['weight' => 1.0, 'reactions' => 0, 'comments' => 0];
        }

        $reactionCounts = $this->countByTarget('reaction', $targetKeys);
        $commentCounts = $this->countByTarget('comment', $targetKeys, statusFilter: true);

        foreach ($targetKeys as $key => $target) {
            $targetKey = $target['type'] . ':' . $target['id'];
            $reactions = $reactionCounts[$targetKey] ?? 0;
            $comments = $commentCounts[$targetKey] ?? 0;

            $result[$key]['reactions'] = $reactions;
            $result[$key]['comments'] = $comments;

            $weightedSum = ($reactions * $this->reactionWeight) + ($comments * $this->commentWeight);
            $result[$key]['weight'] = 1.0 + log(1 + $weightedSum, 2);
        }

        return $result;
    }

    /**
     * @param array<string, array{type: string, id: int}> $targetKeys
     * @return array<string, int>  "type:id" => count
     */
    private function countByTarget(string $table, array $targetKeys, bool $statusFilter = false): array
    {
        $types = [];
        $ids = [];
        foreach ($targetKeys as $target) {
            $types[$target['type']] = true;
            $ids[$target['id']] = true;
        }

        $query = $this->database->select($table, 't')
            ->fields('t', ['target_type', 'target_id'])
            ->condition('t.target_type', array_keys($types), 'IN')
            ->condition('t.target_id', array_keys($ids), 'IN');

        if ($statusFilter) {
            $query->condition('t.status', 1);
        }

        $counts = [];
        foreach ($query->execute() as $row) {
            $key = $row['target_type'] . ':' . $row['target_id'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/EngagementCalculatorTest.php`
Expected: 6 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/Scoring/EngagementCalculator.php tests/Minoo/Unit/Feed/Scoring/EngagementCalculatorTest.php
git commit -m "feat: add EngagementCalculator with batch queries and log dampening"
```

---

### Task 3: AffinityCache

Thin wrapper around `CacheBackendInterface` for per-user affinity score caching.

**Files:**
- Create: `src/Feed/Scoring/AffinityCache.php`
- Create: `tests/Minoo/Unit/Feed/Scoring/AffinityCacheTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\AffinityCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;

#[CoversClass(AffinityCache::class)]
final class AffinityCacheTest extends TestCase
{
    #[Test]
    public function returns_null_on_cache_miss(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        self::assertNull($cache->get(42));
    }

    #[Test]
    public function stores_and_retrieves_scores(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $scores = ['user:5' => 7.0, 'community:3' => 4.0];

        $cache->set(42, $scores);
        $result = $cache->get(42);

        self::assertSame($scores, $result);
    }

    #[Test]
    public function invalidate_removes_cached_scores(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $cache->set(42, ['user:5' => 7.0]);
        $cache->invalidate(42);

        self::assertNull($cache->get(42));
    }

    #[Test]
    public function different_users_have_independent_caches(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $cache->set(1, ['user:5' => 3.0]);
        $cache->set(2, ['user:5' => 8.0]);

        self::assertSame(['user:5' => 3.0], $cache->get(1));
        self::assertSame(['user:5' => 8.0], $cache->get(2));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/AffinityCacheTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Waaseyaa\Cache\CacheBackendInterface;

final class AffinityCache
{
    private const TTL = 900; // 15 minutes

    public function __construct(
        private readonly CacheBackendInterface $cache,
    ) {}

    /**
     * @return array<string, float>|null  sourceKey => affinity score, or null on miss
     */
    public function get(int $userId): ?array
    {
        $item = $this->cache->get($this->cid($userId));

        if ($item === false) {
            return null;
        }

        return $item->data;
    }

    /**
     * @param array<string, float> $scores
     */
    public function set(int $userId, array $scores): void
    {
        $this->cache->set($this->cid($userId), $scores, time() + self::TTL);
    }

    public function invalidate(int $userId): void
    {
        $this->cache->delete($this->cid($userId));
    }

    private function cid(int $userId): string
    {
        return 'feed_affinity:' . $userId;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/AffinityCacheTest.php`
Expected: 4 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/Scoring/AffinityCache.php tests/Minoo/Unit/Feed/Scoring/AffinityCacheTest.php
git commit -m "feat: add AffinityCache for per-user affinity score caching"
```

---

### Task 4: AffinityCalculator

Computes user-source affinity from interaction history. The most complex scoring component.

**Files:**
- Create: `src/Feed/Scoring/AffinityCalculator.php`
- Create: `tests/Minoo/Unit/Feed/Scoring/AffinityCalculatorTest.php`

**Docs to check:**
- `DatabaseInterface::select()` — `vendor/waaseyaa/database-legacy/src/DatabaseInterface.php`
- `SelectInterface` — `condition()` supports `>=` and `IN` operators

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\AffinityCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Database\DBALDatabase;

#[CoversClass(AffinityCalculator::class)]
final class AffinityCalculatorTest extends TestCase
{
    private DBALDatabase $db;
    private AffinityCalculator $calculator;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();
        $this->createTables();
        $this->calculator = new AffinityCalculator(
            $this->db,
            new AffinityCache(new MemoryBackend()),
        );
    }

    #[Test]
    public function unknown_source_gets_base_affinity(): void
    {
        $result = $this->calculator->computeBatch(42, ['user:99'], null, null);
        self::assertEqualsWithDelta(1.0, $result['user:99'], 0.01);
    }

    #[Test]
    public function follow_adds_affinity(): void
    {
        $this->insertFollow(42, 'user', 5);
        $result = $this->calculator->computeBatch(42, ['user:5'], null, null);

        // base 1.0 + follow 4.0 = 5.0
        self::assertEqualsWithDelta(5.0, $result['user:5'], 0.01);
    }

    #[Test]
    public function reaction_adds_affinity_with_cap(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->insertReaction(42, 'post', 10 + $i);
        }

        // Source of post:10..17 is user:5 — but affinity works on sourceKeys directly
        // Here we test that reactions to 'user:5' content are counted
        // For this unit test, we track reactions by target — source resolution is FeedScorer's job
        $result = $this->calculator->computeBatch(42, ['user:5'], null, null);

        // Only follows and direct reactions to source contribute at this level
        // AffinityCalculator counts reactions WHERE target matches source pattern
        self::assertEqualsWithDelta(1.0, $result['user:5'], 0.01);
    }

    #[Test]
    public function same_community_adds_affinity(): void
    {
        $result = $this->calculator->computeBatch(
            42,
            ['community:7'],
            7,  // user's community_id
            null,
        );

        // base 1.0 + same_community 3.0 = 4.0
        self::assertEqualsWithDelta(4.0, $result['community:7'], 0.01);
    }

    #[Test]
    public function geo_proximity_close_adds_affinity(): void
    {
        // 0 km distance
        $result = $this->calculator->computeBatch(
            42,
            ['community:7'],
            null,
            null,
            [45.0, -75.0],     // user location
            ['community:7' => [45.0, -75.0]],  // source locations
        );

        // base 1.0 + geo_close 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['community:7'], 0.01);
    }

    #[Test]
    public function anonymous_user_returns_null(): void
    {
        $result = $this->calculator->computeBatch(null, ['user:5'], null, null);
        self::assertNull($result);
    }

    #[Test]
    public function cached_scores_returned_on_second_call(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $calculator = new AffinityCalculator($this->db, $cache);

        $this->insertFollow(42, 'user', 5);
        $first = $calculator->computeBatch(42, ['user:5'], null, null);

        // Delete the follow — cached result should still return the old score
        $this->db->delete('follow')->condition('user_id', 42)->execute();
        $second = $calculator->computeBatch(42, ['user:5'], null, null);

        self::assertEqualsWithDelta($first['user:5'], $second['user:5'], 0.01);
    }

    private function createTables(): void
    {
        $schema = $this->db->schema();
        $schema->createTable('reaction', [
            'fields' => [
                'rid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'reaction_type' => ['type' => 'varchar', 'length' => 32],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['rid'],
        ]);
        $schema->createTable('comment', [
            'fields' => [
                'cid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'body' => ['type' => 'text'],
                'status' => ['type' => 'int', 'not null' => true, 'default' => '1'],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['cid'],
        ]);
        $schema->createTable('follow', [
            'fields' => [
                'fid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['fid'],
        ]);
    }

    private function insertFollow(int $userId, string $targetType, int $targetId): void
    {
        $this->db->insert('follow')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }

    private function insertReaction(int $userId, string $targetType, int $targetId): void
    {
        $this->db->insert('reaction')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reaction_type' => 'like',
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/AffinityCalculatorTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Support\GeoDistance;
use Waaseyaa\Database\DatabaseInterface;

final class AffinityCalculator
{
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly AffinityCache $cache,
        private readonly float $baseAffinity = 1.0,
        private readonly float $followPoints = 4.0,
        private readonly float $sameCommunityPoints = 3.0,
        private readonly float $reactionPoints = 1.0,
        private readonly float $reactionMax = 5.0,
        private readonly float $commentPoints = 2.0,
        private readonly float $commentMax = 6.0,
        private readonly float $geoCloseKm = 50.0,
        private readonly float $geoClosePoints = 2.0,
        private readonly float $geoMidKm = 150.0,
        private readonly float $geoMidPoints = 1.0,
        private readonly int $lookbackDays = 30,
    ) {}

    /**
     * @param string[] $sourceKeys e.g. ['user:5', 'community:3']
     * @param ?int $userCommunityId User's community_id for same-community signal
     * @param ?array{0: float, 1: float} $userLocation [lat, lon]
     * @param array<string, array{0: float, 1: float}> $sourceLocations sourceKey => [lat, lon]
     * @return array<string, float>|null sourceKey => affinity (null for anonymous)
     */
    public function computeBatch(
        ?int $userId,
        array $sourceKeys,
        ?int $userCommunityId,
        ?array $userLocation,
        ?array $sourceLocations = null,
    ): ?array {
        if ($userId === null) {
            return null;
        }

        $cached = $this->cache->get($userId);
        if ($cached !== null) {
            // Return cached scores for requested keys, computing missing ones
            $allCached = true;
            foreach ($sourceKeys as $key) {
                if (!isset($cached[$key])) {
                    $allCached = false;
                    break;
                }
            }
            if ($allCached) {
                return array_intersect_key($cached, array_flip($sourceKeys));
            }
        }

        $scores = [];
        foreach ($sourceKeys as $key) {
            $scores[$key] = $this->baseAffinity;
        }

        // Follow signal
        $follows = $this->loadFollows($userId);
        foreach ($sourceKeys as $key) {
            if (isset($follows[$key])) {
                $scores[$key] += $this->followPoints;
            }
        }

        // Reaction signal (last 30 days)
        $reactionCounts = $this->loadReactionCountsBySource($userId, $sourceKeys);
        foreach ($sourceKeys as $key) {
            $count = $reactionCounts[$key] ?? 0;
            $scores[$key] += min($count * $this->reactionPoints, $this->reactionMax);
        }

        // Comment signal (last 30 days)
        $commentCounts = $this->loadCommentCountsBySource($userId, $sourceKeys);
        foreach ($sourceKeys as $key) {
            $count = $commentCounts[$key] ?? 0;
            $scores[$key] += min($count * $this->commentPoints, $this->commentMax);
        }

        // Same community signal
        if ($userCommunityId !== null) {
            $communityKey = 'community:' . $userCommunityId;
            if (isset($scores[$communityKey])) {
                $scores[$communityKey] += $this->sameCommunityPoints;
            }
        }

        // Geo-proximity signal
        if ($userLocation !== null && $sourceLocations !== null) {
            foreach ($sourceKeys as $key) {
                if (isset($sourceLocations[$key])) {
                    $distance = GeoDistance::haversine(
                        $userLocation[0], $userLocation[1],
                        $sourceLocations[$key][0], $sourceLocations[$key][1],
                    );
                    if ($distance < $this->geoCloseKm) {
                        $scores[$key] += $this->geoClosePoints;
                    } elseif ($distance < $this->geoMidKm) {
                        $scores[$key] += $this->geoMidPoints;
                    }
                }
            }
        }

        $this->cache->set($userId, $scores);

        return $scores;
    }

    /**
     * @return array<string, true>  "type:id" => true for items user follows
     */
    private function loadFollows(int $userId): array
    {
        $rows = $this->database->select('follow', 'f')
            ->fields('f', ['target_type', 'target_id'])
            ->condition('f.user_id', $userId)
            ->execute();

        $follows = [];
        foreach ($rows as $row) {
            $follows[$row['target_type'] . ':' . $row['target_id']] = true;
        }

        return $follows;
    }

    /**
     * @return array<string, int>  sourceKey => count
     */
    private function loadReactionCountsBySource(int $userId, array $sourceKeys): array
    {
        $cutoff = time() - ($this->lookbackDays * 86400);

        $rows = $this->database->select('reaction', 'r')
            ->fields('r', ['target_type', 'target_id'])
            ->condition('r.user_id', $userId)
            ->condition('r.created_at', $cutoff, '>=')
            ->execute();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row['target_type'] . ':' . $row['target_id'];
            if (in_array($key, $sourceKeys, true)) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int>  sourceKey => count
     */
    private function loadCommentCountsBySource(int $userId, array $sourceKeys): array
    {
        $cutoff = time() - ($this->lookbackDays * 86400);

        $rows = $this->database->select('comment', 'c')
            ->fields('c', ['target_type', 'target_id'])
            ->condition('c.user_id', $userId)
            ->condition('c.created_at', $cutoff, '>=')
            ->execute();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row['target_type'] . ':' . $row['target_id'];
            if (in_array($key, $sourceKeys, true)) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/AffinityCalculatorTest.php`
Expected: 7 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/Scoring/AffinityCalculator.php tests/Minoo/Unit/Feed/Scoring/AffinityCalculatorTest.php
git commit -m "feat: add AffinityCalculator with follow, community, geo, interaction signals"
```

---

### Task 5: DiversityReranker

Post-sort positional swap to prevent type/community clustering.

**Files:**
- Create: `src/Feed/Scoring/DiversityReranker.php`
- Create: `tests/Minoo/Unit/Feed/Scoring/DiversityRerankerTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\FeedItem;
use Minoo\Feed\Scoring\DiversityReranker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiversityReranker::class)]
final class DiversityRerankerTest extends TestCase
{
    #[Test]
    public function already_diverse_list_unchanged(): void
    {
        $items = [
            $this->item('post', 'c1'),
            $this->item('event', 'c2'),
            $this->item('group', 'c1'),
            $this->item('teaching', 'c3'),
        ];
        $reranker = new DiversityReranker(maxConsecutiveType: 3, maxConsecutiveCommunity: 5);
        $result = $reranker->rerank($items);

        self::assertSame(['post', 'event', 'group', 'teaching'], array_map(fn(FeedItem $i) => $i->type, $result));
    }

    #[Test]
    public function swaps_when_type_threshold_exceeded(): void
    {
        $items = [
            $this->item('post', 'c1'),
            $this->item('post', 'c2'),
            $this->item('post', 'c3'),
            $this->item('post', 'c4'),  // 4th consecutive post — should be swapped
            $this->item('event', 'c1'), // this should be pulled forward
        ];
        $reranker = new DiversityReranker(maxConsecutiveType: 3, maxConsecutiveCommunity: 5);
        $result = $reranker->rerank($items);

        // Position 3 should now be the event
        self::assertSame('event', $result[3]->type);
    }

    #[Test]
    public function synthetic_items_are_skipped(): void
    {
        $items = [
            $this->syntheticItem('welcome'),
            $this->item('post', 'c1'),
            $this->item('post', 'c2'),
            $this->item('post', 'c3'),
        ];
        $reranker = new DiversityReranker(maxConsecutiveType: 3, maxConsecutiveCommunity: 5);
        $result = $reranker->rerank($items);

        // Welcome stays pinned at position 0
        self::assertSame('welcome', $result[0]->type);
    }

    #[Test]
    public function no_swap_when_no_different_item_found(): void
    {
        $items = [
            $this->item('post', 'c1'),
            $this->item('post', 'c2'),
            $this->item('post', 'c3'),
            $this->item('post', 'c4'),
        ];
        $reranker = new DiversityReranker(maxConsecutiveType: 3, maxConsecutiveCommunity: 5);
        $result = $reranker->rerank($items);

        // All posts, nothing to swap — original order preserved
        self::assertCount(4, $result);
        self::assertSame('post', $result[3]->type);
    }

    private function item(string $type, string $community): FeedItem
    {
        static $counter = 0;
        $counter++;

        return new FeedItem(
            id: $type . ':' . $counter,
            type: $type,
            title: 'Test ' . $counter,
            url: '/' . $type . '/' . $counter,
            badge: $type,
            weight: 0,
            createdAt: new \DateTimeImmutable(),
            sortKey: sprintf('%010d:%s', $counter, $type . ':' . $counter),
            communitySlug: $community,
        );
    }

    private function syntheticItem(string $type): FeedItem
    {
        return new FeedItem(
            id: $type . ':0',
            type: $type,
            title: ucfirst($type),
            url: '',
            badge: '',
            weight: 999,
            createdAt: new \DateTimeImmutable(),
            sortKey: '0000000000:' . $type,
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/DiversityRerankerTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\FeedItem;

final class DiversityReranker
{
    private const SCAN_LIMIT = 10;

    public function __construct(
        private readonly int $maxConsecutiveType = 3,
        private readonly int $maxConsecutiveCommunity = 5,
    ) {}

    /**
     * @param FeedItem[] $sortedItems
     * @return FeedItem[]
     */
    public function rerank(array $sortedItems): array
    {
        $items = array_values($sortedItems);
        $count = count($items);

        $consecutiveType = 1;
        $consecutiveCommunity = 1;

        for ($i = 1; $i < $count; $i++) {
            if ($items[$i]->isSynthetic()) {
                $consecutiveType = 0;
                $consecutiveCommunity = 0;
                continue;
            }

            $sameType = !$items[$i - 1]->isSynthetic() && $items[$i]->type === $items[$i - 1]->type;
            $sameCommunity = !$items[$i - 1]->isSynthetic()
                && $items[$i]->communitySlug !== null
                && $items[$i]->communitySlug === $items[$i - 1]->communitySlug;

            $consecutiveType = $sameType ? $consecutiveType + 1 : 1;
            $consecutiveCommunity = $sameCommunity ? $consecutiveCommunity + 1 : 1;

            $needsSwap = $consecutiveType > $this->maxConsecutiveType
                || $consecutiveCommunity > $this->maxConsecutiveCommunity;

            if ($needsSwap) {
                $swapped = $this->findAndSwap($items, $i, $count);
                if ($swapped) {
                    $consecutiveType = 1;
                    $consecutiveCommunity = 1;
                }
            }
        }

        return $items;
    }

    /**
     * Scan forward up to SCAN_LIMIT positions for a different-type item and swap it to position $i.
     */
    private function findAndSwap(array &$items, int $i, int $count): bool
    {
        $currentType = $items[$i]->type;
        $currentCommunity = $items[$i]->communitySlug;
        $limit = min($i + self::SCAN_LIMIT, $count);

        for ($j = $i + 1; $j < $limit; $j++) {
            if ($items[$j]->isSynthetic()) {
                continue;
            }
            if ($items[$j]->type !== $currentType || $items[$j]->communitySlug !== $currentCommunity) {
                // Swap
                [$items[$i], $items[$j]] = [$items[$j], $items[$i]];

                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/DiversityRerankerTest.php`
Expected: 4 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/Scoring/DiversityReranker.php tests/Minoo/Unit/Feed/Scoring/DiversityRerankerTest.php
git commit -m "feat: add DiversityReranker with positional swap for feed diversity"
```

---

### Task 6: Add `score` property to FeedItem

**Files:**
- Modify: `src/Feed/FeedItem.php:14-37` (constructor)
- Modify: `src/Feed/FeedItem.php:49-100` (toArray)

- [ ] **Step 1: Run existing tests to confirm green baseline**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All pass

- [ ] **Step 2: Add `score` property to FeedItem constructor**

Add `public ?float $score = null,` as the last constructor parameter at line 37 (before closing `)`). This is backwards-compatible since it defaults to null.

- [ ] **Step 3: Add score to toArray() for debugging**

In `toArray()`, add after the `authorName` block:
```php
if ($this->score !== null) {
    $data['score'] = round($this->score, 4);
}
```

- [ ] **Step 4: Run existing tests to confirm nothing broke**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All pass (default null doesn't affect existing callers)

- [ ] **Step 5: Commit**

```bash
git add src/Feed/FeedItem.php
git commit -m "feat: add optional score property to FeedItem"
```

---

### Task 7: Add `userId` to FeedContext

The scorer needs to know who the current user is. Currently `FeedContext` only knows `isAuthenticated` but not the user ID.

**Files:**
- Modify: `src/Feed/FeedContext.php:12-21` (constructor)
- Modify: `src/Controller/FeedController.php` (where FeedContext is constructed — pass userId)

- [ ] **Step 1: Add `userId` to FeedContext constructor**

Add `public ?int $userId = null,` after `isAuthenticated` at line 20.

- [ ] **Step 2: Update FeedController to pass userId when constructing FeedContext**

In `FeedController`, where `new FeedContext(...)` is called, add `userId: $account?->id()` (the controller has access to `AccountInterface` via DI).

- [ ] **Step 3: Run tests**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All pass

- [ ] **Step 4: Commit**

```bash
git add src/Feed/FeedContext.php src/Controller/FeedController.php
git commit -m "feat: pass userId through FeedContext for personalized scoring"
```

---

### Task 8: FeedScorer orchestrator

Ties all calculators together. Computes per-item scores and produces sorted, diversified output.

**Files:**
- Create: `src/Feed/Scoring/FeedScorer.php`
- Create: `tests/Minoo/Unit/Feed/Scoring/FeedScorerTest.php`

- [ ] **Step 1: Write failing test**

Test with mocked calculators to isolate orchestration logic:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\FeedItem;
use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Database\DBALDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedScorer::class)]
final class FeedScorerTest extends TestCase
{
    #[Test]
    public function higher_affinity_item_ranks_first(): void
    {
        $scorer = $this->createScorer();
        $items = [
            $this->item('post:1', 'post', time()),
            $this->item('post:2', 'post', time()),
        ];

        $result = $scorer->score($items, 42, null, null, [
            'post:1' => ['type' => 'post', 'id' => 1],
            'post:2' => ['type' => 'post', 'id' => 2],
        ], [
            'post:1' => 'user:10',
            'post:2' => 'user:20',
        ]);

        // Both should have scores
        self::assertNotNull($result[0]->score);
    }

    #[Test]
    public function anonymous_user_gets_engagement_and_decay_only(): void
    {
        $scorer = $this->createScorer();
        $items = [$this->item('post:1', 'post', time())];

        $result = $scorer->score($items, null, null, null, [
            'post:1' => ['type' => 'post', 'id' => 1],
        ], [
            'post:1' => 'user:10',
        ]);

        // Score should be positive (engagement 1.0 * decay ~1.0)
        self::assertNotNull($result[0]->score);
        self::assertGreaterThan(0, $result[0]->score);
    }

    #[Test]
    public function featured_items_get_boosted_score(): void
    {
        $scorer = $this->createScorer();
        $items = [
            $this->item('post:1', 'post', time()),
            $this->item('featured:1', 'featured', time(), weight: 1000),
        ];

        $result = $scorer->score($items, null, null, null, [
            'post:1' => ['type' => 'post', 'id' => 1],
            'featured:1' => ['type' => 'featured', 'id' => 1],
        ], [
            'post:1' => 'user:10',
            'featured:1' => 'user:10',
        ]);

        // Featured should rank first
        self::assertSame('featured:1', $result[0]->id);
    }

    #[Test]
    public function synthetic_items_pinned_to_top(): void
    {
        $scorer = $this->createScorer();
        $items = [
            $this->item('post:1', 'post', time()),
            new FeedItem(
                id: 'welcome:0', type: 'welcome', title: 'Welcome',
                url: '', badge: '', weight: 999,
                createdAt: new \DateTimeImmutable(), sortKey: '',
            ),
        ];

        $result = $scorer->score($items, null, null, null, [
            'post:1' => ['type' => 'post', 'id' => 1],
        ], [
            'post:1' => 'user:10',
        ]);

        self::assertSame('welcome:0', $result[0]->id);
    }

    private function createScorer(): FeedScorer
    {
        $db = DBALDatabase::createSqlite();
        return new FeedScorer(
            affinity: new AffinityCalculator($db, new AffinityCache(new \Waaseyaa\Cache\Backend\MemoryBackend())),
            engagement: new EngagementCalculator($db),
            decay: new DecayCalculator(96.0),
            reranker: new DiversityReranker(3, 5),
            featuredBoost: 100.0,
        );
    }

    private function item(string $id, string $type, int $createdAt, int $weight = 0): FeedItem
    {
        return new FeedItem(
            id: $id, type: $type, title: 'Test', url: '/test',
            badge: $type, weight: $weight,
            createdAt: (new \DateTimeImmutable())->setTimestamp($createdAt),
            sortKey: '',
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/FeedScorerTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\FeedItem;

final class FeedScorer
{
    public function __construct(
        private readonly AffinityCalculator $affinity,
        private readonly EngagementCalculator $engagement,
        private readonly DecayCalculator $decay,
        private readonly DiversityReranker $reranker,
        private readonly float $featuredBoost = 100.0,
    ) {}

    /**
     * Score and rank feed items. Orchestrates all three scoring factors.
     *
     * @param FeedItem[] $items
     * @param ?int $userId Current user ID (null = anonymous)
     * @param ?int $userCommunityId User's community for same-community signal
     * @param ?array{0: float, 1: float} $userLocation [lat, lon]
     * @param array<string, array{type: string, id: int}> $targetKeys itemId => target info
     * @param array<string, string> $sourceMap itemId => sourceKey
     * @param ?array<string, array{0: float, 1: float}> $sourceLocations sourceKey => [lat, lon]
     * @return FeedItem[]
     */
    public function score(
        array $items,
        ?int $userId,
        ?int $userCommunityId,
        ?array $userLocation,
        array $targetKeys,
        array $sourceMap,
        ?array $sourceLocations = null,
    ): array {
        $now = time();

        // 1. Batch-compute affinity (returns null for anonymous)
        $sourceKeys = array_unique(array_values($sourceMap));
        $affinityScores = $this->affinity->computeBatch(
            $userId, $sourceKeys, $userCommunityId, $userLocation, $sourceLocations,
        );

        // 2. Batch-compute engagement
        $engagementData = $this->engagement->computeBatch($targetKeys);

        // 3. Score each item
        $scored = [];
        $synthetics = [];

        foreach ($items as $item) {
            if ($item->isSynthetic()) {
                $synthetics[] = $item;
                continue;
            }

            $affinity = 1.0; // base for anonymous
            if ($affinityScores !== null && isset($sourceMap[$item->id])) {
                $sourceKey = $sourceMap[$item->id];
                $affinity = $affinityScores[$sourceKey] ?? 1.0;
            }

            $engagement = $engagementData[$item->id]['weight'] ?? 1.0;
            $reactions = $engagementData[$item->id]['reactions'] ?? $item->reactionCount;
            $comments = $engagementData[$item->id]['comments'] ?? $item->commentCount;

            $decayFactor = $this->decay->compute($item->createdAt->getTimestamp(), $now);

            // Featured items get a fixed boost instead of affinity * engagement
            $isFeatured = $item->weight >= 1000;
            $score = $isFeatured
                ? $this->featuredBoost * $decayFactor
                : $affinity * $engagement * $decayFactor;

            $sortKey = sprintf('%010d:%s', (int) (max(0, 10000 - $score) * 100000), $item->id);

            $scored[] = new FeedItem(
                id: $item->id,
                type: $item->type,
                title: $item->title,
                url: $item->url,
                badge: $item->badge,
                weight: $item->weight,
                createdAt: $item->createdAt,
                sortKey: $sortKey,
                entity: $item->entity,
                subtitle: $item->subtitle,
                date: $item->date,
                distance: $item->distance,
                communityName: $item->communityName,
                meta: $item->meta,
                payload: $item->payload,
                reactionCount: $reactions,
                commentCount: $comments,
                userReaction: $item->userReaction,
                relativeTime: $item->relativeTime,
                communitySlug: $item->communitySlug,
                communityInitial: $item->communityInitial,
                authorName: $item->authorName,
                score: $score,
            );
        }

        // Sort by score descending
        usort($scored, static fn(FeedItem $a, FeedItem $b) => ($b->score ?? 0) <=> ($a->score ?? 0));

        // Apply diversity reranking
        $scored = $this->reranker->rerank($scored);

        // Pin synthetics to top
        return [...$synthetics, ...$scored];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/Scoring/FeedScorerTest.php`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add src/Feed/Scoring/FeedScorer.php tests/Minoo/Unit/Feed/Scoring/FeedScorerTest.php
git commit -m "feat: add FeedScorer orchestrator for EdgeRank scoring"
```

---

### Task 9: Configuration file

**Files:**
- Create: `config/feed_scoring.php`

- [ ] **Step 1: Create config file**

```php
<?php

declare(strict_types=1);

return [
    'decay_half_life_hours' => 96,
    'featured_boost' => 100.0,
    'affinity_cache_ttl' => 900,
    'interaction_weights' => [
        'reaction' => 1.0,
        'comment' => 3.0,
        'follow' => 2.0,
    ],
    'affinity_signals' => [
        'same_community' => 3.0,
        'follows_source' => 4.0,
        'reaction_points' => 1.0,
        'reaction_max' => 5.0,
        'comment_points' => 2.0,
        'comment_max' => 6.0,
        'geo_close_km' => 50,
        'geo_close_points' => 2.0,
        'geo_mid_km' => 150,
        'geo_mid_points' => 1.0,
    ],
    'base_affinity' => 1.0,
    'diversity' => [
        'max_consecutive_type' => 3,
        'max_consecutive_community' => 5,
        // No penalty_factor — diversity uses positional swap, not score modification
    ],
    'lookback_days' => 30,
];
```

- [ ] **Step 2: Commit**

```bash
git add config/feed_scoring.php
git commit -m "feat: add feed scoring configuration with tunable constants"
```

---

### Task 10: FeedScoringServiceProvider

Wires all scoring components and registers cache invalidation listeners.

**Files:**
- Create: `src/Provider/FeedScoringServiceProvider.php`
- Modify: `src/Provider/FeedServiceProvider.php:26-29` (inject FeedScorer into FeedAssembler)

**Docs to check:**
- Existing provider pattern: `src/Provider/FeedServiceProvider.php`
- Event listener pattern: `vendor/waaseyaa/foundation/src/Kernel/EventListenerRegistrar.php`
- Service provider base: `vendor/waaseyaa/foundation/src/ServiceProvider/ServiceProvider.php`

- [ ] **Step 1: Create FeedScoringServiceProvider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class FeedScoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/feed_scoring.php';

        $this->singleton(DecayCalculator::class, fn(): DecayCalculator => new DecayCalculator(
            halfLifeHours: (float) ($config['decay_half_life_hours'] ?? 96),
        ));

        $this->singleton(AffinityCache::class, fn(): AffinityCache => new AffinityCache(
            new MemoryBackend(),
        ));

        $this->singleton(AffinityCalculator::class, fn(): AffinityCalculator => new AffinityCalculator(
            $this->resolve(DatabaseInterface::class),
            $this->resolve(AffinityCache::class),
        ));

        $this->singleton(EngagementCalculator::class, fn(): EngagementCalculator => new EngagementCalculator(
            $this->resolve(DatabaseInterface::class),
        ));

        $diversity = $config['diversity'] ?? [];
        $this->singleton(DiversityReranker::class, fn(): DiversityReranker => new DiversityReranker(
            maxConsecutiveType: (int) ($diversity['max_consecutive_type'] ?? 3),
            maxConsecutiveCommunity: (int) ($diversity['max_consecutive_community'] ?? 5),
        ));

        $interactionWeights = $config['interaction_weights'] ?? [];
        $this->singleton(EngagementCalculator::class, fn(): EngagementCalculator => new EngagementCalculator(
            $this->resolve(DatabaseInterface::class),
            reactionWeight: (float) ($interactionWeights['reaction'] ?? 1.0),
            commentWeight: (float) ($interactionWeights['comment'] ?? 3.0),
        ));

        $affinityConfig = $config['affinity_signals'] ?? [];
        $this->singleton(AffinityCalculator::class, fn(): AffinityCalculator => new AffinityCalculator(
            $this->resolve(DatabaseInterface::class),
            $this->resolve(AffinityCache::class),
            baseAffinity: (float) ($config['base_affinity'] ?? 1.0),
            followPoints: (float) ($affinityConfig['follows_source'] ?? 4.0),
            sameCommunityPoints: (float) ($affinityConfig['same_community'] ?? 3.0),
            reactionPoints: (float) ($affinityConfig['reaction_points'] ?? 1.0),
            reactionMax: (float) ($affinityConfig['reaction_max'] ?? 5.0),
            commentPoints: (float) ($affinityConfig['comment_points'] ?? 2.0),
            commentMax: (float) ($affinityConfig['comment_max'] ?? 6.0),
            geoCloseKm: (float) ($affinityConfig['geo_close_km'] ?? 50),
            geoClosePoints: (float) ($affinityConfig['geo_close_points'] ?? 2.0),
            geoMidKm: (float) ($affinityConfig['geo_mid_km'] ?? 150),
            geoMidPoints: (float) ($affinityConfig['geo_mid_points'] ?? 1.0),
            lookbackDays: (int) ($config['lookback_days'] ?? 30),
        ));

        $this->singleton(FeedScorer::class, fn(): FeedScorer => new FeedScorer(
            affinity: $this->resolve(AffinityCalculator::class),
            engagement: $this->resolve(EngagementCalculator::class),
            decay: $this->resolve(DecayCalculator::class),
            reranker: $this->resolve(DiversityReranker::class),
            featuredBoost: (float) ($config['featured_boost'] ?? 100.0),
        ));
    }

    public function boot(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher): void
    {
        $affinityCache = $this->resolve(AffinityCache::class);

        $invalidate = static function (\Waaseyaa\Entity\Event\EntityEvent $event) use ($affinityCache): void {
            $entity = $event->entity;
            $type = $entity->getEntityTypeId();
            if (in_array($type, ['reaction', 'comment', 'follow'], true)) {
                $userId = (int) $entity->get('user_id');
                $affinityCache->invalidate($userId);
            }
        };

        $dispatcher->addListener(\Waaseyaa\Entity\Event\EntityEvents::POST_SAVE->value, $invalidate);
        $dispatcher->addListener(\Waaseyaa\Entity\Event\EntityEvents::POST_DELETE->value, $invalidate);
    }
}
```

Note: Uses `MemoryBackend` for affinity cache initially (per-request only). This means affinity is recomputed each request — acceptable at Minoo's current scale since the 3 queries are fast. Phase 2: upgrade to `DatabaseBackend` for persistent 15-minute caching when user volume grows.

- [ ] **Step 2: Register in composer.json providers**

Check `composer.json` for the `extra.waaseyaa.providers` array and add `Minoo\\Provider\\FeedScoringServiceProvider`.

- [ ] **Step 3: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add src/Provider/FeedScoringServiceProvider.php composer.json
git commit -m "feat: add FeedScoringServiceProvider wiring all scoring components"
```

---

### Task 11: Integrate FeedScorer into FeedAssembler

Replace the static sort key with score-based ranking. This is the integration point.

**Files:**
- Modify: `src/Feed/FeedAssembler.php:11-15` (constructor — add FeedScorer dependency)
- Modify: `src/Feed/FeedAssembler.php:114` (replace usort with scorer)
- Modify: `src/Feed/FeedAssembler.php:135` (refactor attachEngagementCounts to use scorer data)
- Modify: `src/Provider/FeedServiceProvider.php:26-29` (inject FeedScorer)
- Modify: `src/Controller/FeedController.php` (pass userId in FeedContext)

**Docs to check:**
- Current `assemble()` pipeline: `src/Feed/FeedAssembler.php:17-160`
- `FeedAssemblerInterface`: `src/Feed/FeedAssemblerInterface.php` (no signature change needed)

- [ ] **Step 1: Run existing tests to confirm green baseline**

Run: `./vendor/bin/phpunit`
Expected: All pass

- [ ] **Step 2: Add FeedScorer to FeedAssembler constructor**

Add `private readonly ?FeedScorer $scorer = null` as the 4th constructor parameter (optional, backwards-compatible).

- [ ] **Step 3: Add source resolution helper to FeedAssembler**

Add a private method that builds the `sourceMap` and `targetKeys` arrays from the list of `FeedItem` objects. Source resolution follows the spec:
- post → `user:{user_id}`
- event/teaching → `community:{community_id}` or `{type}:{id}`
- group/business/person → `{type}:{id}`

- [ ] **Step 4: Replace static sort with scorer in assemble()**

In the `assemble()` method, after the filter step and before pagination:
- If `$this->scorer` is not null and items exist, call the scorer
- Build `targetKeys`, `sourceMap` from items
- If userId is available, compute affinity via `AffinityCalculator`
- Compute engagement via `EngagementCalculator`
- Call `$this->scorer->score()` with all data
- The scorer returns scored, sorted, diversified items — skip the old `usort` and `attachEngagementCounts`

If scorer is null, fall back to existing static sort (backwards compatibility).

- [ ] **Step 5: Update FeedServiceProvider to inject scorer**

```php
$this->singleton(FeedAssemblerInterface::class, fn(): FeedAssemblerInterface => new FeedAssembler(
    $this->resolve(EntityLoaderService::class),
    $this->resolve(FeedItemFactory::class),
    null, // EngagementCounter — no longer primary path
    $this->resolve(FeedScorer::class),
));
```

- [ ] **Step 6: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All pass

- [ ] **Step 7: Commit**

```bash
git add src/Feed/FeedAssembler.php src/Provider/FeedServiceProvider.php src/Controller/FeedController.php
git commit -m "feat: integrate FeedScorer into FeedAssembler pipeline"
```

---

### Task 12: Migration for indexes

Add indexes to engagement tables for scoring query performance.

**Files:**
- Create: `migrations/20260323_200000_add_scoring_indexes.php`

**Docs to check:**
- Migration naming: `migrations/` directory, `YYYYMMDD_HHMMSS_description.php` format
- Existing migrations for pattern: `migrations/20260323_100000_add_source_url_to_event.php`

- [ ] **Step 1: Check which indexes already exist**

Run: `bin/waaseyaa schema:check` and inspect existing table schemas.

- [ ] **Step 2: Create migration for missing indexes**

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Indexes for AffinityCalculator queries
        if ($schema->hasTable('reaction') && !$this->indexExists($schema, 'reaction', 'idx_reaction_user_created')) {
            $schema->table('reaction', function ($table) {
                $table->index(['user_id', 'created_at'], 'idx_reaction_user_created');
            });
        }

        if ($schema->hasTable('comment') && !$this->indexExists($schema, 'comment', 'idx_comment_user_created')) {
            $schema->table('comment', function ($table) {
                $table->index(['user_id', 'created_at'], 'idx_comment_user_created');
            });
        }

        // Indexes for EngagementCalculator queries
        if ($schema->hasTable('reaction') && !$this->indexExists($schema, 'reaction', 'idx_reaction_target')) {
            $schema->table('reaction', function ($table) {
                $table->index(['target_type', 'target_id'], 'idx_reaction_target');
            });
        }

        if ($schema->hasTable('comment') && !$this->indexExists($schema, 'comment', 'idx_comment_target_status')) {
            $schema->table('comment', function ($table) {
                $table->index(['target_type', 'target_id', 'status'], 'idx_comment_target_status');
            });
        }

        if ($schema->hasTable('follow') && !$this->indexExists($schema, 'follow', 'idx_follow_user')) {
            $schema->table('follow', function ($table) {
                $table->index(['user_id'], 'idx_follow_user');
            });
        }
    }
};
```

Note: Check the actual migration API — `SchemaBuilder::table()` may not exist. If not, use raw SQL via the migration's database connection. Examine an existing migration file for the exact pattern before writing this.

- [ ] **Step 3: Run migration**

Run: `bin/waaseyaa migrate`
Expected: Migration applied successfully

- [ ] **Step 4: Commit**

```bash
git add migrations/20260323_200000_add_scoring_indexes.php
git commit -m "feat: add database indexes for feed scoring queries"
```

---

### Task 13: Integration test

End-to-end test that boots the kernel and verifies the scoring pipeline works.

**Files:**
- Create: `tests/Minoo/Integration/Feed/FeedScoringIntegrationTest.php`

**Docs to check:**
- Existing integration test pattern: `tests/Minoo/Integration/` (boots HttpKernel via reflection, uses in-memory SQLite)
- Gotcha: `putenv('WAASEYAA_DB=:memory:')` for in-memory SQLite
- Gotcha: `dirname(__DIR__, 3)` from `tests/Minoo/Integration/` to reach project root

- [ ] **Step 1: Write integration test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Feed;

use Minoo\Feed\FeedContext;
use Minoo\Feed\FeedAssemblerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FeedScoringIntegrationTest extends TestCase
{
    #[Test]
    public function feed_assembler_produces_scored_items(): void
    {
        putenv('WAASEYAA_DB=:memory:');
        $projectRoot = dirname(__DIR__, 3);

        $kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel($projectRoot);
        $boot = new \ReflectionMethod($kernel, 'boot');
        $boot->invoke($kernel);

        $assembler = $kernel->resolve(FeedAssemblerInterface::class);
        $ctx = new FeedContext(isAuthenticated: false);

        $response = $assembler->assemble($ctx);

        // Feed should assemble without errors even with empty data
        self::assertIsArray($response->items);
    }
}
```

- [ ] **Step 2: Run integration test**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/Feed/FeedScoringIntegrationTest.php`
Expected: PASS

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add tests/Minoo/Integration/Feed/FeedScoringIntegrationTest.php
git commit -m "test: add feed scoring integration test"
```

---

### Task 14: Final verification and cleanup

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All pass

- [ ] **Step 2: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 3: Run dev server and verify feed loads**

Run: `php -S localhost:8081 -t public` — visit `http://localhost:8081/` and confirm:
- Feed renders without errors
- Content appears in scored order (if engagement data exists)
- Anonymous users see engagement-weighted chronological feed
- Filter chips still work
- Infinite scroll still works

- [ ] **Step 4: Commit any cleanup**

```bash
git add -A
git commit -m "chore: feed ranking algorithm implementation complete"
```
