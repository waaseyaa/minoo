# Feed Ranking Algorithm Design

**Date:** 2026-03-23
**Status:** Draft
**Issue:** TBD

## Problem

The Minoo homepage feed uses a static sort key (`weight:distance:type_slot:timestamp:id`) with hardcoded weights. Featured items float to top, the first two posts get a small boost, and everything else is reverse-chronological with geo-distance as a secondary factor. There is no feedback loop — user engagement (reactions, comments, follows) does not influence what content surfaces next.

## Approach: EdgeRank Classic

Adapt Facebook's original 2010-era EdgeRank formula:

```
Score = Affinity x Weight x Decay
```

Each piece of content in the feed receives a per-user score. Content is sorted by score descending. The three factors:

1. **Affinity** — How connected is the viewing user to the content's author/source
2. **Weight** — How much engagement has this content received, weighted by interaction cost
3. **Decay** — How old is the content (exponential time decay with ~4-day half-life)

### Why EdgeRank Classic

- Proven foundation used by every major social platform as their starting point
- Simple enough to compute at read time without background jobs or ML
- All three factors can be computed from data already in Minoo's database
- Weight constants become tuning knobs for editorial bias later
- Fits SQLite and Waaseyaa's existing query/cache infrastructure

## Design

### 1. Scoring Formula

```
score(user, item) = affinity(user, item.source) x engagement_weight(item) x decay(item.created_at)
```

Where:
- `item.source` is the content author (for posts) or owning entity (for events, groups, teachings)
- Score is a float, higher = more relevant
- Items with score 0 still appear (chronological fallback), but sort below scored items

### 2. Affinity Score

Affinity measures how connected the viewing user is to a content source. It is an implicit signal computed from interaction history.

**Affinity signals (additive):**

| Signal | Points | Source |
|--------|--------|--------|
| Same community | 3.0 | User's `community_id` matches content's `community_id` |
| Follows the source | 4.0 | `follow` entity where `user_id` = viewer, `target_type`/`target_id` = source |
| Reacted to source's content (last 30 days) | 1.0 per reaction (max 5.0) | `reaction` entities by viewer targeting source's content |
| Commented on source's content (last 30 days) | 2.0 per comment (max 6.0) | `comment` entities by viewer targeting source's content |
| Geo-proximity (< 50km) | 2.0 | Haversine distance between user location and content's community |
| Geo-proximity (< 150km) | 1.0 | Haversine distance (wider radius, lower signal) |

**Maximum possible affinity:** 20.0 (same community + follows + 5 reactions + 3 comments + geo-close)

**Base affinity:** 1.0 (all content gets a minimum affinity so new/unfollowed content still appears)

**Anonymous users:** Affinity = 1.0 for all content (no interaction history). Feed falls back to engagement weight + decay only.

**"Source" resolution by content type:**

Each content item resolves to one or more source keys for affinity lookup. Resolution order is deterministic — use the first available.

| Content Type | Source Key | Resolution |
|---|---|---|
| `post` | `user:{user_id}` | Always has `user_id` (required field) |
| `event` | `community:{community_id}` | Community association. Falls back to `event:{id}` if no community. |
| `group` | `group:{id}` | The group entity itself |
| `teaching` | `community:{community_id}` | Community association. Falls back to `teaching:{id}` if no community. |
| `business` | `business:{id}` | The business entity itself |
| `person` | `person:{id}` | The person entity itself |
| `featured_item` | (delegates) | Resolves via the underlying entity's type using the rules above |

### 3. Engagement Weight

Engagement weight measures how much interaction a piece of content has received, regardless of who from. This surfaces "hot" content.

**Interaction weights (industry standard effort-based):**

