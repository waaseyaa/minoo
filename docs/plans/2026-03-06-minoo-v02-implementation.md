# Minoo v0.2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Stand up the first Waaseyaa production app with 13 entity types, taxonomy vocabularies, and NorthCloud ingestion pipeline for ojibwe.lib.umn.edu content.

**Architecture:** Entity-first build. Define all entity types as PHP classes in `src/Entity/` under the `Minoo\` namespace, registered via domain-grouped service providers in `src/Provider/`. Access policies in `src/Access/`. Tests are TDD — write failing test, implement, verify pass, commit. Waaseyaa's auto-wiring handles API routes, admin SPA discovery, and SQL table generation.

**Tech Stack:** PHP 8.3+, Waaseyaa framework (entity, taxonomy, api, admin, ssr packages), PHPUnit 10.5, SQLite, NorthCloud Go microservices (crawler/classifier/publisher), Redis pub/sub.

**Design doc:** `docs/plans/2026-03-06-minoo-v02-design.md`

---

## Task 1: Initialize Git Repo and GitHub Remote

**Files:**
- Create: `.gitignore`
- Create: `README.md`
- Modify: `composer.json` (add `Minoo\` autoload)

**Step 1: Initialize local git repo**

Run:
```bash
cd /home/jones/dev/minoo
git init
```

**Step 2: Create .gitignore**

```gitignore
/vendor/
/.firecrawl/
/waaseyaa.sqlite
/storage/
/files/
.phpunit.result.cache
.phpunit.cache/
*.sqlite-journal
```

**Step 3: Add Minoo autoload namespace to composer.json**

Add to the root `composer.json` under a new `autoload` key:

```json
"autoload": {
    "psr-4": {
        "Minoo\\": "src/"
    }
}
```

Also add to `autoload-dev`:
```json
"Minoo\\Tests\\": "tests/Minoo/"
```

**Step 4: Create src/ and config/ directories**

Run:
```bash
mkdir -p src/Entity src/Provider src/Access
cp -r skeleton/config ./config
```

**Step 5: Regenerate autoloader**

Run:
```bash
composer dump-autoload
```

**Step 6: Create README.md**

```markdown
# Minoo

First Waaseyaa application. Indigenous knowledge platform powered by NorthCloud ingestion.

## Development

```bash
composer dev          # Start PHP dev server + admin SPA
php -S localhost:8081 -t public  # PHP server only
```

## Testing

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```
```

**Step 7: Initial commit**

Run:
```bash
git add .gitignore README.md composer.json src/ config/ docs/plans/2026-03-06-minoo-v02-design.md docs/plans/2026-03-06-minoo-v02-implementation.md
git commit -m "feat: initialize Minoo project with Waaseyaa skeleton"
```

**Step 8: Create GitHub repo and push**

Run:
```bash
gh repo create waaseyaa/minoo --public --description "First Waaseyaa application. Indigenous knowledge platform powered by NorthCloud ingestion." --source . --push
```

**Step 9: Create milestone**

Run:
```bash
gh api repos/waaseyaa/minoo/milestones -f title="v0.2 – First Entities + NorthCloud Ingestion" -f description="Foundation + entity types + NorthCloud ingestion pipeline" -f state="open"
```

---

## Task 2: Add PHPUnit Config for Minoo Tests

**Files:**
- Modify: `phpunit.xml.dist` (add Minoo test suite)
- Create: `tests/Minoo/Unit/.gitkeep`

**Step 1: Add Minoo test suite to phpunit.xml.dist**

Add a new `<testsuite>` inside the `<testsuites>` element:

```xml
<testsuite name="MinooUnit">
    <directory>tests/Minoo/Unit</directory>
</testsuite>
```

**Step 2: Create test directory**

Run:
```bash
mkdir -p tests/Minoo/Unit/Entity tests/Minoo/Unit/Access tests/Minoo/Unit/Provider
```

**Step 3: Verify test runner works**

Run:
```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: `No tests executed.` (0 tests, no errors)

**Step 4: Commit**

Run:
```bash
git add phpunit.xml.dist tests/Minoo/
git commit -m "chore: add Minoo test suite to PHPUnit config"
```

---

## Task 3: Define Event + EventType Entities

**Files:**
- Create: `src/Entity/Event.php`
- Create: `src/Entity/EventType.php`
- Create: `src/Provider/EventServiceProvider.php`
- Test: `tests/Minoo/Unit/Entity/EventTest.php`
- Test: `tests/Minoo/Unit/Entity/EventTypeTest.php`

**Step 1: Write the failing test for Event**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $event = new Event(['title' => 'Spring Powwow', 'type' => 'powwow', 'starts_at' => '2026-06-21 10:00:00']);

        $this->assertSame('Spring Powwow', $event->get('title'));
        $this->assertSame('powwow', $event->bundle());
        $this->assertSame('2026-06-21 10:00:00', $event->get('starts_at'));
        $this->assertSame(1, $event->get('status'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $event = new Event(['title' => 'Test', 'type' => 'gathering', 'starts_at' => '2026-01-01']);

        $this->assertSame('event', $event->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_fields(): void
    {
        $event = new Event([
            'title' => 'Ceremony',
            'type' => 'ceremony',
            'starts_at' => '2026-06-21 10:00:00',
            'ends_at' => '2026-06-21 18:00:00',
            'location' => 'Mille Lacs',
            'description' => 'Annual ceremony.',
        ]);

        $this->assertSame('Mille Lacs', $event->get('location'));
        $this->assertSame('2026-06-21 18:00:00', $event->get('ends_at'));
        $this->assertSame('Annual ceremony.', $event->get('description'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/EventTest.php`
Expected: FAIL — `Class "Minoo\Entity\Event" not found`

**Step 3: Write Event entity class**

Create `src/Entity/Event.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Event extends ContentEntityBase
{
    protected string $entityTypeId = 'event';

    protected array $entityKeys = [
        'id' => 'eid',
        'uuid' => 'uuid',
        'label' => 'title',
        'bundle' => 'type',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/EventTest.php`
