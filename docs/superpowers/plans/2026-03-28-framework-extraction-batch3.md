# Framework Extraction — Batch 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract engagement entities (Reaction, Comment, Follow) and messaging entities (MessageThread, ThreadMessage, ThreadParticipant) from Minoo into two new Waaseyaa framework packages.

**Architecture:** Two new packages: `waaseyaa/engagement` (3 entities + access policy + provider) and `waaseyaa/messaging` (3 entities + provider). Engagement has configurable reaction types via config. Messaging wires MercurePublisher (optional). Post entity stays in Minoo (has community_id). MessageDigestCommand stays in Minoo (has hardcoded Minoo URLs).

**Tech Stack:** PHP 8.4, PHPUnit 10.5, Waaseyaa entity system, Mercure

**Repos:**
- Framework: `/home/jones/dev/waaseyaa` (repo: `waaseyaa/framework`)
- Application: `/home/jones/dev/minoo` (repo: `waaseyaa/minoo`)

**Key difference from Minoo originals:**
- `Reaction::ALLOWED_REACTION_TYPES` becomes a constructor parameter instead of a class constant. The `EngagementServiceProvider` reads `engagement.reaction_types` from config and passes it to the entity type definition. The `Reaction` entity validates against the injected list.

---

## File Map

### Framework: `packages/engagement/` (new)

- `composer.json`
- `src/Reaction.php` — entity with configurable reaction types
- `src/Comment.php` — entity
- `src/Follow.php` — entity
- `src/EngagementAccessPolicy.php` — access policy for all 3 types
- `src/EngagementServiceProvider.php` — registers entities + policy, reads config
- `tests/Unit/ReactionTest.php`
- `tests/Unit/CommentTest.php`
- `tests/Unit/FollowTest.php`
- `tests/Unit/EngagementAccessPolicyTest.php`

### Framework: `packages/messaging/` (new)

- `composer.json`
- `src/MessageThread.php` — entity
- `src/ThreadMessage.php` — entity
- `src/ThreadParticipant.php` — entity
- `src/MessagingServiceProvider.php` — registers entities
- `tests/Unit/MessageThreadTest.php`
- `tests/Unit/ThreadMessageTest.php`
- `tests/Unit/ThreadParticipantTest.php`

### Minoo side

**Modified (import swaps):**
- `src/Provider/EngagementServiceProvider.php` — remove reaction/comment/follow entity types (keep post + routes + UploadHandler)
- `src/Provider/MessagingServiceProvider.php` — remove entity types + MercurePublisher singleton (keep routes only)
- `src/Controller/EngagementController.php` — swap `Minoo\Entity\Reaction` import
- `src/Support/MessageDigestCommand.php` — stays in Minoo (hardcoded URLs)

**Deleted:**
- `src/Entity/Reaction.php`
- `src/Entity/Comment.php`
- `src/Entity/Follow.php`
- `src/Entity/MessageThread.php`
- `src/Entity/ThreadMessage.php`
- `src/Entity/ThreadParticipant.php`
- `src/Access/EngagementAccessPolicy.php`
- Tests for the above (covered by framework tests)

---

## Task 1: Engagement package — Reaction entity

**Files:**
- Create: `packages/engagement/composer.json`
- Create: `packages/engagement/src/Reaction.php`
- Create: `packages/engagement/tests/Unit/ReactionTest.php`

- [ ] **Step 1: Create package scaffold**

Create `packages/engagement/composer.json`:

```json
{
    "name": "waaseyaa/engagement",
    "description": "Social engagement entities (reactions, comments, follows) for Waaseyaa",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {
            "type": "path",
            "url": "../entity"
        },
        {
            "type": "path",
            "url": "../access"
        },
        {
            "type": "path",
            "url": "../foundation"
        }
    ],
    "require": {
        "php": ">=8.4",
        "waaseyaa/entity": "^0.1",
        "waaseyaa/access": "^0.1",
        "waaseyaa/foundation": "^0.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Engagement\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\Engagement\\Tests\\": "tests/"
        }
    },
    "extra": {
        "waaseyaa": {
            "providers": [
                "Waaseyaa\\Engagement\\EngagementServiceProvider"
            ]
        },
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write Reaction test**

Create `packages/engagement/tests/Unit/ReactionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Engagement\Reaction;

