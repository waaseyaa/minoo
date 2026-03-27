# Messaging System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Facebook Messenger-style real-time messaging system with Mercure SSE, group threads, read receipts, typing indicators, message editing/deletion, user blocking, email digests, and a header notification popover.

**Architecture:** Thin JS client + Mercure SSE hub. PHP controllers publish events to Mercure via HTTP POST. Browser subscribes via native EventSource API. No JS framework, no build step. ES modules in `public/js/messaging/`. Email digests via scheduled CLI command using existing MailService + SendGrid.

**Tech Stack:** PHP 8.4, Waaseyaa framework, Mercure hub (standalone Go binary), vanilla JS ES modules, EventSource API, SendGrid, SQLite

**Spec:** `docs/specs/2026-03-27-messaging-system-design.md`

---

## File Structure

### PHP — New Files

| File | Responsibility |
|---|---|
| `src/Entity/UserBlock.php` | Block entity (blocker_id, blocked_id, created_at) |
| `src/Provider/BlockServiceProvider.php` | Registers user_block entity type + block API routes |
| `src/Access/BlockAccessPolicy.php` | Only blocker can view/delete their blocks |
| `src/Controller/BlockController.php` | CRUD endpoints for /api/blocks |
| `src/Support/MercurePublisher.php` | HTTP POST wrapper for publishing Mercure events |
| `src/Support/MessageDigestCommand.php` | CLI command for email digest (bin/waaseyaa messaging:digest) |
| `config/messaging.php` | Messaging configuration (Mercure URLs, digest intervals, limits) |
| `migrations/20260328_100000_add_thread_type_to_message_thread.php` | Add thread_type field |
| `migrations/20260328_100100_add_last_message_at_to_message_thread.php` | Add last_message_at field |
| `migrations/20260328_100200_add_edited_at_to_thread_message.php` | Add edited_at field |
| `migrations/20260328_100300_add_deleted_at_to_thread_message.php` | Add deleted_at field |
| `migrations/20260328_100400_create_user_block_table.php` | user_block table |

### PHP — Modified Files

| File | Changes |
|---|---|
| `src/Entity/MessageThread.php` | Add thread_type, last_message_at defaults |
| `src/Entity/ThreadMessage.php` | Add edited_at, deleted_at defaults |
| `src/Provider/MessagingServiceProvider.php` | Add new field definitions + new routes |
| `src/Access/MessagingAccessPolicy.php` | Add sender-only edit/delete for thread_message |
| `src/Controller/MessagingController.php` | Add editMessage, deleteMessage, markRead, typing, unreadCount + Mercure publishing + block checks |
| `templates/base.html.twig` | Add message icon + popover in header, load popover.js |
| `templates/messages.html.twig` | Replace inline JS with ES module import |
| `templates/components/user-menu.html.twig` | Add messages link |
| `public/css/minoo.css` | Messaging component styles (chat bubbles, popover, responsive) |
| `composer.json` | Add extra.waaseyaa.providers[] entry for BlockServiceProvider |

### JavaScript — New Files

| File | Responsibility |
|---|---|
| `public/js/messaging/index.js` | Entry point — init modules, wire EventSource |
| `public/js/messaging/mercure.js` | Mercure connection manager — subscribe, reconnect, polling fallback |
| `public/js/messaging/thread-list.js` | Sidebar thread list rendering |
| `public/js/messaging/message-view.js` | Chat area — bubbles, scroll, edit/delete UI |
| `public/js/messaging/compose.js` | Input bar — auto-grow, send, typing broadcast |
| `public/js/messaging/typing.js` | Typing indicator display + debounced POST |
| `public/js/messaging/popover.js` | Header popover — unread badge, recent threads |

### Tests — New Files

| File | Covers |
|---|---|
| `tests/Minoo/Unit/Entity/UserBlockTest.php` | UserBlock entity |
| `tests/Minoo/Unit/Access/BlockAccessPolicyTest.php` | BlockAccessPolicy |
| `tests/Minoo/Unit/Controller/BlockControllerTest.php` | BlockController endpoints |
| `tests/Minoo/Unit/Support/MercurePublisherTest.php` | MercurePublisher |
| `tests/Minoo/Unit/Support/MessageDigestCommandTest.php` | Digest command logic |

### Tests — Modified Files

| File | Changes |
|---|---|
| `tests/Minoo/Unit/Entity/MessageThreadTest.php` | Add tests for thread_type, last_message_at |
| `tests/Minoo/Unit/Entity/ThreadMessageTest.php` | Add tests for edited_at, deleted_at |
| `tests/Minoo/Unit/Controller/MessagingControllerTest.php` | Add tests for new endpoints |
| `tests/Minoo/Unit/Access/MessagingAccessPolicyTest.php` | Add tests for sender-only edit/delete |

---

## Task 1: Configuration

**Files:**
- Create: `config/messaging.php`

- [ ] **Step 1: Create messaging config**

```php
<?php

declare(strict_types=1);

return [
    'max_message_length' => 2000,
    'typing_indicator_ttl' => 5,
    'digest_interval' => '4h',
    'digest_debounce' => 15,
    'digest_active_skip' => 30,
    'mercure_hub_url' => env('MERCURE_HUB_URL', 'http://localhost:3000/.well-known/mercure'),
    'mercure_publisher_jwt' => env('MERCURE_PUBLISHER_JWT', ''),
    'mercure_subscriber_jwt_secret' => env('MERCURE_SUBSCRIBER_JWT_SECRET', ''),
    'polling_fallback_interval' => 10,
];
```

- [ ] **Step 2: Commit**

```bash
git add config/messaging.php
git commit -m "feat(#575): add messaging configuration"
```

---

## Task 2: Entity Model — MessageThread Fields

**Files:**
- Modify: `src/Entity/MessageThread.php`
- Modify: `src/Provider/MessagingServiceProvider.php`
- Modify: `tests/Minoo/Unit/Entity/MessageThreadTest.php`
- Create: `migrations/20260328_100000_add_thread_type_to_message_thread.php`
- Create: `migrations/20260328_100100_add_last_message_at_to_message_thread.php`

- [ ] **Step 1: Write failing tests for new fields**

Add to `tests/Minoo/Unit/Entity/MessageThreadTest.php`:

```php
#[Test]
public function it_defaults_thread_type_to_direct(): void
{
    $thread = new MessageThread(['created_by' => 1]);
    $this->assertSame('direct', $thread->get('thread_type'));
}

#[Test]
public function it_accepts_group_thread_type(): void
{
    $thread = new MessageThread(['created_by' => 1, 'thread_type' => 'group']);
    $this->assertSame('group', $thread->get('thread_type'));
}

#[Test]
public function it_rejects_invalid_thread_type(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new MessageThread(['created_by' => 1, 'thread_type' => 'invalid']);
}

#[Test]
public function it_defaults_last_message_at_to_created_at(): void
{
    $thread = new MessageThread(['created_by' => 1]);
    $this->assertSame($thread->get('created_at'), $thread->get('last_message_at'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/MessageThreadTest.php -v
```

Expected: 4 failures (fields don't exist yet).

- [ ] **Step 3: Update MessageThread entity**

Replace `src/Entity/MessageThread.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class MessageThread extends ContentEntityBase
{
    protected string $entityTypeId = 'message_thread';

    protected array $entityKeys = [
        'id' => 'mtid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    private const VALID_THREAD_TYPES = ['direct', 'group'];

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
        if (!in_array($values['thread_type'], self::VALID_THREAD_TYPES, true)) {
            throw new \InvalidArgumentException('thread_type must be direct or group');
        }
        if (!array_key_exists('last_message_at', $values)) {
            $values['last_message_at'] = $values['created_at'];
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Update field definitions in MessagingServiceProvider**

Add to the `message_thread` EntityType `fieldDefinitions` array in `src/Provider/MessagingServiceProvider.php`, after the `'updated_at'` entry:

```php
'thread_type' => ['type' => 'string', 'label' => 'Thread Type', 'weight' => 5, 'default' => 'direct'],
'last_message_at' => ['type' => 'timestamp', 'label' => 'Last Message At', 'weight' => 12],
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/MessageThreadTest.php -v
```

Expected: All 7 tests pass.

- [ ] **Step 6: Create migrations**

`migrations/20260328_100000_add_thread_type_to_message_thread.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add thread_type column to message_thread table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('message_thread')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            ALTER TABLE message_thread ADD COLUMN thread_type TEXT DEFAULT 'direct'
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
    }
};
```

`migrations/20260328_100100_add_last_message_at_to_message_thread.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add last_message_at column to message_thread table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('message_thread')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            ALTER TABLE message_thread ADD COLUMN last_message_at INTEGER DEFAULT 0
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
    }
};
```

- [ ] **Step 7: Commit**

```bash
git add src/Entity/MessageThread.php src/Provider/MessagingServiceProvider.php tests/Minoo/Unit/Entity/MessageThreadTest.php migrations/20260328_10000*.php
git commit -m "feat(#575): add thread_type and last_message_at to MessageThread"
```

---

## Task 3: Entity Model — ThreadMessage Fields

**Files:**
- Modify: `src/Entity/ThreadMessage.php`
- Modify: `src/Provider/MessagingServiceProvider.php`
- Modify: `tests/Minoo/Unit/Entity/ThreadMessageTest.php`
- Create: `migrations/20260328_100200_add_edited_at_to_thread_message.php`
- Create: `migrations/20260328_100300_add_deleted_at_to_thread_message.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Minoo/Unit/Entity/ThreadMessageTest.php`:

```php
#[Test]
public function it_defaults_edited_at_to_null(): void
{
    $msg = new ThreadMessage(['thread_id' => 1, 'sender_id' => 2, 'body' => 'Hello']);
    $this->assertNull($msg->get('edited_at'));
}