Expected: PASS (3 tests, 7 assertions)

**Step 5: Write the failing test for EventType**

Create `tests/Minoo/Unit/Entity/EventTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\EventType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventType::class)]
final class EventTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_machine_name_and_label(): void
    {
        $type = new EventType(['type' => 'powwow', 'name' => 'Powwow']);

        $this->assertSame('powwow', $type->id());
        $this->assertSame('Powwow', $type->label());
        $this->assertSame('event_type', $type->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_description_to_empty(): void
    {
        $type = new EventType(['type' => 'gathering', 'name' => 'Gathering']);

        $this->assertSame('', $type->get('description'));
    }
}
```

**Step 6: Write EventType entity class**

Create `src/Entity/EventType.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class EventType extends ConfigEntityBase
{
    protected string $entityTypeId = 'event_type';

    protected array $entityKeys = [
        'id' => 'type',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('description', $values)) {
            $values['description'] = '';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 7: Run both tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/EventTest.php tests/Minoo/Unit/Entity/EventTypeTest.php`
Expected: PASS (5 tests)

**Step 8: Write EventServiceProvider**

Create `src/Provider/EventServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Event;
use Minoo\Entity\EventType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'event',
            label: 'Event',
            class: Event::class,
            keys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'description' => 'Rich text event description.',
                    'weight' => 5,
                ],
                'location' => [
                    'type' => 'string',
                    'label' => 'Location',
                    'description' => 'Physical location or "online".',
                    'weight' => 10,
                ],
                'starts_at' => [
                    'type' => 'datetime',
                    'label' => 'Starts At',
                    'weight' => 15,
                ],
                'ends_at' => [
                    'type' => 'datetime',
                    'label' => 'Ends At',
                    'description' => 'Leave empty for open-ended events.',
                    'weight' => 16,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Featured Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'event_type',
            label: 'Event Type',
            class: EventType::class,
            keys: ['id' => 'type', 'label' => 'name'],
        ));
    }
}
```

**Step 9: Register provider in composer.json**

Add to `composer.json` under `extra`:

```json
"extra": {
    "waaseyaa": {
        "providers": [
            "Minoo\\Provider\\EventServiceProvider"
        ]
    }
}
```

**Step 10: Commit**

Run:
```bash
git add src/Entity/Event.php src/Entity/EventType.php src/Provider/EventServiceProvider.php tests/Minoo/Unit/Entity/EventTest.php tests/Minoo/Unit/Entity/EventTypeTest.php composer.json
git commit -m "feat: add Event and EventType entity types with service provider"
```

---

## Task 4: Define Group + GroupType Entities

**Files:**
- Create: `src/Entity/Group.php`
- Create: `src/Entity/GroupType.php`
- Create: `src/Provider/GroupServiceProvider.php`
- Test: `tests/Minoo/Unit/Entity/GroupTest.php`
- Test: `tests/Minoo/Unit/Entity/GroupTypeTest.php`

**Step 1: Write the failing test for Group**

Create `tests/Minoo/Unit/Entity/GroupTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Group::class)]
final class GroupTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $group = new Group(['name' => 'Ojibwe Language Table', 'type' => 'online']);

        $this->assertSame('Ojibwe Language Table', $group->get('name'));
        $this->assertSame('online', $group->bundle());
        $this->assertSame(1, $group->get('status'));
        $this->assertSame('group', $group->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_fields(): void
    {
        $group = new Group([
            'name' => 'Mille Lacs Band',
            'type' => 'offline',
            'url' => 'https://millelacsband.com',
            'region' => 'Minnesota',
            'description' => 'Tribal community group.',
        ]);

        $this->assertSame('https://millelacsband.com', $group->get('url'));
        $this->assertSame('Minnesota', $group->get('region'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GroupTest.php`
Expected: FAIL — class not found

**Step 3: Write Group entity class**

Create `src/Entity/Group.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Group extends ContentEntityBase
{
    protected string $entityTypeId = 'group';

    protected array $entityKeys = [
        'id' => 'gid',
        'uuid' => 'uuid',
        'label' => 'name',
        'bundle' => 'type',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GroupTest.php`
Expected: PASS

**Step 5: Write GroupType test and entity (same pattern as EventType)**

Create `tests/Minoo/Unit/Entity/GroupTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\GroupType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupType::class)]
final class GroupTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_machine_name_and_label(): void
    {
        $type = new GroupType(['type' => 'online', 'name' => 'Online Community']);

        $this->assertSame('online', $type->id());
        $this->assertSame('Online Community', $type->label());
        $this->assertSame('group_type', $type->getEntityTypeId());
    }
}
```

Create `src/Entity/GroupType.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class GroupType extends ConfigEntityBase
{
    protected string $entityTypeId = 'group_type';

    protected array $entityKeys = [
        'id' => 'type',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('description', $values)) {
            $values['description'] = '';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 6: Write GroupServiceProvider**

Create `src/Provider/GroupServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Group;
use Minoo\Entity\GroupType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class GroupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'group',
            label: 'Community Group',
            class: Group::class,
            keys: ['id' => 'gid', 'uuid' => 'uuid', 'label' => 'name', 'bundle' => 'type'],
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 5,
                ],
                'url' => [
                    'type' => 'uri',
                    'label' => 'Website',
                    'description' => 'External website URL.',
                    'weight' => 10,
                ],
                'region' => [
                    'type' => 'string',
                    'label' => 'Region',
                    'description' => 'Geographic region.',
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'group_type',
            label: 'Group Type',
            class: GroupType::class,
            keys: ['id' => 'type', 'label' => 'name'],
        ));
    }
}
```

**Step 7: Register provider in composer.json**

Add `"Minoo\\Provider\\GroupServiceProvider"` to the `extra.waaseyaa.providers` array.

**Step 8: Run all Minoo tests**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (8 tests)

**Step 9: Commit**

Run:
```bash
git add src/Entity/Group.php src/Entity/GroupType.php src/Provider/GroupServiceProvider.php tests/Minoo/Unit/Entity/GroupTest.php tests/Minoo/Unit/Entity/GroupTypeTest.php composer.json
git commit -m "feat: add Group and GroupType entity types with service provider"
```

---

## Task 5: Define CulturalGroup Entity (Hierarchical)

**Files:**
- Create: `src/Entity/CulturalGroup.php`
- Create: `src/Provider/CulturalGroupServiceProvider.php`
- Test: `tests/Minoo/Unit/Entity/CulturalGroupTest.php`

**Step 1: Write the failing test**

Create `tests/Minoo/Unit/Entity/CulturalGroupTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\CulturalGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalGroup::class)]
final class CulturalGroupTest extends TestCase
{
    #[Test]
    public function it_creates_root_group(): void
    {
        $group = new CulturalGroup(['name' => 'Anishinaabe']);

        $this->assertSame('Anishinaabe', $group->get('name'));
        $this->assertNull($group->get('parent_id'));
        $this->assertSame('cultural_group', $group->getEntityTypeId());
        $this->assertSame(1, $group->get('status'));
    }