#[CoversClass(Reaction::class)]
final class ReactionTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $reaction = new Reaction([
            'user_id' => 1,
            'target_type' => 'post',
            'target_id' => 42,
            'reaction_type' => 'like',
        ]);

        $this->assertSame(1, (int) $reaction->get('user_id'));
        $this->assertSame('like', $reaction->get('reaction_type'));
        $this->assertNotNull($reaction->get('created_at'));
    }

    #[Test]
    public function requires_user_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id');
        new Reaction(['target_type' => 'post', 'target_id' => 1, 'reaction_type' => 'like']);
    }

    #[Test]
    public function requires_reaction_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reaction_type');
        new Reaction(['user_id' => 1, 'target_type' => 'post', 'target_id' => 1]);
    }

    #[Test]
    public function rejects_invalid_reaction_type_with_defaults(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reaction_type');
        new Reaction([
            'user_id' => 1,
            'target_type' => 'post',
            'target_id' => 1,
            'reaction_type' => 'invalid',
        ]);
    }

    #[Test]
    public function accepts_custom_allowed_types(): void
    {
        $reaction = new Reaction(
            values: [
                'user_id' => 1,
                'target_type' => 'post',
                'target_id' => 1,
                'reaction_type' => 'miigwech',
            ],
            allowedReactionTypes: ['like', 'miigwech'],
        );

        $this->assertSame('miigwech', $reaction->get('reaction_type'));
    }

    #[Test]
    public function rejects_type_not_in_custom_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Reaction(
            values: [
                'user_id' => 1,
                'target_type' => 'post',
                'target_id' => 1,
                'reaction_type' => 'love',
            ],
            allowedReactionTypes: ['like', 'miigwech'],
        );
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/engagement/tests/Unit/ReactionTest.php`
Expected: FAIL — class not found

- [ ] **Step 4: Write Reaction entity**

Create `packages/engagement/src/Reaction.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Entity\ContentEntityBase;

final class Reaction extends ContentEntityBase
{
    protected string $entityTypeId = 'reaction';

    protected array $entityKeys = [
        'id' => 'rid',
        'uuid' => 'uuid',
        'label' => 'reaction_type',
    ];

    public const array DEFAULT_REACTION_TYPES = ['like', 'love', 'celebrate'];

    /**
     * @param array<string, mixed> $values
     * @param list<string>|null $allowedReactionTypes Custom allowed types (null = use defaults)
     */
    public function __construct(array $values = [], ?array $allowedReactionTypes = null)
    {
        foreach (['user_id', 'target_type', 'target_id', 'reaction_type'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $allowed = $allowedReactionTypes ?? self::DEFAULT_REACTION_TYPES;
        if (!in_array($values['reaction_type'], $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid reaction_type '{$values['reaction_type']}'. Allowed: " . implode(', ', $allowed),
            );
        }

        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 5: Run tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/engagement/tests/Unit/ReactionTest.php`
Expected: OK (6 tests)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/engagement/
git commit -m "feat(engagement): new package with Reaction entity and configurable types

Refs: #701"
```

---

## Task 2: Engagement — Comment + Follow entities

**Files:**
- Create: `packages/engagement/src/Comment.php`
- Create: `packages/engagement/src/Follow.php`
- Create: `packages/engagement/tests/Unit/CommentTest.php`
- Create: `packages/engagement/tests/Unit/FollowTest.php`

- [ ] **Step 1: Write Comment test**

Create `packages/engagement/tests/Unit/CommentTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Engagement\Comment;

#[CoversClass(Comment::class)]
final class CommentTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $comment = new Comment([
            'user_id' => 1,
            'target_type' => 'post',
            'target_id' => 42,
            'body' => 'Great post!',
        ]);

        $this->assertSame('Great post!', $comment->get('body'));
        $this->assertSame(1, (int) $comment->get('status'));
        $this->assertNotNull($comment->get('created_at'));
    }

    #[Test]
    public function requires_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('body');
        new Comment(['user_id' => 1, 'target_type' => 'post', 'target_id' => 1]);
    }

    #[Test]
    public function requires_user_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id');
        new Comment(['target_type' => 'post', 'target_id' => 1, 'body' => 'test']);
    }
}
```

- [ ] **Step 2: Write Comment entity**

Create `packages/engagement/src/Comment.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Entity\ContentEntityBase;

final class Comment extends ContentEntityBase
{
    protected string $entityTypeId = 'comment';

    protected array $entityKeys = [
        'id' => 'cid',
        'uuid' => 'uuid',
        'label' => 'body',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['user_id', 'target_type', 'target_id', 'body'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 3: Write Follow test**

Create `packages/engagement/tests/Unit/FollowTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Engagement\Follow;

#[CoversClass(Follow::class)]
final class FollowTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $follow = new Follow([
            'user_id' => 1,
            'target_type' => 'community',
            'target_id' => 42,
        ]);

        $this->assertSame(1, (int) $follow->get('user_id'));
        $this->assertSame('community', $follow->get('target_type'));
        $this->assertNotNull($follow->get('created_at'));
    }

    #[Test]
    public function requires_user_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id');
        new Follow(['target_type' => 'post', 'target_id' => 1]);
    }

    #[Test]
    public function requires_target_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('target_type');
        new Follow(['user_id' => 1, 'target_id' => 1]);
    }
}
```

- [ ] **Step 4: Write Follow entity**

Create `packages/engagement/src/Follow.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Entity\ContentEntityBase;