| Interaction | Weight |
|---|---|
| Reaction (any type) | 1.0 |
| Comment | 3.0 |
| Follow (on the content's source) | 2.0 |

**Formula:**

```
engagement_weight(item) = 1.0 + log2(1 + sum_of_weighted_interactions)
```

The `log2` dampens runaway popularity — an item with 100 reactions doesn't score 100x higher than one with 10. The `+1.0` base ensures content with zero engagement still has weight.

**Examples:**
- 0 interactions: `1.0 + log2(1 + 0) = 1.0`
- 3 reactions + 1 comment: `1.0 + log2(1 + 6) = 1.0 + 2.81 = 3.81`
- 10 reactions + 5 comments + 2 follows: `1.0 + log2(1 + 29) = 1.0 + 4.91 = 5.91`

### 4. Time Decay

Exponential decay with a configurable half-life.

**Formula:**

```
decay(created_at) = 0.5 ^ (age_in_hours / half_life_hours)
```

**Default half-life:** 96 hours (4 days)

**Examples (at 96h half-life):**
- 1 hour old: 0.993
- 12 hours old: 0.917
- 1 day old: 0.841
- 2 days old: 0.707
- 4 days old: 0.500
- 7 days old: 0.297
- 14 days old: 0.088

Content older than ~14 days scores very low but never hits zero — it can still appear if affinity and engagement are strong enough.

### 5. Special Items

Certain feed items bypass or augment the scoring:

| Item Type | Behavior |
|---|---|
| `featured_item` | Score = `featured_boost` (default 100.0) x decay. Always floats above organic content while active. |
| `welcome` card | Synthetic, unscored. Pinned to position 0 on first visit. |
| `communities` card | Synthetic, unscored. Pinned to position 1 when shown. |

### 6. Content Diversity

To prevent the feed from being dominated by one type or one community, apply a **positional swap** after scoring:

- Walk the score-sorted list from top to bottom
- Track consecutive same-type count and consecutive same-community count
- When 3+ consecutive items of the same type: find the next different-type item in the list and swap it forward into the current position
- When 5+ consecutive items from the same community: same swap logic
- Synthetic items (welcome, communities) are pinned and skipped during reranking

This is a positional reranking pass, not a score modification — base scores remain untouched for debugging and tuning.

## Architecture

### New Classes

```
src/Feed/
├── Scoring/
│   ├── FeedScorer.php           # Orchestrates score computation
│   ├── AffinityCalculator.php   # Computes user-source affinity
│   ├── EngagementCalculator.php # Computes content engagement weight
│   ├── DecayCalculator.php      # Computes time decay
│   ├── DiversityReranker.php    # Post-sort diversity enforcement
│   └── AffinityCache.php        # Cache layer for affinity scores
```

### FeedScorer

The central orchestrator. Called by `FeedAssembler` after items are built but before sorting.

```php
final class FeedScorer
{
    public function __construct(
        private readonly AffinityCalculator $affinity,
        private readonly EngagementCalculator $engagement,
        private readonly DecayCalculator $decay,
        private readonly DiversityReranker $reranker,
        private readonly ConfigFactoryInterface $config,
    ) {}

    /** @param FeedItem[] $items */
    public function score(array $items, ?int $userId, ?array $userLocation): array
    {
        // 1. Batch-load affinity scores for all unique sources
        // 2. Batch-load engagement counts for all items
        // 3. Compute per-item scores
        // 4. Sort by score descending
        // 5. Apply diversity reranking
        // 6. Return reranked items
    }
}
```

### AffinityCalculator

Computes affinity between a user and a set of content sources. Uses batch queries to avoid N+1.

```php
final class AffinityCalculator
{
    public function __construct(
        private readonly EntityStorageInterface $reactionStorage,
        private readonly EntityStorageInterface $commentStorage,
        private readonly EntityStorageInterface $followStorage,
        private readonly AffinityCache $cache,
    ) {}

    /**
     * @param int $userId
     * @param string[] $sourceKeys  e.g. ['user:5', 'group:12', 'community:3']
     * @return array<string, float>  sourceKey => affinity score
     */
    public function computeBatch(int $userId, array $sourceKeys, ?array $userLocation): array;
}
```

**Query strategy:**

For a given user, load in two queries:
1. All reactions by this user in the last 30 days → group by `target_type:target_id` → resolve to source
2. All comments by this user in the last 30 days → group by `target_type:target_id` → resolve to source
3. All follows by this user → direct source mapping

These use `DatabaseInterface::select()` with conditions, not entity queries, for efficiency:

```php
// Reactions in last 30 days by this user
$db->select('reaction', 'r')
    ->fields('r', ['target_type', 'target_id'])
    ->condition('r.user_id', $userId)
    ->condition('r.created_at', time() - (30 * 86400), '>=')
    ->execute();
```

Community membership and geo-proximity come from the user's profile and the content's community data (already loaded by `FeedAssembler`).

### AffinityCache

Wraps `CacheBackendInterface` to cache per-user affinity maps. Affinity doesn't change on every request — caching for 15 minutes is safe.

```php
final class AffinityCache
{
    public function __construct(
        private readonly CacheBackendInterface $cache,
    ) {}

    public function get(int $userId): ?array;       // array<string, float> or null
    public function set(int $userId, array $scores): void;  // TTL: 900s
    public function invalidate(int $userId): void;
}
```

**Cache key:** `feed_affinity:{userId}`
**TTL:** 900 seconds (15 minutes)
**Invalidation:** CID-based via `$cache->delete("feed_affinity:{$userId}")` — no tag-based invalidation needed since we always know the exact cache key to clear.

**Invalidation:** On engagement actions (react, comment, follow, unfollow), invalidate the acting user's affinity cache. Uses CID-based invalidation (not tag-based) since we always know the exact user ID.

Registered in `FeedScoringServiceProvider::boot()` via Symfony EventDispatcher:

```php
// In FeedScoringServiceProvider::boot()
$dispatcher->addListener(EntityEvents::POST_SAVE->value, static function (EntityEvent $event) use ($affinityCache): void {
    $entity = $event->entity;
    $type = $entity->getEntityTypeId();
    if (in_array($type, ['reaction', 'comment', 'follow'], true)) {
        $userId = (int) $entity->get('user_id');
        $affinityCache->invalidate($userId);  // deletes CID 'feed_affinity:{userId}'
    }
});

$dispatcher->addListener(EntityEvents::POST_DELETE->value, static function (EntityEvent $event) use ($affinityCache): void {
    $entity = $event->entity;
    $type = $entity->getEntityTypeId();
    if (in_array($type, ['reaction', 'comment', 'follow'], true)) {
        $userId = (int) $entity->get('user_id');
        $affinityCache->invalidate($userId);
    }
});
```

This follows the same pattern as `EventListenerRegistrar` in the framework kernel — inline closures registered via `addListener()` on the Symfony `EventDispatcherInterface`.

### EngagementCalculator

Computes engagement weight for a batch of content items. Single query approach.

```php
final class EngagementCalculator
{
    public function __construct(
        private readonly DatabaseInterface $database,
    ) {}

    /**
     * @param array<string, string> $targetKeys  itemId => 'type:id' (e.g. 'post:45')
     * @return array<string, float>  itemId => engagement weight
     */
    public function computeBatch(array $targetKeys): array;
}
```

**Query strategy:**

Two aggregate queries (reactions + comments) with `GROUP BY target_type, target_id`:

```php
// Reaction counts per target
$db->select('reaction', 'r')
    ->addField('r', 'target_type', 'target_type')
    ->addField('r', 'target_id', 'target_id')
    ->condition('r.target_type', $types, 'IN')
    ->condition('r.target_id', $ids, 'IN')
    ->execute();
// Then count in PHP per target_type:target_id pair

// Comment counts per target
$db->select('comment', 'c')
    ->addField('c', 'target_type', 'target_type')
    ->addField('c', 'target_id', 'target_id')
    ->condition('c.status', 1)
    ->condition('c.target_type', $types, 'IN')
    ->condition('c.target_id', $ids, 'IN')
    ->execute();
```

Note: `SqlEntityQuery` doesn't support `GROUP BY` or aggregate functions, so we use `DatabaseInterface::select()` directly. This is the correct approach per Waaseyaa conventions — use the query builder for complex queries, entity queries for simple CRUD.

Apply the formula: `1.0 + log2(1 + weighted_sum)`

### DecayCalculator

Pure computation, no database access.

```php
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

Half-life is configurable via `ConfigFactoryInterface`:

```php
$config = $configFactory->get('minoo.feed_scoring');
$halfLife = $config->get('decay_half_life_hours') ?? 96.0;
```

### DiversityReranker

Post-sort pass that prevents type/community clustering.

```php
final class DiversityReranker
{
    public function __construct(
        private readonly int $maxConsecutiveType = 3,
        private readonly int $maxConsecutiveCommunity = 5,
    ) {}

    /** @param FeedItem[] $sortedItems */
    public function rerank(array $sortedItems): array
    {
        // Walk the sorted list top-to-bottom
        // Track consecutive same-type count and same-community count
        // When threshold exceeded, scan forward for next different-type/community
        //   item and swap it into current position
        // Synthetic items (welcome, communities) are pinned — skip them
        // If no different item found within next 10 positions, leave as-is
    }
}
```

### FeedScoringServiceProvider

Wires all scoring components into the DI container.

```php
final class FeedScoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register DecayCalculator as singleton (reads config for half-life)
        // Register AffinityCache with DatabaseBackend cache bin
        // Register AffinityCalculator with engagement storages + cache
        // Register EngagementCalculator with DatabaseInterface
        // Register DiversityReranker with config-driven thresholds
        // Register FeedScorer with all calculators + ConfigFactoryInterface
    }

    public function boot(EventDispatcherInterface $dispatcher): void
    {
        // Register affinity cache invalidation listeners:
        // $dispatcher->addListener(EntityEvents::POST_SAVE->value, ...)
        // $dispatcher->addListener(EntityEvents::POST_DELETE->value, ...)
        // (see AffinityCache invalidation section for callback implementation)
    }
}
```

### Integration with FeedAssembler

`FeedAssembler::assemble()` changes:

```
Current pipeline:
  gather → transform → inject → filter → sort(sortKey) → paginate