    #[Test]
    public function it_creates_child_group_with_parent(): void
    {
        $group = new CulturalGroup([
            'name' => 'Ojibwe',
            'parent_id' => 1,
            'depth_label' => 'tribe',
        ]);

        $this->assertSame(1, $group->get('parent_id'));
        $this->assertSame('tribe', $group->get('depth_label'));
    }

    #[Test]
    public function it_defaults_sort_order_to_zero(): void
    {
        $group = new CulturalGroup(['name' => 'Test']);

        $this->assertSame(0, $group->get('sort_order'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CulturalGroupTest.php`
Expected: FAIL

**Step 3: Write CulturalGroup entity**

Create `src/Entity/CulturalGroup.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class CulturalGroup extends ContentEntityBase
{
    protected string $entityTypeId = 'cultural_group';

    protected array $entityKeys = [
        'id' => 'cgid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('sort_order', $values)) {
            $values['sort_order'] = 0;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CulturalGroupTest.php`
Expected: PASS (3 tests)

**Step 5: Write CulturalGroupServiceProvider**

Create `src/Provider/CulturalGroupServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\CulturalGroup;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class CulturalGroupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'cultural_group',
            label: 'Cultural Group',
            class: CulturalGroup::class,
            keys: ['id' => 'cgid', 'uuid' => 'uuid', 'label' => 'name'],
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'parent_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Parent Group',
                    'description' => 'Self-referential hierarchy.',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 5,
                ],
                'depth_label' => [
                    'type' => 'string',
                    'label' => 'Depth Label',
                    'description' => 'Free-text depth descriptor (nation, tribe, band, clan).',
                    'weight' => 6,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 10,
                ],
                'metadata' => [
                    'type' => 'text',
                    'label' => 'Metadata',
                    'description' => 'JSON blob for extensible properties.',
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'label' => 'Sort Order',
                    'description' => 'Manual ordering within siblings.',
                    'weight' => 25,
                    'default' => 0,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));
    }
}
```

**Step 6: Register provider, run all tests, commit**

Add `"Minoo\\Provider\\CulturalGroupServiceProvider"` to `extra.waaseyaa.providers`.

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (11 tests)

```bash
git add src/Entity/CulturalGroup.php src/Provider/CulturalGroupServiceProvider.php tests/Minoo/Unit/Entity/CulturalGroupTest.php composer.json
git commit -m "feat: add CulturalGroup entity with hierarchical parent_id tree"
```

---

## Task 6: Define Teaching + TeachingType Entities

**Files:**
- Create: `src/Entity/Teaching.php`
- Create: `src/Entity/TeachingType.php`
- Create: `src/Provider/TeachingServiceProvider.php`
- Test: `tests/Minoo/Unit/Entity/TeachingTest.php`
- Test: `tests/Minoo/Unit/Entity/TeachingTypeTest.php`

**Step 1: Write the failing test for Teaching**

Create `tests/Minoo/Unit/Entity/TeachingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Teaching;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teaching::class)]
final class TeachingTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $teaching = new Teaching([
            'title' => 'Seven Grandfather Teachings',
            'type' => 'culture',
            'content' => 'The Seven Grandfather Teachings...',
        ]);

        $this->assertSame('Seven Grandfather Teachings', $teaching->get('title'));
        $this->assertSame('culture', $teaching->bundle());
        $this->assertSame('teaching', $teaching->getEntityTypeId());
        $this->assertSame(1, $teaching->get('status'));
    }

    #[Test]
    public function it_supports_cultural_group_reference(): void
    {
        $teaching = new Teaching([
            'title' => 'Ojibwe Creation Story',
            'type' => 'history',
            'content' => 'Long ago...',
            'cultural_group_id' => 42,
        ]);

        $this->assertSame(42, $teaching->get('cultural_group_id'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/TeachingTest.php`
Expected: FAIL

**Step 3: Write Teaching entity**

Create `src/Entity/Teaching.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Teaching extends ContentEntityBase
{
    protected string $entityTypeId = 'teaching';

    protected array $entityKeys = [
        'id' => 'tid',
        'uuid' => 'uuid',
        'label' => 'title',
        'bundle' => 'type',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Run test to verify it passes, write TeachingType (same pattern)**

Create `tests/Minoo/Unit/Entity/TeachingTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\TeachingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeachingType::class)]
final class TeachingTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_machine_name_and_label(): void
    {
        $type = new TeachingType(['type' => 'culture', 'name' => 'Culture']);

        $this->assertSame('culture', $type->id());
        $this->assertSame('Culture', $type->label());
        $this->assertSame('teaching_type', $type->getEntityTypeId());
    }
}
```

Create `src/Entity/TeachingType.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class TeachingType extends ConfigEntityBase
{
    protected string $entityTypeId = 'teaching_type';

    protected array $entityKeys = [
        'id' => 'type',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('description', $values)) {
            $values['description'] = '';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 5: Write TeachingServiceProvider**

Create `src/Provider/TeachingServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Teaching;
use Minoo\Entity\TeachingType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class TeachingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: Teaching::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'content' => [
                    'type' => 'text_long',
                    'label' => 'Content',
                    'description' => 'Full teaching content.',
                    'weight' => 5,
                ],
                'cultural_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Cultural Group',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 10,
                ],
                'tags' => [
                    'type' => 'entity_reference',
                    'label' => 'Tags',
                    'description' => 'Cross-cutting topic tags.',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'teaching_type',
            label: 'Teaching Type',
            class: TeachingType::class,
            keys: ['id' => 'type', 'label' => 'name'],
        ));
    }
}
```

**Step 6: Register provider, run all tests, commit**

Add `"Minoo\\Provider\\TeachingServiceProvider"` to `extra.waaseyaa.providers`.

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (14 tests)

```bash
git add src/Entity/Teaching.php src/Entity/TeachingType.php src/Provider/TeachingServiceProvider.php tests/Minoo/Unit/Entity/TeachingTest.php tests/Minoo/Unit/Entity/TeachingTypeTest.php composer.json
git commit -m "feat: add Teaching and TeachingType entities with tags taxonomy reference"
```

---

## Task 7: Define CulturalCollection Entity

**Files:**
- Create: `src/Entity/CulturalCollection.php`
- Create: `src/Provider/CulturalCollectionServiceProvider.php`
- Test: `tests/Minoo/Unit/Entity/CulturalCollectionTest.php`

**Step 1: Write the failing test**

Create `tests/Minoo/Unit/Entity/CulturalCollectionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\CulturalCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalCollection::class)]
final class CulturalCollectionTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $item = new CulturalCollection([
            'title' => 'Loon',
            'media_id' => 1,
        ]);

        $this->assertSame('Loon', $item->get('title'));
        $this->assertSame(1, $item->get('media_id'));
        $this->assertSame('cultural_collection', $item->getEntityTypeId());
        $this->assertSame(1, $item->get('status'));
    }

    #[Test]
    public function it_supports_source_attribution(): void
    {
        $item = new CulturalCollection([
            'title' => 'Bandolier Bag',
            'media_id' => 2,
            'source_url' => 'https://ojibwe.lib.umn.edu/collection/bandolier-bag',
            'source_attribution' => 'Copyright Minnesota Historical Society',
        ]);

        $this->assertSame('https://ojibwe.lib.umn.edu/collection/bandolier-bag', $item->get('source_url'));
        $this->assertSame('Copyright Minnesota Historical Society', $item->get('source_attribution'));
    }
}
```

**Step 2: Run test to verify it fails, then implement**

Create `src/Entity/CulturalCollection.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class CulturalCollection extends ContentEntityBase
{
    protected string $entityTypeId = 'cultural_collection';

    protected array $entityKeys = [
        'id' => 'ccid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 3: Write CulturalCollectionServiceProvider**

Create `src/Provider/CulturalCollectionServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\CulturalCollection;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class CulturalCollectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'cultural_collection',
            label: 'Cultural Collection',
            class: CulturalCollection::class,
            keys: ['id' => 'ccid', 'uuid' => 'uuid', 'label' => 'title'],
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'description' => 'Cultural context and significance.',
                    'weight' => 5,
                ],
                'gallery' => [
                    'type' => 'entity_reference',
                    'label' => 'Gallery',
                    'description' => 'Gallery category (taxonomy term).',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 10,
                ],
                'source_url' => [
                    'type' => 'uri',
                    'label' => 'Source URL',
                    'description' => 'Original URL from ojibwe.lib.umn.edu.',
                    'weight' => 15,
                ],
                'source_attribution' => [
                    'type' => 'string',
                    'label' => 'Source Attribution',
                    'weight' => 16,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Primary Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));
    }
}
```

**Step 4: Register, test, commit**

Add `"Minoo\\Provider\\CulturalCollectionServiceProvider"` to providers.

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (16 tests)

```bash
git add src/Entity/CulturalCollection.php src/Provider/CulturalCollectionServiceProvider.php tests/Minoo/Unit/Entity/CulturalCollectionTest.php composer.json
git commit -m "feat: add CulturalCollection entity with gallery taxonomy reference"
```

---

## Task 8: Define Language Entities (DictionaryEntry, ExampleSentence, WordPart, Speaker)

**Files:**
- Create: `src/Entity/DictionaryEntry.php`
- Create: `src/Entity/ExampleSentence.php`
- Create: `src/Entity/WordPart.php`
- Create: `src/Entity/Speaker.php`
- Create: `src/Provider/LanguageServiceProvider.php`
- Test: `tests/Minoo/Unit/Entity/DictionaryEntryTest.php`
- Test: `tests/Minoo/Unit/Entity/ExampleSentenceTest.php`
- Test: `tests/Minoo/Unit/Entity/WordPartTest.php`
- Test: `tests/Minoo/Unit/Entity/SpeakerTest.php`

**Step 1: Write the failing test for DictionaryEntry**

Create `tests/Minoo/Unit/Entity/DictionaryEntryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\DictionaryEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryEntry::class)]
final class DictionaryEntryTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'jiimaan',
            'definition' => 'a canoe; a boat',
            'part_of_speech' => 'ni',
        ]);

        $this->assertSame('jiimaan', $entry->get('word'));
        $this->assertSame('a canoe; a boat', $entry->get('definition'));
        $this->assertSame('ni', $entry->get('part_of_speech'));
        $this->assertSame('dictionary_entry', $entry->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_language_code_to_oj(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'makwa',
            'definition' => 'a bear',
            'part_of_speech' => 'na',
        ]);

        $this->assertSame('oj', $entry->get('language_code'));
    }

    #[Test]
    public function it_stores_inflected_forms_as_json_string(): void
    {
        $forms = json_encode([
            ['form' => 'jiimaanan', 'label' => 'pl'],
            ['form' => 'jiimaanens', 'label' => 'dim'],
        ], JSON_THROW_ON_ERROR);

        $entry = new DictionaryEntry([
            'word' => 'jiimaan',
            'definition' => 'a canoe',
            'part_of_speech' => 'ni',
            'inflected_forms' => $forms,
        ]);

        $this->assertSame($forms, $entry->get('inflected_forms'));
    }
}
```

**Step 2: Implement DictionaryEntry**

Create `src/Entity/DictionaryEntry.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class DictionaryEntry extends ContentEntityBase
{
    protected string $entityTypeId = 'dictionary_entry';

    protected array $entityKeys = [
        'id' => 'deid',
        'uuid' => 'uuid',
        'label' => 'word',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('language_code', $values)) {
            $values['language_code'] = 'oj';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 3: Write ExampleSentence test and entity**

Create `tests/Minoo/Unit/Entity/ExampleSentenceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\ExampleSentence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleSentence::class)]
final class ExampleSentenceTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Biidaaboode jiimaan.',
            'english_text' => 'The canoe is floating this way.',
            'dictionary_entry_id' => 1,
        ]);

        $this->assertSame('Biidaaboode jiimaan.', $sentence->get('ojibwe_text'));
        $this->assertSame('The canoe is floating this way.', $sentence->get('english_text'));
        $this->assertSame(1, $sentence->get('dictionary_entry_id'));
        $this->assertSame('example_sentence', $sentence->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_language_code_to_oj(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Test',
            'english_text' => 'Test',
            'dictionary_entry_id' => 1,
        ]);

        $this->assertSame('oj', $sentence->get('language_code'));
    }

    #[Test]
    public function it_supports_speaker_and_audio(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Test',
            'english_text' => 'Test',
            'dictionary_entry_id' => 1,
            'speaker_id' => 5,
            'audio_url' => 'https://ojibwe.lib.umn.edu/audio/123.mp3',
        ]);

        $this->assertSame(5, $sentence->get('speaker_id'));
        $this->assertSame('https://ojibwe.lib.umn.edu/audio/123.mp3', $sentence->get('audio_url'));
    }
}
```

Create `src/Entity/ExampleSentence.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class ExampleSentence extends ContentEntityBase
{
    protected string $entityTypeId = 'example_sentence';

    protected array $entityKeys = [
        'id' => 'esid',
        'uuid' => 'uuid',
        'label' => 'ojibwe_text',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('language_code', $values)) {
            $values['language_code'] = 'oj';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Write WordPart test and entity**

Create `tests/Minoo/Unit/Entity/WordPartTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\WordPart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPart::class)]
final class WordPartTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $part = new WordPart([
            'form' => 'minw-',
            'type' => 'initial',
        ]);

        $this->assertSame('minw-', $part->get('form'));
        $this->assertSame('initial', $part->get('type'));
        $this->assertSame('word_part', $part->getEntityTypeId());
    }

    #[Test]
    public function it_supports_definition_and_source(): void
    {
        $part = new WordPart([
            'form' => '-aabid-',
            'type' => 'medial',
            'definition' => 'tooth, teeth',
            'source_url' => 'https://ojibwe.lib.umn.edu/word-part/aabid-medial',
        ]);

        $this->assertSame('tooth, teeth', $part->get('definition'));
        $this->assertSame('https://ojibwe.lib.umn.edu/word-part/aabid-medial', $part->get('source_url'));
    }
}
```

Create `src/Entity/WordPart.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class WordPart extends ContentEntityBase
{
    protected string $entityTypeId = 'word_part';

    protected array $entityKeys = [
        'id' => 'wpid',
        'uuid' => 'uuid',
        'label' => 'form',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 5: Write Speaker test and entity**

Create `tests/Minoo/Unit/Entity/SpeakerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Speaker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Speaker::class)]
final class SpeakerTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $speaker = new Speaker([
            'name' => 'Larry Smallwood',
            'code' => 'ls',
        ]);

        $this->assertSame('Larry Smallwood', $speaker->get('name'));
        $this->assertSame('ls', $speaker->get('code'));
        $this->assertSame('speaker', $speaker->getEntityTypeId());
    }

    #[Test]
    public function it_supports_bio_and_media(): void
    {
        $speaker = new Speaker([
            'name' => 'Eugene Stillday',
            'code' => 'es',
            'bio' => 'Elder and language keeper.',
            'media_id' => 10,
        ]);

        $this->assertSame('Elder and language keeper.', $speaker->get('bio'));
        $this->assertSame(10, $speaker->get('media_id'));
    }
}
```

Create `src/Entity/Speaker.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Speaker extends ContentEntityBase
{
    protected string $entityTypeId = 'speaker';

    protected array $entityKeys = [
        'id' => 'sid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 6: Write LanguageServiceProvider**

Create `src/Provider/LanguageServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\DictionaryEntry;
use Minoo\Entity\ExampleSentence;
use Minoo\Entity\Speaker;
use Minoo\Entity\WordPart;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class LanguageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'dictionary_entry',
            label: 'Dictionary Entry',
            class: DictionaryEntry::class,
            keys: ['id' => 'deid', 'uuid' => 'uuid', 'label' => 'word'],
            fieldDefinitions: [
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'definition' => ['type' => 'string', 'label' => 'Definition', 'weight' => 5],
                'part_of_speech' => ['type' => 'string', 'label' => 'Part of Speech', 'description' => 'Code: ni, na, vai, vti, vta, vii, nad, nid, etc.', 'weight' => 6],
                'stem' => ['type' => 'string', 'label' => 'Stem', 'description' => 'Root stem (e.g., /jiimaan-/).', 'weight' => 7],
                'inflected_forms' => ['type' => 'text', 'label' => 'Inflected Forms', 'description' => 'JSON array of form/label pairs.', 'weight' => 8],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'description' => 'ISO-style code (e.g., oj, oj-sw, oj-nw).', 'weight' => 9, 'default' => 'oj'],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'weight' => 15],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'example_sentence',
            label: 'Example Sentence',
            class: ExampleSentence::class,
            keys: ['id' => 'esid', 'uuid' => 'uuid', 'label' => 'ojibwe_text'],
            fieldDefinitions: [
                'english_text' => ['type' => 'string', 'label' => 'English Translation', 'weight' => 5],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 10],
                'speaker_id' => ['type' => 'entity_reference', 'label' => 'Speaker', 'settings' => ['target_type' => 'speaker'], 'weight' => 15],
                'audio_url' => ['type' => 'uri', 'label' => 'Audio URL', 'weight' => 20],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'weight' => 25, 'default' => 'oj'],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'word_part',
            label: 'Word Part',
            class: WordPart::class,
            keys: ['id' => 'wpid', 'uuid' => 'uuid', 'label' => 'form'],
            fieldDefinitions: [
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'type' => ['type' => 'string', 'label' => 'Type', 'description' => 'initial, medial, or final.', 'weight' => 5],
                'definition' => ['type' => 'string', 'label' => 'Definition', 'weight' => 10],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'weight' => 15],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'speaker',
            label: 'Speaker',
            class: Speaker::class,
            keys: ['id' => 'sid', 'uuid' => 'uuid', 'label' => 'name'],
            fieldDefinitions: [
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'code' => ['type' => 'string', 'label' => 'Speaker Code', 'description' => 'Abbreviation (e.g., es, nj, gh).', 'weight' => 5],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 10],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 20],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }
}
```

**Step 7: Register provider, run all tests, commit**

Add `"Minoo\\Provider\\LanguageServiceProvider"` to `extra.waaseyaa.providers`.

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (24 tests)

```bash
git add src/Entity/DictionaryEntry.php src/Entity/ExampleSentence.php src/Entity/WordPart.php src/Entity/Speaker.php src/Provider/LanguageServiceProvider.php tests/Minoo/Unit/Entity/ composer.json
git commit -m "feat: add language entities (DictionaryEntry, ExampleSentence, WordPart, Speaker)"
```

---

## Task 9: Implement Access Policies for All Entity Types

**Files:**
- Create: `src/Access/EventAccessPolicy.php`
- Create: `src/Access/GroupAccessPolicy.php`
- Create: `src/Access/CulturalGroupAccessPolicy.php`
- Create: `src/Access/TeachingAccessPolicy.php`
- Create: `src/Access/CulturalCollectionAccessPolicy.php`
- Create: `src/Access/LanguageAccessPolicy.php`
- Test: `tests/Minoo/Unit/Access/EventAccessPolicyTest.php`
- Test: `tests/Minoo/Unit/Access/LanguageAccessPolicyTest.php`

All Minoo content entities follow the same access pattern:
- **View**: Published content is viewable by anyone with `access content` permission. Unpublished only by admins.
- **Create/Update/Delete**: Requires admin permission.

**Step 1: Write the failing test for EventAccessPolicy**

Create `tests/Minoo/Unit/Access/EventAccessPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\EventAccessPolicy;
use Minoo\Entity\Event;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventAccessPolicy::class)]
final class EventAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_published_event(): void
    {
        $policy = new EventAccessPolicy();
        $event = new Event(['title' => 'Powwow', 'type' => 'powwow', 'starts_at' => '2026-06-21', 'status' => 1]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($event, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_event(): void
    {
        $policy = new EventAccessPolicy();
        $event = new Event(['title' => 'Draft', 'type' => 'powwow', 'starts_at' => '2026-06-21', 'status' => 0]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($event, 'view', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_event(): void
    {
        $policy = new EventAccessPolicy();
        $event = new Event(['title' => 'Test', 'type' => 'powwow', 'starts_at' => '2026-06-21']);
        $account = $this->createAdminAccount();

        $result = $policy->access($event, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_event(): void
    {
        $policy = new EventAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('event', 'powwow', $account);

        $this->assertFalse($result->isAllowed());
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    private function createAdminAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['administrator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/EventAccessPolicyTest.php`
Expected: FAIL

**Step 3: Implement EventAccessPolicy**

Create `src/Access/EventAccessPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Minoo\Entity\Event;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'event')]
final class EventAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'event';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => $this->viewAccess($entity, $account),
            default => AccessResult::neutral('Non-admin cannot modify events.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create events.');
    }

    private function viewAccess(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        assert($entity instanceof Event);

        if ((int) $entity->get('status') === 1 && $account->hasPermission('access content')) {
            return AccessResult::allowed('Published and user has access content.');
        }

        return AccessResult::neutral('Cannot view unpublished event.');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/EventAccessPolicyTest.php`
Expected: PASS (4 tests)

**Step 5: Create remaining access policies (same pattern)**

Create identical policies for each entity type, changing only the class name, entity type string, and assertion type. The pattern is the same for all Minoo entities:

`src/Access/GroupAccessPolicy.php` — `#[PolicyAttribute(entityType: 'group')]`
`src/Access/CulturalGroupAccessPolicy.php` — `#[PolicyAttribute(entityType: 'cultural_group')]`
`src/Access/TeachingAccessPolicy.php` — `#[PolicyAttribute(entityType: 'teaching')]`
`src/Access/CulturalCollectionAccessPolicy.php` — `#[PolicyAttribute(entityType: 'cultural_collection')]`

Create `src/Access/LanguageAccessPolicy.php` covering all 4 language entities:

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'])]
final class LanguageAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'];

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
            'view' => (int) $entity->get('status') === 1 && $account->hasPermission('access content')
                ? AccessResult::allowed('Published and user has access content.')
                : AccessResult::neutral('Cannot view unpublished language content.'),
            default => AccessResult::neutral('Non-admin cannot modify language content.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create language content.');
    }
}
```

**Step 6: Write LanguageAccessPolicy test**

Create `tests/Minoo/Unit/Access/LanguageAccessPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\LanguageAccessPolicy;
use Minoo\Entity\DictionaryEntry;
use Minoo\Entity\Speaker;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageAccessPolicy::class)]
final class LanguageAccessPolicyTest extends TestCase
{
    #[Test]
    public function it_applies_to_all_language_entity_types(): void
    {
        $policy = new LanguageAccessPolicy();

        $this->assertTrue($policy->appliesTo('dictionary_entry'));
        $this->assertTrue($policy->appliesTo('example_sentence'));
        $this->assertTrue($policy->appliesTo('word_part'));
        $this->assertTrue($policy->appliesTo('speaker'));
        $this->assertFalse($policy->appliesTo('node'));
    }

