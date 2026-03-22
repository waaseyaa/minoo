# Social Feed v1 Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the Social Feed v1 milestone by fixing 3 bugs, shipping 3 features, and adding test coverage across 9 PRs.

**Architecture:** The feed uses a 3-layer pattern: Controllers (FeedController, EngagementController) → Feed Assembly (FeedAssembler, EntityLoaderService, FeedItemFactory, EngagementCounter) → Engagement Entities (Reaction, Comment, Post, Follow). Each PR targets a specific layer without cross-cutting concerns.

**Tech Stack:** PHP 8.4, Waaseyaa framework, Twig 3, vanilla CSS/JS, PHPUnit 10.5, Playwright

**Spec:** `docs/superpowers/specs/2026-03-22-social-feed-v1-sprint-design.md`

---

## File Map

### Phase 1 — Bug Fixes
| Task | Creates | Modifies | Tests |
|------|---------|----------|-------|
| 1: Hotfix 404 | — | `src/Feed/FeedAssembler.php` | Manual verification via Playwright |
| 2: Entity validation | `migrations/NNNN_rename_emoji_to_reaction_type.php` | `src/Entity/Reaction.php`, `src/Entity/Comment.php`, `src/Entity/Post.php`, `src/Entity/Follow.php`, `src/Provider/EngagementServiceProvider.php`, `src/Feed/EngagementCounter.php` | `tests/Minoo/Unit/Entity/ReactionTest.php`, `tests/Minoo/Unit/Entity/CommentTest.php`, `tests/Minoo/Unit/Entity/PostTest.php`, `tests/Minoo/Unit/Entity/FollowTest.php`, `tests/Minoo/Unit/Feed/EngagementCounterTest.php` |
| 3: Silent failure logging | — | `src/Controller/FeedController.php`, `src/Feed/EntityLoaderService.php` | `tests/Minoo/Unit/Controller/FeedControllerLoggingTest.php` |
| 4: Polish recovery | — | `resources/lang/en.php`, `src/Feed/FeedItemFactory.php`, `src/Controller/FeedController.php`, `src/Feed/RelativeTime.php`, `templates/components/feed-card.html.twig`, `templates/components/feed-engagement.html.twig`, `templates/feed-sidebar-left.html.twig`, `public/css/minoo.css` | Playwright visual verification |

### Phase 2 — Features
| Task | Creates | Modifies | Tests |
|------|---------|----------|-------|
| 5: User posts | `src/Access/PostAccessPolicy.php`, `templates/components/post-card.html.twig`, `templates/components/feed-create-post.html.twig` (update) | `src/Entity/Post.php`, `src/Provider/EngagementServiceProvider.php`, `src/Feed/FeedItemFactory.php`, `src/Feed/FeedAssembler.php`, `templates/feed.html.twig`, `public/css/minoo.css` | `tests/Minoo/Unit/Entity/PostTest.php` (extend), `tests/Minoo/Unit/Access/PostAccessPolicyTest.php` |
| 6: CSS responsive | — | `public/css/minoo.css` | Playwright responsive screenshots |
| 7: Bookmarkable URLs | — | `src/Controller/FeedController.php`, `templates/feed.html.twig` | `tests/Minoo/Unit/Controller/FeedControllerFilterTest.php` |

### Phase 3 — Tests
| Task | Creates | Modifies | Tests |
|------|---------|----------|-------|
| 8: Integration tests | `tests/Minoo/Integration/Controller/EngagementControllerTest.php` | — | Self |
| 9: Playwright e2e | `tests/Playwright/social-feed.spec.ts` | — | Self |

---

## Task 1: Hotfix — Engagement API 404

**Issue:** `FeedAssembler::attachEngagementCounts()` (line 207) passes `$item->id` (string) to `EngagementCounter::getCounts()`, which expects `list<array{type: string, id: int}>`. The type mismatch means counts never resolve, and the engagement API route returns 404 because the JS sends requests to an endpoint that can't match the expected data format.

**Files:**
- Modify: `src/Feed/FeedAssembler.php:205-230`

- [ ] **Step 1: Read current code and verify the bug**

Run: `php -S localhost:8081 -t public &` then use Playwright to click "Interested" button and confirm 404 at `/api/engagement/react`.

- [ ] **Step 2: Fix the type mismatch in attachEngagementCounts**

In `src/Feed/FeedAssembler.php`, replace line 207:

```php
// BEFORE (line 207):
$ids = array_map(fn(FeedItem $item) => $item->id, $items);

// AFTER:
$ids = array_map(fn(FeedItem $item) => ['type' => $item->type, 'id' => (int) $item->id], $items);
```

Also fix line 211 — the counts lookup key must match the format returned by `EngagementCounter::getCounts()` which keys by `"type:id"`:

```php
// BEFORE (line 211):
$itemCounts = $counts[$item->id] ?? null;

// AFTER:
$itemCounts = $counts[$item->type . ':' . $item->id] ?? null;
```

- [ ] **Step 3: Verify the engagement route exists**

