# Social Feed Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform Minoo's homepage feed into a Facebook-style social hub with three-column layout, community-attributed cards, reactions, comments, user posts, and follows.

**Architecture:** Four new entity types (reaction, comment, post, follow) registered via a single EngagementServiceProvider. FeedAssembler enhanced to attach engagement counts and community attribution. Three-column CSS grid layout with responsive collapse. Vanilla JS for progressive enhancement of engagement interactions.

**Tech Stack:** PHP 8.4, Waaseyaa entity system, Twig 3, SQLite, vanilla CSS (design tokens), vanilla JS

**Spec:** `docs/superpowers/specs/2026-03-21-social-feed-redesign.md`

---

## File Map

### New Files

| File | Responsibility |
|---|---|
| `src/Entity/Reaction.php` | Reaction entity (interested/going/miigwech/recommend) |
| `src/Entity/Comment.php` | Comment entity |
| `src/Entity/Post.php` | User-generated post entity |
| `src/Entity/Follow.php` | Community follow entity |
| `src/Provider/EngagementServiceProvider.php` | Registers all 4 engagement entities + API routes |
| `src/Access/EngagementAccessPolicy.php` | Access control for engagement entities |
| `src/Controller/EngagementController.php` | API endpoints for react/comment/follow/post |
| `src/Feed/EngagementCounter.php` | Counts reactions/comments per entity (batch query) |
| `src/Feed/RelativeTime.php` | Formats timestamps as "2h ago", "Yesterday", etc. |
| `migrations/20260321_120000_create_reactions_table.php` | Reactions schema |
| `migrations/20260321_120100_create_comments_table.php` | Comments schema |
| `migrations/20260321_120200_create_posts_table.php` | Posts schema |
| `migrations/20260321_120300_create_follows_table.php` | Follows schema |
| `templates/components/feed-sidebar-left.html.twig` | Left nav sidebar |
| `templates/components/feed-sidebar-right.html.twig` | Trending/upcoming/suggested widgets |
| `templates/components/feed-create-post.html.twig` | Create post box |
| `templates/components/feed-comments.html.twig` | Inline comment section |
| `tests/Minoo/Unit/Entity/ReactionTest.php` | Reaction entity tests |
| `tests/Minoo/Unit/Entity/CommentTest.php` | Comment entity tests |
| `tests/Minoo/Unit/Entity/PostTest.php` | Post entity tests |
| `tests/Minoo/Unit/Entity/FollowTest.php` | Follow entity tests |
| `tests/Minoo/Unit/Feed/EngagementCounterTest.php` | Engagement counter tests |
| `tests/Minoo/Unit/Feed/RelativeTimeTest.php` | Relative time formatter tests |

### Modified Files

| File | Changes |
|---|---|
| `src/Feed/FeedItem.php` | Add engagement counts, community avatar, relative time fields |
| `src/Feed/FeedItemFactory.php` | Attach community attribution, relative timestamps |
| `src/Feed/FeedAssembler.php` | Inject engagement counts, include posts in feed |
| `src/Controller/FeedController.php` | Pass sidebar data (trending, upcoming, suggested) to template |
| `templates/feed.html.twig` | Three-column grid, include sidebars + create-post |
| `templates/components/feed-card.html.twig` | Community-attributed card layout with engagement row |
| `templates/base.html.twig` | Add CSRF meta tag for JS API calls |
| `public/css/minoo.css` | Three-column layout, updated card styles, sidebar widgets, engagement UI |

---

## Phase 1: Engagement Entities & Backend (Tasks 1–6)

### Task 1: Reaction Entity