    #[Test]
    public function anonymous_can_view_published_dictionary_entry(): void
    {
        $policy = new LanguageAccessPolicy();
        $entry = new DictionaryEntry(['word' => 'makwa', 'definition' => 'bear', 'part_of_speech' => 'na', 'status' => 1]);

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return $p === 'access content'; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->access($entry, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_speaker(): void
    {
        $policy = new LanguageAccessPolicy();

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return $p === 'access content'; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->createAccess('speaker', '', $account);
        $this->assertFalse($result->isAllowed());
    }
}
```

**Step 7: Run all tests, commit**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (31+ tests)

```bash
git add src/Access/ tests/Minoo/Unit/Access/
git commit -m "feat: add access policies for all Minoo entity types"
```

---

## Task 10: Create Taxonomy Vocabulary Seed Data

**Files:**
- Create: `src/Seed/TaxonomySeeder.php`
- Test: `tests/Minoo/Unit/Seed/TaxonomySeederTest.php`

This task creates a seeder class that provides the initial taxonomy vocabulary and term data for `gallery` and `teaching_tags`. The seeder returns structured arrays that can be used by CLI commands or integration tests.

**Step 1: Write the test**

Create `tests/Minoo/Unit/Seed/TaxonomySeederTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\TaxonomySeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxonomySeeder::class)]
final class TaxonomySeederTest extends TestCase
{
    #[Test]
    public function it_provides_gallery_vocabulary_with_terms(): void
    {
        $data = TaxonomySeeder::galleryVocabulary();

        $this->assertSame('gallery', $data['vocabulary']['vid']);
        $this->assertSame('Gallery', $data['vocabulary']['name']);
        $this->assertCount(6, $data['terms']);
        $this->assertSame('fishing', $data['terms'][0]['name']);
    }