Check `src/Provider/EngagementServiceProvider.php` routes section confirms `POST /api/engagement/react` is registered and maps to `EngagementController::react`. If the route is missing, add it.

**Note:** The reaction field is currently called `emoji` (not `reaction_type`). Do NOT rename it in this PR — that happens in Task 2b. The controller and JS will still reference `emoji` at this point, which is correct.

- [ ] **Step 4: Run tests to verify no regressions**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

- [ ] **Step 5: Verify with Playwright**

Navigate to `http://localhost:8081`, click "Interested" on an event card. Console should show no 404. Button should toggle state.

- [ ] **Step 6: Commit**

```bash
git add src/Feed/FeedAssembler.php
git commit -m "fix: engagement counter type mismatch causing API 404

FeedAssembler passed string IDs to EngagementCounter.getCounts() which
expects array{type,id}. Fixed the mapping and lookup key format.

Fixes the broken Interested/Recommend buttons on the homepage feed."
```

---

## Task 2: Entity Validation + Type Integrity (#412)

**Files:**
- Modify: `src/Entity/Reaction.php`, `src/Entity/Comment.php`, `src/Entity/Post.php`, `src/Entity/Follow.php`
- Modify: `src/Provider/EngagementServiceProvider.php`
- Modify: `src/Feed/EngagementCounter.php`
- Create: `migrations/NNNN_rename_emoji_to_reaction_type.php`
- Test: `tests/Minoo/Unit/Entity/ReactionTest.php`, `tests/Minoo/Unit/Entity/PostTest.php`

### Sub-task 2a: Constructor validation + created_at defaults

- [ ] **Step 1: Write failing test for Reaction constructor validation**

```php
// tests/Minoo/Unit/Entity/ReactionTest.php
#[Test]
public function constructorRequiresUserIdAndTarget(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new Reaction([]);
}

#[Test]
public function createdAtDefaultsToCurrentTime(): void
{
    $before = time();
    $reaction = new Reaction([
        'user_id' => 1,
        'target_type' => 'event',
        'target_id' => 42,
        'reaction_type' => 'interested',
    ]);
    $after = time();

    $this->assertGreaterThanOrEqual($before, $reaction->get('created_at'));
    $this->assertLessThanOrEqual($after, $reaction->get('created_at'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ReactionTest.php -v`
Expected: FAIL — no validation, created_at defaults to 0.

- [ ] **Step 3: Add constructor validation to Reaction**

```php
// src/Entity/Reaction.php
public function __construct(array $values = [])
{
    $required = ['user_id', 'target_type', 'target_id', 'reaction_type'];
    foreach ($required as $field) {
        if (!array_key_exists($field, $values) || $values[$field] === null || $values[$field] === '') {
            throw new \InvalidArgumentException("Reaction requires '{$field}'");
        }
    }

    if (!array_key_exists('created_at', $values)) {
        $values['created_at'] = time();
    }

    parent::__construct($values, $this->entityTypeId, $this->entityKeys);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ReactionTest.php -v`
Expected: PASS

- [ ] **Step 5: Repeat for Comment, Post, Follow entities**

Apply the same pattern:
- **Comment:** Require `user_id`, `target_type`, `target_id`, `body`. Default `created_at` to `time()`, `status` to 1.
- **Post:** Require `user_id`, `body`. Default `created_at` to `time()`, `updated_at` to `time()`, `status` to 1. Add `community_id` as required field.
- **Follow:** Require `user_id`, `target_type`, `target_id`. Default `created_at` to `time()`.

Write corresponding unit tests for each.

- [ ] **Step 6: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All pass. Some existing tests may need updating if they construct entities without required fields — fix those by adding the required fields to test data.

- [ ] **Step 7: Commit**

```bash
git add src/Entity/ tests/Minoo/Unit/Entity/
git commit -m "fix(#412): add constructor validation and time() defaults to engagement entities"
```

### Sub-task 2b: Rename emoji → reaction_type

- [ ] **Step 8: Write failing test for reaction_type whitelist**

```php
#[Test]
public function rejectsInvalidReactionType(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new Reaction([
        'user_id' => 1,
        'target_type' => 'event',
        'target_id' => 42,
        'reaction_type' => 'invalid_type',
    ]);
}

#[Test]
public function acceptsValidReactionTypes(): void
{
    foreach (['like', 'interested', 'recommend', 'miigwech'] as $type) {
        $reaction = new Reaction([
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
            'reaction_type' => $type,
        ]);
        $this->assertSame($type, $reaction->get('reaction_type'));
    }
}
```

- [ ] **Step 9: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ReactionTest.php --filter=rejectsInvalid -v`
Expected: FAIL — no whitelist validation yet.

- [ ] **Step 10: Add reaction_type whitelist to Reaction constructor**

```php
private const ALLOWED_REACTION_TYPES = ['like', 'interested', 'recommend', 'miigwech', 'connect'];