**Files:**
- Create: `src/Entity/Reaction.php`
- Create: `tests/Minoo/Unit/Entity/ReactionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Reaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reaction::class)]
final class ReactionTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $reaction = new Reaction([
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 'evt-1',
            'reaction_type' => 'interested',
        ]);

        $this->assertSame(1, $reaction->get('user_id'));
        $this->assertSame('event', $reaction->get('target_type'));
        $this->assertSame('evt-1', $reaction->get('target_id'));
        $this->assertSame('interested', $reaction->get('reaction_type'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $reaction = new Reaction([]);
        $this->assertSame('reaction', $reaction->getEntityTypeId());
    }

    #[Test]
    public function it_validates_reaction_types(): void
    {
        $valid = ['interested', 'going', 'miigwech', 'recommend'];
        foreach ($valid as $type) {
            $r = new Reaction(['reaction_type' => $type]);
            $this->assertSame($type, $r->get('reaction_type'));
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ReactionTest.php -v`
Expected: FAIL — class Reaction not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);
namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Reaction extends ContentEntityBase
{
    protected string $entityTypeId = 'reaction';

    protected array $entityKeys = [
        'id' => 'rid',
        'uuid' => 'uuid',
        'label' => 'reaction_type',
    ];

    public function __construct(array $values = [])
    {
        $values += [
            'user_id' => 0,
            'target_type' => '',
            'target_id' => '',
            'reaction_type' => 'interested',
            'created_at' => time(),
            'status' => 1,
        ];
        parent::__construct($values);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ReactionTest.php -v`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Reaction.php tests/Minoo/Unit/Entity/ReactionTest.php
git commit -m "feat: add Reaction entity type"
```

---

### Task 2: Comment, Post, Follow Entities

**Files:**
- Create: `src/Entity/Comment.php`, `src/Entity/Post.php`, `src/Entity/Follow.php`
- Create: `tests/Minoo/Unit/Entity/CommentTest.php`, `tests/Minoo/Unit/Entity/PostTest.php`, `tests/Minoo/Unit/Entity/FollowTest.php`

- [ ] **Step 1: Write failing tests for all three entities**

Follow the same pattern as ReactionTest. Key differences:

**CommentTest** — entity type `comment`, key `cid`, fields: `user_id`, `target_type`, `target_id`, `body` (text), `created_at`

**PostTest** — entity type `post`, key `pid`, fields: `user_id`, `community_id`, `body` (text), `status` (default 1), `created_at`

**FollowTest** — entity type `follow`, key `fid`, fields: `user_id`, `target_type`, `target_id`, `created_at`

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ --filter "Comment|Post|Follow" -v`
Expected: FAIL — classes not found

- [ ] **Step 3: Write implementations**

**Comment.php**: `$entityTypeId = 'comment'`, keys: `id => cid, uuid => uuid, label => body`, defaults: `user_id => 0, target_type => '', target_id => '', body => '', created_at => time(), status => 1`

**Post.php**: `$entityTypeId = 'post'`, keys: `id => pid, uuid => uuid, label => body`, defaults: `user_id => 0, community_id => '', body => '', status => 1, created_at => time()`

**Follow.php**: `$entityTypeId = 'follow'`, keys: `id => fid, uuid => uuid, label => target_type`, defaults: `user_id => 0, target_type => '', target_id => '', created_at => time(), status => 1`

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ --filter "Comment|Post|Follow" -v`
Expected: PASS (all tests)

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Comment.php src/Entity/Post.php src/Entity/Follow.php \
  tests/Minoo/Unit/Entity/CommentTest.php tests/Minoo/Unit/Entity/PostTest.php \
  tests/Minoo/Unit/Entity/FollowTest.php
git commit -m "feat: add Comment, Post, Follow entity types"
```

---

### Task 3: EngagementServiceProvider

**Files:**
- Create: `src/Provider/EngagementServiceProvider.php`
- Create: `migrations/20260321_120000_create_reactions_table.php`
- Create: `migrations/20260321_120100_create_comments_table.php`
- Create: `migrations/20260321_120200_create_posts_table.php`
- Create: `migrations/20260321_120300_create_follows_table.php`

- [ ] **Step 1: Write the service provider**

Register all 4 entity types with field definitions. Follow `EventServiceProvider` pattern.

```php
<?php
declare(strict_types=1);
namespace Minoo\Provider;

use Minoo\Entity\Comment;
use Minoo\Entity\Follow;
use Minoo\Entity\Post;
use Minoo\Entity\Reaction;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Provider\ServiceProviderBase;

final class EngagementServiceProvider extends ServiceProviderBase
{
    public function register(): void
    {
        // Reaction
        $this->entityType(new EntityType(
            id: 'reaction',
            label: 'Reaction',
            class: Reaction::class,
            keys: ['id' => 'rid', 'uuid' => 'uuid', 'label' => 'reaction_type'],
            fieldDefinitions: [
                'rid' => ['type' => 'string', 'label' => 'Reaction ID'],
                'uuid' => ['type' => 'string', 'label' => 'UUID'],
                'user_id' => ['type' => 'integer', 'label' => 'User ID'],
                'target_type' => ['type' => 'string', 'label' => 'Target Entity Type'],
                'target_id' => ['type' => 'string', 'label' => 'Target Entity ID'],
                'reaction_type' => ['type' => 'string', 'label' => 'Reaction Type'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created'],
                'status' => ['type' => 'boolean', 'label' => 'Status', 'default' => true],
            ],
        ));

        // Comment
        $this->entityType(new EntityType(
            id: 'comment',
            label: 'Comment',
            class: Comment::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'body'],
            fieldDefinitions: [
                'cid' => ['type' => 'string', 'label' => 'Comment ID'],
                'uuid' => ['type' => 'string', 'label' => 'UUID'],
                'user_id' => ['type' => 'integer', 'label' => 'User ID'],
                'target_type' => ['type' => 'string', 'label' => 'Target Entity Type'],
                'target_id' => ['type' => 'string', 'label' => 'Target Entity ID'],
                'body' => ['type' => 'text_long', 'label' => 'Comment Body'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created'],
                'status' => ['type' => 'boolean', 'label' => 'Status', 'default' => true],
            ],
        ));

        // Post
        $this->entityType(new EntityType(
            id: 'post',
            label: 'Post',
            class: Post::class,
            keys: ['id' => 'pid', 'uuid' => 'uuid', 'label' => 'body'],
            fieldDefinitions: [
                'pid' => ['type' => 'string', 'label' => 'Post ID'],
                'uuid' => ['type' => 'string', 'label' => 'UUID'],
                'user_id' => ['type' => 'integer', 'label' => 'User ID'],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community']],
                'body' => ['type' => 'text_long', 'label' => 'Post Body'],
                'status' => ['type' => 'boolean', 'label' => 'Status', 'default' => true],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created'],
            ],
        ));

        // Follow
        $this->entityType(new EntityType(
            id: 'follow',
            label: 'Follow',
            class: Follow::class,
            keys: ['id' => 'fid', 'uuid' => 'uuid', 'label' => 'target_type'],
            fieldDefinitions: [
                'fid' => ['type' => 'string', 'label' => 'Follow ID'],
                'uuid' => ['type' => 'string', 'label' => 'UUID'],
                'user_id' => ['type' => 'integer', 'label' => 'User ID'],
                'target_type' => ['type' => 'string', 'label' => 'Target Entity Type'],
                'target_id' => ['type' => 'string', 'label' => 'Target Entity ID'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created'],
                'status' => ['type' => 'boolean', 'label' => 'Status', 'default' => true],
            ],
        ));
    }
}
```

- [ ] **Step 2: Write migrations**

Each migration follows the pattern in `20260315_110500_add_consent_fields.php`:

```php
<?php
// migrations/20260321_120000_create_reactions_table.php
declare(strict_types=1);
use Waaseyaa\Database\Migration;
use Waaseyaa\Database\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('reaction')) {
            $schema->getConnection()->executeStatement('
                CREATE TABLE reaction (
                    rid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid TEXT NOT NULL DEFAULT "",
                    user_id INTEGER NOT NULL,
                    target_type TEXT NOT NULL,
                    target_id TEXT NOT NULL,
                    reaction_type TEXT NOT NULL DEFAULT "interested",
                    created_at INTEGER NOT NULL DEFAULT 0,
                    status INTEGER NOT NULL DEFAULT 1,
                    UNIQUE(user_id, target_type, target_id)
                )
            ');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('reaction')) {
            $schema->getConnection()->executeStatement('DROP TABLE reaction');
        }
    }
};
```

Repeat for `comment` (cid, user_id, target_type, target_id, body TEXT, created_at, status), `post` (pid, user_id, community_id, body TEXT, status, created_at), `follow` (fid, user_id, target_type, target_id, created_at, status — UNIQUE on user_id+target_type+target_id).

- [ ] **Step 3: Delete stale manifest, run migrations**

```bash
rm -f storage/framework/packages.php
bin/waaseyaa migrate
```

- [ ] **Step 4: Run all tests to verify no regressions**

Run: `./vendor/bin/phpunit -v`
Expected: All existing tests pass + new entity tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Provider/EngagementServiceProvider.php migrations/
git commit -m "feat: register engagement entities with migrations"
```

---

### Task 4: EngagementAccessPolicy

**Files:**
- Create: `src/Access/EngagementAccessPolicy.php`

- [ ] **Step 1: Write the access policy**

```php
<?php
declare(strict_types=1);
namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['reaction', 'comment', 'post', 'follow'])]
final class EngagementAccessPolicy implements AccessPolicyInterface
{
    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        // Admins and coordinators can delete anything
        if ($operation === 'delete' && ($account->hasPermission('administer content') || in_array('elder_coordinator', $account->getRoles(), true))) {
            return AccessResult::allowed();
        }

        // Users can view all engagement
        if ($operation === 'view') {
            return AccessResult::allowed();
        }

        // Users can delete their own
        if ($operation === 'delete' && $account->isAuthenticated() && (int) $entity->get('user_id') === (int) $account->id()) {
            return AccessResult::allowed();
        }

        return AccessResult::neutral();
    }

    public function createAccess(string $entityType, AccountInterface $account): AccessResult
    {
        return $account->isAuthenticated() ? AccessResult::allowed() : AccessResult::neutral();
    }
}
```

- [ ] **Step 2: Delete manifest, run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit -v
```
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Access/EngagementAccessPolicy.php
git commit -m "feat: add EngagementAccessPolicy for reactions/comments/posts/follows"
```

---

### Task 5: EngagementCounter Service

**Files:**
- Create: `src/Feed/EngagementCounter.php`
- Create: `tests/Minoo/Unit/Feed/EngagementCounterTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
declare(strict_types=1);
namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\EngagementCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(EngagementCounter::class)]
final class EngagementCounterTest extends TestCase
{
    #[Test]
    public function it_returns_zero_counts_for_unknown_entities(): void
    {
        $reactionStorage = $this->createMock(EntityStorageInterface::class);
        $reactionStorage->method('getQuery')->willReturn(new class {
            public function condition(string $field, mixed $value): self { return $this; }
            public function count(): int { return 0; }
        });

        $commentStorage = $this->createMock(EntityStorageInterface::class);
        $commentStorage->method('getQuery')->willReturn(new class {
            public function condition(string $field, mixed $value): self { return $this; }
            public function count(): int { return 0; }
        });

        $counter = new EngagementCounter($reactionStorage, $commentStorage);
        $counts = $counter->getCounts('event', 'evt-1');

        $this->assertSame(0, $counts['reactions']);
        $this->assertSame(0, $counts['comments']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/EngagementCounterTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php
declare(strict_types=1);
namespace Minoo\Feed;

use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class EngagementCounter
{
    public function __construct(
        private readonly EntityStorageInterface $reactionStorage,
        private readonly EntityStorageInterface $commentStorage,
    ) {}

    /** @return array{reactions: int, comments: int} */
    public function getCounts(string $targetType, string $targetId): array
    {
        $reactions = $this->reactionStorage->getQuery()
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->count();

        $comments = $this->commentStorage->getQuery()
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->count();

        return ['reactions' => $reactions, 'comments' => $comments];
    }

    /**
     * Batch count for multiple entities (reduces queries).
     * @param array<array{type: string, id: string}> $targets
     * @return array<string, array{reactions: int, comments: int}> keyed by "type:id"
     */
    public function getBatchCounts(array $targets): array
    {
        $results = [];
        foreach ($targets as $target) {
            $key = $target['type'] . ':' . $target['id'];
            $results[$key] = $this->getCounts($target['type'], $target['id']);
        }
        return $results;
    }

    /**
     * Check if a specific user has reacted to a target.
     */
    public function getUserReaction(int $userId, string $targetType, string $targetId): ?string
    {
        $ids = $this->reactionStorage->getQuery()
            ->condition('user_id', $userId)
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->execute();

        if (empty($ids)) {
            return null;
        }

        $reaction = $this->reactionStorage->load(reset($ids));
        return $reaction?->get('reaction_type');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/EngagementCounterTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/EngagementCounter.php tests/Minoo/Unit/Feed/EngagementCounterTest.php
git commit -m "feat: add EngagementCounter for reaction/comment counts"
```

---

### Task 6: RelativeTime Formatter + EngagementController API

**Files:**
- Create: `src/Feed/RelativeTime.php`
- Create: `tests/Minoo/Unit/Feed/RelativeTimeTest.php`
- Create: `src/Controller/EngagementController.php`

- [ ] **Step 1: Write RelativeTime failing test**

```php
<?php
declare(strict_types=1);
namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\RelativeTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelativeTime::class)]
final class RelativeTimeTest extends TestCase
{
    #[Test]
    public function it_formats_recent_timestamps(): void
    {
        $now = time();
        $this->assertSame('just now', RelativeTime::format($now, $now));
        $this->assertSame('2m ago', RelativeTime::format($now - 120, $now));
        $this->assertSame('1h ago', RelativeTime::format($now - 3600, $now));
        $this->assertSame('5h ago', RelativeTime::format($now - 18000, $now));
    }

    #[Test]
    public function it_formats_older_timestamps(): void
    {
        $now = strtotime('2026-03-21 12:00:00');
        $this->assertSame('Yesterday', RelativeTime::format(strtotime('2026-03-20 12:00:00'), $now));
        $this->assertSame('Mar 18', RelativeTime::format(strtotime('2026-03-18 12:00:00'), $now));
        $this->assertSame('Jan 5, 2025', RelativeTime::format(strtotime('2025-01-05 12:00:00'), $now));
    }
}
```

- [ ] **Step 2: Write RelativeTime implementation**

```php
<?php
declare(strict_types=1);
namespace Minoo\Feed;

final class RelativeTime
{
    public static function format(int $timestamp, ?int $now = null): string
    {
        $now ??= time();
        $diff = $now - $timestamp;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return (int)($diff / 60) . 'm ago';
        if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
        if ($diff < 172800) return 'Yesterday';

        $date = new \DateTimeImmutable("@$timestamp");
        $nowDate = new \DateTimeImmutable("@$now");

        if ($date->format('Y') === $nowDate->format('Y')) {
            return $date->format('M j');
        }

        return $date->format('M j, Y');
    }
}
```

- [ ] **Step 3: Run RelativeTime tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/RelativeTimeTest.php -v`
Expected: PASS

- [ ] **Step 4: Write EngagementController**

```php
<?php
declare(strict_types=1);
namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class EngagementController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function react(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $targetType = $data['target_type'] ?? '';
        $targetId = $data['target_id'] ?? '';
        $reactionType = $data['reaction_type'] ?? 'interested';

        $valid = ['interested', 'going', 'miigwech', 'recommend'];
        if (!in_array($reactionType, $valid, true)) {
            return new JsonResponse(['error' => 'Invalid reaction type'], 400);
        }

        $storage = $this->entityTypeManager->getStorage('reaction');

        // Check for existing reaction
        $existing = $storage->getQuery()
            ->condition('user_id', (int) $account->id())
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->execute();

        if (!empty($existing)) {
            // Update existing
            $reaction = $storage->load(reset($existing));
            $reaction->set('reaction_type', $reactionType);
            $storage->save($reaction);
        } else {
            // Create new
            $storage->create([
                'user_id' => (int) $account->id(),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reaction_type' => $reactionType,
                'created_at' => time(),
            ]);
        }

        return new JsonResponse(['success' => true]);
    }

    public function deleteReaction(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $targetType = $params['target_type'] ?? '';
        $targetId = $params['id'] ?? '';
        $storage = $this->entityTypeManager->getStorage('reaction');

        $existing = $storage->getQuery()
            ->condition('user_id', (int) $account->id())
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->execute();

        if (!empty($existing)) {
            $storage->delete($storage->load(reset($existing)));
        }

        return new JsonResponse(['success' => true]);
    }

    public function comment(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $body = trim($data['body'] ?? '');
        if ($body === '' || mb_strlen($body) > 2000) {
            return new JsonResponse(['error' => 'Comment must be 1-2000 characters'], 400);
        }

        $storage = $this->entityTypeManager->getStorage('comment');
        $comment = $storage->create([
            'user_id' => (int) $account->id(),
            'target_type' => $data['target_type'] ?? '',
            'target_id' => $data['target_id'] ?? '',
            'body' => $body,
            'created_at' => time(),
        ]);

        return new JsonResponse([
            'success' => true,
            'comment' => [
                'id' => $comment->id(),
                'body' => $body,
                'created_at' => time(),
            ],
        ]);
    }

    public function deleteComment(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $storage = $this->entityTypeManager->getStorage('comment');
        $comment = $storage->load($params['cid'] ?? '');
        if (!$comment) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $isOwner = (int) $comment->get('user_id') === (int) $account->id();
        $isAdmin = $account->hasPermission('administer content') || in_array('elder_coordinator', $account->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $storage->delete($comment);
        return new JsonResponse(['success' => true]);
    }

    public function getComments(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $storage = $this->entityTypeManager->getStorage('comment');
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $ids = $storage->getQuery()
            ->condition('target_type', $params['type'] ?? '')
            ->condition('target_id', $params['id'] ?? '')
            ->sort('created_at', 'DESC')
            ->range($offset, $limit)
            ->execute();

        $comments = [];
        foreach ($ids as $id) {
            $c = $storage->load($id);
            if ($c) {
                $comments[] = [
                    'id' => $c->id(),
                    'body' => $c->get('body'),
                    'user_id' => $c->get('user_id'),
                    'created_at' => $c->get('created_at'),
                    'relative_time' => \Minoo\Feed\RelativeTime::format((int) $c->get('created_at')),
                ];
            }
        }

        return new JsonResponse(['comments' => $comments, 'page' => $page]);
    }

    public function follow(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $storage = $this->entityTypeManager->getStorage('follow');

        // Check for existing
        $existing = $storage->getQuery()
            ->condition('user_id', (int) $account->id())
            ->condition('target_type', $data['target_type'] ?? '')
            ->condition('target_id', $data['target_id'] ?? '')
            ->execute();

        if (empty($existing)) {
            $storage->create([
                'user_id' => (int) $account->id(),
                'target_type' => $data['target_type'] ?? '',
                'target_id' => $data['target_id'] ?? '',
                'created_at' => time(),
            ]);
        }

        return new JsonResponse(['success' => true]);
    }

    public function deleteFollow(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $storage = $this->entityTypeManager->getStorage('follow');
        $existing = $storage->getQuery()
            ->condition('user_id', (int) $account->id())
            ->condition('target_type', $params['target_type'] ?? '')
            ->condition('target_id', $params['id'] ?? '')
            ->execute();

        if (!empty($existing)) {
            $storage->delete($storage->load(reset($existing)));
        }

        return new JsonResponse(['success' => true]);
    }

    public function createPost(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $body = trim($data['body'] ?? '');
        if ($body === '' || mb_strlen($body) > 5000) {
            return new JsonResponse(['error' => 'Post must be 1-5000 characters'], 400);
        }
        if (empty($data['community_id'])) {
            return new JsonResponse(['error' => 'Community is required'], 400);
        }

        $storage = $this->entityTypeManager->getStorage('post');
        $post = $storage->create([
            'user_id' => (int) $account->id(),
            'community_id' => $data['community_id'],
            'body' => $body,
            'status' => 1,
            'created_at' => time(),
        ]);

        return new JsonResponse(['success' => true, 'id' => $post->id()]);
    }

    public function deletePost(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Login required'], 401);
        }

        $storage = $this->entityTypeManager->getStorage('post');
        $post = $storage->load($params['pid'] ?? '');
        if (!$post) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $isOwner = (int) $post->get('user_id') === (int) $account->id();
        $isAdmin = $account->hasPermission('administer content') || in_array('elder_coordinator', $account->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $storage->delete($post);
        return new JsonResponse(['success' => true]);
    }
}
```

- [ ] **Step 5: Register routes in EngagementServiceProvider**

Add route registrations to the provider's `register()` method:

```php
// In EngagementServiceProvider::register(), after entity type registrations:
$this->route('POST', '/api/react', EngagementController::class, 'react');
$this->route('DELETE', '/api/react/{target_type}/{id}', EngagementController::class, 'deleteReaction');
$this->route('POST', '/api/comment', EngagementController::class, 'comment');
$this->route('DELETE', '/api/comment/{cid}', EngagementController::class, 'deleteComment');
$this->route('GET', '/api/comments/{type}/{id}', EngagementController::class, 'getComments');
$this->route('POST', '/api/follow', EngagementController::class, 'follow');
$this->route('DELETE', '/api/follow/{target_type}/{id}', EngagementController::class, 'deleteFollow');
$this->route('POST', '/api/post', EngagementController::class, 'createPost');
$this->route('DELETE', '/api/post/{pid}', EngagementController::class, 'deletePost');
```

- [ ] **Step 6: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add src/Feed/RelativeTime.php tests/Minoo/Unit/Feed/RelativeTimeTest.php \
  src/Controller/EngagementController.php src/Provider/EngagementServiceProvider.php
git commit -m "feat: add engagement API (react, comment, follow, post endpoints)"
```

---

## Phase 2: Feed Integration (Tasks 7–8)

### Task 7: Enhance FeedItem + FeedItemFactory with Engagement Data

**Files:**
- Modify: `src/Feed/FeedItem.php`
- Modify: `src/Feed/FeedItemFactory.php`

- [ ] **Step 1: Add engagement fields to FeedItem**

Add to the readonly constructor in `FeedItem.php`:

```php
// New fields — add after existing constructor parameters
public int $reactionCount = 0,
public int $commentCount = 0,
public ?string $userReaction = null,
public ?string $relativeTime = null,
public ?string $communitySlug = null,
public ?string $communityInitial = null,
```

Update `toArray()` to include new fields:

```php
if ($this->reactionCount > 0) $data['reactionCount'] = $this->reactionCount;
if ($this->commentCount > 0) $data['commentCount'] = $this->commentCount;
if ($this->userReaction !== null) $data['userReaction'] = $this->userReaction;
if ($this->relativeTime !== null) $data['relativeTime'] = $this->relativeTime;
if ($this->communitySlug !== null) $data['communitySlug'] = $this->communitySlug;
if ($this->communityInitial !== null) $data['communityInitial'] = $this->communityInitial;
```

- [ ] **Step 2: Update FeedItemFactory to compute relative time and community data**

In each `build*()` method, add:

```php
relativeTime: RelativeTime::format((int) ($entity->get('created_at') ?? 0)),
communitySlug: $this->resolveCommunitySlug($entity),
communityInitial: $this->resolveCommunityInitial($entity),
```

Add helper methods:

```php
private function resolveCommunitySlug(ContentEntityBase $entity): ?string
{
    $communityId = $entity->get('community_id');
    if (!$communityId) return null;
    // Community slug lookup via entity loader
    return $this->communitySlugCache[$communityId] ?? null;
}

private function resolveCommunityInitial(ContentEntityBase $entity): ?string
{
    $name = $entity->get('community') ?? '';
    return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : null;
}
```

- [ ] **Step 3: Run tests**

Run: `./vendor/bin/phpunit -v`
Expected: All pass (existing tests may need updated assertions for new constructor params)

- [ ] **Step 4: Commit**

```bash
git add src/Feed/FeedItem.php src/Feed/FeedItemFactory.php
git commit -m "feat: add engagement and community fields to FeedItem"
```

---

### Task 8: Inject Engagement Counts + Posts into FeedAssembler

**Files:**
- Modify: `src/Feed/FeedAssembler.php`
- Modify: `src/Provider/EngagementServiceProvider.php` (register EngagementCounter singleton)

- [ ] **Step 1: Register EngagementCounter in provider**

Add to `EngagementServiceProvider::register()`:

```php
$this->singleton(EngagementCounter::class, function ($container) {
    $etm = $container->get(EntityTypeManager::class);
    return new EngagementCounter(
        $etm->getStorage('reaction'),
        $etm->getStorage('comment'),
    );
});
```

- [ ] **Step 2: Update FeedAssembler to accept EngagementCounter**

Add `EngagementCounter` to constructor, then after assembling items, batch-query counts:

```php
// After items are assembled, before sorting:
$targets = [];
foreach ($items as $item) {
    if (!$item->isSynthetic()) {
        $targets[] = ['type' => $item->type, 'id' => $item->id];
    }
}
$counts = $this->engagementCounter->getBatchCounts($targets);

// Create new items with counts attached (FeedItem is readonly, so reconstruct)
```

- [ ] **Step 3: Include user posts in feed**

Load posts from storage, create FeedItems via factory, merge into the feed items array.

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit -v`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add src/Feed/FeedAssembler.php src/Provider/EngagementServiceProvider.php
git commit -m "feat: inject engagement counts and user posts into feed"
```

---

## Phase 3: Frontend — Layout & Templates (Tasks 9–13)

### Task 9: CSRF Meta Tag in Base Template

**Files:**
- Modify: `templates/base.html.twig`

- [ ] **Step 1: Add CSRF meta tag to head**

After the existing `<meta>` tags in `base.html.twig`:

```twig
{% if csrf_token is defined %}
  <meta name="csrf-token" content="{{ csrf_token }}">
{% endif %}
```

- [ ] **Step 2: Ensure FeedController passes CSRF token**

Update `FeedController::index()` to pass the token to the template context.

- [ ] **Step 3: Commit**

```bash
git add templates/base.html.twig src/Controller/FeedController.php
git commit -m "feat: add CSRF meta tag for engagement API calls"
```

---

### Task 10: Three-Column Layout CSS

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add layout grid CSS**

Replace the existing feed header/container styles with a three-column grid:

```css
/* ── Feed Layout (three-column) ── */
.feed-layout {
  display: grid;
  grid-template-columns: 220px minmax(0, 600px) 260px;
  gap: var(--space-md);
  max-inline-size: 1200px;
  margin-inline: auto;
  padding-inline: var(--gutter);
}

@media (max-width: 1199px) {
  .feed-layout {
    grid-template-columns: 220px minmax(0, 1fr);
  }
  .feed-sidebar--right { display: none; }
}

@media (max-width: 1023px) {
  .feed-layout {
    grid-template-columns: 1fr;
  }
  .feed-sidebar--left { display: none; }
}
```

Add sidebar nav, sidebar widget, and create-post styles.

- [ ] **Step 2: Update card styles for community-attributed design**

Replace left border with top bar. Add attribution row, detail box, engagement row, action buttons.

- [ ] **Step 3: Verify in browser**

Run: `php -S localhost:8081 -t public`
Check: http://localhost:8081 — three columns visible at wide viewport

- [ ] **Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: three-column feed layout with updated card styles"
```

---

### Task 11: Feed Template Restructure

**Files:**
- Modify: `templates/feed.html.twig`
- Create: `templates/components/feed-sidebar-left.html.twig`
- Create: `templates/components/feed-sidebar-right.html.twig`
- Create: `templates/components/feed-create-post.html.twig`

- [ ] **Step 1: Create left sidebar template**

```twig
<aside class="feed-sidebar feed-sidebar--left">
  <nav class="sidebar-nav" aria-label="Feed navigation">
    <a href="{{ lang_url('/events') }}" class="sidebar-nav__item sidebar-nav__item--events">
      <span class="sidebar-nav__icon">🎪</span> {{ trans('nav.events') }}
    </a>
    <a href="{{ lang_url('/communities') }}" class="sidebar-nav__item sidebar-nav__item--communities">
      <span class="sidebar-nav__icon">🏘</span> {{ trans('nav.communities') }}
    </a>
    {# ... teachings, people, businesses, volunteer, elder support #}
  </nav>

  {% if followed_communities is defined and followed_communities|length > 0 %}
    <div class="sidebar-widget">
      <h4 class="sidebar-widget__title">{{ trans('feed.your_communities') }}</h4>
      {% for community in followed_communities %}
        <a href="{{ lang_url('/communities/' ~ community.slug) }}" class="sidebar-nav__item">{{ community.name }}</a>
      {% endfor %}
    </div>
  {% endif %}
</aside>
```

- [ ] **Step 2: Create right sidebar template**

```twig
<aside class="feed-sidebar feed-sidebar--right">
  <div class="sidebar-widget">
    <h4 class="sidebar-widget__title">{{ trans('feed.trending') }}</h4>
    {% for item in trending %}
      <a href="{{ lang_url(item.url) }}" class="sidebar-widget__item">
        <span class="feed-badge feed-badge--{{ item.type }}">{{ item.badge }}</span>
        {{ item.title }}
      </a>
    {% endfor %}
  </div>

  <div class="sidebar-widget">
    <h4 class="sidebar-widget__title">{{ trans('feed.upcoming_events') }}</h4>
    {% for event in upcoming_events %}
      <a href="{{ lang_url(event.url) }}" class="sidebar-widget__item">
        <strong>{{ event.title }}</strong>
        <span class="sidebar-widget__meta">{{ event.date }}</span>
      </a>
    {% endfor %}
  </div>

  <div class="sidebar-widget">
    <h4 class="sidebar-widget__title">{{ trans('feed.suggested_communities') }}</h4>
    {% for community in suggested_communities %}
      <a href="{{ lang_url('/communities/' ~ community.slug) }}" class="sidebar-widget__item">
        {{ community.name }}
        {% if community.distance %}<span class="sidebar-widget__meta">{{ community.distance|round(1) }} km</span>{% endif %}
      </a>
    {% endfor %}
  </div>
</aside>
```

- [ ] **Step 3: Create create-post template**

```twig
<div class="feed-create">
  {% if account is defined and account.isAuthenticated() %}
    <div class="feed-create__prompt" id="create-post-trigger">
      <div class="feed-create__avatar">{{ account_initial }}</div>
      <span class="feed-create__placeholder">{{ trans('feed.whats_happening') }}</span>
    </div>
    <form class="feed-create__form" id="create-post-form" hidden>
      <textarea class="feed-create__textarea" name="body" placeholder="{{ trans('feed.whats_happening') }}" maxlength="5000" required></textarea>
      <div class="feed-create__actions">
        <select name="community_id" class="feed-create__community" required>
          {% for community in user_communities %}
            <option value="{{ community.id }}"{% if community.is_default %} selected{% endif %}>{{ community.name }}</option>
          {% endfor %}
        </select>
        <button type="submit" class="btn btn--primary">{{ trans('feed.post') }}</button>
      </div>
    </form>
  {% else %}
    <div class="feed-create__guest">
      <p>{{ trans('feed.join_conversation') }}</p>
      <a href="{{ lang_url('/login') }}" class="btn btn--secondary">{{ trans('nav.login') }}</a>
    </div>
  {% endif %}
</div>
```

- [ ] **Step 4: Restructure feed.html.twig with three-column grid**

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('page.title') }} — Minoo{% endblock %}

{% block content %}
  <div class="feed-layout">
    {% include "components/feed-sidebar-left.html.twig" %}

    <div class="feed-main">
      {% include "components/feed-create-post.html.twig" %}

      <nav class="feed-chips" aria-label="Filter feed">
        {# ... existing filter chips ... #}
      </nav>

      <div class="feed-container" id="feed" data-next-cursor="{{ nextCursor }}" data-active-filter="{{ activeFilter }}">
        {% for item in response.items %}
          {% include "components/feed-card.html.twig" with { item: item } only %}
        {% endfor %}
      </div>

      {% if nextCursor %}
        <div class="feed-sentinel" id="feed-sentinel">
          <div class="feed-card feed-card--loading" aria-hidden="true">
            <div class="feed-card__skeleton"></div>
          </div>
        </div>
      {% endif %}
    </div>

    {% include "components/feed-sidebar-right.html.twig" %}
  </div>

  {# ... existing infinite scroll JS ... #}
{% endblock %}
```

- [ ] **Step 5: Commit**

```bash
git add templates/feed.html.twig templates/components/feed-sidebar-left.html.twig \
  templates/components/feed-sidebar-right.html.twig templates/components/feed-create-post.html.twig
git commit -m "feat: three-column feed layout with sidebars and create-post box"
```

---

### Task 12: Community-Attributed Feed Card Template

**Files:**
- Modify: `templates/components/feed-card.html.twig`

- [ ] **Step 1: Rewrite card template with community attribution**

```twig
{# Unified feed card — community-attributed hybrid design #}
{% if item.type == 'welcome' %}
  <article class="feed-card feed-card--welcome" data-id="{{ item.id }}">
    <h3 class="feed-card__title">{{ item.title }}</h3>
    <p class="feed-card__meta">{{ trans('page.about_body') }}</p>
    <a href="{{ lang_url(item.url) }}" class="btn btn--secondary">{{ trans('page.about_cta') }}</a>
  </article>
{% elseif item.type == 'communities' %}
  <article class="feed-card feed-card--communities" data-id="{{ item.id }}">
    <div class="feed-card__attribution">
      <div class="feed-card__avatar feed-card__avatar--communities">🏘</div>
      <div class="feed-card__attribution-text">
        <span class="feed-card__source">{{ trans('feed.communities_near_you') }}</span>
      </div>
    </div>
    <h3 class="feed-card__title">{{ item.title }}</h3>
    <div class="feed-pills">
      {% for community in item.payload.communities|default([]) %}
        <a href="{{ lang_url('/communities/' ~ community.slug) }}" class="feed-pill">{{ community.name }}</a>
      {% endfor %}
    </div>
  </article>
{% elseif item.type == 'post' %}
  <article class="feed-card feed-card--post" data-id="{{ item.id }}" data-entity-type="post">
    <div class="feed-card__attribution">
      <div class="feed-card__avatar">{{ item.communityInitial|default('?') }}</div>
      <div class="feed-card__attribution-text">
        <a href="{{ lang_url('/communities/' ~ item.communitySlug) }}" class="feed-card__source">{{ item.communityName }}</a>
        <span class="feed-card__attribution-action">· {{ item.relativeTime|default('') }}</span>
      </div>
    </div>
    <p class="feed-card__body">{{ item.meta }}</p>
    {% include "components/feed-engagement.html.twig" with { item: item } only %}
  </article>
{% else %}
  <article class="feed-card feed-card--{{ item.type }}" data-id="{{ item.id }}" data-entity-type="{{ item.type }}">
    <div class="feed-card__attribution">
      <div class="feed-card__avatar feed-card__avatar--{{ item.type }}">{{ item.communityInitial|default(item.badge|slice(0,1)) }}</div>
      <div class="feed-card__attribution-text">
        {% if item.communityName %}
          <a href="{{ lang_url('/communities/' ~ item.communitySlug) }}" class="feed-card__source">{{ item.communityName }}</a>
          <span class="feed-card__attribution-action">{{ trans('feed.posted_' ~ item.type) }} · {{ item.relativeTime|default('') }}</span>
        {% else %}
          <span class="feed-card__source">Minoo</span>
          <span class="feed-card__attribution-action">· {{ item.badge }} · {{ item.relativeTime|default('') }}</span>
        {% endif %}
      </div>
    </div>
    <h3 class="feed-card__title"><a href="{{ lang_url(item.url) }}">{{ item.title }}</a></h3>
    {% if item.subtitle %}<p class="feed-card__subtitle">{{ item.subtitle }}</p>{% endif %}
    {% if item.meta %}
      <div class="feed-card__detail-box">
        {% if item.date %}<div class="feed-card__detail">📅 {{ item.date }}</div>{% endif %}
        {% if item.communityName and item.type == 'event' %}<div class="feed-card__detail">📍 {{ item.meta }}</div>{% endif %}
      </div>
    {% endif %}
    {% include "components/feed-engagement.html.twig" with { item: item } only %}
  </article>
{% endif %}
```

- [ ] **Step 2: Create engagement row partial**

Create `templates/components/feed-engagement.html.twig`:

```twig
{# Engagement counts + action buttons #}
{% if item.reactionCount is defined or item.commentCount is defined %}
  <div class="feed-card__engagement">
    {% if item.reactionCount|default(0) > 0 %}
      <span>👍 {{ item.reactionCount }} {{ trans('feed.interested') }}</span>
    {% endif %}
    {% if item.commentCount|default(0) > 0 %}
      <span>{{ item.commentCount }} {{ trans('feed.comments_count') }}</span>
    {% endif %}
  </div>
{% endif %}
<div class="feed-card__actions">
  <button class="feed-action" data-action="react" data-type="{{ item.type }}" data-id="{{ item.id }}">
    <span class="feed-action__icon">👍</span>
    <span class="feed-action__label">{{ trans('feed.action_' ~ item.type)|default(trans('feed.interested')) }}</span>
  </button>
  <button class="feed-action" data-action="comment" data-type="{{ item.type }}" data-id="{{ item.id }}">
    <span class="feed-action__icon">💬</span>
    <span class="feed-action__label">{{ trans('feed.comment') }}</span>
  </button>
  <button class="feed-action" data-action="share" data-url="{{ lang_url(item.url) }}" data-title="{{ item.title }}">
    <span class="feed-action__icon">↗</span>
    <span class="feed-action__label">{{ trans('feed.share') }}</span>
  </button>
</div>
<div class="feed-card__comments" data-type="{{ item.type }}" data-id="{{ item.id }}" hidden></div>
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/feed-card.html.twig templates/components/feed-engagement.html.twig
git commit -m "feat: community-attributed card design with engagement row"
```

---

### Task 13: FeedController — Sidebar Data

**Files:**
- Modify: `src/Controller/FeedController.php`

- [ ] **Step 1: Pass sidebar data to template**

Update `index()` to query and pass:
- `trending`: Top 5 entities by reaction count (fallback: newest 5)
- `upcoming_events`: Next 3 events by date
- `suggested_communities`: Nearby communities from location cookie
- `followed_communities`: Communities user follows (empty if anonymous)
- `user_communities`: For create-post community selector
- `account_initial`: First letter of user's display name

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All pass

- [ ] **Step 3: Commit**

```bash
git add src/Controller/FeedController.php
git commit -m "feat: pass sidebar and create-post data to feed template"
```

---

## Phase 4: Frontend — JavaScript Interactions (Task 14)

### Task 14: Engagement JavaScript

**Files:**
- Modify: `templates/feed.html.twig` (script block)

- [ ] **Step 1: Add engagement interaction JS**

Add to the `{% block scripts %}` at the end of `feed.html.twig`:

```javascript
(function() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };

  // Reaction toggling
  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('[data-action="react"]');
    if (!btn) return;
    const type = btn.dataset.type;
    const id = btn.dataset.id;
    const isActive = btn.classList.contains('feed-action--active');

    try {
      if (isActive) {
        await fetch(`/api/react/${type}/${id}`, { method: 'DELETE', headers });
        btn.classList.remove('feed-action--active');
      } else {
        const reactionType = getReactionType(type);
        await fetch('/api/react', {
          method: 'POST', headers,
          body: JSON.stringify({ target_type: type, target_id: id, reaction_type: reactionType })
        });
        btn.classList.add('feed-action--active');
      }
    } catch (err) { console.error('Reaction error:', err); }
  });

  // Comment expansion
  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('[data-action="comment"]');
    if (!btn) return;
    const card = btn.closest('.feed-card');
    const section = card.querySelector('.feed-card__comments');
    if (section.hidden) {
      section.hidden = false;
      if (!section.dataset.loaded) {
        const type = btn.dataset.type;
        const id = btn.dataset.id;
        const res = await fetch(`/api/comments/${type}/${id}`);
        const data = await res.json();
        section.innerHTML = renderComments(data.comments, type, id);
        section.dataset.loaded = '1';
      }
    } else {
      section.hidden = true;
    }
  });

  // Share
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="share"]');
    if (!btn) return;
    const url = window.location.origin + btn.dataset.url;
    if (navigator.share) {
      navigator.share({ url, title: btn.dataset.title });
    } else {
      navigator.clipboard.writeText(url).then(() => {
        btn.querySelector('.feed-action__label').textContent = 'Copied!';
        setTimeout(() => { btn.querySelector('.feed-action__label').textContent = 'Share'; }, 2000);
      });
    }
  });

  // Create post
  const trigger = document.getElementById('create-post-trigger');
  const form = document.getElementById('create-post-form');
  if (trigger && form) {
    trigger.addEventListener('click', () => { form.hidden = false; trigger.hidden = true; });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const body = form.querySelector('textarea').value.trim();
      const communityId = form.querySelector('select').value;
      if (!body) return;
      try {
        const res = await fetch('/api/post', {
          method: 'POST', headers,
          body: JSON.stringify({ body, community_id: communityId })
        });
        if (res.ok) location.reload();
      } catch (err) { console.error('Post error:', err); }
    });
  }

  function getReactionType(entityType) {
    const map = { event: 'interested', teaching: 'miigwech', business: 'recommend', post: 'miigwech' };
    return map[entityType] || 'interested';
  }

  function renderComments(comments, type, id) {
    let html = '<div class="feed-comments__list">';
    comments.forEach(c => {
      html += `<div class="feed-comment"><strong>User</strong> <span class="feed-comment__time">${c.relative_time}</span><p>${escapeHtml(c.body)}</p></div>`;
    });
    html += '</div>';
    html += `<form class="feed-comments__form" data-type="${type}" data-id="${id}">
      <input type="text" placeholder="Write a comment..." maxlength="2000" required>
      <button type="submit">Post</button>
    </form>`;
    return html;
  }

  // Comment submission
  document.addEventListener('submit', async function(e) {
    const form = e.target.closest('.feed-comments__form');
    if (!form) return;
    e.preventDefault();
    const input = form.querySelector('input');
    const body = input.value.trim();
    if (!body) return;
    try {
      const res = await fetch('/api/comment', {
        method: 'POST', headers,
        body: JSON.stringify({ target_type: form.dataset.type, target_id: form.dataset.id, body })
      });
      if (res.ok) {
        const data = await res.json();
        const list = form.previousElementSibling;
        list.insertAdjacentHTML('beforeend',
          `<div class="feed-comment"><strong>You</strong> <span class="feed-comment__time">just now</span><p>${escapeHtml(body)}</p></div>`
        );
        input.value = '';
      }
    } catch (err) { console.error('Comment error:', err); }
  });

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
})();
```

- [ ] **Step 2: Commit**

```bash
git add templates/feed.html.twig
git commit -m "feat: engagement interactions JS (react, comment, share, create post)"
```

---

## Phase 5: Visual Polish & Testing (Tasks 15–16)

### Task 15: Complete CSS Polish

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add all remaining component styles**

Styles for: `.feed-card__attribution`, `.feed-card__avatar`, `.feed-card__detail-box`, `.feed-card__engagement`, `.feed-card__actions`, `.feed-action`, `.feed-action--active`, `.feed-card__comments`, `.feed-comment`, `.feed-comments__form`, `.feed-create`, `.sidebar-nav`, `.sidebar-widget`, responsive overrides.

- [ ] **Step 2: Visual verification with Playwright**

Take screenshots at 1280px, 1024px, and 375px widths. Verify three-column, two-column, and mobile layouts.

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: complete feed CSS polish — cards, sidebars, engagement, responsive"
```

---

### Task 16: Playwright End-to-End Tests

**Files:**
- Create or update Playwright test files

- [ ] **Step 1: Test three-column layout renders**

Navigate to homepage, verify `.feed-layout` contains `.feed-sidebar--left`, `.feed-main`, `.feed-sidebar--right`.

- [ ] **Step 2: Test feed card structure**

Verify cards have `.feed-card__attribution`, `.feed-card__actions`, domain-colored top bars.

- [ ] **Step 3: Test reaction click updates UI**

Log in, click "Interested" button, verify `.feed-action--active` class is added.

- [ ] **Step 4: Test comment expansion**

Click "Comment" button, verify comment section expands, submit a comment, verify it appears.

- [ ] **Step 5: Test create post flow**

Log in, click create-post prompt, fill textarea, submit, verify page reloads with new post.

- [ ] **Step 6: Test responsive collapse**

Resize to 1024px — verify right sidebar hidden. Resize to 768px — verify left sidebar hidden.

- [ ] **Step 7: Commit**

```bash
git add tests/
git commit -m "test: Playwright e2e tests for social feed"
```

---

## GitHub Issues & Milestone

After all tasks are implemented, create a GitHub milestone and issues:

### Milestone: `Social Feed v1`

### Issues (one per phase):
1. **Engagement entity types & migrations** (Tasks 1–4)
2. **Engagement API endpoints** (Tasks 5–6)
3. **Feed integration — engagement counts & user posts** (Tasks 7–8)
4. **Three-column layout & sidebar templates** (Tasks 9–11)
5. **Community-attributed card redesign** (Task 12)
6. **Feed controller — sidebar data** (Task 13)
7. **Engagement JavaScript interactions** (Task 14)
8. **CSS polish & responsive** (Task 15)
9. **Playwright e2e tests** (Task 16)