    #[Test]
    public function it_provides_teaching_tags_vocabulary_with_terms(): void
    {
        $data = TaxonomySeeder::teachingTagsVocabulary();

        $this->assertSame('teaching_tags', $data['vocabulary']['vid']);
        $this->assertCount(6, $data['terms']);
        $this->assertSame('ceremony', $data['terms'][0]['name']);
    }
}
```

**Step 2: Implement TaxonomySeeder**

Create `src/Seed/TaxonomySeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Seed;

final class TaxonomySeeder
{
    /** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
    public static function galleryVocabulary(): array
    {
        return [
            'vocabulary' => ['vid' => 'gallery', 'name' => 'Gallery', 'description' => 'Cultural collection gallery categories.'],
            'terms' => [
                ['name' => 'fishing', 'vid' => 'gallery'],
                ['name' => 'sugaring', 'vid' => 'gallery'],
                ['name' => 'lodges', 'vid' => 'gallery'],
                ['name' => 'hidework', 'vid' => 'gallery'],
                ['name' => 'ricing', 'vid' => 'gallery'],
                ['name' => 'wintertravel', 'vid' => 'gallery'],
            ],
        ];
    }

    /** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
    public static function teachingTagsVocabulary(): array
    {
        return [
            'vocabulary' => ['vid' => 'teaching_tags', 'name' => 'Teaching Tags', 'description' => 'Cross-cutting topic tags for teachings.'],
            'terms' => [
                ['name' => 'ceremony', 'vid' => 'teaching_tags'],
                ['name' => 'governance', 'vid' => 'teaching_tags'],
                ['name' => 'land', 'vid' => 'teaching_tags'],
                ['name' => 'kinship', 'vid' => 'teaching_tags'],
                ['name' => 'language', 'vid' => 'teaching_tags'],
                ['name' => 'history', 'vid' => 'teaching_tags'],
            ],
        ];
    }
}
```

**Step 3: Run tests, commit**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (33+ tests)

```bash
mkdir -p tests/Minoo/Unit/Seed
git add src/Seed/TaxonomySeeder.php tests/Minoo/Unit/Seed/TaxonomySeederTest.php
git commit -m "feat: add taxonomy seeder for gallery and teaching_tags vocabularies"
```

---

## Task 11: Create Config Entity Seed Data

**Files:**
- Create: `src/Seed/ConfigSeeder.php`
- Test: `tests/Minoo/Unit/Seed/ConfigSeederTest.php`

**Step 1: Write the test**

Create `tests/Minoo/Unit/Seed/ConfigSeederTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\ConfigSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigSeeder::class)]
final class ConfigSeederTest extends TestCase
{
    #[Test]
    public function it_provides_event_types(): void
    {
        $types = ConfigSeeder::eventTypes();

        $this->assertCount(3, $types);
        $this->assertSame('powwow', $types[0]['type']);
        $this->assertSame('Powwow', $types[0]['name']);
    }

    #[Test]
    public function it_provides_group_types(): void
    {
        $types = ConfigSeeder::groupTypes();

        $this->assertCount(3, $types);
        $this->assertSame('online', $types[0]['type']);
    }

    #[Test]
    public function it_provides_teaching_types(): void
    {
        $types = ConfigSeeder::teachingTypes();

        $this->assertCount(3, $types);
        $this->assertSame('culture', $types[0]['type']);
    }
}
```

**Step 2: Implement ConfigSeeder**

Create `src/Seed/ConfigSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Seed;

final class ConfigSeeder
{
    /** @return list<array{type: string, name: string, description: string}> */
    public static function eventTypes(): array
    {
        return [
            ['type' => 'powwow', 'name' => 'Powwow', 'description' => 'Traditional gathering with dance and song.'],
            ['type' => 'gathering', 'name' => 'Gathering', 'description' => 'Community gathering or meeting.'],
            ['type' => 'ceremony', 'name' => 'Ceremony', 'description' => 'Sacred or cultural ceremony.'],
        ];
    }