public function __construct(array $values = [])
{
    // ... existing required field checks ...

    if (!in_array($values['reaction_type'], self::ALLOWED_REACTION_TYPES, true)) {
        throw new \InvalidArgumentException(
            "Invalid reaction_type '{$values['reaction_type']}'. Allowed: " . implode(', ', self::ALLOWED_REACTION_TYPES)
        );
    }

    // ... rest of constructor ...
}
```

- [ ] **Step 11: Update entityKeys label from 'emoji' to 'reaction_type'**

```php
protected array $entityKeys = [
    'id' => 'rid',
    'uuid' => 'uuid',
    'label' => 'reaction_type',  // was 'emoji'
];
```

- [ ] **Step 12: Update EngagementServiceProvider field definitions**

In `src/Provider/EngagementServiceProvider.php`, find the reaction entity type field definitions and rename `emoji` to `reaction_type`.

- [ ] **Step 13: Create migration for column rename**

```bash
bin/waaseyaa make:migration rename_emoji_to_reaction_type
```

Edit the generated migration:

```php
return new Migration(
    up: function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE reaction RENAME COLUMN emoji TO reaction_type');
    },
    down: function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE reaction RENAME COLUMN reaction_type TO emoji');
    },
);
```

- [ ] **Step 14: Run migration and tests**

Run: `bin/waaseyaa migrate && ./vendor/bin/phpunit`
Expected: All pass.

- [ ] **Step 15: Update EngagementController to use reaction_type**

Grep for `emoji` in `src/Controller/EngagementController.php` and replace with `reaction_type` in the `react()` method request body parsing.

- [ ] **Step 16: Update JS in feed.html.twig**

Find the JS that sends `emoji` in the POST body to `/api/engagement/react` and change to `reaction_type`.

- [ ] **Step 17: Commit**

```bash
git add src/Entity/Reaction.php src/Provider/EngagementServiceProvider.php \
  src/Controller/EngagementController.php templates/feed.html.twig \
  migrations/ tests/Minoo/Unit/Entity/ReactionTest.php
git commit -m "fix(#412): rename emoji to reaction_type with whitelist validation"
```

### Sub-task 2c: N+1 query batching in EngagementCounter

- [ ] **Step 18: Write test for batch efficiency**

```php
// tests/Minoo/Unit/Feed/EngagementCounterTest.php
#[Test]
public function getCountsUsesInClauseNotPerItemQueries(): void
{
    // This test verifies the method works with multiple targets
    // The N+1 fix is structural — verify correct results with batch input
    $targets = [
        ['type' => 'event', 'id' => 1],
        ['type' => 'event', 'id' => 2],
        ['type' => 'business', 'id' => 3],
    ];
    $counts = $this->counter->getCounts($targets);

    $this->assertCount(3, $counts);
    $this->assertArrayHasKey('event:1', $counts);
    $this->assertArrayHasKey('event:2', $counts);
    $this->assertArrayHasKey('business:3', $counts);
}
```

- [ ] **Step 19: Refactor EngagementCounter to use batch queries**

Replace the per-item loop in `getCounts()` (lines 38-55) with two batch queries using raw SQL with IN clauses:

```php
public function getCounts(array $targets): array
{
    if ($targets === []) {
        return [];
    }

    $result = [];
    foreach ($targets as $target) {
        $key = $target['type'] . ':' . $target['id'];
        $result[$key] = ['reactions' => 0, 'comments' => 0];
    }

    // Group targets by type for efficient IN queries
    $byType = [];
    foreach ($targets as $target) {
        $byType[$target['type']][] = $target['id'];
    }

    $reactionStorage = $this->entityTypeManager->getStorage('reaction');
    $commentStorage = $this->entityTypeManager->getStorage('comment');

    foreach ($byType as $type => $ids) {
        // Batch reaction counts
        $reactions = $reactionStorage->getQuery()
            ->condition('target_type', $type)
            ->condition('target_id', $ids, 'IN')
            ->execute();

        foreach ($reactions as $reaction) {
            $key = $type . ':' . $reaction->get('target_id');
            if (isset($result[$key])) {
                $result[$key]['reactions']++;
            }
        }

        // Batch comment counts
        $comments = $commentStorage->getQuery()
            ->condition('target_type', $type)
            ->condition('target_id', $ids, 'IN')
            ->condition('status', 1)
            ->execute();

        foreach ($comments as $comment) {
            $key = $type . ':' . $comment->get('target_id');
            if (isset($result[$key])) {
                $result[$key]['comments']++;
            }
        }
    }

    return $result;
}
```

**Note:** Check if Waaseyaa's query builder supports `->condition('field', $array, 'IN')`. If not, use raw PDO with parameterized IN clauses. Read `waaseyaa_get_spec entity-system` for query builder capabilities.

- [ ] **Step 20: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All pass.

- [ ] **Step 21: Commit**

```bash
git add src/Feed/EngagementCounter.php tests/Minoo/Unit/Feed/
git commit -m "fix(#412): batch engagement count queries to eliminate N+1

