# Waaseyaa Alpha Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring Minoo in line with Waaseyaa framework HEAD (commits beyond alpha.103), adopting the community-scoped tenancy system and sovereignty configuration introduced in #1093–#1094.

**Architecture:** All 842 existing tests pass — no breaking API changes require fixes. The work is purely additive: implement `HasCommunityInterface` on the 7+ entities whose providers already declare a `community_id` field, and add `sovereignty_profile` to `config/waaseyaa.php`. The framework's `CommunityMiddleware` auto-registers via `#[AsMiddleware]` — no manual wiring needed. `CommunityScope` injection into storage drivers happens at boot time for entities that implement the interface.

**Tech Stack:** PHP 8.4, PHPUnit 10, Waaseyaa framework packages (waaseyaa/entity, waaseyaa/foundation), Playwright for smoke tests.

---

## Context

**Waaseyaa commits since alpha.103 (HEAD):**
- `65373499` fix: add public surface dispositions for community + sovereignty types
- `80542b7d` feat(#1094): community-scoped query isolation (multi-tenancy)
- `94f8b5af` feat(#1093): add SovereigntyProfile to Layer 0 (foundation)
- `c290e798` docs: add Public Surface sections to specs
- `368b1403` feat(#571): rewrite ControllerDispatcher as thin router delegator
- + domain router additions (SsrRouter, GraphQlRouter, JsonApiRouter, SearchRouter, McpRouter, etc.)

**What Minoo needs:**
1. `HasCommunityInterface` + `HasCommunityTrait` on entities with `community_id` fields
2. `sovereignty_profile` key in `config/waaseyaa.php`

**What Minoo does NOT need:**
- Any routing changes — ControllerDispatcher change is internal; Minoo's `RouteBuilder`-based routes are unaffected
- Any auth changes — `AuthServiceProvider` has no custom `RateLimiter` bindings
- Any event changes — `EventServiceProvider` is entity+routes only, no framework EventBus usage

**Entities requiring `HasCommunityInterface` (confirmed by grep on provider `community_id` field declarations):**
- `src/Entity/Event.php` (EventServiceProvider)
- `src/Entity/Group.php` (GroupServiceProvider)
- `src/Entity/Teaching.php` (TeachingServiceProvider)
- `src/Entity/OralHistory.php` (OralHistoryServiceProvider)
- `src/Entity/Contributor.php` (ContributorServiceProvider)
- `src/Entity/Post.php` (has `community_id` in constructor defaults)
- `src/Entity/Leader.php` (LeaderServiceProvider — verify in Task 3)
- Engagement entity — verify in Task 4

---

## Task 1: Create branch and confirm baseline

**Files:**
- None (branch only)

- [ ] **Step 1: Create the branch**

```bash
cd /home/jones/dev/minoo
git checkout release/v1
git pull
git checkout -b chore/waaseyaa-alpha-sync
```

Expected: branch created with clean working tree.

- [ ] **Step 2: Run PHPUnit baseline**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit --no-coverage 2>&1 | tail -5
```

Expected: `OK, but some tests were skipped!` — `Tests: 842, Assertions: 2427, Skipped: 3.`

If different, stop and investigate before proceeding.

- [ ] **Step 3: Commit (no-op, just establishes branch)**

```bash
git commit --allow-empty -m "chore: begin waaseyaa alpha sync (HasCommunityInterface + sovereignty_profile)"
```

---

## Task 2: Implement HasCommunityInterface on Event, Teaching, Group

**Files:**
- Modify: `src/Entity/Event.php`
- Modify: `src/Entity/Teaching.php`
- Modify: `src/Entity/Group.php`
- Test: `tests/Minoo/Unit/Entity/EventHasCommunityTest.php`
- Test: `tests/Minoo/Unit/Entity/TeachingHasCommunityTest.php`
- Test: `tests/Minoo/Unit/Entity/GroupHasCommunityTest.php`

- [ ] **Step 1: Write failing test for Event**

Create `tests/Minoo/Unit/Entity/EventHasCommunityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Event::class)]
final class EventHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Event());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $event = new Event();
        $this->assertNull($event->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $event = new Event(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $event->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $event = new Event();
        $event->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $event->getCommunityId());
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Entity/EventHasCommunityTest.php --no-coverage 2>&1 | tail -8
```

Expected: FAIL — `Call to undefined method Minoo\Entity\Event::getCommunityId()`

- [ ] **Step 3: Implement HasCommunityInterface on Event**

Edit `src/Entity/Event.php` — add the two use statements and interface declaration:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Event extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;

    protected string $entityTypeId = 'event';
    // ... rest of class unchanged
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Entity/EventHasCommunityTest.php --no-coverage 2>&1 | tail -5
```

Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Write and run failing test for Teaching**

Create `tests/Minoo/Unit/Entity/TeachingHasCommunityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Teaching;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Teaching::class)]
final class TeachingHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Teaching());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Teaching())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $teaching = new Teaching(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $teaching->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $teaching = new Teaching();
        $teaching->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $teaching->getCommunityId());
    }
}
```

Run it:

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Entity/TeachingHasCommunityTest.php --no-coverage 2>&1 | tail -5
```

Expected: FAIL

- [ ] **Step 6: Implement HasCommunityInterface on Teaching**

Edit `src/Entity/Teaching.php` — same pattern as Event:

```php
use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Teaching extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;
    // ... rest unchanged
```

Run test: expected `OK (4 tests, 4 assertions)`

- [ ] **Step 7: Write and run failing test for Group**

Create `tests/Minoo/Unit/Entity/GroupHasCommunityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Group::class)]
final class GroupHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Group());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Group())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $group = new Group(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $group->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $group = new Group();
        $group->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $group->getCommunityId());
    }
}
```

Run it: expected FAIL.

- [ ] **Step 8: Implement HasCommunityInterface on Group**

Edit `src/Entity/Group.php`:

```php
use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Group extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;
    // ... rest unchanged