#[Test]
public function it_defaults_deleted_at_to_null(): void
{
    $msg = new ThreadMessage(['thread_id' => 1, 'sender_id' => 2, 'body' => 'Hello']);
    $this->assertNull($msg->get('deleted_at'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/ThreadMessageTest.php -v
```

Expected: 2 failures.

- [ ] **Step 3: Update ThreadMessage entity**

Replace `src/Entity/ThreadMessage.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

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

- [ ] **Step 4: Update field definitions in MessagingServiceProvider**

Add to the `thread_message` EntityType `fieldDefinitions` array in `src/Provider/MessagingServiceProvider.php`, after the `'created_at'` entry:

```php
'edited_at' => ['type' => 'timestamp', 'label' => 'Edited At', 'weight' => 11, 'default' => null],
'deleted_at' => ['type' => 'timestamp', 'label' => 'Deleted At', 'weight' => 12, 'default' => null],
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/ThreadMessageTest.php -v
```

Expected: All tests pass.

- [ ] **Step 6: Create migrations**

`migrations/20260328_100200_add_edited_at_to_thread_message.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add edited_at column to thread_message table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('thread_message')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            ALTER TABLE thread_message ADD COLUMN edited_at INTEGER DEFAULT NULL
        ");
    }

    public function down(SchemaBuilder $schema): void {}
};
```

`migrations/20260328_100300_add_deleted_at_to_thread_message.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add deleted_at column to thread_message table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('thread_message')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            ALTER TABLE thread_message ADD COLUMN deleted_at INTEGER DEFAULT NULL
        ");
    }

    public function down(SchemaBuilder $schema): void {}
};
```

- [ ] **Step 7: Commit**

```bash
git add src/Entity/ThreadMessage.php src/Provider/MessagingServiceProvider.php tests/Minoo/Unit/Entity/ThreadMessageTest.php migrations/20260328_10020*.php migrations/20260328_10030*.php
git commit -m "feat(#575): add edited_at and deleted_at to ThreadMessage"
```

---

## Task 4: UserBlock Entity + Access Policy + Provider

**Files:**
- Create: `src/Entity/UserBlock.php`
- Create: `src/Access/BlockAccessPolicy.php`
- Create: `src/Provider/BlockServiceProvider.php`
- Create: `migrations/20260328_100400_create_user_block_table.php`
- Create: `tests/Minoo/Unit/Entity/UserBlockTest.php`
- Create: `tests/Minoo/Unit/Access/BlockAccessPolicyTest.php`
- Modify: `composer.json` (add provider)

- [ ] **Step 1: Write failing entity tests**

`tests/Minoo/Unit/Entity/UserBlockTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\UserBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserBlock::class)]
final class UserBlockTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $before = time();
        $block = new UserBlock(['blocker_id' => 1, 'blocked_id' => 2]);
        $after = time();

        $this->assertSame(1, $block->get('blocker_id'));
        $this->assertSame(2, $block->get('blocked_id'));
        $this->assertGreaterThanOrEqual($before, $block->get('created_at'));
        $this->assertLessThanOrEqual($after, $block->get('created_at'));
    }

    #[Test]
    public function constructor_requires_blocker_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('blocker_id');
        new UserBlock(['blocked_id' => 2]);
    }

    #[Test]
    public function constructor_requires_blocked_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('blocked_id');
        new UserBlock(['blocker_id' => 1]);
    }

    #[Test]
    public function it_rejects_self_block(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot block yourself');
        new UserBlock(['blocker_id' => 1, 'blocked_id' => 1]);
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $block = new UserBlock(['blocker_id' => 1, 'blocked_id' => 2]);
        $this->assertSame('user_block', $block->getEntityTypeId());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/UserBlockTest.php -v
```

Expected: Fail (class doesn't exist).

- [ ] **Step 3: Implement UserBlock entity**

`src/Entity/UserBlock.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class UserBlock extends ContentEntityBase
{
    protected string $entityTypeId = 'user_block';

    protected array $entityKeys = [
        'id' => 'ubid',
        'uuid' => 'uuid',
        'label' => 'blocker_id',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['blocker_id', 'blocked_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if ((int) $values['blocker_id'] === (int) $values['blocked_id']) {
            throw new \InvalidArgumentException('Cannot block yourself');
        }

        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run entity tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/UserBlockTest.php -v
```

Expected: All 5 tests pass.

- [ ] **Step 5: Write failing access policy tests**

`tests/Minoo/Unit/Access/BlockAccessPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\BlockAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

#[CoversClass(BlockAccessPolicy::class)]
final class BlockAccessPolicyTest extends TestCase
{
    private BlockAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new BlockAccessPolicy();
    }

    private function mockAccount(int $id, bool $isAdmin = false): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn(true);
        $account->method('hasPermission')->willReturn($isAdmin);

        return $account;
    }

    private function mockBlock(int $blockerId): EntityInterface
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getEntityTypeId')->willReturn('user_block');
        $entity->method('get')->willReturnCallback(fn(string $field) => match ($field) {
            'blocker_id' => $blockerId,
            default => null,
        });

        return $entity;
    }

    #[Test]
    public function it_applies_to_user_block(): void
    {
        $this->assertTrue($this->policy->appliesTo('user_block'));
        $this->assertFalse($this->policy->appliesTo('message_thread'));
    }

    #[Test]
    public function blocker_can_view_own_blocks(): void
    {
        $block = $this->mockBlock(5);
        $account = $this->mockAccount(5);

        $result = $this->policy->access($block, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function non_blocker_cannot_view_blocks(): void
    {
        $block = $this->mockBlock(5);
        $account = $this->mockAccount(99);

        $result = $this->policy->access($block, 'view', $account);
        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function blocker_can_delete_own_blocks(): void
    {
        $block = $this->mockBlock(5);
        $account = $this->mockAccount(5);

        $result = $this->policy->access($block, 'delete', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function admin_can_view_any_block(): void
    {
        $block = $this->mockBlock(5);
        $account = $this->mockAccount(99, isAdmin: true);

        $result = $this->policy->access($block, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function authenticated_user_can_create_blocks(): void
    {
        $account = $this->mockAccount(1);
        $result = $this->policy->createAccess('user_block', 'user_block', $account);
        $this->assertTrue($result->isAllowed());
    }
}
```

- [ ] **Step 6: Implement BlockAccessPolicy**

`src/Access/BlockAccessPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'user_block')]
final class BlockAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'user_block';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        $blockerId = $entity->get('blocker_id');
        if ($blockerId !== null && (int) $blockerId === (int) $account->id()) {
            return AccessResult::allowed('Blocker may manage own blocks.');
        }

        return AccessResult::neutral('Only the blocker may access this block.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated users may create blocks.');
        }

        return AccessResult::neutral('Anonymous users cannot create blocks.');
    }
}
```

- [ ] **Step 7: Run access policy tests**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Access/BlockAccessPolicyTest.php -v
```

Expected: All 6 tests pass.

- [ ] **Step 8: Create BlockServiceProvider**

`src/Provider/BlockServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\UserBlock;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'user_block',
            label: 'User Block',
            class: UserBlock::class,
            keys: ['id' => 'ubid', 'uuid' => 'uuid', 'label' => 'blocker_id'],
            group: 'messaging',
            fieldDefinitions: [
                'blocker_id' => ['type' => 'integer', 'label' => 'Blocker ID', 'weight' => 0],
                'blocked_id' => ['type' => 'integer', 'label' => 'Blocked ID', 'weight' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'blocks.index',
            RouteBuilder::create('/api/blocks')
                ->controller('Minoo\\Controller\\BlockController::index')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'blocks.store',
            RouteBuilder::create('/api/blocks')
                ->controller('Minoo\\Controller\\BlockController::store')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'blocks.delete',
            RouteBuilder::create('/api/blocks/{user_id}')
                ->controller('Minoo\\Controller\\BlockController::delete')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('user_id', '\\d+')
                ->build(),
        );
    }
}
```

- [ ] **Step 9: Create migration**

`migrations/20260328_100400_create_user_block_table.php`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create user_block table for user blocking.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('user_block')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE user_block (
                ubid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                blocker_id CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('user_block')) {
            $schema->getConnection()->executeStatement('DROP TABLE user_block');
        }
    }
};
```

- [ ] **Step 10: Register BlockServiceProvider in composer.json**

Add `"Minoo\\Provider\\BlockServiceProvider"` to the `extra.waaseyaa.providers` array in `composer.json`.

- [ ] **Step 11: Rebuild manifest**

```bash
rm -f storage/framework/packages.php
php bin/waaseyaa optimize:manifest
```

- [ ] **Step 12: Run all tests**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/UserBlockTest.php tests/Minoo/Unit/Access/BlockAccessPolicyTest.php -v
```

Expected: All 11 tests pass.

- [ ] **Step 13: Commit**

```bash
git add src/Entity/UserBlock.php src/Access/BlockAccessPolicy.php src/Provider/BlockServiceProvider.php migrations/20260328_100400_create_user_block_table.php tests/Minoo/Unit/Entity/UserBlockTest.php tests/Minoo/Unit/Access/BlockAccessPolicyTest.php composer.json
git commit -m "feat(#575): add UserBlock entity, access policy, and service provider"
```

---

## Task 5: BlockController

**Files:**
- Create: `src/Controller/BlockController.php`
- Create: `tests/Minoo/Unit/Controller/BlockControllerTest.php`

- [ ] **Step 1: Write failing tests**

`tests/Minoo/Unit/Controller/BlockControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\BlockController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(BlockController::class)]
final class BlockControllerTest extends TestCase
{
    private EntityTypeManager $etm;
    private BlockController $controller;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->controller = new BlockController($this->etm);
    }

    private function mockAccount(int $id): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn(true);
        $account->method('hasPermission')->willReturn(false);

        return $account;
    }

    private function jsonRequest(array $body): HttpRequest
    {
        return HttpRequest::create('/', 'POST', [], [], [], [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function mockBlockStorage(array $existingBlockerIds = []): EntityStorageInterface
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('sort')->willReturn($query);
        $query->method('execute')->willReturn($existingBlockerIds);
        $storage->method('getQuery')->willReturn($query);

        return $storage;
    }

    #[Test]
    public function store_creates_block(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage([]);

        $block = $this->createMock(EntityInterface::class);
        $block->method('id')->willReturn(10);
        $storage->method('create')->willReturn($block);
        $storage->method('save')->willReturn(1);

        $this->etm->method('getStorage')->willReturnMap([
            ['user_block', $storage],
        ]);

        $request = $this->jsonRequest(['blocked_id' => 2]);
        $response = $this->controller->store([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
    }

    #[Test]
    public function store_rejects_self_block(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage([]);
        $this->etm->method('getStorage')->willReturnMap([
            ['user_block', $storage],
        ]);

        $request = $this->jsonRequest(['blocked_id' => 1]);
        $response = $this->controller->store([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
    }

    #[Test]
    public function delete_removes_block(): void
    {
        $account = $this->mockAccount(1);

        $block = $this->createMock(EntityInterface::class);
        $block->method('id')->willReturn(10);
        $block->method('get')->willReturnCallback(fn(string $f) => match ($f) {
            'blocker_id' => 1,
            default => null,
        });

        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn([10]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('load')->with(10)->willReturn($block);
        $storage->expects($this->once())->method('delete')->with([$block]);

        $this->etm->method('getStorage')->willReturnMap([
            ['user_block', $storage],
        ]);

        $response = $this->controller->delete(['user_id' => '2'], [], $account, HttpRequest::create('/'));

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function delete_returns_404_when_not_blocked(): void
    {
        $account = $this->mockAccount(1);

        $storage = $this->mockBlockStorage([]);
        $this->etm->method('getStorage')->willReturnMap([
            ['user_block', $storage],
        ]);

        $response = $this->controller->delete(['user_id' => '2'], [], $account, HttpRequest::create('/'));

        $this->assertSame(404, $response->statusCode);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/BlockControllerTest.php -v
```

Expected: Fail (class doesn't exist).

- [ ] **Step 3: Implement BlockController**

`src/Controller/BlockController.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\SSR\SsrResponse;

final class BlockController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->blockStorage();
        $ids = $storage->getQuery()
            ->condition('blocker_id', (int) $account->id())
            ->sort('created_at', 'DESC')
            ->execute();

        $blocks = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $payload = array_map(static fn(EntityInterface $block): array => [
            'id' => (int) $block->id(),
            'blocked_id' => (int) $block->get('blocked_id'),
            'created_at' => (int) $block->get('created_at'),
        ], $blocks);

        return $this->json(['blocks' => $payload]);
    }

    public function store(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $blockerId = (int) $account->id();
        $blockedId = (int) ($data['blocked_id'] ?? 0);

        if ($blockedId <= 0) {
            return $this->json(['error' => 'blocked_id is required'], 422);
        }

        if ($blockerId === $blockedId) {
            return $this->json(['error' => 'Cannot block yourself'], 422);
        }

        $storage = $this->blockStorage();

        // Check for existing block.
        $existing = $storage->getQuery()
            ->condition('blocker_id', $blockerId)
            ->condition('blocked_id', $blockedId)
            ->range(0, 1)
            ->execute();

        if ($existing !== []) {
            return $this->json(['error' => 'User already blocked'], 409);
        }

        try {
            $block = $storage->create([
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
            ]);
            $storage->save($block);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid block payload'], 422);
        }

        return $this->json(['id' => (int) $block->id()], 201);
    }

    public function delete(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $blockerId = (int) $account->id();
        $blockedUserId = (int) ($params['user_id'] ?? 0);

        $storage = $this->blockStorage();
        $ids = $storage->getQuery()
            ->condition('blocker_id', $blockerId)
            ->condition('blocked_id', $blockedUserId)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return $this->json(['error' => 'Block not found'], 404);
        }

        $block = $storage->load((int) reset($ids));
        if ($block === null) {
            return $this->json(['error' => 'Block not found'], 404);
        }

        $storage->delete([$block]);

        return $this->json(['removed' => true]);
    }

    private function blockStorage(): EntityStorageInterface
    {
        return $this->entityTypeManager->getStorage('user_block');
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

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/BlockControllerTest.php -v
```

Expected: All 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/BlockController.php tests/Minoo/Unit/Controller/BlockControllerTest.php
git commit -m "feat(#575): add BlockController with CRUD endpoints"
```

---

## Task 6: MercurePublisher Service

**Files:**
- Create: `src/Support/MercurePublisher.php`
- Create: `tests/Minoo/Unit/Support/MercurePublisherTest.php`

- [ ] **Step 1: Write failing tests**

`tests/Minoo/Unit/Support/MercurePublisherTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\MercurePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MercurePublisher::class)]
final class MercurePublisherTest extends TestCase
{
    #[Test]
    public function it_builds_correct_post_body(): void
    {
        $publisher = new MercurePublisher('http://hub/.well-known/mercure', 'test-jwt');

        $body = $publisher->buildPostBody('/threads/1', ['type' => 'message', 'body' => 'Hello']);

        $this->assertStringContainsString('topic=%2Fthreads%2F1', $body);
        $this->assertStringContainsString('data=', $body);
        $this->assertStringContainsString('"type":"message"', urldecode($body));
    }

    #[Test]
    public function is_configured_returns_false_without_jwt(): void
    {
        $publisher = new MercurePublisher('http://hub/.well-known/mercure', '');
        $this->assertFalse($publisher->isConfigured());
    }

    #[Test]
    public function is_configured_returns_true_with_jwt(): void
    {
        $publisher = new MercurePublisher('http://hub/.well-known/mercure', 'test-jwt');
        $this->assertTrue($publisher->isConfigured());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Support/MercurePublisherTest.php -v
```

Expected: Fail (class doesn't exist).

- [ ] **Step 3: Implement MercurePublisher**

`src/Support/MercurePublisher.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

final class MercurePublisher
{
    public function __construct(
        private readonly string $hubUrl,
        private readonly string $publisherJwt,
    ) {}

    /**
     * Publish an event to a Mercure topic.
     *
     * @param array<string, mixed> $data
     * @return bool True if published successfully, false otherwise.
     */
    public function publish(string $topic, array $data): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $ch = curl_init($this->hubUrl);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->buildPostBody($topic, $data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->publisherJwt,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $httpCode >= 200 && $httpCode < 300;
    }

    public function isConfigured(): bool
    {
        return $this->hubUrl !== '' && $this->publisherJwt !== '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function buildPostBody(string $topic, array $data): string
    {
        return http_build_query([
            'topic' => $topic,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Support/MercurePublisherTest.php -v
```

Expected: All 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Support/MercurePublisher.php tests/Minoo/Unit/Support/MercurePublisherTest.php
git commit -m "feat(#575): add MercurePublisher service for SSE event publishing"
```

---

## Task 7: Enhanced MessagingController — New Endpoints + Mercure

**Files:**
- Modify: `src/Controller/MessagingController.php`
- Modify: `src/Provider/MessagingServiceProvider.php`
- Modify: `tests/Minoo/Unit/Controller/MessagingControllerTest.php`

- [ ] **Step 1: Write failing tests for new endpoints**

Add to `tests/Minoo/Unit/Controller/MessagingControllerTest.php`:

```php
#[Test]
public function editMessage_rejects_non_sender(): void
{
    $account = $this->mockAccount(1);
    $senderId = 99;

    // Participant check passes.
    $participantStorage = $this->createMock(EntityStorageInterface::class);
    $pQuery = $this->createMock(EntityQueryInterface::class);
    $pQuery->method('condition')->willReturn($pQuery);
    $pQuery->method('range')->willReturn($pQuery);
    $pQuery->method('execute')->willReturn([1]);
    $participantStorage->method('getQuery')->willReturn($pQuery);

    // Message belongs to a different sender.
    $message = $this->createMock(EntityInterface::class);
    $message->method('id')->willReturn(5);
    $message->method('get')->willReturnCallback(fn(string $f) => match ($f) {
        'sender_id' => $senderId,
        'deleted_at' => null,
        default => null,
    });

    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(5)->willReturn($message);

    $this->etm->method('getStorage')->willReturnMap([
        ['thread_participant', $participantStorage],
        ['thread_message', $messageStorage],
    ]);

    $request = $this->jsonRequest(['body' => 'Edited']);
    $response = $this->controller->editMessage(['id' => '10', 'mid' => '5'], [], $account, $request);

    $this->assertSame(403, $response->statusCode);
}

#[Test]
public function editMessage_updates_body_and_sets_edited_at(): void
{
    $account = $this->mockAccount(1);

    $participantStorage = $this->createMock(EntityStorageInterface::class);
    $pQuery = $this->createMock(EntityQueryInterface::class);
    $pQuery->method('condition')->willReturn($pQuery);
    $pQuery->method('range')->willReturn($pQuery);
    $pQuery->method('execute')->willReturn([1]);
    $participantStorage->method('getQuery')->willReturn($pQuery);

    $message = $this->createMock(EntityInterface::class);
    $message->method('id')->willReturn(5);
    $message->method('get')->willReturnCallback(fn(string $f) => match ($f) {
        'sender_id' => 1,
        'thread_id' => 10,
        'deleted_at' => null,
        default => null,
    });
    $message->method('set')->willReturn($message);

    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(5)->willReturn($message);
    $messageStorage->expects($this->once())->method('save')->with($message);

    $this->etm->method('getStorage')->willReturnMap([
        ['thread_participant', $participantStorage],
        ['thread_message', $messageStorage],
    ]);

    $request = $this->jsonRequest(['body' => 'Edited body']);
    $response = $this->controller->editMessage(['id' => '10', 'mid' => '5'], [], $account, $request);

    $this->assertSame(200, $response->statusCode);
}

#[Test]
public function deleteMessage_soft_deletes(): void
{
    $account = $this->mockAccount(1);

    $participantStorage = $this->createMock(EntityStorageInterface::class);
    $pQuery = $this->createMock(EntityQueryInterface::class);
    $pQuery->method('condition')->willReturn($pQuery);
    $pQuery->method('range')->willReturn($pQuery);
    $pQuery->method('execute')->willReturn([1]);
    $participantStorage->method('getQuery')->willReturn($pQuery);

    $message = $this->createMock(EntityInterface::class);
    $message->method('id')->willReturn(5);
    $message->method('get')->willReturnCallback(fn(string $f) => match ($f) {
        'sender_id' => 1,
        'thread_id' => 10,
        'deleted_at' => null,
        default => null,
    });
    $message->method('set')->willReturn($message);

    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(5)->willReturn($message);
    $messageStorage->expects($this->once())->method('save')->with($message);

    $this->etm->method('getStorage')->willReturnMap([
        ['thread_participant', $participantStorage],
        ['thread_message', $messageStorage],
    ]);

    $response = $this->controller->deleteMessage(['id' => '10', 'mid' => '5'], [], $account, HttpRequest::create('/'));

    $this->assertSame(200, $response->statusCode);
}

#[Test]
public function markRead_updates_last_read_at(): void
{
    $account = $this->mockAccount(1);

    $participantStorage = $this->createMock(EntityStorageInterface::class);
    $pQuery = $this->createMock(EntityQueryInterface::class);
    $pQuery->method('condition')->willReturn($pQuery);
    $pQuery->method('range')->willReturn($pQuery);
    $pQuery->method('execute')->willReturn([1]);
    $participantStorage->method('getQuery')->willReturn($pQuery);

    $participant = $this->createMock(EntityInterface::class);
    $participant->method('set')->willReturn($participant);
    $participantStorage->method('load')->with(1)->willReturn($participant);
    $participantStorage->expects($this->once())->method('save')->with($participant);

    $this->etm->method('getStorage')->willReturnMap([
        ['thread_participant', $participantStorage],
    ]);

    $response = $this->controller->markRead(['id' => '10'], [], $account, HttpRequest::create('/'));

    $this->assertSame(200, $response->statusCode);
}

#[Test]
public function unreadCount_returns_count(): void
{
    $account = $this->mockAccount(1);

    $participantStorage = $this->createMock(EntityStorageInterface::class);
    $pQuery = $this->createMock(EntityQueryInterface::class);
    $pQuery->method('condition')->willReturn($pQuery);
    $pQuery->method('sort')->willReturn($pQuery);
    $pQuery->method('execute')->willReturn([]);
    $participantStorage->method('getQuery')->willReturn($pQuery);

    $this->etm->method('getStorage')->willReturnMap([
        ['thread_participant', $participantStorage],
    ]);

    $response = $this->controller->unreadCount([], [], $account, HttpRequest::create('/'));

    $this->assertSame(200, $response->statusCode);
    $this->assertStringContainsString('"count":0', $response->content);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/MessagingControllerTest.php -v
```

Expected: 5 failures (methods don't exist).

- [ ] **Step 3: Add new methods to MessagingController**

Add the `MercurePublisher` constructor parameter and new methods to `src/Controller/MessagingController.php`. Update the constructor:

```php
public function __construct(
    private readonly EntityTypeManager $entityTypeManager,
    private readonly ?MercurePublisher $mercurePublisher = null,
) {}
```

Add import at top:
```php
use Minoo\Support\MercurePublisher;
```

Add these methods before the private helper methods:

```php
public function editMessage(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $threadId = (int) ($params['id'] ?? 0);
    $messageId = (int) ($params['mid'] ?? 0);
    $userId = (int) $account->id();

    if (!$this->isParticipant($threadId, $userId)) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $messageStorage = $this->messageStorage();
    $message = $messageStorage->load($messageId);
    if ($message === null) {
        return $this->json(['error' => 'Not found'], 404);
    }

    if ($message->get('deleted_at') !== null) {
        return $this->json(['error' => 'Message has been deleted'], 410);
    }

    if ((int) $message->get('sender_id') !== $userId) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $data = $this->jsonBody($request);
    $body = trim((string) ($data['body'] ?? ''));
    if ($body === '' || mb_strlen($body) > 2000) {
        return $this->json(['error' => 'Body must be 1-2000 characters'], 422);
    }

    $now = time();
    $message->set('body', $body);
    $message->set('edited_at', $now);
    $messageStorage->save($message);

    $this->publishMercure("/threads/{$threadId}", [
        'type' => 'message_edited',
        'id' => $messageId,
        'body' => $body,
        'edited_at' => $now,
    ]);

    return $this->json(['id' => $messageId, 'body' => $body, 'edited_at' => $now]);
}

public function deleteMessage(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $threadId = (int) ($params['id'] ?? 0);
    $messageId = (int) ($params['mid'] ?? 0);
    $userId = (int) $account->id();

    if (!$this->isParticipant($threadId, $userId)) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $messageStorage = $this->messageStorage();
    $message = $messageStorage->load($messageId);
    if ($message === null) {
        return $this->json(['error' => 'Not found'], 404);
    }

    if ($message->get('deleted_at') !== null) {
        return $this->json(['error' => 'Already deleted'], 410);
    }

    if ((int) $message->get('sender_id') !== $userId) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $now = time();
    $message->set('deleted_at', $now);
    $messageStorage->save($message);

    $this->publishMercure("/threads/{$threadId}", [
        'type' => 'message_deleted',
        'id' => $messageId,
    ]);

    return $this->json(['id' => $messageId, 'deleted' => true]);
}

public function markRead(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $threadId = (int) ($params['id'] ?? 0);
    $userId = (int) $account->id();

    $participantStorage = $this->participantStorage();
    $ids = $participantStorage->getQuery()
        ->condition('thread_id', $threadId)
        ->condition('user_id', $userId)
        ->range(0, 1)
        ->execute();

    if ($ids === []) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $participant = $participantStorage->load((int) reset($ids));
    if ($participant === null) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $now = time();
    $participant->set('last_read_at', $now);
    $participantStorage->save($participant);

    $this->publishMercure("/threads/{$threadId}", [
        'type' => 'read',
        'user_id' => $userId,
        'last_read_at' => $now,
    ]);

    return $this->json(['last_read_at' => $now]);
}

public function typing(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $threadId = (int) ($params['id'] ?? 0);
    $userId = (int) $account->id();

    if (!$this->isParticipant($threadId, $userId)) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    $this->publishMercure("/threads/{$threadId}", [
        'type' => 'typing',
        'user_id' => $userId,
        'display_name' => (string) ($account->get('name') ?? ''),
    ]);

    return $this->json(['ok' => true]);
}

public function unreadCount(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $userId = (int) $account->id();
    $participantStorage = $this->participantStorage();
    $messageStorage = $this->messageStorage();

    $participantRows = $this->participantsForUser($participantStorage, $userId);
    $unread = 0;

    foreach ($participantRows as $participant) {
        $threadId = (int) $participant->get('thread_id');
        $lastReadAt = (int) $participant->get('last_read_at');

        $ids = $messageStorage->getQuery()
            ->condition('thread_id', $threadId)
            ->condition('created_at', $lastReadAt, '>')
            ->execute();

        if ($ids !== []) {
            $unread += count($ids);
        }
    }

    return $this->json(['count' => $unread]);
}
```

Add private helper method:

```php
private function publishMercure(string $topic, array $data): void
{
    $this->mercurePublisher?->publish($topic, $data);
}
```

- [ ] **Step 4: Update createMessage to publish Mercure event and set last_message_at**

In the `createMessage` method, after `$storage->save($message);` and before `$thread = $threadStorage->load($threadId);`, add Mercure publishing. Update the thread save section to also set `last_message_at`:

Replace the thread update block:
```php
$thread = $threadStorage->load($threadId);
if ($thread !== null) {
    $thread->set('updated_at', $now);
    $thread->set('last_message_at', $now);
    $threadStorage->save($thread);
}

$this->publishMercure("/threads/{$threadId}", [
    'type' => 'message',
    'message' => [
        'id' => (int) $message->id(),
        'thread_id' => $threadId,
        'sender_id' => $userId,
        'body' => $body,
        'created_at' => $now,
    ],
]);
```

- [ ] **Step 5: Update indexThreads to sort by last_message_at and include unread count**

In `indexThreads`, change the `usort` line:

```php
usort($threads, static fn(EntityInterface $a, EntityInterface $b): int => ((int) $b->get('last_message_at')) <=> ((int) $a->get('last_message_at')));
```

In the payload foreach, add unread count calculation:

```php
foreach ($threads as $thread) {
    $threadId = (int) $thread->id();
    $latestMessage = $this->latestMessageForThread($messageStorage, $threadId);

    // Find this user's participant row to get last_read_at.
    $lastReadAt = 0;
    foreach ($participantRows as $participant) {
        if ((int) $participant->get('thread_id') === $threadId) {
            $lastReadAt = (int) $participant->get('last_read_at');
            break;
        }
    }

    $unreadIds = $messageStorage->getQuery()
        ->condition('thread_id', $threadId)
        ->condition('created_at', $lastReadAt, '>')
        ->execute();

    $payload[] = [
        'id' => $threadId,
        'title' => (string) $thread->get('title'),
        'thread_type' => (string) ($thread->get('thread_type') ?? 'direct'),
        'created_by' => (int) $thread->get('created_by'),
        'updated_at' => (int) $thread->get('updated_at'),
        'last_message_at' => (int) ($thread->get('last_message_at') ?? 0),
        'last_message' => $latestMessage,
        'unread_count' => count($unreadIds),
    ];
}
```

- [ ] **Step 6: Update indexMessages to cursor pagination and soft-deleted messages**

In `indexMessages`, replace the pagination and payload logic. Change from offset to cursor-based (`?before={mid}&limit=50`):

```php
$limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
$beforeId = (int) ($query['before'] ?? 0);

$queryBuilder = $storage->getQuery()
    ->condition('thread_id', $threadId)
    ->sort('created_at', 'DESC')
    ->range(0, $limit);

if ($beforeId > 0) {
    $queryBuilder->condition('tmid', $beforeId, '<');
}

$ids = $queryBuilder->execute();
$messages = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
// Reverse to show oldest first.
$messages = array_reverse($messages);
```

Update the message payload mapping:

```php
$payload = array_map(static fn(EntityInterface $message): array => [
    'id' => (int) $message->id(),
    'thread_id' => (int) $message->get('thread_id'),
    'sender_id' => (int) $message->get('sender_id'),
    'body' => $message->get('deleted_at') !== null ? '' : (string) $message->get('body'),
    'created_at' => (int) $message->get('created_at'),
    'edited_at' => $message->get('edited_at'),
    'deleted_at' => $message->get('deleted_at'),
], $messages);
```

- [ ] **Step 7: Add new routes to MessagingServiceProvider**

Add to the `routes()` method in `src/Provider/MessagingServiceProvider.php`:

```php
$router->addRoute(
    'messaging.messages.edit',
    RouteBuilder::create('/api/messaging/threads/{id}/messages/{mid}')
        ->controller('Minoo\\Controller\\MessagingController::editMessage')
        ->requireAuthentication()
        ->methods('PATCH')
        ->requirement('id', '\\d+')
        ->requirement('mid', '\\d+')
        ->build(),
);

$router->addRoute(
    'messaging.messages.delete',
    RouteBuilder::create('/api/messaging/threads/{id}/messages/{mid}')
        ->controller('Minoo\\Controller\\MessagingController::deleteMessage')
        ->requireAuthentication()
        ->methods('DELETE')
        ->requirement('id', '\\d+')
        ->requirement('mid', '\\d+')
        ->build(),
);

$router->addRoute(
    'messaging.threads.read',
    RouteBuilder::create('/api/messaging/threads/{id}/read')
        ->controller('Minoo\\Controller\\MessagingController::markRead')
        ->requireAuthentication()
        ->methods('POST')
        ->requirement('id', '\\d+')
        ->build(),
);

$router->addRoute(
    'messaging.threads.typing',
    RouteBuilder::create('/api/messaging/threads/{id}/typing')
        ->controller('Minoo\\Controller\\MessagingController::typing')
        ->requireAuthentication()
        ->methods('POST')
        ->requirement('id', '\\d+')
        ->build(),
);

$router->addRoute(
    'messaging.unread',
    RouteBuilder::create('/api/messaging/unread-count')
        ->controller('Minoo\\Controller\\MessagingController::unreadCount')
        ->requireAuthentication()
        ->methods('GET')
        ->build(),
);
```

- [ ] **Step 8: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/MessagingControllerTest.php -v
```

Expected: All 8 tests pass.

- [ ] **Step 9: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 10: Commit**

```bash
git add src/Controller/MessagingController.php src/Provider/MessagingServiceProvider.php tests/Minoo/Unit/Controller/MessagingControllerTest.php
git commit -m "feat(#575): add edit/delete/markRead/typing/unreadCount endpoints with Mercure publishing"
```

---

## Task 8: Block Checks in MessagingController

**Files:**
- Modify: `src/Controller/MessagingController.php`
- Modify: `tests/Minoo/Unit/Controller/MessagingControllerTest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Minoo/Unit/Controller/MessagingControllerTest.php`:

```php
#[Test]
public function createThread_rejects_blocked_participant(): void
{
    $account = $this->mockAccount(1);

    $blockStorage = $this->createMock(EntityStorageInterface::class);
    $blockQuery = $this->createMock(EntityQueryInterface::class);
    $blockQuery->method('condition')->willReturn($blockQuery);
    $blockQuery->method('range')->willReturn($blockQuery);
    $blockQuery->method('execute')->willReturn([100]); // Block exists.
    $blockStorage->method('getQuery')->willReturn($blockQuery);

    $this->etm->method('getStorage')->willReturnMap([
        ['user_block', $blockStorage],
    ]);

    $request = $this->jsonRequest([
        'participant_ids' => [2],
        'body' => 'Hello',
    ]);

    $response = $this->controller->createThread([], [], $account, $request);

    $this->assertSame(403, $response->statusCode);
    $this->assertStringContainsString('blocked', $response->content);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/MessagingControllerTest.php --filter=createThread_rejects_blocked -v
```

Expected: Fail.

- [ ] **Step 3: Add block checking to createThread**

In `createThread()`, after `$participantIds` is populated and before the `count($participantIds) < 2` check, add:

```php
// Check for blocks between creator and any participant.
$blockStorage = $this->entityTypeManager->getStorage('user_block');
foreach ($participantIds as $participantId) {
    if ($participantId === $creatorId) {
        continue;
    }

    // Check if either user has blocked the other.
    $blocked = $blockStorage->getQuery()
        ->condition('blocker_id', $participantId)
        ->condition('blocked_id', $creatorId)
        ->range(0, 1)
        ->execute();

    if ($blocked !== []) {
        return $this->json(['error' => 'Cannot message a user who has blocked you'], 403);
    }

    $blocking = $blockStorage->getQuery()
        ->condition('blocker_id', $creatorId)
        ->condition('blocked_id', $participantId)
        ->range(0, 1)
        ->execute();

    if ($blocking !== []) {
        return $this->json(['error' => 'Cannot message a user you have blocked'], 403);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/MessagingControllerTest.php -v
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/MessagingController.php tests/Minoo/Unit/Controller/MessagingControllerTest.php
git commit -m "feat(#575): add block validation to thread creation"
```

---

## Task 9: Mercure JWT Helper + Registration in Provider

**Files:**
- Modify: `src/Provider/MessagingServiceProvider.php`

- [ ] **Step 1: Register MercurePublisher as singleton in MessagingServiceProvider**

Add to the `register()` method:

```php
$this->singleton(MercurePublisher::class, function (): MercurePublisher {
    $config = $this->config('messaging');
    return new MercurePublisher(
        (string) ($config['mercure_hub_url'] ?? ''),
        (string) ($config['mercure_publisher_jwt'] ?? ''),
    );
});
```

Add import:
```php
use Minoo\Support\MercurePublisher;
```

- [ ] **Step 2: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass (MercurePublisher is optional in constructor).

- [ ] **Step 3: Commit**

```bash
git add src/Provider/MessagingServiceProvider.php
git commit -m "feat(#575): register MercurePublisher singleton in MessagingServiceProvider"
```

---

## Task 10: Email Digest Command

**Files:**
- Create: `src/Support/MessageDigestCommand.php`
- Create: `tests/Minoo/Unit/Support/MessageDigestCommandTest.php`

- [ ] **Step 1: Write failing tests**

`tests/Minoo/Unit/Support/MessageDigestCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\MailService;
use Minoo\Support\MessageDigestCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(MessageDigestCommand::class)]
final class MessageDigestCommandTest extends TestCase
{
    private EntityTypeManager $etm;
    private MailService $mailService;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->mailService = $this->createMock(MailService::class);
    }

    private function mockStorage(array $ids = []): EntityStorageInterface
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturn($query);
        $query->method('sort')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn($ids);
        $storage->method('getQuery')->willReturn($query);

        return $storage;
    }

    #[Test]
    public function it_skips_when_mail_not_configured(): void
    {
        $this->mailService->method('isConfigured')->willReturn(false);
        $this->mailService->expects($this->never())->method('sendPlain');

        $command = new MessageDigestCommand($this->etm, $this->mailService, [
            'digest_debounce' => 15,
            'digest_active_skip' => 30,
        ]);

        $command->execute();
    }

    #[Test]
    public function it_skips_users_with_no_unread(): void
    {
        $this->mailService->method('isConfigured')->willReturn(true);

        // No participants found = no users to notify.
        $participantStorage = $this->mockStorage([]);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
        ]);

        $this->mailService->expects($this->never())->method('sendPlain');

        $command = new MessageDigestCommand($this->etm, $this->mailService, [
            'digest_debounce' => 15,
            'digest_active_skip' => 30,
        ]);

        $command->execute();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Support/MessageDigestCommandTest.php -v
```

Expected: Fail (class doesn't exist).

- [ ] **Step 3: Implement MessageDigestCommand**

`src/Support/MessageDigestCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class MessageDigestCommand
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly MailService $mailService,
        private readonly array $config,
    ) {}

    public function execute(): void
    {
        if (!$this->mailService->isConfigured()) {
            return;
        }

        $debounceMinutes = (int) ($this->config['digest_debounce'] ?? 15);
        $activeSkipMinutes = (int) ($this->config['digest_active_skip'] ?? 30);
        $now = time();
        $debounceThreshold = $now - ($debounceMinutes * 60);

        $participantStorage = $this->entityTypeManager->getStorage('thread_participant');
        $messageStorage = $this->entityTypeManager->getStorage('thread_message');
        $userStorage = $this->entityTypeManager->getStorage('user');

        // Get all participants.
        $allParticipantIds = $participantStorage->getQuery()
            ->sort('user_id', 'ASC')
            ->execute();

        if ($allParticipantIds === []) {
            return;
        }

        $allParticipants = array_values($participantStorage->loadMultiple($allParticipantIds));

        // Group participants by user.
        $byUser = [];
        foreach ($allParticipants as $participant) {
            $userId = (int) $participant->get('user_id');
            $byUser[$userId][] = $participant;
        }

        foreach ($byUser as $userId => $userParticipants) {
            $user = $userStorage->load($userId);
            if ($user === null) {
                continue;
            }

            // Check notification preferences.
            $prefs = $user->get('notification_preferences');
            if (is_string($prefs)) {
                $prefs = json_decode($prefs, true);
            }
            if (is_array($prefs) && isset($prefs['email_digest']) && $prefs['email_digest'] === false) {
                continue;
            }

            $email = (string) ($user->get('mail') ?? '');
            $name = (string) ($user->get('name') ?? '');
            if ($email === '') {
                continue;
            }

            // Collect unread messages across all threads.
            $threadSummaries = [];
            $totalUnread = 0;

            foreach ($userParticipants as $participant) {
                $threadId = (int) $participant->get('thread_id');
                $lastReadAt = (int) $participant->get('last_read_at');

                $unreadIds = $messageStorage->getQuery()
                    ->condition('thread_id', $threadId)
                    ->condition('created_at', $lastReadAt, '>')
                    ->condition('created_at', $debounceThreshold, '<')
                    ->sort('created_at', 'DESC')
                    ->range(0, 5)
                    ->execute();

                if ($unreadIds === []) {
                    continue;
                }

                $unreadMessages = array_values($messageStorage->loadMultiple($unreadIds));
                $totalUnread += count($unreadIds);

                $preview = count($unreadMessages) > 0
                    ? (string) $unreadMessages[0]->get('body')
                    : '';

                $threadSummaries[] = [
                    'thread_id' => $threadId,
                    'count' => count($unreadIds),
                    'preview' => mb_substr($preview, 0, 100),
                ];
            }

            if ($totalUnread === 0) {
                continue;
            }

            $this->sendDigestEmail($email, $name, $totalUnread, $threadSummaries);
        }
    }

    /** @param list<array{thread_id: int, count: int, preview: string}> $summaries */
    private function sendDigestEmail(string $email, string $name, int $totalUnread, array $summaries): void
    {
        $greeting = $name !== '' ? "Hey {$name}," : 'Hey,';
        $threadCount = count($summaries);
        $subject = "You have {$totalUnread} unread message" . ($totalUnread !== 1 ? 's' : '') . ' on Minoo';

        $body = "{$greeting}\n\n";
        $body .= "You have unread messages in {$threadCount} conversation" . ($threadCount !== 1 ? 's' : '') . ":\n\n";

        foreach ($summaries as $summary) {
            $body .= "- Thread #{$summary['thread_id']} ({$summary['count']} new)\n";
            if ($summary['preview'] !== '') {
                $body .= "  \"{$summary['preview']}\"\n";
            }
        }

        $body .= "\nOpen Messages: https://minoo.sagamok.ca/messages\n";
        $body .= "\n--\nMinoo - Sagamok Anishnawbek Community Platform\n";

        $this->mailService->sendPlain($email, $subject, $body);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Support/MessageDigestCommandTest.php -v
```

Expected: All 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Support/MessageDigestCommand.php tests/Minoo/Unit/Support/MessageDigestCommandTest.php
git commit -m "feat(#575): add MessageDigestCommand for email notification digests"
```

---

## Task 11: JavaScript — Mercure Connection Manager

**Files:**
- Create: `public/js/messaging/mercure.js`

- [ ] **Step 1: Create mercure.js**

`public/js/messaging/mercure.js`:

```js
/**
 * Mercure SSE connection manager.
 * Handles subscribe, reconnect, and polling fallback.
 */
export class MercureConnection {
  /** @param {string} hubUrl */
  /** @param {function} onEvent */
  /** @param {number} pollingInterval — fallback polling interval in ms */
  constructor(hubUrl, onEvent, pollingInterval = 10000) {
    this.hubUrl = hubUrl;
    this.onEvent = onEvent;
    this.pollingInterval = pollingInterval;
    this.topics = [];
    this.eventSource = null;
    this.pollTimer = null;
    this.pollFn = null;
  }

  /**
   * Subscribe to Mercure topics via EventSource.
   * @param {string[]} topics
   * @param {function} [pollFallback] — called during polling fallback
   */
  subscribe(topics, pollFallback = null) {
    this.topics = topics;
    this.pollFn = pollFallback;
    this.connect();
  }

  connect() {
    this.disconnect();

    const url = new URL(this.hubUrl);
    for (const topic of this.topics) {
      url.searchParams.append('topic', topic);
    }

    this.eventSource = new EventSource(url, { withCredentials: true });

    this.eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        this.onEvent(data);
      } catch {
        // Ignore malformed events.
      }
    };

    this.eventSource.onerror = () => {
      this.startPolling();
    };

    this.eventSource.onopen = () => {
      this.stopPolling();
    };
  }

  startPolling() {
    if (this.pollTimer || !this.pollFn) return;
    this.pollTimer = setInterval(() => this.pollFn(), this.pollingInterval);
  }

  stopPolling() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  }

  disconnect() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    this.stopPolling();
  }

  /** Reconnect with new topic list (e.g., after joining/leaving a thread). */
  updateTopics(topics) {
    this.topics = topics;
    this.connect();
  }
}
```

- [ ] **Step 2: Commit**

```bash
git add public/js/messaging/mercure.js
git commit -m "feat(#575): add Mercure SSE connection manager module"
```

---

## Task 12: JavaScript — Thread List Module

**Files:**
- Create: `public/js/messaging/thread-list.js`

- [ ] **Step 1: Create thread-list.js**

`public/js/messaging/thread-list.js`:

```js
/**
 * Thread list sidebar — renders threads, handles selection, unread state.
 */

/** @param {string} text */
function escapeHtml(text) {
  return String(text)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

export class ThreadList {
  /**
   * @param {HTMLElement} containerEl
   * @param {function} onSelectThread — called with threadId when a thread is clicked
   */
  constructor(containerEl, onSelectThread) {
    this.containerEl = containerEl;
    this.onSelectThread = onSelectThread;
    this.threads = [];
    this.activeThreadId = null;

    this.containerEl.addEventListener('click', (event) => {
      const button = event.target.closest('[data-thread-id]');
      if (!button) return;
      const threadId = Number(button.dataset.threadId);
      this.setActive(threadId);
      this.onSelectThread(threadId);
    });
  }

  /** @param {Array} threads */
  render(threads) {
    this.threads = threads;

    if (threads.length === 0) {
      this.containerEl.innerHTML = '<p class="messages-empty-note">No conversations yet</p>';
      return;
    }

    this.containerEl.innerHTML = threads.map((thread) => {
      const isActive = this.activeThreadId === thread.id;
      const isUnread = thread.unread_count > 0;
      const title = thread.title?.trim() || `Thread #${thread.id}`;
      const preview = thread.last_message?.body || 'No messages yet';
      const time = thread.last_message_at ? this.formatTime(thread.last_message_at) : '';
      const avatarClass = thread.thread_type === 'group' ? 'messages-avatar--group' : '';

      return `<button class="messages-thread-list__item ${isActive ? 'messages-thread-list__item--active' : ''} ${isUnread ? 'messages-thread-list__item--unread' : ''}" data-thread-id="${thread.id}">
        <span class="messages-avatar ${avatarClass}">${escapeHtml(this.initials(title))}</span>
        <span class="messages-thread-list__body">
          <span class="messages-thread-list__header">
            <span class="messages-thread-list__title">${escapeHtml(title)}</span>
            <span class="messages-thread-list__time">${escapeHtml(time)}</span>
          </span>
          <span class="messages-thread-list__preview">${escapeHtml(preview)}</span>
        </span>
        ${isUnread ? '<span class="messages-unread-dot"></span>' : ''}
      </button>`;
    }).join('');
  }

  setActive(threadId) {
    this.activeThreadId = threadId;
    this.containerEl.querySelectorAll('[data-thread-id]').forEach((el) => {
      el.classList.toggle('messages-thread-list__item--active', Number(el.dataset.threadId) === threadId);
    });
  }

  /** Update a single thread's preview and unread state without full re-render. */
  updateThread(threadId, { lastMessage, unreadCount }) {
    const thread = this.threads.find((t) => t.id === threadId);
    if (!thread) return;

    if (lastMessage) thread.last_message = lastMessage;
    if (unreadCount !== undefined) thread.unread_count = unreadCount;
    thread.last_message_at = Math.floor(Date.now() / 1000);

    // Re-sort by recency and re-render.
    this.threads.sort((a, b) => (b.last_message_at || 0) - (a.last_message_at || 0));
    this.render(this.threads);
  }

  /** @param {string} name */
  initials(name) {
    return name.split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase() || '?';
  }

  /** @param {number} timestamp */
  formatTime(timestamp) {
    const diff = Math.floor(Date.now() / 1000) - timestamp;
    if (diff < 60) return 'now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
  }
}
```

- [ ] **Step 2: Commit**

```bash
git add public/js/messaging/thread-list.js
git commit -m "feat(#575): add thread list sidebar JS module"
```

---

## Task 13: JavaScript — Message View Module

**Files:**
- Create: `public/js/messaging/message-view.js`

- [ ] **Step 1: Create message-view.js**

`public/js/messaging/message-view.js`:

```js
/**
 * Chat area — renders message bubbles, handles scroll, edit/delete UI.
 */

function escapeHtml(text) {
  return String(text)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

export class MessageView {
  /**
   * @param {HTMLElement} containerEl
   * @param {number} currentUserId
   */
  constructor(containerEl, currentUserId) {
    this.containerEl = containerEl;
    this.currentUserId = currentUserId;
    this.messages = [];
    this.threadId = null;

    this.containerEl.addEventListener('click', (event) => {
      const editBtn = event.target.closest('[data-action="edit"]');
      const deleteBtn = event.target.closest('[data-action="delete"]');

      if (editBtn) this.handleEdit(Number(editBtn.dataset.messageId));
      if (deleteBtn) this.handleDelete(Number(deleteBtn.dataset.messageId));
    });
  }

  /** @param {number} threadId */
  /** @param {Array} messages */
  render(threadId, messages) {
    this.threadId = threadId;
    this.messages = messages;

    if (messages.length === 0) {
      this.containerEl.innerHTML = '<p class="messages-empty-note">No messages yet. Say hello!</p>';
      return;
    }

    const html = messages.map((msg) => this.renderBubble(msg)).join('');
    this.containerEl.innerHTML = `<div class="messages-message-list">${html}</div>`;
    this.scrollToBottom();
  }

  renderBubble(msg) {
    const isOwn = msg.sender_id === this.currentUserId;
    const isDeleted = msg.deleted_at !== null && msg.deleted_at !== undefined;
    const isEdited = msg.edited_at !== null && msg.edited_at !== undefined;
    const direction = isOwn ? 'outgoing' : 'incoming';
    const body = isDeleted ? '<em>This message was deleted</em>' : escapeHtml(msg.body);
    const time = new Date(msg.created_at * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const editedLabel = isEdited && !isDeleted ? ' · (edited)' : '';

    const actions = isOwn && !isDeleted
      ? `<span class="messages-bubble__actions">
          <button data-action="edit" data-message-id="${msg.id}" title="Edit">✎</button>
          <button data-action="delete" data-message-id="${msg.id}" title="Delete">✕</button>
        </span>`
      : '';

    return `<article class="messages-bubble messages-bubble--${direction} ${isDeleted ? 'messages-bubble--deleted' : ''}" data-message-id="${msg.id}">
      <div class="messages-bubble__content">${body}</div>
      <div class="messages-bubble__meta">${time}${editedLabel}${actions}</div>
    </article>`;
  }

  /** Append a new message (optimistic or from Mercure). */
  appendMessage(msg) {
    this.messages.push(msg);
    const list = this.containerEl.querySelector('.messages-message-list');
    if (list) {
      list.insertAdjacentHTML('beforeend', this.renderBubble(msg));
      this.scrollToBottom();
    }
  }

  /** Update a message in place (edit event). */
  updateMessage(messageId, body, editedAt) {
    const el = this.containerEl.querySelector(`[data-message-id="${messageId}"]`);
    if (!el) return;

    const msg = this.messages.find((m) => m.id === messageId);
    if (msg) {
      msg.body = body;
      msg.edited_at = editedAt;
    }

    el.outerHTML = this.renderBubble(msg || { id: messageId, body, edited_at: editedAt, sender_id: 0, created_at: 0 });
  }

  /** Mark a message as deleted in place. */
  markDeleted(messageId) {
    const msg = this.messages.find((m) => m.id === messageId);
    if (msg) {
      msg.deleted_at = Math.floor(Date.now() / 1000);
      msg.body = '';
    }

    const el = this.containerEl.querySelector(`[data-message-id="${messageId}"]`);
    if (el && msg) {
      el.outerHTML = this.renderBubble(msg);
    }
  }

  async handleEdit(messageId) {
    const msg = this.messages.find((m) => m.id === messageId);
    if (!msg) return;

    const newBody = prompt('Edit message:', msg.body);
    if (newBody === null || newBody.trim() === '' || newBody === msg.body) return;

    const response = await fetch(`/api/messaging/threads/${this.threadId}/messages/${messageId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ body: newBody.trim() }),
    });

    if (response.ok) {
      const data = await response.json();
      this.updateMessage(messageId, data.body, data.edited_at);
    }
  }

  async handleDelete(messageId) {
    if (!confirm('Delete this message?')) return;

    const response = await fetch(`/api/messaging/threads/${this.threadId}/messages/${messageId}`, {
      method: 'DELETE',
    });

    if (response.ok) {
      this.markDeleted(messageId);
    }
  }

  scrollToBottom() {
    this.containerEl.scrollTop = this.containerEl.scrollHeight;
  }
}
```

- [ ] **Step 2: Commit**

```bash
git add public/js/messaging/message-view.js
git commit -m "feat(#575): add message view JS module with edit/delete UI"
```

---

## Task 14: JavaScript — Compose + Typing Modules

**Files:**
- Create: `public/js/messaging/compose.js`
- Create: `public/js/messaging/typing.js`

- [ ] **Step 1: Create compose.js**

`public/js/messaging/compose.js`:

```js
/**
 * Compose bar — auto-grow textarea, send on Enter, typing broadcast.
 */
export class ComposeBar {
  /**
   * @param {HTMLElement} containerEl — element to render the compose bar into
   * @param {function} onSend — called with message body string
   * @param {function} onTyping — called when user is typing (debounced externally)
   */
  constructor(containerEl, onSend, onTyping) {
    this.containerEl = containerEl;
    this.onSend = onSend;
    this.onTyping = onTyping;
    this.maxLength = 2000;
  }

  render() {
    this.containerEl.innerHTML = `
      <form class="messages-compose" id="messages-compose-form">
        <label for="messages-compose-input" class="visually-hidden">Type a message</label>
        <textarea id="messages-compose-input" class="messages-compose__input" rows="1" maxlength="${this.maxLength}" placeholder="Type a message..." required></textarea>
        <button type="submit" class="messages-compose__send" aria-label="Send">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 2.5l14 7.5-14 7.5v-6l8-1.5-8-1.5z"/></svg>
        </button>
      </form>`;

    const form = this.containerEl.querySelector('#messages-compose-form');
    const input = this.containerEl.querySelector('#messages-compose-input');

    // Auto-grow.
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 120) + 'px';
      this.onTyping();
    });

    // Send on Enter (Shift+Enter for newline).
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        form.requestSubmit();
      }
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const body = input.value.trim();
      if (!body) return;
      this.onSend(body);
      input.value = '';
      input.style.height = 'auto';
    });
  }

  focus() {
    const input = this.containerEl.querySelector('#messages-compose-input');
    if (input) input.focus();
  }
}
```

- [ ] **Step 2: Create typing.js**

`public/js/messaging/typing.js`:

```js
/**
 * Typing indicators — debounced POST to server, render/expire display.
 */
export class TypingIndicator {
  /**
   * @param {HTMLElement} containerEl — element to render typing indicator into
   * @param {number} currentUserId
   */
  constructor(containerEl, currentUserId) {
    this.containerEl = containerEl;
    this.currentUserId = currentUserId;
    this.typingUsers = new Map(); // userId -> { displayName, timer }
    this.sendTimer = null;
    this.sendDebounceMs = 2000;
    this.expireMs = 5000;
    this.threadId = null;
  }

  setThread(threadId) {
    this.threadId = threadId;
    this.typingUsers.clear();
    this.render();
  }

  /** Call when the local user types — debounces the POST. */
  localTyping() {
    if (!this.threadId) return;

    if (this.sendTimer) return;

    this.sendTimer = setTimeout(() => {
      this.sendTimer = null;
    }, this.sendDebounceMs);

    fetch(`/api/messaging/threads/${this.threadId}/typing`, {
      method: 'POST',
    }).catch(() => {});
  }

  /** Called when a Mercure typing event arrives. */
  remoteTyping(userId, displayName) {
    if (userId === this.currentUserId) return;

    const existing = this.typingUsers.get(userId);
    if (existing?.timer) clearTimeout(existing.timer);

    const timer = setTimeout(() => {
      this.typingUsers.delete(userId);
      this.render();
    }, this.expireMs);

    this.typingUsers.set(userId, { displayName, timer });
    this.render();
  }

  render() {
    if (this.typingUsers.size === 0) {
      this.containerEl.innerHTML = '';
      return;
    }

    const names = [...this.typingUsers.values()].map((u) => u.displayName);
    const text = names.length === 1
      ? `${names[0]} is typing`
      : `${names.join(', ')} are typing`;

    this.containerEl.innerHTML = `<div class="messages-typing">${text}<span class="messages-typing__dots">...</span></div>`;
  }
}
```

- [ ] **Step 3: Commit**

```bash
git add public/js/messaging/compose.js public/js/messaging/typing.js
git commit -m "feat(#575): add compose bar and typing indicator JS modules"
```

---

## Task 15: JavaScript — Entry Point + Popover

**Files:**
- Create: `public/js/messaging/index.js`
- Create: `public/js/messaging/popover.js`

- [ ] **Step 1: Create index.js**

`public/js/messaging/index.js`:

```js
/**
 * Messaging entry point — wires all modules together.
 * Loaded on /messages page via <script type="module">.
 */
import { MercureConnection } from './mercure.js';
import { ThreadList } from './thread-list.js';
import { MessageView } from './message-view.js';
import { ComposeBar } from './compose.js';
import { TypingIndicator } from './typing.js';

const app = document.getElementById('messages-app');
if (app) {
  const userId = Number(app.dataset.userId);
  const hubUrl = app.dataset.hubUrl || '/hub';

  const threadListEl = document.getElementById('messages-thread-list');
  const threadViewEl = document.getElementById('messages-thread-view');
  const composeEl = document.getElementById('messages-compose-area');
  const typingEl = document.getElementById('messages-typing-area');

  if (!threadListEl || !threadViewEl || !composeEl || !typingEl) {
    throw new Error('Missing required DOM elements');
  }

  const threadList = new ThreadList(threadListEl, openThread);
  const messageView = new MessageView(threadViewEl, userId);
  const composeBar = new ComposeBar(composeEl, sendMessage, () => typing.localTyping());
  const typing = new TypingIndicator(typingEl, userId);

  let currentThreadId = null;
  let mercure = null;

  // Initial load.
  loadThreads();

  async function loadThreads() {
    try {
      const response = await fetch('/api/messaging/threads');
      if (!response.ok) throw new Error('Failed');
      const json = await response.json();
      const threads = Array.isArray(json.threads) ? json.threads : [];
      threadList.render(threads);

      // Subscribe to all thread topics + user unread.
      const topics = threads.map((t) => `/threads/${t.id}`);
      topics.push(`/users/${userId}/unread`);

      if (mercure) mercure.disconnect();
      mercure = new MercureConnection(hubUrl, handleMercureEvent);
      mercure.subscribe(topics, loadThreads);
    } catch {
      threadListEl.innerHTML = '<p class="messages-empty-note">Failed to load conversations</p>';
    }
  }

  async function openThread(threadId) {
    currentThreadId = threadId;
    typing.setThread(threadId);

    try {
      const response = await fetch(`/api/messaging/threads/${threadId}/messages?limit=100`);
      if (!response.ok) throw new Error('Failed');
      const json = await response.json();
      const messages = Array.isArray(json.messages) ? json.messages : [];
      messageView.render(threadId, messages);
      composeBar.render();
      composeBar.focus();

      // Mark as read.
      fetch(`/api/messaging/threads/${threadId}/read`, { method: 'POST' }).catch(() => {});
    } catch {
      threadViewEl.innerHTML = '<p class="messages-empty-note">Failed to load messages</p>';
    }
  }

  async function sendMessage(body) {
    if (!currentThreadId) return;

    // Optimistic append.
    const tempMsg = {
      id: Date.now(),
      thread_id: currentThreadId,
      sender_id: userId,
      body,
      created_at: Math.floor(Date.now() / 1000),
      edited_at: null,
      deleted_at: null,
    };
    messageView.appendMessage(tempMsg);

    try {
      await fetch(`/api/messaging/threads/${currentThreadId}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ body }),
      });
    } catch {
      // Message will appear via Mercure or next load.
    }

    threadList.updateThread(currentThreadId, { lastMessage: { body }, unreadCount: 0 });
  }

  function handleMercureEvent(data) {
    switch (data.type) {
      case 'message':
        if (data.message.thread_id === currentThreadId && data.message.sender_id !== userId) {
          messageView.appendMessage(data.message);
          fetch(`/api/messaging/threads/${currentThreadId}/read`, { method: 'POST' }).catch(() => {});
        }
        threadList.updateThread(data.message.thread_id, { lastMessage: data.message });
        break;

      case 'message_edited':
        messageView.updateMessage(data.id, data.body, data.edited_at);
        break;

      case 'message_deleted':
        messageView.markDeleted(data.id);
        break;

      case 'typing':
        typing.remoteTyping(data.user_id, data.display_name);
        break;

      case 'read':
        // Could update read receipt indicators here in future.
        break;

      case 'unread':
        // Handled by popover module.
        break;
    }
  }
}
```

- [ ] **Step 2: Create popover.js**

`public/js/messaging/popover.js`:

```js
/**
 * Header message popover — badge + dropdown.
 * Loaded on every page via base.html.twig.
 */

function escapeHtml(text) {
  return String(text)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

const trigger = document.getElementById('messages-popover-trigger');
const badge = document.getElementById('messages-badge');
const dropdown = document.getElementById('messages-popover-dropdown');

if (trigger && badge && dropdown) {
  const userId = Number(trigger.dataset.userId);
  const hubUrl = trigger.dataset.hubUrl || '/hub';

  // Load initial unread count.
  updateBadge();

  // Subscribe to unread topic via EventSource.
  try {
    const url = new URL(hubUrl, window.location.origin);
    url.searchParams.append('topic', `/users/${userId}/unread`);
    const es = new EventSource(url, { withCredentials: true });
    es.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        if (data.type === 'unread') {
          setBadge(data.count);
        }
      } catch {}
    };
  } catch {}

  // Toggle dropdown.
  trigger.addEventListener('click', async (event) => {
    event.stopPropagation();
    const isOpen = !dropdown.hidden;
    dropdown.hidden = isOpen;
    trigger.setAttribute('aria-expanded', String(!isOpen));

    if (!isOpen) {
      await loadRecentThreads();
    }
  });

  // Close on outside click.
  document.addEventListener('click', () => {
    dropdown.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
  });
  dropdown.addEventListener('click', (event) => event.stopPropagation());

  async function updateBadge() {
    try {
      const response = await fetch('/api/messaging/unread-count');
      if (!response.ok) return;
      const data = await response.json();
      setBadge(data.count || 0);
    } catch {}
  }

  function setBadge(count) {
    badge.textContent = count > 99 ? '99+' : String(count);
    badge.hidden = count === 0;
  }

  async function loadRecentThreads() {
    try {
      const response = await fetch('/api/messaging/threads?limit=5');
      if (!response.ok) return;
      const data = await response.json();
      const threads = Array.isArray(data.threads) ? data.threads : [];

      if (threads.length === 0) {
        dropdown.innerHTML = '<div class="messages-popover__empty">No conversations yet</div><a href="/messages" class="messages-popover__link">Open Messages</a>';
        return;
      }

      dropdown.innerHTML = threads.map((thread) => {
        const title = thread.title?.trim() || `Thread #${thread.id}`;
        const preview = thread.last_message?.body || 'No messages';
        const isUnread = thread.unread_count > 0;
        return `<a href="/messages#thread-${thread.id}" class="messages-popover__item ${isUnread ? 'messages-popover__item--unread' : ''}">
          <span class="messages-popover__title">${escapeHtml(title)}</span>
          <span class="messages-popover__preview">${escapeHtml(preview)}</span>
        </a>`;
      }).join('') + '<a href="/messages" class="messages-popover__link">Open Messages</a>';
    } catch {}
  }
}
```

- [ ] **Step 3: Commit**

```bash
git add public/js/messaging/index.js public/js/messaging/popover.js
git commit -m "feat(#575): add messaging entry point and header popover JS modules"
```

---

## Task 16: Templates — Messages Page + Header Popover

**Files:**
- Modify: `templates/messages.html.twig`
- Modify: `templates/base.html.twig`
- Modify: `templates/components/user-menu.html.twig`

- [ ] **Step 1: Replace messages.html.twig**

Replace the entire contents of `templates/messages.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('messages.title') }}{% endblock %}

{% block content %}
  <section class="messages-page">
    {% if account is not defined or not account.isAuthenticated() %}
      <div class="messages-empty">
        <h2>{{ trans('messages.auth_required_title') }}</h2>
        <p>{{ trans('messages.auth_required_body') }}</p>
      </div>
    {% else %}
      <div class="messages-layout" id="messages-app" data-user-id="{{ account.id() }}" data-hub-url="/hub">
        <aside class="messages-sidebar">
          <div class="messages-sidebar__header">
            <h2>Chats</h2>
            <button class="messages-compose-btn" id="messages-new-thread" aria-label="New message">✎</button>
          </div>
          <div id="messages-thread-list" class="messages-thread-list"></div>
        </aside>
        <main class="messages-main">
          <div id="messages-thread-view" class="messages-thread-view">
            <p class="messages-thread-view__placeholder">{{ trans('messages.select_thread') }}</p>
          </div>
          <div id="messages-typing-area"></div>
          <div id="messages-compose-area"></div>
        </main>
      </div>
    {% endif %}
  </section>
{% endblock %}

{% block scripts %}
  {{ parent() }}
  {% if account is defined and account.isAuthenticated() %}
    <script type="module" src="/js/messaging/index.js"></script>
  {% endif %}
{% endblock %}
```

- [ ] **Step 2: Add message icon to base.html.twig header**

In `templates/base.html.twig`, in the `site-header__right` div, add the message popover BEFORE the user-menu include:

```twig
        <div class="site-header__right">
          {% if account is defined and account.isAuthenticated() %}
          <div class="messages-popover" id="messages-popover">
            <button class="messages-popover__trigger" id="messages-popover-trigger" data-user-id="{{ account.id() }}" data-hub-url="/hub" aria-expanded="false" aria-controls="messages-popover-dropdown" aria-label="Messages">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <span class="messages-badge" id="messages-badge" hidden>0</span>
            </button>
            <div class="messages-popover__dropdown" id="messages-popover-dropdown" hidden></div>
          </div>
          {% endif %}
          {% include "components/user-menu.html.twig" %}
        </div>
```

- [ ] **Step 3: Add popover.js script to base.html.twig**

In the scripts block area at the bottom of `templates/base.html.twig`, add:

```twig
{% if account is defined and account.isAuthenticated() %}
  <script type="module" src="/js/messaging/popover.js"></script>
{% endif %}
```

- [ ] **Step 4: Add messages link to user-menu.html.twig**

In `templates/components/user-menu.html.twig`, in the first `user-menu__section` div, add before the profile link:

```twig
      <a href="/messages" class="user-menu__item">Messages</a>
```

- [ ] **Step 5: Commit**

```bash
git add templates/messages.html.twig templates/base.html.twig templates/components/user-menu.html.twig
git commit -m "feat(#575): update templates for messaging UI + header popover"
```

---

## Task 17: CSS — Messaging Components

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add messaging CSS to `@layer components`**

Add the following CSS block inside the existing `@layer components` section in `public/css/minoo.css`. Place it after the existing `.messages-*` rules, replacing them entirely:

```css
  /* === Messaging === */
  .messages-page {
    display: grid;
    gap: var(--space-md);
    height: calc(100dvh - var(--header-height, 4rem) - var(--space-lg));
  }

  .messages-layout {
    display: grid;
    grid-template-columns: minmax(16rem, 22rem) 1fr;
    gap: 0;
    height: 100%;
    background: var(--surface-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
  }

  .messages-sidebar {
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .messages-sidebar__header {
    padding: var(--space-sm) var(--space-sm);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;

    & h2 { margin: 0; font-size: var(--text-lg); }
  }

  .messages-compose-btn {
    background: var(--surface-elevated);
    border: none;
    color: var(--text-secondary);
    border-radius: 50%;
    width: 2rem;
    height: 2rem;
    cursor: pointer;
    font-size: var(--text-base);
  }

  .messages-thread-list {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-2xs);
  }

  .messages-thread-list__item {
    display: flex;
    gap: var(--space-xs);
    padding: var(--space-xs);
    border: none;
    border-radius: var(--radius-md);
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
    width: 100%;
    align-items: center;

    &:hover { background: var(--surface-elevated); }
    &.messages-thread-list__item--active { background: var(--surface-elevated); }
    &.messages-thread-list__item--unread .messages-thread-list__title { font-weight: 700; }
    &.messages-thread-list__item--unread .messages-thread-list__preview { color: var(--text-primary); font-weight: 600; }
  }

  .messages-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: var(--accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: var(--text-sm);
    flex-shrink: 0;

    &.messages-avatar--group { border-radius: var(--radius-md); }
  }

  .messages-thread-list__body {
    flex: 1;
    min-width: 0;
    display: grid;
    gap: 0.125rem;
  }

  .messages-thread-list__header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
  }

  .messages-thread-list__title {
    font-weight: 600;
    font-size: var(--text-sm);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .messages-thread-list__time {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    flex-shrink: 0;
  }

  .messages-thread-list__preview {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .messages-unread-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: var(--accent);
    flex-shrink: 0;
  }

  .messages-main {
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .messages-thread-view {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-sm);
  }

  .messages-thread-view__placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-tertiary);
  }

  .messages-message-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-2xs);
  }

  .messages-bubble {
    max-width: 70%;
    display: flex;
    flex-direction: column;

    &.messages-bubble--outgoing {
      align-self: flex-end;

      & .messages-bubble__content {
        background: var(--accent);
        color: white;
        border-radius: 1.125rem 1.125rem 0.25rem 1.125rem;
      }

      & .messages-bubble__meta { text-align: right; }
    }

    &.messages-bubble--incoming {
      align-self: flex-start;

      & .messages-bubble__content {
        background: var(--surface-elevated);
        border-radius: 1.125rem 1.125rem 1.125rem 0.25rem;
      }
    }

    &.messages-bubble--deleted {
      opacity: 0.6;

      & .messages-bubble__content { font-style: italic; }
    }
  }

  .messages-bubble__content {
    padding: var(--space-xs) var(--space-sm);
    font-size: var(--text-sm);
    line-height: 1.4;
    word-break: break-word;
  }

  .messages-bubble__meta {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    padding: 0.125rem var(--space-sm);
  }

  .messages-bubble__actions {
    display: inline-flex;
    gap: var(--space-2xs);
    margin-left: var(--space-xs);

    & button {
      background: none;
      border: none;
      color: var(--text-tertiary);
      cursor: pointer;
      font-size: var(--text-xs);
      padding: 0;

      &:hover { color: var(--text-primary); }
    }
  }

  .messages-compose {
    display: flex;
    gap: var(--space-xs);
    padding: var(--space-sm);
    border-top: 1px solid var(--border);
    align-items: flex-end;
  }

  .messages-compose__input {
    flex: 1;
    background: var(--surface-elevated);
    border: none;
    border-radius: 1.25rem;
    padding: var(--space-xs) var(--space-sm);
    color: var(--text-primary);
    font-size: var(--text-sm);
    resize: none;
    max-height: 7.5rem;
    font-family: inherit;

    &::placeholder { color: var(--text-tertiary); }
    &:focus { outline: 2px solid var(--accent); outline-offset: -2px; }
  }

  .messages-compose__send {
    background: var(--accent);
    border: none;
    color: white;
    border-radius: 50%;
    width: 2.25rem;
    height: 2.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &:hover { opacity: 0.9; }
  }

  .messages-typing {
    padding: var(--space-2xs) var(--space-sm);
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    font-style: italic;
  }

  .messages-typing__dots {
    animation: typing-dots 1.4s infinite;
  }

  @keyframes typing-dots {
    0%, 20% { opacity: 0.2; }
    50% { opacity: 1; }
    80%, 100% { opacity: 0.2; }
  }

  .messages-empty-note {
    color: var(--text-tertiary);
    text-align: center;
    padding: var(--space-lg);
  }

  /* Header popover */
  .messages-popover {
    position: relative;
  }

  .messages-popover__trigger {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    position: relative;
    padding: var(--space-2xs);

    &:hover { color: var(--text-primary); }
  }

  .messages-badge {
    position: absolute;
    top: -0.25rem;
    right: -0.25rem;
    background: var(--error, #ef4444);
    color: white;
    font-size: 0.625rem;
    font-weight: 700;
    min-width: 1rem;
    height: 1rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.25rem;
  }

  .messages-popover__dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 20rem;
    background: var(--surface-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg, 0 10px 25px rgba(0,0,0,0.2));
    z-index: 100;
    overflow: hidden;
    margin-top: var(--space-2xs);
  }

  .messages-popover__item {
    display: block;
    padding: var(--space-xs) var(--space-sm);
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--border);

    &:hover { background: var(--surface-elevated); }
    &.messages-popover__item--unread { background: rgba(91,141,239,0.08); }
  }

  .messages-popover__title {
    display: block;
    font-weight: 600;
    font-size: var(--text-sm);
  }

  .messages-popover__preview {
    display: block;
    font-size: var(--text-xs);
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .messages-popover__link {
    display: block;
    text-align: center;
    padding: var(--space-xs);
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--accent);
    text-decoration: none;

    &:hover { background: var(--surface-elevated); }
  }

  .messages-popover__empty {
    padding: var(--space-md);
    text-align: center;
    color: var(--text-tertiary);
    font-size: var(--text-sm);
  }

  /* Mobile responsive */
  @media (max-width: 48rem) {
    .messages-layout {
      grid-template-columns: 1fr;
    }

    .messages-sidebar {
      border-right: none;
    }

    .messages-main {
      display: none;
    }

    .messages-layout.messages-layout--thread-open .messages-sidebar {
      display: none;
    }

    .messages-layout.messages-layout--thread-open .messages-main {
      display: flex;
    }
  }