    /** @return list<array{type: string, name: string}> */
    public static function groupTypes(): array
    {
        return [
            ['type' => 'online', 'name' => 'Online Community'],
            ['type' => 'offline', 'name' => 'Local Community'],
            ['type' => 'advocacy', 'name' => 'Advocacy Organization'],
        ];
    }

    /** @return list<array{type: string, name: string}> */
    public static function teachingTypes(): array
    {
        return [
            ['type' => 'culture', 'name' => 'Culture'],
            ['type' => 'history', 'name' => 'History'],
            ['type' => 'language', 'name' => 'Language'],
        ];
    }
}
```

**Step 3: Run tests, commit**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: PASS (36+ tests)

```bash
git add src/Seed/ConfigSeeder.php tests/Minoo/Unit/Seed/ConfigSeederTest.php
git commit -m "feat: add config seeders for event, group, and teaching types"
```

---

## Task 12: Smoke Test — Boot Kernel with All Entity Types

**Files:**
- Test: `tests/Minoo/Integration/BootTest.php`

This integration test boots the Waaseyaa kernel with all Minoo providers registered and verifies entity types are discovered.

**Step 1: Add integration test suite to phpunit.xml.dist**

Add:
```xml
<testsuite name="MinooIntegration">
    <directory>tests/Minoo/Integration</directory>