```

Run test: expected `OK (4 tests, 4 assertions)`

- [ ] **Step 9: Run full suite to confirm no regressions**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit --no-coverage 2>&1 | tail -5
```

Expected: `Tests: 854, Assertions: 2439, Skipped: 3.` (12 new assertions)

- [ ] **Step 10: Commit**

```bash
cd /home/jones/dev/minoo
git add src/Entity/Event.php src/Entity/Teaching.php src/Entity/Group.php \
    tests/Minoo/Unit/Entity/EventHasCommunityTest.php \
    tests/Minoo/Unit/Entity/TeachingHasCommunityTest.php \
    tests/Minoo/Unit/Entity/GroupHasCommunityTest.php
git commit -m "feat: implement HasCommunityInterface on Event, Teaching, Group"
```

---

## Task 3: Implement HasCommunityInterface on OralHistory, Post, Leader

**Files:**
- Modify: `src/Entity/OralHistory.php`
- Modify: `src/Entity/Post.php`
- Modify: `src/Entity/Leader.php`
- Test: `tests/Minoo/Unit/Entity/OralHistoryHasCommunityTest.php`
- Test: `tests/Minoo/Unit/Entity/PostHasCommunityTest.php`
- Test: `tests/Minoo/Unit/Entity/LeaderHasCommunityTest.php`

- [ ] **Step 1: Verify Leader has community_id field in its provider**

```bash
grep -A5 'community_id' /home/jones/dev/minoo/src/Provider/LeaderServiceProvider.php | head -10
```

Expected: a field definition entry for `community_id`. If absent, skip Leader in this task.

- [ ] **Step 2: Write failing tests for OralHistory, Post, Leader**

Create `tests/Minoo/Unit/Entity/OralHistoryHasCommunityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\OralHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(OralHistory::class)]
final class OralHistoryHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new OralHistory());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new OralHistory())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $oh = new OralHistory(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $oh->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $oh = new OralHistory();
        $oh->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $oh->getCommunityId());
    }
}
```

Create `tests/Minoo/Unit/Entity/PostHasCommunityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Post::class)]
final class PostHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Post());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Post())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $post = new Post(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $post->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $post = new Post();
        $post->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $post->getCommunityId());
    }
}
```

Create `tests/Minoo/Unit/Entity/LeaderHasCommunityTest.php` (same pattern, substituting `Leader` for entity class and `lid`/`leader_id` for the ID key — verify by reading the file first):

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Leader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Leader::class)]
final class LeaderHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Leader());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Leader())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $leader = new Leader(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $leader->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $leader = new Leader();
        $leader->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $leader->getCommunityId());
    }
}
```

- [ ] **Step 3: Run tests to confirm they fail**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryHasCommunityTest.php tests/Minoo/Unit/Entity/PostHasCommunityTest.php tests/Minoo/Unit/Entity/LeaderHasCommunityTest.php --no-coverage 2>&1 | tail -8
```