```

- [ ] **Step 2: Bump CSS version in base.html.twig**

Update the CSS link in `templates/base.html.twig`:

```html
<link rel="stylesheet" href="/css/minoo.css?v=42">
```

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat(#575): add messaging CSS components — bubbles, popover, responsive layout"
```

---

## Task 18: Run Migrations + Full Test Suite

- [ ] **Step 1: Delete stale manifest**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 2: Run migrations**

```bash
bin/waaseyaa migrate
```

Expected: 5 new migrations applied.

- [ ] **Step 3: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass (existing + new).

- [ ] **Step 4: Run schema check**

```bash
bin/waaseyaa schema:check
```

Expected: No drift detected.

- [ ] **Step 5: Commit any remaining changes**

```bash
git status
```

If clean, no commit needed. If there are uncommitted changes (e.g., from manifest rebuild), stage and commit them.

---

## Task 19: Close GitHub Issues

- [ ] **Step 1: Close completed issues**

```bash
gh issue close 424 --comment "Implemented in messaging system feature branch"
gh issue close 425 --comment "Implemented in messaging system feature branch"
gh issue close 427 --comment "Implemented in messaging system feature branch"
gh issue close 428 --comment "Implemented in messaging system feature branch"
gh issue close 429 --comment "Implemented in messaging system feature branch — templates/messages.html.twig"
gh issue close 430 --comment "Implemented in messaging system feature branch — thread view + reply"
```

Note: #431 (integration + Playwright tests) stays open — covered in a follow-up task.

- [ ] **Step 2: Verify milestone state**

```bash
gh issue list --milestone "Messaging" --state all --json number,title,state | head -30
```