Replaces per-item query loop (2 queries × N items) with grouped IN
queries (2 queries × unique target types). For a 20-item feed with
3 entity types, this reduces queries from 40 to 6."
```

- [ ] **Step 22: Final commit for PR 2**

Run full test suite, then create PR:

```bash
./vendor/bin/phpunit
# If all pass, push and create PR
```

---

## Task 3: Silent Failure Logging (#410)

**Files:**
- Modify: `src/Controller/FeedController.php`
- Modify: `src/Feed/EntityLoaderService.php`

- [ ] **Step 1: Audit all catch blocks**

Read `src/Controller/FeedController.php` and `src/Feed/EntityLoaderService.php`. Identify all `catch (\Throwable)` blocks. Expected: 6+ methods.

- [ ] **Step 2: Write test verifying logging on database error**

```php
// tests/Minoo/Unit/Controller/FeedControllerLoggingTest.php
#[Test]
public function buildTrendingLogsOnDatabaseError(): void
{
    // Arrange: mock storage to throw PDOException
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
        ->willThrowException(new \PDOException('SQLSTATE: table not found'));

    $entityTypeManager = $this->createMock(EntityTypeManager::class);
    $entityTypeManager->method('getStorage')
        ->with('reaction')
        ->willReturn($storage);

    $controller = $this->createFeedController($entityTypeManager);

    // Act: capture error_log output
    $logged = [];
    set_error_handler(function (int $errno, string $errstr) use (&$logged): bool {
        $logged[] = $errstr;
        return true;
    });

    $result = $this->invokePrivateMethod($controller, 'buildTrending');
    restore_error_handler();

    // Assert: returns empty, but logs the error
    $this->assertSame([], $result);
    $this->assertNotEmpty($logged, 'Expected error to be logged on PDOException');
    $this->assertStringContainsString('buildTrending', $logged[0]);
    $this->assertStringContainsString('Database error', $logged[0]);
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/FeedControllerLoggingTest.php -v`
Expected: FAIL — current code catches `\Throwable` silently with no `error_log()`.

- [ ] **Step 4: Narrow catches and add logging in FeedController**

For each catch block, apply this pattern:

```php
// BEFORE:
} catch (\Throwable) {
    return [];
}

// AFTER:
} catch (\PDOException $e) {
    error_log(sprintf(
        '[FeedController::%s] Database error: %s',
        __FUNCTION__,
        $e->getMessage()
    ));
    return [];
} catch (\RuntimeException $e) {
    error_log(sprintf(
        '[FeedController::%s] Runtime error: %s',
        __FUNCTION__,
        $e->getMessage()
    ));
    return [];
}
```

Apply to:
- `buildTrending()` — 2 nested catches
- `buildUpcomingEvents()`
- `buildSuggestedCommunities()`
- `buildFollowedCommunities()`

- [ ] **Step 5: Narrow catches in EntityLoaderService**

Apply same pattern to:
- `loadFeaturedItems()`
- `loadPosts()`

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All pass, including the new logging test.

- [ ] **Step 7: Commit**

```bash
git add src/Controller/FeedController.php src/Feed/EntityLoaderService.php
git commit -m "fix(#410): add logging to catch blocks, narrow exception types

6 methods used catch(\Throwable) returning empty defaults with no
logging. Narrowed to PDOException + RuntimeException and added
error_log() with method context. Database failures are now visible
in logs instead of silently producing empty sidebars."
```

---

## Task 4: Polish Recovery (#416, closes #406)

**Files:**
- Modify: `resources/lang/en.php`, `src/Feed/FeedItemFactory.php`, `src/Controller/FeedController.php`, `src/Feed/RelativeTime.php`, `templates/components/feed-card.html.twig`, `templates/components/feed-engagement.html.twig`, `templates/feed-sidebar-left.html.twig`, `public/css/minoo.css`

- [ ] **Step 1: Check reflog for lost commits**

```bash
git reflog --all | grep social-feed-smoke-test
```

If found, cherry-pick the polish commits — this is the fastest path.

If reflog has expired, each remaining step below is a self-contained re-implementation guided by the itemized list in issue #416. The current production state (visible at localhost:8081 with the production DB) serves as the "before" baseline. Use Playwright screenshots to compare before/after at each step.

**Note:** This task is intentionally less prescriptive than others because the work is recovering existing code, not writing new code. If the reflog works, steps 2-6 become verification only. If re-implementing, use the issue body of #406 and #416 as the detailed requirements — they list every specific fix with root cause.

- [ ] **Step 2: Add missing translation keys**

Verify `resources/lang/en.php` has all `feed.*` keys. Compare against the template references. Add any missing keys (the explore agent found 21 existing keys — verify completeness).

- [ ] **Step 3: Fix community name resolution in FeedItemFactory**

Verify `resolveCommunityName()` exists and is called in `buildEvent()`, `buildGroup()`, and `buildBusiness()`. If missing, add:

```php
private function resolveCommunityName(object $entity, array $communities): string
{
    $communityId = $entity->get('community_id');
    if ($communityId === null) {
        return 'Minoo';
    }
    foreach ($communities as $c) {
        if ((int) $c->id() === (int) $communityId) {
            return $c->get('name') ?? 'Minoo';
        }
    }
    return 'Minoo';
}
```

- [ ] **Step 4: Fix date formatting**

Verify `FeedController::formatEventDate()` exists and formats dates as "May 9, 2026 at 12:00 PM". If missing, add the helper.

Verify `RelativeTime::format()` handles both int timestamps and DateTimeImmutable input.

- [ ] **Step 5: Update card templates**

Verify `feed-card.html.twig` has:
- Community-attributed header (avatar + community name + meta)
- SVG icons (not emoji) for action buttons
- Engagement row with domain-specific labels

- [ ] **Step 6: CSS polish**

Verify `minoo.css` has styles for:
- Card padding and action buttons with CSS mask icons
- Sidebar alignment
- Nav alignment (li margin reset)
- Feed grid alignment with header
- Badge colors for Featured + Communities
- Location bar layout (Change button beside text)
- Multi-colored ribbon

- [ ] **Step 7: Verify with Playwright**

Take screenshots at desktop viewport. Compare against the pre-polish screenshot (`social-feed-homepage.png`).

- [ ] **Step 8: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All pass.

- [ ] **Step 9: Commit**

```bash
git add resources/lang/en.php src/Feed/FeedItemFactory.php \
  src/Controller/FeedController.php src/Feed/RelativeTime.php \
  templates/ public/css/minoo.css