Expected: all three fail with `Call to undefined method`.

- [ ] **Step 4: Implement HasCommunityInterface on OralHistory**

Edit `src/Entity/OralHistory.php`:

```php
use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class OralHistory extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;
    // ... rest unchanged
```

- [ ] **Step 5: Implement HasCommunityInterface on Post**

Edit `src/Entity/Post.php`:

```php
use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Post extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;
    // ... rest unchanged
```

- [ ] **Step 6: Implement HasCommunityInterface on Leader**

Read `src/Entity/Leader.php` first to confirm it extends `ContentEntityBase` and has no conflicting `getCommunityId`. Then edit:

```php
use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Leader extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;
    // ... rest unchanged
```

- [ ] **Step 7: Run the three new tests**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryHasCommunityTest.php tests/Minoo/Unit/Entity/PostHasCommunityTest.php tests/Minoo/Unit/Entity/LeaderHasCommunityTest.php --no-coverage 2>&1 | tail -5
```

Expected: `OK (12 tests, 12 assertions)`

- [ ] **Step 8: Run full suite**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit --no-coverage 2>&1 | tail -5
```

Expected: All pass.

- [ ] **Step 9: Commit**

```bash
cd /home/jones/dev/minoo
git add src/Entity/OralHistory.php src/Entity/Post.php src/Entity/Leader.php \
    tests/Minoo/Unit/Entity/OralHistoryHasCommunityTest.php \
    tests/Minoo/Unit/Entity/PostHasCommunityTest.php \
    tests/Minoo/Unit/Entity/LeaderHasCommunityTest.php
git commit -m "feat: implement HasCommunityInterface on OralHistory, Post, Leader"
```

---

## Task 4: Implement HasCommunityInterface on Contributor + audit EngagementServiceProvider

**Files:**
- Modify: `src/Entity/Contributor.php`
- Possibly modify: one or more entities registered by `EngagementServiceProvider`
- Test: `tests/Minoo/Unit/Entity/ContributorHasCommunityTest.php`
- Test: (new file per engagement entity if applicable)

- [ ] **Step 1: Write failing test for Contributor**

Create `tests/Minoo/Unit/Entity/ContributorHasCommunityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Contributor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Contributor::class)]
final class ContributorHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Contributor());
    }

    #[Test]
    public function get_community_id_returns_null_when_unset(): void
    {
        $this->assertNull((new Contributor())->getCommunityId());
    }

    #[Test]
    public function get_community_id_returns_value_from_constructor(): void
    {
        $c = new Contributor(['community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $c->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $c = new Contributor();
        $c->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $c->getCommunityId());
    }
}
```

Run: `php vendor/bin/phpunit tests/Minoo/Unit/Entity/ContributorHasCommunityTest.php --no-coverage 2>&1 | tail -5` — expected FAIL.

- [ ] **Step 2: Implement on Contributor**

Edit `src/Entity/Contributor.php`:

```php
use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Contributor extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;
    // ... rest unchanged
```

Run test: expected `OK (4 tests, 4 assertions)`

- [ ] **Step 3: Audit EngagementServiceProvider**

```bash
cat /home/jones/dev/minoo/src/Provider/EngagementServiceProvider.php
```

Find the entity class(es) it registers. For each class that has `community_id` in its field definitions:
- Write a test (same pattern as above, substituting the entity class name)
- Add `implements HasCommunityInterface` + `use HasCommunityTrait` to the entity class
- Run the test to confirm it passes

If `EngagementServiceProvider` uses an entity class that does NOT extend `ContentEntityBase`, stop and flag for human review before proceeding.

- [ ] **Step 4: Run full suite**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit --no-coverage 2>&1 | tail -5
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/minoo
git add src/Entity/Contributor.php tests/Minoo/Unit/Entity/ContributorHasCommunityTest.php
# Add any engagement entity files if modified in Step 3
git commit -m "feat: implement HasCommunityInterface on Contributor (+ engagement entities)"
```

---

## Task 5: Add sovereignty_profile to config/waaseyaa.php

**Files:**
- Modify: `config/waaseyaa.php`
- Test: `tests/Minoo/Unit/Config/SovereigntyConfigTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Minoo/Unit/Config/SovereigntyConfigTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Sovereignty\SovereigntyConfig;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