</testsuite>
```

**Step 2: Write the integration test**

Create `tests/Minoo/Integration/BootTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class BootTest extends TestCase
{
    #[Test]
    public function kernel_boots_with_all_minoo_entity_types(): void
    {
        $kernel = new HttpKernel(dirname(__DIR__, 2));
        $kernel->boot();

        $manager = $kernel->getContainer()->get(EntityTypeManagerInterface::class);

        // Built-in entity types
        $this->assertNotNull($manager->getDefinition('node'));
        $this->assertNotNull($manager->getDefinition('taxonomy_term'));
        $this->assertNotNull($manager->getDefinition('user'));

        // Minoo entity types
        $this->assertNotNull($manager->getDefinition('event'));
        $this->assertNotNull($manager->getDefinition('event_type'));
        $this->assertNotNull($manager->getDefinition('group'));
        $this->assertNotNull($manager->getDefinition('group_type'));
        $this->assertNotNull($manager->getDefinition('cultural_group'));
        $this->assertNotNull($manager->getDefinition('teaching'));
        $this->assertNotNull($manager->getDefinition('teaching_type'));
        $this->assertNotNull($manager->getDefinition('cultural_collection'));
        $this->assertNotNull($manager->getDefinition('dictionary_entry'));
        $this->assertNotNull($manager->getDefinition('example_sentence'));
        $this->assertNotNull($manager->getDefinition('word_part'));
        $this->assertNotNull($manager->getDefinition('speaker'));
    }
}
```

**Step 3: Run the integration test**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/BootTest.php`