final class Follow extends ContentEntityBase
{
    protected string $entityTypeId = 'follow';

    protected array $entityKeys = [
        'id' => 'fid',
        'uuid' => 'uuid',
        'label' => 'target_type',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['user_id', 'target_type', 'target_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 5: Run all engagement tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/engagement/tests/`
Expected: OK (12 tests)

- [ ] **Step 6: Commit**

```bash
git add packages/engagement/
git commit -m "feat(engagement): add Comment and Follow entities

Refs: #701"
```

---

## Task 3: Engagement — EngagementAccessPolicy + EngagementServiceProvider

**Files:**
- Create: `packages/engagement/src/EngagementAccessPolicy.php`
- Create: `packages/engagement/src/EngagementServiceProvider.php`
- Create: `packages/engagement/tests/Unit/EngagementAccessPolicyTest.php`

- [ ] **Step 1: Write access policy test**

Create `packages/engagement/tests/Unit/EngagementAccessPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Engagement\EngagementAccessPolicy;
use Waaseyaa\Entity\EntityInterface;

#[CoversClass(EngagementAccessPolicy::class)]
final class EngagementAccessPolicyTest extends TestCase
{
    private EngagementAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new EngagementAccessPolicy();
    }

    #[Test]
    public function applies_to_engagement_types(): void
    {
        $this->assertTrue($this->policy->appliesTo('reaction'));
        $this->assertTrue($this->policy->appliesTo('comment'));
        $this->assertTrue($this->policy->appliesTo('follow'));
        $this->assertFalse($this->policy->appliesTo('post'));
    }

    #[Test]
    public function admin_is_always_allowed(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->with('administer content')->willReturn(true);
        $entity = $this->createMock(EntityInterface::class);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function view_is_publicly_allowed(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $entity = $this->createMock(EntityInterface::class);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function owner_can_delete(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('id')->willReturn(42);

        $entity = $this->createMock(EntityInterface::class);
        $entity->method('get')->with('user_id')->willReturn(42);

        $result = $this->policy->access($entity, 'delete', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function non_owner_cannot_delete(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('id')->willReturn(99);

        $entity = $this->createMock(EntityInterface::class);
        $entity->method('get')->with('user_id')->willReturn(42);

        $result = $this->policy->access($entity, 'delete', $account);
        $this->assertTrue($result->isNeutral());
    }

    #[Test]
    public function authenticated_can_create(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('isAuthenticated')->willReturn(true);

        $result = $this->policy->createAccess('reaction', 'reaction', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('isAuthenticated')->willReturn(false);

        $result = $this->policy->createAccess('reaction', 'reaction', $account);
        $this->assertTrue($result->isNeutral());
    }
}
```

- [ ] **Step 2: Write EngagementAccessPolicy**

Create `packages/engagement/src/EngagementAccessPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['reaction', 'comment', 'follow'])]
final class EngagementAccessPolicy implements AccessPolicyInterface
{
    /** @var list<string> */
    private const TYPES = ['reaction', 'comment', 'follow'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::allowed('Engagement entities are publicly viewable.'),
            'delete' => $this->ownerCheck($entity, $account),
            default => AccessResult::neutral('Non-admin cannot modify engagement entities.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated users may create engagement entities.');
        }

        return AccessResult::neutral('Anonymous users cannot create engagement entities.');
    }

    private function ownerCheck(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        $userId = $entity->get('user_id');

        if ($userId !== null && (int) $userId === (int) $account->id()) {
            return AccessResult::allowed('Owner may delete own engagement entity.');
        }

        return AccessResult::neutral('Non-owner cannot delete engagement entity.');
    }
}
```

- [ ] **Step 3: Write EngagementServiceProvider**

Create `packages/engagement/src/EngagementServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $reactionTypes = $this->config['engagement']['reaction_types'] ?? Reaction::DEFAULT_REACTION_TYPES;

        $this->entityType(new EntityType(
            id: 'reaction',
            label: 'Reaction',
            class: Reaction::class,
            keys: ['id' => 'rid', 'uuid' => 'uuid', 'label' => 'reaction_type'],
            group: 'engagement',
            fieldDefinitions: [
                'reaction_type' => ['type' => 'string', 'label' => 'Reaction Type', 'weight' => 0],
                'user_id' => ['type' => 'integer', 'label' => 'User ID', 'weight' => 1],
                'target_type' => ['type' => 'string', 'label' => 'Target Entity Type', 'weight' => 2],
                'target_id' => ['type' => 'integer', 'label' => 'Target Entity ID', 'weight' => 3],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'comment',
            label: 'Comment',
            class: Comment::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'body'],
            group: 'engagement',
            fieldDefinitions: [
                'body' => ['type' => 'text_long', 'label' => 'Body', 'weight' => 0],
                'user_id' => ['type' => 'integer', 'label' => 'User ID', 'weight' => 1],
                'target_type' => ['type' => 'string', 'label' => 'Target Entity Type', 'weight' => 2],
                'target_id' => ['type' => 'integer', 'label' => 'Target Entity ID', 'weight' => 3],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 5, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'follow',
            label: 'Follow',
            class: Follow::class,
            keys: ['id' => 'fid', 'uuid' => 'uuid', 'label' => 'target_type'],
            group: 'engagement',
            fieldDefinitions: [
                'user_id' => ['type' => 'integer', 'label' => 'User ID', 'weight' => 0],
                'target_type' => ['type' => 'string', 'label' => 'Target Entity Type', 'weight' => 1],
                'target_id' => ['type' => 'integer', 'label' => 'Target Entity ID', 'weight' => 2],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
            ],
        ));
    }
}
```

- [ ] **Step 4: Run all engagement tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/engagement/tests/`
Expected: OK (19 tests)

- [ ] **Step 5: Commit**

```bash
git add packages/engagement/
git commit -m "feat(engagement): add EngagementAccessPolicy and EngagementServiceProvider

Refs: #701"
```

---

## Task 4: Messaging package — MessageThread entity

**Files:**
- Create: `packages/messaging/composer.json`
- Create: `packages/messaging/src/MessageThread.php`
- Create: `packages/messaging/tests/Unit/MessageThreadTest.php`

- [ ] **Step 1: Create package scaffold**

Create `packages/messaging/composer.json`:

```json
{
    "name": "waaseyaa/messaging",
    "description": "Direct messaging infrastructure for Waaseyaa: threads, messages, participants",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {
            "type": "path",
            "url": "../entity"
        },
        {
            "type": "path",
            "url": "../foundation"
        },
        {
            "type": "path",
            "url": "../mercure"
        }
    ],
    "require": {
        "php": ">=8.4",
        "waaseyaa/entity": "^0.1",
        "waaseyaa/foundation": "^0.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Messaging\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\Messaging\\Tests\\": "tests/"
        }
    },
    "extra": {
        "waaseyaa": {
            "providers": [
                "Waaseyaa\\Messaging\\MessagingServiceProvider"
            ]
        },
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write MessageThread test**

Create `packages/messaging/tests/Unit/MessageThreadTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Messaging\MessageThread;

#[CoversClass(MessageThread::class)]
final class MessageThreadTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $thread = new MessageThread(['created_by' => 1]);
        $this->assertSame(1, (int) $thread->get('created_by'));
        $this->assertSame('', $thread->get('title'));
        $this->assertSame('direct', $thread->get('thread_type'));
        $this->assertNotNull($thread->get('created_at'));
    }

    #[Test]
    public function requires_created_by(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('created_by');
        new MessageThread([]);
    }

    #[Test]
    public function rejects_invalid_thread_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('thread_type');
        new MessageThread(['created_by' => 1, 'thread_type' => 'invalid']);
    }

    #[Test]
    public function accepts_group_thread_type(): void
    {
        $thread = new MessageThread(['created_by' => 1, 'thread_type' => 'group']);
        $this->assertSame('group', $thread->get('thread_type'));
    }
}
```

- [ ] **Step 3: Write MessageThread entity**

Create `packages/messaging/src/MessageThread.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\ContentEntityBase;

final class MessageThread extends ContentEntityBase
{
    protected string $entityTypeId = 'message_thread';

    protected array $entityKeys = [
        'id' => 'mtid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!isset($values['created_by'])) {
            throw new \InvalidArgumentException('Missing required field: created_by');
        }

        if (!array_key_exists('title', $values)) {
            $values['title'] = '';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = $values['created_at'];
        }
        if (!array_key_exists('thread_type', $values)) {
            $values['thread_type'] = 'direct';
        }
        if (!in_array($values['thread_type'], ['direct', 'group'], true)) {
            throw new \InvalidArgumentException('thread_type must be direct or group');
        }
        if (!array_key_exists('last_message_at', $values)) {
            $values['last_message_at'] = $values['created_at'];
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/messaging/tests/Unit/MessageThreadTest.php`
Expected: OK (4 tests)

- [ ] **Step 5: Commit**

```bash
git add packages/messaging/
git commit -m "feat(messaging): new package with MessageThread entity

Refs: #702"
```

---

## Task 5: Messaging — ThreadMessage + ThreadParticipant entities

**Files:**
- Create: `packages/messaging/src/ThreadMessage.php`
- Create: `packages/messaging/src/ThreadParticipant.php`
- Create: `packages/messaging/tests/Unit/ThreadMessageTest.php`
- Create: `packages/messaging/tests/Unit/ThreadParticipantTest.php`

- [ ] **Step 1: Write ThreadMessage test**

Create `packages/messaging/tests/Unit/ThreadMessageTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Messaging\ThreadMessage;

#[CoversClass(ThreadMessage::class)]
final class ThreadMessageTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $msg = new ThreadMessage(['thread_id' => 1, 'sender_id' => 2, 'body' => 'Hello']);
        $this->assertSame('Hello', $msg->get('body'));
        $this->assertSame(1, (int) $msg->get('status'));
        $this->assertNull($msg->get('edited_at'));
    }

    #[Test]
    public function trims_body(): void
    {
        $msg = new ThreadMessage(['thread_id' => 1, 'sender_id' => 2, 'body' => '  Hello  ']);
        $this->assertSame('Hello', $msg->get('body'));
    }

    #[Test]
    public function rejects_empty_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('1-2000');
        new ThreadMessage(['thread_id' => 1, 'sender_id' => 2, 'body' => '   ']);
    }

    #[Test]
    public function rejects_body_over_2000_chars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ThreadMessage(['thread_id' => 1, 'sender_id' => 2, 'body' => str_repeat('a', 2001)]);
    }

    #[Test]
    public function requires_thread_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('thread_id');
        new ThreadMessage(['sender_id' => 1, 'body' => 'test']);
    }
}
```

- [ ] **Step 2: Write ThreadMessage entity**

Create `packages/messaging/src/ThreadMessage.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\ContentEntityBase;

final class ThreadMessage extends ContentEntityBase
{
    protected string $entityTypeId = 'thread_message';

    protected array $entityKeys = [
        'id' => 'tmid',
        'uuid' => 'uuid',
        'label' => 'body',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['thread_id', 'sender_id', 'body'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $body = trim((string) $values['body']);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new \InvalidArgumentException('Body must be 1-2000 characters');
        }

        $values['body'] = $body;
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }
        if (!array_key_exists('edited_at', $values)) {
            $values['edited_at'] = null;
        }
        if (!array_key_exists('deleted_at', $values)) {
            $values['deleted_at'] = null;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 3: Write ThreadParticipant test**

Create `packages/messaging/tests/Unit/ThreadParticipantTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Messaging\ThreadParticipant;

#[CoversClass(ThreadParticipant::class)]
final class ThreadParticipantTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $p = new ThreadParticipant(['thread_id' => 1, 'user_id' => 2, 'thread_creator_id' => 1]);
        $this->assertSame('member', $p->get('role'));
        $this->assertNotNull($p->get('joined_at'));
        $this->assertSame(0, (int) $p->get('last_read_at'));
    }

    #[Test]
    public function accepts_owner_role(): void
    {
        $p = new ThreadParticipant(['thread_id' => 1, 'user_id' => 2, 'thread_creator_id' => 1, 'role' => 'owner']);
        $this->assertSame('owner', $p->get('role'));
    }

    #[Test]
    public function rejects_invalid_role(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role');
        new ThreadParticipant(['thread_id' => 1, 'user_id' => 2, 'thread_creator_id' => 1, 'role' => 'admin']);
    }

    #[Test]
    public function requires_thread_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('thread_id');
        new ThreadParticipant(['user_id' => 2, 'thread_creator_id' => 1]);
    }
}
```

- [ ] **Step 4: Write ThreadParticipant entity**

Create `packages/messaging/src/ThreadParticipant.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\ContentEntityBase;