final class SovereigntyConfigTest extends TestCase
{
    #[Test]
    public function minoo_config_resolves_northops_profile_by_default(): void
    {
        $appConfig = require __DIR__ . '/../../../../config/waaseyaa.php';
        $sovereignty = SovereigntyConfig::fromArray($appConfig);
        $this->assertSame(SovereigntyProfile::NorthOps, $sovereignty->getProfile());
    }

    #[Test]
    public function sovereignty_profile_env_var_overrides_default(): void
    {
        $_ENV['WAASEYAA_SOVEREIGNTY_PROFILE'] = 'local';
        putenv('WAASEYAA_SOVEREIGNTY_PROFILE=local');

        $appConfig = require __DIR__ . '/../../../../config/waaseyaa.php';
        $sovereignty = SovereigntyConfig::fromArray($appConfig);
        $this->assertSame(SovereigntyProfile::Local, $sovereignty->getProfile());

        putenv('WAASEYAA_SOVEREIGNTY_PROFILE=');
        unset($_ENV['WAASEYAA_SOVEREIGNTY_PROFILE']);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Config/SovereigntyConfigTest.php --no-coverage 2>&1 | tail -8
```

Expected: FAIL — `SovereigntyProfile::Local` is returned (default fallback) instead of `NorthOps`, because `sovereignty_profile` key is absent from config.

- [ ] **Step 3: Add sovereignty_profile to config/waaseyaa.php**

Open `config/waaseyaa.php`. After the opening `return [` line, add as the first key:

```php
// Sovereignty profile: 'local', 'self_hosted', or 'northops'.
// Override with WAASEYAA_SOVEREIGNTY_PROFILE env var for per-environment control.
'sovereignty_profile' => getenv('WAASEYAA_SOVEREIGNTY_PROFILE') ?: 'northops',
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit tests/Minoo/Unit/Config/SovereigntyConfigTest.php --no-coverage 2>&1 | tail -5
```

Expected: `OK (2 tests, 2 assertions)`

- [ ] **Step 5: Run full suite**

```bash
cd /home/jones/dev/minoo && php vendor/bin/phpunit --no-coverage 2>&1 | tail -5
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/minoo
git add config/waaseyaa.php tests/Minoo/Unit/Config/SovereigntyConfigTest.php
git commit -m "feat: add sovereignty_profile to config (NorthOps default)"
```

---

## Task 6: Smoke tests + update CLAUDE.md

**Files:**
- Modify: `CLAUDE.md` (bump alpha reference)

- [ ] **Step 1: Run Playwright smoke tests**

```bash
cd /home/jones/dev/minoo && npx playwright test --reporter=line 2>&1 | tail -20
```

Expected: all pass. If any fail, investigate and fix before proceeding.

- [ ] **Step 2: Update CLAUDE.md alpha reference**

Open `CLAUDE.md`. Find the line referencing `alpha.103` and update it to reflect the current sync point (note: Waaseyaa is now at HEAD beyond alpha.103, these commits cover community tenancy + domain routers):

Replace the alpha reference to document what was adopted:

```
Last framework sync: Waaseyaa HEAD (community-scoped tenancy #1093-#1094, domain router architecture #571)
```

The exact location and phrasing depends on what `CLAUDE.md` currently says — read the file, find the alpha reference, and update accordingly.

- [ ] **Step 3: Commit**

```bash
cd /home/jones/dev/minoo
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md to reflect waaseyaa alpha sync"
```

- [ ] **Step 4: Use superpowers:verification-before-completion**

Before raising the PR, invoke `superpowers:verification-before-completion` to confirm:
- All entities with `community_id` field definitions now implement `HasCommunityInterface`
- `config/waaseyaa.php` has `sovereignty_profile` key
- All 842+ PHPUnit tests pass
- Playwright smoke tests pass
- No untracked entity files were missed

- [ ] **Step 5: Use superpowers:finishing-a-development-branch**

Raise PR against `release/v1` with title: `feat: waaseyaa alpha sync — community tenancy + sovereignty config`