New pipeline:
  gather → transform → inject → filter → score → sort(score) → diversify → paginate
```

The `FeedScorer` replaces the static `sortKey` sort. The sort key remains on `FeedItem` for cursor-based pagination (encode score + id into cursor), but ranking is driven by the computed score.

**FeedItem changes:**

`FeedItem` is a `final readonly class` with a named-argument constructor. Adding a `score` property requires updating the constructor signature and every call site in `FeedItemFactory` and `FeedAssembler::attachEngagementCounts()`. Since `FeedScorer` operates on already-built `FeedItem` instances, the cleanest approach is:

1. Add `?float $score = null` as the last constructor parameter (backwards-compatible default)
2. `FeedScorer` creates new `FeedItem` instances with the score set (FeedItem is immutable, so no mutation)
3. Update `buildSortKey()` in `FeedItemFactory` to accept an optional score override

The existing `sortKey` is replaced by a score-based sort key for pagination:

```
sortKey = sprintf('%010d:%s', (int)(max(0, 10000 - $score) * 100000), $id)
```

Scores are normalized to integer range 0-1,000,000,000 (inverted so higher scores sort first as strings). This avoids floating-point precision issues at `PHP_FLOAT_MAX` extremes while preserving stable cursor-based pagination.

### Configuration

Stored in Waaseyaa config system as `minoo.feed_scoring`:

```yaml
# config/minoo.feed_scoring.yml
decay_half_life_hours: 96
featured_boost: 100.0
affinity_cache_ttl: 900
interaction_weights:
  reaction: 1.0
  comment: 3.0
  follow: 2.0