git commit -m "fix(#416): re-apply polish lost in squash merge

Restores: translation keys, community name resolution, date formatting,
SVG icons, card template redesign, CSS polish, sidebar alignment.

Fixes #406
Fixes #416"
```

---

## Task 5: User Posts in Feed (#390)

**Files:**
- Create: `src/Access/PostAccessPolicy.php`
- Create: `templates/components/post-card.html.twig`
- Modify: `src/Entity/Post.php` (already done in Task 2)
- Modify: `src/Provider/EngagementServiceProvider.php`
- Modify: `src/Feed/FeedItemFactory.php`
- Modify: `src/Feed/FeedAssembler.php`
- Modify: `templates/feed.html.twig`
- Modify: `templates/components/feed-create-post.html.twig`
- Modify: `public/css/minoo.css`
- Test: `tests/Minoo/Unit/Access/PostAccessPolicyTest.php`

### Sub-task 5a: Access Policy

- [ ] **Step 1: Write failing test for PostAccessPolicy**

```php
// tests/Minoo/Unit/Access/PostAccessPolicyTest.php
#[Test]
public function anonymousCanView(): void
{
    $this->assertTrue($this->policy->canView($this->post, null));
}

#[Test]
public function anonymousCannotCreate(): void
{
    $this->assertFalse($this->policy->canCreate(null));
}

#[Test]
public function authenticatedCanCreate(): void
{
    $this->assertTrue($this->policy->canCreate($this->user));
}

#[Test]
public function authorCanDelete(): void
{
    $this->assertTrue($this->policy->canDelete($this->post, $this->author));
}

#[Test]
public function nonAuthorCannotDelete(): void
{
    $this->assertFalse($this->policy->canDelete($this->post, $this->otherUser));
}