final class ThreadParticipant extends ContentEntityBase
{
    protected string $entityTypeId = 'thread_participant';

    protected array $entityKeys = [
        'id' => 'tpid',
        'uuid' => 'uuid',
        'label' => 'role',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['thread_id', 'user_id', 'thread_creator_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('role', $values)) {
            $values['role'] = 'member';
        }
        if (!in_array((string) $values['role'], ['owner', 'member'], true)) {
            throw new \InvalidArgumentException('Invalid role: ' . (string) $values['role']);
        }
        if (!array_key_exists('joined_at', $values)) {
            $values['joined_at'] = time();
        }
        if (!array_key_exists('last_read_at', $values)) {
            $values['last_read_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 5: Write MessagingServiceProvider**

Create `packages/messaging/src/MessagingServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'message_thread',
            label: 'Message Thread',
            class: MessageThread::class,
            keys: ['id' => 'mtid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'messaging',
            fieldDefinitions: [
                'title' => ['type' => 'string', 'label' => 'Title', 'weight' => 0],
                'created_by' => ['type' => 'integer', 'label' => 'Created By', 'weight' => 1],
                'thread_type' => ['type' => 'string', 'label' => 'Thread Type', 'weight' => 2, 'default' => 'direct'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 11],
                'last_message_at' => ['type' => 'timestamp', 'label' => 'Last Message At', 'weight' => 12, 'default' => 0],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'thread_participant',
            label: 'Thread Participant',
            class: ThreadParticipant::class,
            keys: ['id' => 'tpid', 'uuid' => 'uuid', 'label' => 'role'],
            group: 'messaging',
            fieldDefinitions: [
                'thread_id' => ['type' => 'integer', 'label' => 'Thread ID', 'weight' => 0],
                'user_id' => ['type' => 'integer', 'label' => 'User ID', 'weight' => 1],
                'thread_creator_id' => ['type' => 'integer', 'label' => 'Thread Creator ID', 'weight' => 2],
                'role' => ['type' => 'string', 'label' => 'Role', 'weight' => 3, 'default' => 'member'],
                'joined_at' => ['type' => 'timestamp', 'label' => 'Joined', 'weight' => 10],
                'last_read_at' => ['type' => 'timestamp', 'label' => 'Last Read', 'weight' => 11, 'default' => 0],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'thread_message',
            label: 'Thread Message',
            class: ThreadMessage::class,
            keys: ['id' => 'tmid', 'uuid' => 'uuid', 'label' => 'body'],
            group: 'messaging',
            fieldDefinitions: [
                'thread_id' => ['type' => 'integer', 'label' => 'Thread ID', 'weight' => 0],
                'sender_id' => ['type' => 'integer', 'label' => 'Sender ID', 'weight' => 1],
                'body' => ['type' => 'text_long', 'label' => 'Body', 'weight' => 2],
                'status' => ['type' => 'boolean', 'label' => 'Status', 'weight' => 3, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
                'edited_at' => ['type' => 'timestamp', 'label' => 'Edited At', 'weight' => 11, 'default' => null],
                'deleted_at' => ['type' => 'timestamp', 'label' => 'Deleted At', 'weight' => 12, 'default' => null],
            ],
        ));
    }
}
```

- [ ] **Step 6: Run all messaging tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/messaging/tests/`
Expected: OK (13 tests)

- [ ] **Step 7: Commit**

```bash
git add packages/messaging/
git commit -m "feat(messaging): add ThreadMessage, ThreadParticipant, and MessagingServiceProvider

Refs: #702"
```

---

## Task 6: Framework PRs, merge, tag, split repos

- [ ] **Step 1: Create and merge engagement PR**
- [ ] **Step 2: Create and merge messaging PR**
- [ ] **Step 3: Tag `v0.1.0-alpha.65`**
- [ ] **Step 4: Create split repos** — `gh repo create waaseyaa/engagement` and `waaseyaa/messaging` (add to Packagist)
- [ ] **Step 5: Push split repos** — subtree split + push + tag for engagement, messaging

---

## Task 7: Minoo import swap

- [ ] **Step 1: Update composer.json** — add `waaseyaa/engagement` and `waaseyaa/messaging`, run `composer update`

- [ ] **Step 2: Swap entity imports**

```bash
# Engagement entities
sed -i 's/use Minoo\\Entity\\Reaction;/use Waaseyaa\\Engagement\\Reaction;/g' src/Controller/EngagementController.php
sed -i 's/use Minoo\\Entity\\Comment;/use Waaseyaa\\Engagement\\Comment;/g' src/Provider/EngagementServiceProvider.php
sed -i 's/use Minoo\\Entity\\Follow;/use Waaseyaa\\Engagement\\Follow;/g' src/Provider/EngagementServiceProvider.php
sed -i 's/use Minoo\\Entity\\Reaction;/use Waaseyaa\\Engagement\\Reaction;/g' src/Provider/EngagementServiceProvider.php

# Messaging entities
sed -i 's/use Minoo\\Entity\\MessageThread;/use Waaseyaa\\Messaging\\MessageThread;/g' src/Provider/MessagingServiceProvider.php
sed -i 's/use Minoo\\Entity\\ThreadMessage;/use Waaseyaa\\Messaging\\ThreadMessage;/g' src/Provider/MessagingServiceProvider.php
sed -i 's/use Minoo\\Entity\\ThreadParticipant;/use Waaseyaa\\Messaging\\ThreadParticipant;/g' src/Provider/MessagingServiceProvider.php
```

- [ ] **Step 3: Simplify EngagementServiceProvider** — remove reaction/comment/follow entity type registrations (framework handles them). Keep: `post` entity type, `UploadHandler` singleton, routes.

- [ ] **Step 4: Simplify MessagingServiceProvider** — remove all entity type registrations + MercurePublisher singleton (framework handles both). Keep: routes only.

- [ ] **Step 5: Delete old Minoo entity files**

```bash
rm src/Entity/Reaction.php src/Entity/Comment.php src/Entity/Follow.php
rm src/Entity/MessageThread.php src/Entity/ThreadMessage.php src/Entity/ThreadParticipant.php
rm src/Access/EngagementAccessPolicy.php
```

- [ ] **Step 6: Delete Minoo tests covered by framework**

```bash
rm tests/Minoo/Unit/Entity/ReactionTest.php tests/Minoo/Unit/Entity/CommentTest.php tests/Minoo/Unit/Entity/FollowTest.php
rm tests/Minoo/Unit/Entity/MessageThreadTest.php tests/Minoo/Unit/Entity/ThreadMessageTest.php tests/Minoo/Unit/Entity/ThreadParticipantTest.php
rm tests/Minoo/Unit/Access/EngagementAccessPolicyTest.php
```

- [ ] **Step 7: Add Minoo config** — `engagement.reaction_types`

```php
'engagement' => [
    'reaction_types' => ['like', 'interested', 'recommend', 'miigwech', 'connect'],
],
```

- [ ] **Step 8: Update remaining imports** — check for any `Minoo\Entity\Reaction` etc in test files

- [ ] **Step 9: Clear cache, run tests**

```bash
rm -f storage/framework/packages.php
composer dump-autoload
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 10: Commit and create PR**

```bash
git add -A
git commit -m "refactor: swap Minoo engagement/messaging for framework packages (Batch 3)"
git push -u origin HEAD
gh pr create --title "refactor: swap Minoo engagement/messaging for framework packages (Batch 3)"
```