affinity_signals:
  same_community: 3.0
  follows_source: 4.0
  reaction_points: 1.0
  reaction_max: 5.0
  comment_points: 2.0
  comment_max: 6.0
  geo_close_km: 50
  geo_close_points: 2.0
  geo_mid_km: 150
  geo_mid_points: 1.0
base_affinity: 1.0
diversity:
  max_consecutive_type: 3
  max_consecutive_community: 5
  penalty_factor: 0.5
lookback_days: 30
```

All constants are tunable without code changes. This is the editorial bias knob — adjust weights to favor teachings over posts, boost community events, etc.

### Cache Infrastructure

**New cache bin:** `cache_feed_affinity`

Registered in `FeedScoringServiceProvider` via `CacheConfiguration` bin factory:

```php
'cache_feed_affinity' => fn() => new DatabaseBackend($pdo, 'cache_feed_affinity'),
```

**Migration:** New migration to create the `cache_feed_affinity` table (DatabaseBackend auto-creates, but explicit migration is cleaner).

**No new entity tables needed.** All scoring data comes from existing `reaction`, `comment`, and `follow` tables.

### Performance Budget

**Target:** Feed scoring adds < 50ms to feed load time.

**Strategy:**
- Affinity: cached per user (15min TTL). Cache miss = 3 queries. Cache hit = 0 queries.
- Engagement: 2 aggregate queries per feed page (batch all items). No caching needed — these are fast aggregate queries on indexed columns.
- Decay: Pure math, negligible cost.
- Diversity: Single pass over sorted array, O(n).

**Indexing requirements:**
- `reaction` table: index on `(user_id, created_at)` for affinity queries
- `reaction` table: index on `(target_type, target_id)` for engagement queries
- `comment` table: index on `(user_id, created_at)` for affinity queries
- `comment` table: index on `(target_type, target_id, status)` for engagement queries
- `follow` table: index on `(user_id)` for affinity queries

These may already exist. Check existing schema and add migrations only for missing indexes.

## Testing Strategy

### Unit Tests

| Class | Test Focus |
|---|---|
| `DecayCalculator` | Known decay values at specific ages; configurable half-life |
| `EngagementCalculator` | Weight formula with known interaction counts; log dampening; zero-interaction base |
| `AffinityCalculator` | Each signal type independently; additive scoring; max caps; base affinity for unknown sources |
| `DiversityReranker` | Consecutive type swap threshold; consecutive community swap threshold; synthetic item pinning; scan-forward limit |
| `FeedScorer` | End-to-end scoring with mocked calculators; anonymous user fallback; featured item boost |

### Integration Tests

| Scenario | Validation |
|---|---|
| User reacts to posts from community A, feed shows community A content higher | Affinity signal works |
| Popular post (many reactions) outranks newer post with no reactions | Engagement weight works |
| 7-day-old teaching with high engagement still appears in top 10 | Decay + engagement balance |
| Feed doesn't show 5 posts in a row from same community | Diversity reranker works |
| Anonymous user sees engagement-weighted chronological feed | Graceful degradation |
| Affinity cache invalidates on new reaction | Cache lifecycle works |

## Cold Start Behavior

On first page load with empty engagement history (new user, or platform launch):

- All items get `affinity = 1.0` (base)
- All items get `engagement_weight = 1.0` (no interactions yet)
- Only `decay` varies between items

The feed effectively becomes **reverse-chronological**, which is the current behavior. This is the expected and correct cold-start experience — the algorithm improves over time as users engage.

## Relationship to Existing EngagementCounter

`FeedAssembler::attachEngagementCounts()` currently uses per-item queries to count reactions and comments for display (showing "3 reactions, 2 comments" on each card). `EngagementCalculator` serves a different purpose — computing a weighted score for ranking.

**Strategy:** `EngagementCalculator` replaces the N+1 counting queries with batch queries. After scoring, the per-item reaction/comment counts needed for display are extracted from the same batch results and attached to `FeedItem` instances. `attachEngagementCounts()` is refactored to use the batch data already loaded by `EngagementCalculator`, eliminating the N+1 problem.

### Performance Test

| Scenario | Data Volume | Budget |
|---|---|---|
| Feed scoring with warm affinity cache | 50 feed items, 500 reactions, 200 comments | < 20ms |
| Feed scoring with cold affinity cache | Same data, cache miss | < 50ms |
| Full feed assembly end-to-end | Same data, including gather + transform + score + diversify + paginate | < 150ms |

## Migration Path

This replaces the static sort in `FeedAssembler` but preserves:
- The `FeedItem` value object (adds `score` field)
- Cursor-based pagination (new sort key format)
- Filter chips (applied before scoring)
- Synthetic items (pinned positions)
- All existing entity types and engagement actions

**No breaking changes to the feed API contract.** The `/api/feed` endpoint returns the same `FeedResponse` shape — only the ordering changes.

## Future Extensions

These are explicitly NOT in scope but the design accommodates them:

- **Editorial weights per content type** — adjust `interaction_weights` in config to boost teachings
- **ML-based scoring** — replace `FeedScorer` with an ML model; same interface
- **Explicit user controls** — "show more/less" adds a multiplier to affinity
- **Real-time trending** — `EngagementCalculator` with a time window becomes a trending signal
- **A/B testing** — swap `FeedScorer` implementations via config flag