#[Test]
public function coordinatorCanDelete(): void
{
    $this->assertTrue($this->policy->canDelete($this->post, $this->coordinator));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/PostAccessPolicyTest.php -v`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Create PostAccessPolicy**

```php
// src/Access/PostAccessPolicy.php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\PolicyAttribute;

#[PolicyAttribute(entityType: 'post')]
final class PostAccessPolicy implements AccessPolicyInterface
{
    public function canView(object $entity, ?object $account): bool
    {
        return true; // Public content
    }

    public function canCreate(?object $account): bool
    {
        return $account !== null; // Auth required
    }

    public function canEdit(object $entity, ?object $account): bool
    {
        if ($account === null) {
            return false;
        }
        return (int) $entity->get('user_id') === (int) $account->id();
    }

    public function canDelete(object $entity, ?object $account): bool
    {
        if ($account === null) {
            return false;
        }
        // Author or coordinator
        if ((int) $entity->get('user_id') === (int) $account->id()) {
            return true;
        }
        // Check for coordinator role
        $roles = $account->get('roles') ?? [];
        return in_array('coordinator', (array) $roles, true);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/PostAccessPolicyTest.php -v`
Expected: PASS

- [ ] **Step 5: Delete stale manifest cache**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 6: Commit**

```bash
git add src/Access/PostAccessPolicy.php tests/Minoo/Unit/Access/PostAccessPolicyTest.php
git commit -m "feat(#390): add PostAccessPolicy — public-read, auth-create, author+coordinator delete"
```

### Sub-task 5b: Feed integration

- [ ] **Step 7: Add buildPost() to FeedItemFactory**

```php
public function buildPost(object $post, ?array $communityCoords, ?float $userLat, ?float $userLon): FeedItem
{
    $userName = $post->get('user_name') ?? 'Community Member';
    $userInitials = $this->getInitials($userName);
    $createdAt = (int) $post->get('created_at');

    return new FeedItem(
        id: (string) $post->id(),
        type: 'post',
        title: '',
        url: '',
        badge: null,
        weight: 0,
        createdAt: $createdAt,
        sortKey: $this->buildSortKey(0, null, 'post', $createdAt, (int) $post->id()),
        entity: $post,
        subtitle: $post->get('body') ?? '',
        communityName: $userName,
        meta: [
            'user_initials' => $userInitials,
            'user_name' => $userName,
            'relative_time' => RelativeTime::format($createdAt),
        ],
    );
}

private function getInitials(string $name): string
{
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper($parts[0][0] . $parts[array_key_last($parts)][0]);
    }
    return strtoupper(substr($name, 0, 2));
}
```

- [ ] **Step 8: Wire posts into FeedAssembler**

Verify `FeedAssembler::assemble()` already calls `$this->loader->loadPosts()`. If not, add it alongside the other entity loaders.

- [ ] **Step 9: Create post-card template**

```twig
{# templates/components/post-card.html.twig #}
<article class="card card--post">
  <div class="card__attribution">
    <div class="card__avatar card__avatar--post">{{ item.meta.user_initials }}</div>
    <div class="card__meta">
      <span class="card__author">{{ item.meta.user_name }}</span>
      <span class="card__timestamp">shared a post &middot; {{ item.meta.relative_time }}</span>
    </div>
  </div>
  <p class="card__body">{{ item.subtitle }}</p>
  {% include "components/feed-engagement.html.twig" with { item: item } %}
</article>
```

- [ ] **Step 10: Add post card rendering to feed-card.html.twig**

Add a condition for `item.type == 'post'` that includes `post-card.html.twig`.

- [ ] **Step 11: Update create-post template for authenticated users**

Update `feed-create-post.html.twig` to show a text input + submit button for authenticated users (currently shows login prompt for anonymous).

- [ ] **Step 12: Add CSS for post cards**

Add to `@layer components` in `minoo.css`:

```css
.card--post {
  border-inline-start: 3px solid var(--accent-post, oklch(0.7 0.15 50));
}
.card__avatar--post {
  background-color: var(--accent-post, oklch(0.7 0.15 50));
}
```

- [ ] **Step 13: Run tests and verify with Playwright**

Run: `./vendor/bin/phpunit`
Then start dev server and use Playwright to verify posts appear in feed.

- [ ] **Step 14: Commit**

```bash
git add src/Feed/FeedItemFactory.php src/Feed/FeedAssembler.php \
  templates/components/post-card.html.twig templates/components/feed-card.html.twig \
  templates/components/feed-create-post.html.twig public/css/minoo.css
git commit -m "feat(#390): integrate user posts into homepage feed

Posts show with user attribution (initials avatar, name, relative time).
Post cards use distinct accent color. Create-post UI shows for
authenticated users. FeedAssembler includes posts with no auth branching."
```

---

## Task 6: CSS Responsive Polish (#398)

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Start dev server and take baseline screenshots**

Use Playwright to take screenshots at 1280px, 1024px, 768px, and 375px viewports.

- [ ] **Step 2: Polish >1200px (3-column)**

Verify all components render correctly: card attribution rows, action buttons, sidebar nav, right sidebar widgets. Fix any spacing or alignment issues.

- [ ] **Step 3: Polish 1024–1199px (2-column)**

Right sidebar is hidden. Verify feed cards expand properly, filter chips don't overflow, create-post box is sized correctly.

- [ ] **Step 4: Polish <1024px (1-column)**

Both sidebars hidden. Verify:
- Cards are full-width with appropriate padding
- Action buttons have ≥44px touch targets
- Filter chips scroll horizontally if needed (add `overflow-x: auto`)
- Create-post box is usable on mobile
- No horizontal overflow anywhere

- [ ] **Step 5: Test at 375px**

Narrowest common mobile viewport. Verify tighter padding, no overflow, readable text.

- [ ] **Step 6: Take after screenshots and compare**

Use Playwright to capture at all 4 viewports. Visual comparison with baseline.

- [ ] **Step 7: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All pass (CSS-only changes).

- [ ] **Step 8: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#398): responsive CSS polish for feed components

Polish all feed components at existing breakpoints (1200/1024).
Touch targets ≥44px on mobile, filter chips scroll horizontally,
card padding tightened at 375px. No new breakpoints added."
```

---

## Task 7: Bookmarkable Filter URLs (#415)

**Files:**
- Modify: `src/Controller/FeedController.php`
- Modify: `templates/feed.html.twig`
- Test: `tests/Minoo/Unit/Controller/FeedControllerFilterTest.php`

- [ ] **Step 1: Write failing test for server-side filter**

```php
// tests/Minoo/Unit/Controller/FeedControllerFilterTest.php
#[Test]
public function filterQueryParamPassesToFeedContext(): void
{
    // Verify FeedController reads ?filter= and passes to FeedContext
    $request = new Request(['filter' => 'event']);
    $context = $this->controller->buildFeedContext($request);
    $this->assertSame('event', $context->activeFilter);
}

#[Test]
public function invalidFilterDefaultsToAll(): void
{
    $request = new Request(['filter' => 'invalid']);
    $context = $this->controller->buildFeedContext($request);
    $this->assertSame('all', $context->activeFilter);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/FeedControllerFilterTest.php -v`
Expected: FAIL

- [ ] **Step 3: Add server-side filter validation**

In `FeedController::index()`, validate the filter param:

```php
// URL-facing filter names → internal entity type names
$filterMap = [
    'all' => 'all',
    'event' => 'event',
    'group' => 'group',
    'business' => 'business',
    'people' => 'resource_person',
];
$filterParam = $request->query->get('filter', 'all');
if (!array_key_exists($filterParam, $filterMap)) {
    $filterParam = 'all';
}
// Pass the internal entity type name to FeedContext for assembler filtering
$filter = $filterMap[$filterParam];
```

Pass the internal entity type name to `FeedContext::activeFilter` for assembler filtering, and pass the URL-facing `$filterParam` to the template for active chip rendering. The template uses URL names (`people`), the assembler uses entity type names (`resource_person`).

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/FeedControllerFilterTest.php -v`
Expected: PASS

- [ ] **Step 5: Update filter chips to links with progressive enhancement**

In `templates/feed.html.twig`, change filter buttons to anchor tags:

```twig
<nav class="feed-filters" aria-label="Filter feed">
  {% set filters = {all: 'All', event: 'Events', group: 'Groups', business: 'Businesses', people: 'People'} %}
  {% for key, label in filters %}
    <a href="?filter={{ key }}"
       class="feed-chip{% if activeFilter == key %} feed-chip--active{% endif %}"
       data-filter="{{ key }}">{{ label }}</a>
  {% endfor %}
</nav>
```

Add JS progressive enhancement:

```javascript
document.querySelectorAll('.feed-chip').forEach(chip => {
    chip.addEventListener('click', (e) => {
        e.preventDefault();
        const filter = chip.dataset.filter;
        history.pushState({ filter }, '', `?filter=${filter}`);
        // Apply client-side filter
        applyFilter(filter);
        // Update active state
        document.querySelectorAll('.feed-chip').forEach(c => c.classList.remove('feed-chip--active'));
        chip.classList.add('feed-chip--active');
    });
});

window.addEventListener('popstate', (e) => {
    const filter = e.state?.filter || 'all';
    applyFilter(filter);
    document.querySelectorAll('.feed-chip').forEach(c => {
        c.classList.toggle('feed-chip--active', c.dataset.filter === filter);
    });
});
```

- [ ] **Step 6: Verify with Playwright**

Test scenarios:
1. Navigate to `/?filter=event` — only events show, "Events" chip is active
2. Click "Groups" chip — URL updates to `?filter=group`, groups show
3. Browser back — returns to `?filter=event`
4. Direct navigation to `/?filter=business` — businesses show server-side

- [ ] **Step 7: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All pass.

- [ ] **Step 8: Commit**

```bash
git add src/Controller/FeedController.php templates/feed.html.twig \
  tests/Minoo/Unit/Controller/
git commit -m "feat(#415): bookmarkable feed filter URLs

Filter chips are now <a> tags with ?filter= hrefs. Server-side
validation passes filter to FeedContext. JS progressive enhancement
uses pushState for instant filtering. Back/forward navigation works.

Fixes #415"
```

---

## Task 8: EngagementController Integration Tests (#413)

**Files:**
- Create: `tests/Minoo/Integration/Controller/EngagementControllerTest.php`

- [ ] **Step 1: Set up test class with kernel boot**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Minoo\Controller\EngagementController::class)]
final class EngagementControllerTest extends TestCase
{
    private \Waaseyaa\Http\HttpKernel $kernel;

    protected function setUp(): void
    {
        putenv('WAASEYAA_DB=:memory:');

        $projectRoot = dirname(__DIR__, 3); // tests/Minoo/Integration/ → project root
        $this->kernel = new \Waaseyaa\Http\HttpKernel($projectRoot);

        // Boot is protected — use reflection (same pattern as existing integration tests)
        $boot = new \ReflectionMethod($this->kernel, 'boot');
        $boot->invoke($this->kernel);

        // Run migrations to set up schema
        // Get the container and run schema setup for engagement entity types
    }

    protected function tearDown(): void
    {
        putenv('WAASEYAA_DB');
    }

    /**
     * Helper: simulate an HTTP request through the kernel.
     * Adapt based on existing integration test patterns in tests/Minoo/Integration/.
     */
    private function request(string $method, string $uri, array $body = [], ?array $session = null): array
    {
        // Build Request, optionally set session for auth, dispatch through kernel
        // Return ['status' => int, 'body' => string]
    }
}
```

Reference: `tests/Minoo/Integration/` for the existing kernel boot pattern (3 levels up with `dirname(__DIR__, 3)`).

- [ ] **Step 2: Write auth enforcement tests**

Test that all mutation endpoints return 401 for anonymous requests:
- POST `/api/engagement/react`
- POST `/api/engagement/comment`
- POST `/api/engagement/follow`
- POST `/api/engagement/post`
- DELETE `/api/engagement/react/{id}`

- [ ] **Step 3: Write input validation tests**

Test invalid `target_type` → 400, invalid `reaction_type` → 400, body exceeding length limit → 400.

- [ ] **Step 4: Write CRUD tests**

Test create reaction → 201, create comment → 201, create follow → 201, create post → 201, delete own reaction → 204.

- [ ] **Step 5: Write ownership tests**

Test can't delete another user's reaction → 403. Coordinator can delete any → 204.

- [ ] **Step 6: Write duplicate handling tests**

Test second reaction on same target = upsert (not error). Second follow = idempotent 200.

- [ ] **Step 7: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All pass.

- [ ] **Step 8: Commit**

```bash
git add tests/Minoo/Integration/Controller/EngagementControllerTest.php
git commit -m "test(#413): add EngagementController integration tests

Covers auth enforcement (401), CSRF validation, input validation
(target_type + reaction_type whitelists), CRUD operations, ownership
checks, coordinator override, and duplicate handling (upsert/idempotent).

Fixes #413"
```

---

## Task 9: Playwright E2E Tests (#399)

**Files:**
- Create: `tests/Playwright/social-feed.spec.ts`

- [ ] **Step 1: Create test file with page fixture**

```typescript
import { test, expect } from '@playwright/test';

test.describe('Social Feed', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('http://localhost:8081');
    });

    // Tests go here
});
```

- [ ] **Step 2: Layout tests**

```typescript
test('renders three-column layout', async ({ page }) => {
    await expect(page.locator('.feed-sidebar--left')).toBeVisible();
    await expect(page.locator('.feed-main')).toBeVisible();
    await expect(page.locator('.feed-sidebar--right')).toBeVisible();
});

test('cards have attribution and action buttons', async ({ page }) => {
    const firstCard = page.locator('article').first();
    await expect(firstCard.locator('.card__attribution')).toBeVisible();
    await expect(firstCard.getByRole('button', { name: /Interested|Recommend|Like/ })).toBeVisible();
});
```

- [ ] **Step 3: Filter tests**

```typescript
test('filter chips show only matching content', async ({ page }) => {
    await page.getByRole('link', { name: 'Events' }).click();
    // All visible cards should be events
    const cards = page.locator('article');
    const count = await cards.count();
    expect(count).toBeGreaterThan(0);
});

test('filter URL is bookmarkable', async ({ page }) => {
    await page.goto('http://localhost:8081/?filter=event');
    await expect(page.locator('.feed-chip--active')).toHaveText('Events');
});

test('browser back navigates filter state', async ({ page }) => {
    await page.getByRole('link', { name: 'Events' }).click();
    await page.getByRole('link', { name: 'Groups' }).click();
    await page.goBack();
    await expect(page).toHaveURL(/filter=event/);
});
```

- [ ] **Step 4: Responsive tests**

```typescript
test('right sidebar hidden at 1024px', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await expect(page.locator('.feed-sidebar--right')).not.toBeVisible();
    await expect(page.locator('.feed-sidebar--left')).toBeVisible();
});

test('both sidebars hidden below 1024px', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await expect(page.locator('.feed-sidebar--left')).not.toBeVisible();
    await expect(page.locator('.feed-sidebar--right')).not.toBeVisible();
});
```

- [ ] **Step 5: Post card attribution test**

```typescript
test('post cards show user attribution', async ({ page }) => {
    // Requires posts in database
    const postCard = page.locator('.card--post').first();
    if (await postCard.count() > 0) {
        await expect(postCard.locator('.card__author')).toBeVisible();
        await expect(postCard.locator('.card__timestamp')).toContainText('shared a post');
    }
});
```

- [ ] **Step 6: Run Playwright tests**

Run: `npx playwright test tests/Playwright/social-feed.spec.ts`
Expected: All pass.

- [ ] **Step 7: Commit**

```bash
git add tests/Playwright/social-feed.spec.ts
git commit -m "test(#399): add Playwright e2e tests for social feed

Covers layout, card structure, filter chips with bookmarkable URLs,
responsive collapse at 1024/768, and post card user attribution.

Fixes #399"
```

---

## PR Strategy

Each task becomes its own PR:

| PR | Branch | Closes | Depends On |
|----|--------|--------|------------|
| 1 | `fix/engagement-404` | — | — |
| 2 | `fix/412-entity-validation` | #412 | PR 1 |
| 3 | `fix/410-silent-failures` | #410 | PR 1 |
| 4 | `fix/416-polish-recovery` | #406, #416 | PR 1 |
| 5 | `feat/390-user-posts` | #390 | PR 2 |
| 6 | `feat/398-responsive-css` | #398 | PR 4 |
| 7 | `feat/415-bookmarkable-urls` | #415 | PR 4 |
| 8 | `test/413-engagement-tests` | #413 | PR 1, PR 2 |
| 9 | `test/399-playwright-e2e` | #399 | All Phase 1 + 2 |

**Parallelism:** PRs 2, 3, and 4 can be worked on in parallel after PR 1 merges. PRs 5, 6, 7 can be parallelized after their dependencies merge. PRs 8 and 9 are sequential (8 before 9).