Expected: PASS — if it fails, this is where we find and fix Waaseyaa framework bugs.

**Step 4: Commit**

```bash
mkdir -p tests/Minoo/Integration
git add phpunit.xml.dist tests/Minoo/Integration/BootTest.php
git commit -m "test: add kernel boot smoke test verifying all Minoo entity types"
```

---

## Task 13: Create GitHub Issues for Remaining Work

After the entity foundation is solid (Tasks 1-12), create GitHub issues for the remaining milestone work. These are tracked in GitHub, not in this plan — they represent future tasks.

**Step 1: Create issues using gh CLI**

Run each `gh issue create` command for the remaining work items from the design doc:

```bash
# NorthCloud integration
gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Configure ojibwe.lib.umn.edu source in NorthCloud source-manager" --body "Add crawler source config with CSS selectors for dictionary entries, collections, word parts, and speaker pages."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Build Minoo-side structured importer for ojibwe.lib.umn.edu" --body "Custom importer that re-fetches NorthCloud-discovered pages and parses structured linguistic data into Waaseyaa entities (dictionary_entry, example_sentence, word_part, speaker, cultural_collection)."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Wire standard NorthCloud article ingestion via Node entities" --body "Subscribe to content:indigenous and indigenous:category:* Redis channels. Create Node entities of bundle type 'article' from NorthCloud article messages."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Add ingestion diagnostics and admin review queue" --body "Dashboard for monitoring ingestion status, reviewing imported content, and flagging items for manual review."

# Public UI
gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Design new Minoo visual identity" --body "New color palette, typography, and spacing tokens for Minoo. Broader global scope than diidjaaheer's Anishinaabe-focused palette."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Build SSR public pages for events and community groups" --body "Twig templates for event listing, event detail, group listing, group detail pages."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Build SSR public pages for cultural knowledge" --body "Twig templates for cultural group hierarchy, teaching listing/detail, cultural collection gallery."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Build SSR public pages for language content" --body "Twig templates for dictionary browse/search, entry detail with audio, word part explorer, speaker profiles."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Build global navigation and layout" --body "Site-wide header, footer, navigation menu, responsive layout shell."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" --title "Add search and filtering across entity types" --body "Cross-entity search, per-type filtering, and pagination for all public-facing listing pages."
```

**Step 2: Commit any local changes and push**

```bash
git push origin main
```

---

## Summary

| Task | What | Tests | Entities |
|------|------|-------|----------|
| 1 | Git init + GitHub repo + milestone | — | — |
| 2 | PHPUnit config for Minoo | — | — |
| 3 | Event + EventType | 5 | 2 |
| 4 | Group + GroupType | 3 | 2 |
| 5 | CulturalGroup | 3 | 1 |
| 6 | Teaching + TeachingType | 3 | 2 |
| 7 | CulturalCollection | 2 | 1 |
| 8 | Language entities (4) | 10 | 4 |
| 9 | Access policies (6) | 7 | — |
| 10 | Taxonomy seeders | 2 | — |
| 11 | Config seeders | 3 | — |
| 12 | Kernel boot smoke test | 1 | — |
| 13 | GitHub issues for remaining work | — | — |

**Total: 13 tasks, ~39 tests, 12 entity types + 2 taxonomy vocabularies**
