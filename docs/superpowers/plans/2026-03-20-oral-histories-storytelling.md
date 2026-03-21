# Oral Histories & Storytelling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add oral history entities, contributor migration, cultural protocol awareness, media handling, and hybrid browse templates to Minoo.

**Architecture:** Entity-first build order — migrate speaker→contributor first (riskiest), then add oral history entities, access policies, and finally templates + media. All entities follow Minoo's existing ContentEntityBase/ConfigEntityBase patterns. Templates use Twig SSR with vanilla CSS.

**Tech Stack:** PHP 8.4, Waaseyaa CMS framework, SQLite, Twig 3, vanilla CSS (`@layer`), PHPUnit 10.5

**Spec:** `docs/superpowers/specs/2026-03-20-oral-histories-storytelling-design.md`

**Skills:** `@minoo:entities` for entity patterns, `@minoo:frontend-ssr` for templates/CSS, `@waaseyaa-app-development` for entity registration

---

## File Structure

### New files

| File | Responsibility |
|---|---|
| `src/Entity/Contributor.php` | Contributor entity class (replaces Speaker) |
| `src/Entity/OralHistory.php` | Oral history content entity |
| `src/Entity/OralHistoryType.php` | Oral history type config entity (bundle) |
| `src/Entity/OralHistoryCollection.php` | Collection content entity |
| `src/Provider/OralHistoryServiceProvider.php` | Entity registration + routes for oral histories |
| `src/Provider/ContributorServiceProvider.php` | Contributor entity registration + routes |
| `src/Access/OralHistoryAccessPolicy.php` | Access policy for oral history domain |
| `src/Access/ContributorAccessPolicy.php` | Consent-gated access policy for contributors |
| `src/Controller/OralHistoryController.php` | HTTP controller for oral history pages |
| `src/Controller/ContributorController.php` | HTTP controller for contributor profiles |
| `migrations/20260320_010000_rename_speaker_to_contributor.php` | Table rename + new columns |
| `templates/oral-histories.html.twig` | Listing, collection detail, story detail, living record |
| `templates/contributors.html.twig` | Stub contributor profile page |
| `templates/components/oral-history-card.html.twig` | Story card component |
| `templates/components/collection-card.html.twig` | Collection card component |
| `templates/components/protocol-notice.html.twig` | Cultural protocol notice component |
| `tests/Minoo/Unit/Entity/ContributorTest.php` | Contributor entity tests |
| `tests/Minoo/Unit/Entity/OralHistoryTest.php` | Oral history entity tests |
| `tests/Minoo/Unit/Entity/OralHistoryTypeTest.php` | Oral history type entity tests |
| `tests/Minoo/Unit/Entity/OralHistoryCollectionTest.php` | Collection entity tests |
| `tests/Minoo/Unit/Access/OralHistoryAccessPolicyTest.php` | Access policy tests |
| `tests/Minoo/Unit/Access/ContributorAccessPolicyTest.php` | Contributor access policy tests |

### Modified files

| File | Change |
|---|---|
| `src/Provider/LanguageServiceProvider.php` | Remove speaker registration, import Contributor |
| `src/Access/LanguageAccessPolicy.php` | Replace `'speaker'` with `'contributor'` in entity type array |
| `src/Entity/Speaker.php` | Delete (replaced by Contributor) |
| `tests/Minoo/Unit/Entity/SpeakerTest.php` | Delete (replaced by ContributorTest) |
| `public/css/minoo.css` | Add protocol-notice, audio-player, oral-history card styles in `@layer components` |
| `templates/base.html.twig` | Add "Our Stories" nav link |

---

## Task 1: Contributor Entity Class

**Files:**
- Create: `src/Entity/Contributor.php`
- Test: `tests/Minoo/Unit/Entity/ContributorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Contributor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Contributor::class)]
final class ContributorTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $contributor = new Contributor([
            'name' => 'Elder Mary Owl',
        ]);

        $this->assertSame('Elder Mary Owl', $contributor->get('name'));
        $this->assertSame('contributor', $contributor->getEntityTypeId());
        $this->assertSame(1, $contributor->get('status'));
    }

    #[Test]
    public function it_supports_cultural_fields(): void
    {
        $contributor = new Contributor([
            'name' => 'James Owl',
            'role' => 'Elder',
            'clan' => 'Bear',
            'community_id' => 42,
            'cultural_group_id' => 7,
            'dialect' => 'Nishnaabemwin',
        ]);

        $this->assertSame('Elder', $contributor->get('role'));
        $this->assertSame('Bear', $contributor->get('clan'));
        $this->assertSame(42, $contributor->get('community_id'));
        $this->assertSame(7, $contributor->get('cultural_group_id'));
        $this->assertSame('Nishnaabemwin', $contributor->get('dialect'));
    }

    #[Test]
    public function it_defaults_consent_to_false(): void
    {
        $contributor = new Contributor(['name' => 'Test']);

        $this->assertSame(0, $contributor->get('consent_public'));
        $this->assertSame(0, $contributor->get('consent_record'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ContributorTest.php`
Expected: FAIL — class `Minoo\Entity\Contributor` not found

- [ ] **Step 3: Write the Contributor entity**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Contributor extends ContentEntityBase
{
    protected string $entityTypeId = 'contributor';

    protected array $entityKeys = [
        'id' => 'coid',
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
        if (!array_key_exists('consent_public', $values)) {
            $values['consent_public'] = 0;
        }
        if (!array_key_exists('consent_record', $values)) {
            $values['consent_record'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ContributorTest.php`
Expected: 3 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Contributor.php tests/Minoo/Unit/Entity/ContributorTest.php
git commit -m "feat(#367): add Contributor entity class"
```

---

## Task 2: Contributor Migration

**Files:**
- Create: `migrations/20260320_010000_rename_speaker_to_contributor.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Rename speaker table to contributors and add new cultural fields.
 *
 * Adds: slug, cultural_group_id, clan, role, lineage_notes, photo,
 *       consent_public, consent_record.
 * Renames: sid → coid.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        if ($schema->hasTable('speaker')) {
            $connection->executeStatement('ALTER TABLE speaker RENAME TO contributors');
        }

        if (!$schema->hasTable('contributors')) {
            return;
        }

        // Rename primary key sid → coid
        // SQLite doesn't support RENAME COLUMN before 3.25.0 but Minoo targets modern SQLite
        if ($schema->hasColumn('contributors', 'sid')) {
            $connection->executeStatement('ALTER TABLE contributors RENAME COLUMN sid TO coid');
        }

        $newColumns = [
            'cultural_group_id' => 'INTEGER DEFAULT NULL',
            'clan' => 'TEXT DEFAULT NULL',
            'role' => 'TEXT DEFAULT NULL',
            'lineage_notes' => 'TEXT DEFAULT NULL',
            'photo' => 'TEXT DEFAULT NULL',
            'consent_public' => 'INTEGER NOT NULL DEFAULT 0',
            'consent_record' => 'INTEGER NOT NULL DEFAULT 0',
        ];

        foreach ($newColumns as $column => $definition) {
            if (!$schema->hasColumn('contributors', $column)) {
                $connection->executeStatement(
                    "ALTER TABLE contributors ADD COLUMN {$column} {$definition}",
                );
            }
        }

        // Backfill slugs from names for existing rows
        // slug may already exist from speaker entity
        if (!$schema->hasColumn('contributors', 'slug')) {
            $connection->executeStatement(
                'ALTER TABLE contributors ADD COLUMN slug TEXT DEFAULT NULL',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
        // For safety, this is a no-op.
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `bin/waaseyaa migrate`
Expected: Migration applied successfully

- [ ] **Step 3: Verify with schema check**

Run: `bin/waaseyaa migrate:status`
Expected: Migration listed as applied

- [ ] **Step 4: Commit**

```bash
git add migrations/20260320_010000_rename_speaker_to_contributor.php
git commit -m "feat(#367): add speaker-to-contributor migration"
```

---

## Task 3: Update Language Domain (Speaker → Contributor)

**Files:**
- Modify: `src/Provider/LanguageServiceProvider.php`
- Modify: `src/Access/LanguageAccessPolicy.php`
- Delete: `src/Entity/Speaker.php`
- Delete: `tests/Minoo/Unit/Entity/SpeakerTest.php` (if it exists)

- [ ] **Step 1: Update LanguageServiceProvider**

In `src/Provider/LanguageServiceProvider.php`:
- Change `use Minoo\Entity\Speaker;` to `use Minoo\Entity\Contributor;`
- Replace the speaker EntityType registration (lines 83-106) with:

```php
        $this->entityType(new EntityType(
            id: 'contributor',
            label: 'Contributor',
            class: Contributor::class,
            keys: ['id' => 'coid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'code' => ['type' => 'string', 'label' => 'Speaker Code', 'description' => 'Abbreviation (e.g., es, nj, gh).', 'weight' => 5],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 10],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 12],
                'cultural_group_id' => ['type' => 'entity_reference', 'label' => 'Cultural Group', 'settings' => ['target_type' => 'cultural_group'], 'weight' => 13],
                'dialect' => ['type' => 'string', 'label' => 'Dialect', 'weight' => 14],
                'clan' => ['type' => 'string', 'label' => 'Clan', 'weight' => 15],
                'role' => ['type' => 'string', 'label' => 'Role', 'description' => 'Elder, Knowledge Keeper, Fluent Speaker, Storyteller, Youth, Community Member.', 'weight' => 16],
                'lineage_notes' => ['type' => 'text_long', 'label' => 'Lineage Notes', 'weight' => 17],
                'photo' => ['type' => 'string', 'label' => 'Photo', 'description' => 'File path to profile photo.', 'weight' => 20],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo (legacy)', 'settings' => ['target_type' => 'media'], 'weight' => 21],
                'copyright_status' => ['type' => 'string', 'label' => 'Copyright Status', 'default_value' => 'unknown', 'weight' => 99],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this contributor profile may be shown publicly.', 'weight' => 28, 'default' => 0],
                'consent_record' => ['type' => 'boolean', 'label' => 'Recording Consent', 'description' => 'Whether this contributor consented to digital recording.', 'weight' => 29, 'default' => 0],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'weight' => 30, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 31, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
```

- [ ] **Step 2: Update example_sentence FK to reference contributor**

In `src/Provider/LanguageServiceProvider.php`, in the `example_sentence` EntityType fieldDefinitions, change the `speaker_id` field:

```php
                'contributor_id' => ['type' => 'entity_reference', 'label' => 'Contributor', 'settings' => ['target_type' => 'contributor'], 'weight' => 15],
```

Remove the old `'speaker_id'` entry. Also add a column rename to the migration (Task 2) or add a separate step in the migration to rename `speaker_id` → `contributor_id` on the `example_sentence` table:

```php
        if ($schema->hasTable('example_sentence') && $schema->hasColumn('example_sentence', 'speaker_id')) {
            $connection->executeStatement('ALTER TABLE example_sentence RENAME COLUMN speaker_id TO contributor_id');
        }
```

- [ ] **Step 3: Update LanguageAccessPolicy**

In `src/Access/LanguageAccessPolicy.php`:
- Change the attribute to: `#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region'])]`
- Change the const to: `private const ENTITY_TYPES = ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region'];`
- Remove `'contributor'` from the Language policy — `ContributorAccessPolicy` (Task 9) is the sole policy for the contributor entity

- [ ] **Step 4: Delete Speaker entity**

```bash
git rm src/Entity/Speaker.php
git rm -f tests/Minoo/Unit/Entity/SpeakerTest.php
```

- [ ] **Step 5: Delete stale manifest cache**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 6: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All tests pass. If any test references `Speaker::class`, update it to `Contributor::class`.

- [ ] **Step 7: Commit**

```bash
git add src/Provider/LanguageServiceProvider.php src/Access/LanguageAccessPolicy.php
git commit -m "feat(#367): migrate speaker to contributor in Language domain"
```

---

## Task 4: OralHistoryType Config Entity

**Files:**
- Create: `src/Entity/OralHistoryType.php`
- Test: `tests/Minoo/Unit/Entity/OralHistoryTypeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\OralHistoryType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistoryType::class)]
final class OralHistoryTypeTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $type = new OralHistoryType([
            'type' => 'creation_story',
            'name' => 'Creation Story',
        ]);

        $this->assertSame('creation_story', $type->get('type'));
        $this->assertSame('Creation Story', $type->get('name'));
        $this->assertSame('oral_history_type', $type->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_description_to_empty(): void
    {
        $type = new OralHistoryType([
            'type' => 'personal_narrative',
            'name' => 'Personal Narrative',
        ]);

        $this->assertSame('', $type->get('description'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryTypeTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write OralHistoryType entity**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class OralHistoryType extends ConfigEntityBase
{
    protected string $entityTypeId = 'oral_history_type';

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

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryTypeTest.php`
Expected: 2 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/OralHistoryType.php tests/Minoo/Unit/Entity/OralHistoryTypeTest.php
git commit -m "feat(#352): add OralHistoryType config entity"
```

---

## Task 5: OralHistoryCollection Entity

**Files:**
- Create: `src/Entity/OralHistoryCollection.php`
- Test: `tests/Minoo/Unit/Entity/OralHistoryCollectionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\OralHistoryCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistoryCollection::class)]
final class OralHistoryCollectionTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $collection = new OralHistoryCollection([
            'title' => 'Creation Stories',
        ]);

        $this->assertSame('Creation Stories', $collection->get('title'));
        $this->assertSame('oral_history_collection', $collection->getEntityTypeId());
        $this->assertSame(1, $collection->get('status'));
    }

    #[Test]
    public function it_supports_protocol_fields(): void
    {
        $collection = new OralHistoryCollection([
            'title' => 'Winter Stories',
            'season' => 'winter',
            'protocol_level' => 'guidance',
            'protocol_notes' => 'Traditionally shared during winter months.',
            'story_cycle_order_type' => 'sequential',
        ]);

        $this->assertSame('winter', $collection->get('season'));
        $this->assertSame('guidance', $collection->get('protocol_level'));
        $this->assertSame('sequential', $collection->get('story_cycle_order_type'));
    }

    #[Test]
    public function it_defaults_protocol_level_to_open(): void
    {
        $collection = new OralHistoryCollection(['title' => 'Test']);

        $this->assertSame('open', $collection->get('protocol_level'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryCollectionTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write OralHistoryCollection entity**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class OralHistoryCollection extends ContentEntityBase
{
    protected string $entityTypeId = 'oral_history_collection';

    protected array $entityKeys = [
        'id' => 'ohcid',
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
        if (!array_key_exists('protocol_level', $values)) {
            $values['protocol_level'] = 'open';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryCollectionTest.php`
Expected: 3 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/OralHistoryCollection.php tests/Minoo/Unit/Entity/OralHistoryCollectionTest.php
git commit -m "feat(#358): add OralHistoryCollection entity"
```

---

## Task 6: OralHistory Entity

**Files:**
- Create: `src/Entity/OralHistory.php`
- Test: `tests/Minoo/Unit/Entity/OralHistoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\OralHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistory::class)]
final class OralHistoryTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $story = new OralHistory([
            'title' => 'How Nanabush Brought Fire',
            'type' => 'creation_story',
        ]);

        $this->assertSame('How Nanabush Brought Fire', $story->get('title'));
        $this->assertSame('creation_story', $story->bundle());
        $this->assertSame('oral_history', $story->getEntityTypeId());
        $this->assertSame(1, $story->get('status'));
    }

    #[Test]
    public function it_supports_media_fields(): void
    {
        $story = new OralHistory([
            'title' => 'Test Story',
            'type' => 'historical_account',
            'media_type' => 'self_hosted',
            'media_path' => 'storage/media/oral-histories/1-test-story.mp3',
            'media_duration' => 720,
            'media_format' => 'audio',
        ]);

        $this->assertSame('self_hosted', $story->get('media_type'));
        $this->assertSame(720, $story->get('media_duration'));
        $this->assertSame('audio', $story->get('media_format'));
    }

    #[Test]
    public function it_supports_living_record(): void
    {
        $story = new OralHistory([
            'title' => 'The Seven Fires Prophecy',
            'type' => 'creation_story',
            'is_living_record' => 1,
            'protocol_level' => 'living_record',
            'summary' => 'A foundational prophecy of the Anishinaabe people.',
        ]);

        $this->assertSame(1, $story->get('is_living_record'));
        $this->assertSame('living_record', $story->get('protocol_level'));
    }

    #[Test]
    public function it_defaults_protocol_level_to_open(): void
    {
        $story = new OralHistory(['title' => 'Test', 'type' => 'test']);

        $this->assertSame('open', $story->get('protocol_level'));
        $this->assertSame(0, $story->get('is_living_record'));
    }

    #[Test]
    public function it_supports_narrator_attribution(): void
    {
        $story = new OralHistory([
            'title' => 'Test',
            'type' => 'test',
            'contributor_id' => 5,
            'narrator_name' => 'Elder Mary Owl',
        ]);

        $this->assertSame(5, $story->get('contributor_id'));
        $this->assertSame('Elder Mary Owl', $story->get('narrator_name'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write OralHistory entity**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class OralHistory extends ContentEntityBase
{
    protected string $entityTypeId = 'oral_history';

    protected array $entityKeys = [
        'id' => 'ohid',
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
        if (!array_key_exists('protocol_level', $values)) {
            $values['protocol_level'] = 'open';
        }
        if (!array_key_exists('is_living_record', $values)) {
            $values['is_living_record'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/OralHistoryTest.php`
Expected: 5 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/OralHistory.php tests/Minoo/Unit/Entity/OralHistoryTest.php
git commit -m "feat(#352): add OralHistory entity with media and protocol fields"
```

---

## Task 7: OralHistory Service Provider

**Files:**
- Create: `src/Provider/OralHistoryServiceProvider.php`

- [ ] **Step 1: Write the service provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\OralHistory;
use Minoo\Entity\OralHistoryCollection;
use Minoo\Entity\OralHistoryType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class OralHistoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'oral_history',
            label: 'Oral History',
            class: OralHistory::class,
            keys: ['id' => 'ohid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            group: 'stories',
            fieldDefinitions: [
                'title' => ['type' => 'string', 'label' => 'Title', 'weight' => 0],
                'type' => ['type' => 'string', 'label' => 'Type', 'weight' => -1],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'content' => ['type' => 'text_long', 'label' => 'Transcript', 'description' => 'Full transcript text. Empty for living records.', 'weight' => 5],
                'summary' => ['type' => 'text', 'label' => 'Summary', 'description' => 'Short description for cards and living-record pages.', 'weight' => 6],
                'contributor_id' => ['type' => 'entity_reference', 'label' => 'Narrator', 'settings' => ['target_type' => 'contributor'], 'weight' => 10],
                'narrator_name' => ['type' => 'string', 'label' => 'Narrator Name', 'description' => 'Fallback text attribution when no contributor entity exists.', 'weight' => 11],
                'collection_id' => ['type' => 'entity_reference', 'label' => 'Collection', 'settings' => ['target_type' => 'oral_history_collection'], 'weight' => 12],
                'story_order' => ['type' => 'integer', 'label' => 'Story Order', 'description' => 'Position within collection. Null if standalone.', 'weight' => 13],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 14],
                'cultural_group_id' => ['type' => 'entity_reference', 'label' => 'Cultural Group', 'settings' => ['target_type' => 'cultural_group'], 'weight' => 15],
                'season' => ['type' => 'string', 'label' => 'Season', 'description' => 'winter, spring, summer, fall, all.', 'weight' => 20],
                'protocol_level' => ['type' => 'string', 'label' => 'Protocol Level', 'description' => 'open, guidance, living_record.', 'weight' => 21],
                'protocol_notes' => ['type' => 'text_long', 'label' => 'Protocol Notes', 'description' => 'Soft guidance text shown to visitors.', 'weight' => 22],
                'is_living_record' => ['type' => 'boolean', 'label' => 'Living Record', 'description' => 'True = no transcript, no media, placeholder only.', 'weight' => 23, 'default' => 0],
                'media_type' => ['type' => 'string', 'label' => 'Media Type', 'description' => 'self_hosted, external, or null.', 'weight' => 25],
                'media_path' => ['type' => 'string', 'label' => 'Media Path', 'description' => 'Path to self-hosted file.', 'weight' => 26],
                'media_url' => ['type' => 'uri', 'label' => 'Media URL', 'description' => 'External embed URL.', 'weight' => 27],
                'media_duration' => ['type' => 'integer', 'label' => 'Duration', 'description' => 'Duration in seconds.', 'weight' => 28],
                'media_format' => ['type' => 'string', 'label' => 'Media Format', 'description' => 'audio or video.', 'weight' => 29],
                'recorded_date' => ['type' => 'string', 'label' => 'Recorded Date', 'description' => 'Supports partial dates like "Winter 2021".', 'weight' => 30],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'weight' => 35, 'default' => 1],
                'consent_record' => ['type' => 'boolean', 'label' => 'Recording Consent', 'weight' => 36, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'weight' => 37, 'default' => 0],
                'tags' => ['type' => 'string', 'label' => 'Tags', 'description' => 'Comma-separated.', 'weight' => 38],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 40, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 50],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 51],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'oral_history_type',
            label: 'Oral History Type',
            class: OralHistoryType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'stories',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'description' => ['type' => 'text', 'label' => 'Description', 'weight' => 5],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'oral_history_collection',
            label: 'Oral History Collection',
            class: OralHistoryCollection::class,
            keys: ['id' => 'ohcid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'stories',
            fieldDefinitions: [
                'title' => ['type' => 'string', 'label' => 'Title', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'description' => ['type' => 'text_long', 'label' => 'Description', 'weight' => 5],
                'curator_notes' => ['type' => 'text_long', 'label' => 'Curator Notes', 'description' => 'Internal curation notes.', 'weight' => 6],
                'season' => ['type' => 'string', 'label' => 'Season', 'description' => 'winter, spring, summer, fall, all.', 'weight' => 10],
                'ceremony_context' => ['type' => 'string', 'label' => 'Ceremony Context', 'weight' => 11],
                'protocol_level' => ['type' => 'string', 'label' => 'Protocol Level', 'description' => 'open, guidance, living_record.', 'weight' => 12],
                'protocol_notes' => ['type' => 'text_long', 'label' => 'Protocol Notes', 'weight' => 13],
                'story_cycle_order_type' => ['type' => 'string', 'label' => 'Story Order Type', 'description' => 'sequential or unordered.', 'weight' => 14],
                'contributor_id' => ['type' => 'entity_reference', 'label' => 'Curator', 'settings' => ['target_type' => 'contributor'], 'weight' => 15],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 16],
                'cultural_group_id' => ['type' => 'entity_reference', 'label' => 'Cultural Group', 'settings' => ['target_type' => 'cultural_group'], 'weight' => 17],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'oral_histories.list',
            RouteBuilder::create('/oral-histories')
                ->controller('Minoo\\Controller\\OralHistoryController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // Register collections route BEFORE wildcard slug
        $router->addRoute(
            'oral_histories.collection',
            RouteBuilder::create('/oral-histories/collections/{slug}')
                ->controller('Minoo\\Controller\\OralHistoryController::collection')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'oral_histories.show',
            RouteBuilder::create('/oral-histories/{slug}')
                ->controller('Minoo\\Controller\\OralHistoryController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
```

- [ ] **Step 2: Delete stale manifest cache and run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Provider/OralHistoryServiceProvider.php
git commit -m "feat(#352): add OralHistoryServiceProvider with entity registration and routes"
```

---

## Task 8: Contributor Service Provider

**Files:**
- Create: `src/Provider/ContributorServiceProvider.php`
- Create: `src/Controller/ContributorController.php`

Note: This task moves the contributor entity registration OUT of `LanguageServiceProvider` and into its own provider, since contributor is now a cross-domain entity.

- [ ] **Step 1: Create ContributorServiceProvider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ContributorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contributor EntityType is registered in LanguageServiceProvider
        // (it was originally speaker). This provider only adds routes.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'contributors.show',
            RouteBuilder::create('/contributors/{slug}')
                ->controller('Minoo\\Controller\\ContributorController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
```

- [ ] **Step 2: Create ContributorController**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class ContributorController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('contributor');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $contributor = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('contributors.html.twig', [
            'path' => '/contributors/' . $slug,
            'contributor' => $contributor,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $contributor !== null ? 200 : 404,
        );
    }
}
```

- [ ] **Step 3: Delete stale manifest and run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add src/Provider/ContributorServiceProvider.php src/Controller/ContributorController.php
git commit -m "feat(#367): add ContributorServiceProvider and controller"
```

---

## Task 9: Access Policies

**Files:**
- Create: `src/Access/OralHistoryAccessPolicy.php`
- Create: `src/Access/ContributorAccessPolicy.php`
- Test: `tests/Minoo/Unit/Access/OralHistoryAccessPolicyTest.php`
- Test: `tests/Minoo/Unit/Access/ContributorAccessPolicyTest.php`

- [ ] **Step 1: Write OralHistoryAccessPolicy test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\OralHistoryAccessPolicy;
use Minoo\Entity\OralHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessResult;

#[CoversClass(OralHistoryAccessPolicy::class)]
final class OralHistoryAccessPolicyTest extends TestCase
{
    private OralHistoryAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new OralHistoryAccessPolicy();
    }

    #[Test]
    public function it_applies_to_oral_history_types(): void
    {
        $this->assertTrue($this->policy->appliesTo('oral_history'));
        $this->assertTrue($this->policy->appliesTo('oral_history_type'));
        $this->assertTrue($this->policy->appliesTo('oral_history_collection'));
        $this->assertFalse($this->policy->appliesTo('teaching'));
    }

    #[Test]
    public function it_allows_viewing_published_content(): void
    {
        $entity = new OralHistory(['title' => 'Test', 'type' => 'test', 'status' => 1]);
        $account = $this->createStub(\Waaseyaa\Access\AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function it_denies_viewing_unpublished_content(): void
    {
        $entity = new OralHistory(['title' => 'Test', 'type' => 'test', 'status' => 0]);
        $account = $this->createStub(\Waaseyaa\Access\AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isNeutral());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/OralHistoryAccessPolicyTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write OralHistoryAccessPolicy**

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['oral_history', 'oral_history_type', 'oral_history_collection'])]
final class OralHistoryAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['oral_history', 'oral_history_type', 'oral_history_collection'];

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
            'view' => (int) $entity->get('status') === 1
                ? AccessResult::allowed('Published content is publicly viewable.')
                : AccessResult::neutral('Cannot view unpublished oral history.'),
            default => AccessResult::neutral('Non-admin cannot modify oral histories.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create oral histories.');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/OralHistoryAccessPolicyTest.php`
Expected: 3 tests, all PASS

- [ ] **Step 5: Write ContributorAccessPolicy test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\ContributorAccessPolicy;
use Minoo\Entity\Contributor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessResult;

#[CoversClass(ContributorAccessPolicy::class)]
final class ContributorAccessPolicyTest extends TestCase
{
    private ContributorAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ContributorAccessPolicy();
    }

    #[Test]
    public function it_allows_viewing_published_consented_contributor(): void
    {
        $entity = new Contributor(['name' => 'Test', 'status' => 1, 'consent_public' => 1]);
        $account = $this->createStub(\Waaseyaa\Access\AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function it_denies_viewing_non_consented_contributor(): void
    {
        $entity = new Contributor(['name' => 'Test', 'status' => 1, 'consent_public' => 0]);
        $account = $this->createStub(\Waaseyaa\Access\AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isNeutral());
    }

    #[Test]
    public function it_admin_bypasses_consent(): void
    {
        $entity = new Contributor(['name' => 'Test', 'status' => 1, 'consent_public' => 0]);
        $account = $this->createStub(\Waaseyaa\Access\AccountInterface::class);
        $account->method('hasPermission')->willReturn(true);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }
}
```

- [ ] **Step 6: Write ContributorAccessPolicy**

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['contributor'])]
final class ContributorAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'contributor';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => (int) $entity->get('status') === 1 && (int) $entity->get('consent_public') === 1
                ? AccessResult::allowed('Published and consented contributor is publicly viewable.')
                : AccessResult::neutral('Cannot view non-consented contributor.'),
            default => AccessResult::neutral('Non-admin cannot modify contributors.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create contributors.');
    }
}
```

- [ ] **Step 7: Run all access policy tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/`
Expected: All tests PASS

- [ ] **Step 8: Delete stale manifest and run full suite**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 9: Commit**

```bash
git add src/Access/OralHistoryAccessPolicy.php src/Access/ContributorAccessPolicy.php tests/Minoo/Unit/Access/
git commit -m "feat(#352): add OralHistory and Contributor access policies"
```

---

## Task 10: OralHistory Controller

**Files:**
- Create: `src/Controller/OralHistoryController.php`

- [ ] **Step 1: Write OralHistoryController**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class OralHistoryController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $collectionStorage = $this->entityTypeManager->getStorage('oral_history_collection');
        $collectionIds = $collectionStorage->getQuery()
            ->condition('status', 1)
            ->sort('title', 'ASC')
            ->execute();
        $collections = $collectionIds !== [] ? array_values($collectionStorage->loadMultiple($collectionIds)) : [];

        $storyStorage = $this->entityTypeManager->getStorage('oral_history');
        $storyIds = $storyStorage->getQuery()
            ->condition('status', 1)
            ->sort('created_at', 'DESC')
            ->execute();
        $stories = $storyIds !== [] ? array_values($storyStorage->loadMultiple($storyIds)) : [];

        $html = $this->twig->render('oral-histories.html.twig', [
            'path' => '/oral-histories',
            'collections' => $collections,
            'stories' => $stories,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function collection(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $collectionStorage = $this->entityTypeManager->getStorage('oral_history_collection');
        $collectionIds = $collectionStorage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $collection = $collectionIds !== [] ? $collectionStorage->load(reset($collectionIds)) : null;

        $stories = [];
        $curator = null;
        if ($collection !== null) {
            $storyStorage = $this->entityTypeManager->getStorage('oral_history');
            $storyIds = $storyStorage->getQuery()
                ->condition('collection_id', $collection->id())
                ->condition('status', 1)
                ->sort('story_order', 'ASC')
                ->execute();
            $stories = $storyIds !== [] ? array_values($storyStorage->loadMultiple($storyIds)) : [];

            $curatorId = $collection->get('contributor_id');
            if ($curatorId !== null && $curatorId !== '') {
                $contributorStorage = $this->entityTypeManager->getStorage('contributor');
                $curator = $contributorStorage->load((int) $curatorId);
            }
        }

        $html = $this->twig->render('oral-histories.html.twig', [
            'path' => '/oral-histories/collections/' . $slug,
            'collection' => $collection,
            'stories' => $stories,
            'curator' => $curator,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $collection !== null ? 200 : 404,
        );
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storyStorage = $this->entityTypeManager->getStorage('oral_history');
        $storyIds = $storyStorage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $story = $storyIds !== [] ? $storyStorage->load(reset($storyIds)) : null;

        $collection = null;
        $collectionStories = [];
        $contributor = null;

        if ($story !== null) {
            // Load contributor for narrator attribution
            $contributorId = $story->get('contributor_id');
            if ($contributorId !== null && $contributorId !== '') {
                $contributorStorage = $this->entityTypeManager->getStorage('contributor');
                $contributor = $contributorStorage->load((int) $contributorId);
            }

            // Load collection for prev/next navigation
            $collectionId = $story->get('collection_id');
            if ($collectionId !== null && $collectionId !== '') {
                $collectionStorage = $this->entityTypeManager->getStorage('oral_history_collection');
                $collection = $collectionStorage->load((int) $collectionId);

                if ($collection !== null) {
                    $siblingIds = $storyStorage->getQuery()
                        ->condition('collection_id', $collectionId)
                        ->condition('status', 1)
                        ->sort('story_order', 'ASC')
                        ->execute();
                    $collectionStories = $siblingIds !== [] ? array_values($storyStorage->loadMultiple($siblingIds)) : [];
                }
            }
        }

        $html = $this->twig->render('oral-histories.html.twig', [
            'path' => '/oral-histories/' . $slug,
            'story' => $story,
            'collection' => $collection,
            'collection_stories' => $collectionStories,
            'contributor' => $contributor,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $story !== null ? 200 : 404,
        );
    }
}
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All existing tests still pass

- [ ] **Step 3: Commit**

```bash
git add src/Controller/OralHistoryController.php
git commit -m "feat(#364): add OralHistoryController for listing, collection, and detail routes"
```

---

## Task 11: Protocol Notice Component

**Files:**
- Create: `templates/components/protocol-notice.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Create protocol-notice component**

```twig
{# Protocol notice for oral histories and collections.
   Input: entity with protocol_level and protocol_notes fields.
   Renders nothing for protocol_level == 'open'. #}

{% if entity.protocol_level is defined %}
  {% if entity.protocol_level == 'guidance' and entity.protocol_notes is defined %}
    <aside class="protocol-notice protocol-notice--guidance">
      <strong>Cultural note:</strong> {{ entity.protocol_notes }}
    </aside>
  {% elseif entity.protocol_level == 'living_record' %}
    <div class="protocol-notice protocol-notice--living-record">
      <div class="protocol-notice__icon">🪶</div>
      <div class="protocol-notice__title">This story lives in oral tradition</div>
      <p class="protocol-notice__body">
        This teaching is shared person-to-person, as it has been for generations.
        It is acknowledged here but not recorded digitally, out of respect for the tradition.
      </p>
    </div>
  {% endif %}
{% endif %}
```

- [ ] **Step 2: Add CSS to minoo.css**

Add the following inside `@layer components` in `public/css/minoo.css`:

```css
/* Protocol notices */
.protocol-notice--guidance {
  border-left: 3px solid oklch(0.75 0.15 85);
  padding: 0.75rem 1rem;
  background: oklch(0.75 0.15 85 / 0.08);
  border-radius: 0 0.375rem 0.375rem 0;
  margin-block-end: 1.5rem;
}

.protocol-notice--living-record {
  border: 2px dashed oklch(0.75 0.15 85);
  border-radius: 0.5rem;
  padding: 1.5rem;
  text-align: center;
  margin-block-end: 1.5rem;
}

.protocol-notice__icon {
  font-size: 2rem;
  margin-block-end: 0.5rem;
}

.protocol-notice__title {
  font-weight: 700;
  margin-block-end: 0.5rem;
}

.protocol-notice__body {
  color: var(--text-secondary);
  max-inline-size: 40ch;
  margin-inline: auto;
}
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/protocol-notice.html.twig public/css/minoo.css
git commit -m "feat(#373): add protocol notice component with guidance and living record variants"
```

---

## Task 12: Card Components

**Files:**
- Create: `templates/components/oral-history-card.html.twig`
- Create: `templates/components/collection-card.html.twig`

- [ ] **Step 1: Create oral-history-card component**

```twig
<article class="card card--oral-history{% if is_living_record|default(false) %} card--living-record{% endif %}">
  <a href="{{ url }}" class="card__link">
    {% if is_living_record|default(false) %}
      <span class="card__badge card__badge--living-record">Living Record</span>
    {% elseif type is defined and type %}
      <span class="card__badge">{{ type|capitalize }}</span>
    {% endif %}

    <h3 class="card__title">{{ title }}</h3>

    {% if narrator_name is defined and narrator_name %}
      <p class="card__meta">Told by {{ narrator_name }}</p>
    {% endif %}

    {% if community_name is defined and community_name %}
      <p class="card__meta">{{ community_name }}</p>
    {% endif %}

    {% if is_living_record|default(false) %}
      <p class="card__excerpt card__excerpt--living-record">This story lives in oral tradition</p>
    {% else %}
      {% if media_format is defined and media_duration is defined and media_duration %}
        <p class="card__meta">
          {% if media_format == 'audio' %}🎵{% else %}🎬{% endif %}
          {{ (media_duration / 60)|round }} min
          &middot; {{ media_format|capitalize }}
        </p>
      {% endif %}

      {% if summary is defined and summary %}
        <p class="card__excerpt">{{ summary|length > 120 ? summary|slice(0, 120) ~ '…' : summary }}</p>
      {% endif %}
    {% endif %}
  </a>
</article>
```

- [ ] **Step 2: Create collection-card component**

```twig
<article class="card card--collection">
  <a href="/oral-histories/collections/{{ slug }}" class="card__link">
    <h3 class="card__title">{{ title }}</h3>

    {% if description is defined and description %}
      <p class="card__excerpt">{{ description|length > 120 ? description|slice(0, 120) ~ '…' : description }}</p>
    {% endif %}

    <div class="card__tags">
      {% if season is defined and season and season != 'all' %}
        <span class="card__tag card__tag--season">
          {% if season == 'winter' %}❄️{% elseif season == 'spring' %}🌱{% elseif season == 'summer' %}☀️{% elseif season == 'fall' %}🍂{% endif %}
          {{ season|capitalize }}
        </span>
      {% endif %}
      {% if protocol_level is defined and protocol_level == 'guidance' %}
        <span class="card__tag card__tag--protocol">Guidance</span>
      {% endif %}
      <span>{{ story_count|default(0) }} stories</span>
    </div>

    {% if curator_name is defined and curator_name %}
      <p class="card__meta">Curated by {{ curator_name }}</p>
    {% endif %}

    <span class="card__action">View collection →</span>
  </a>
</article>
```

- [ ] **Step 3: Add card CSS**

Add inside `@layer components` in `public/css/minoo.css`:

```css
/* Oral history cards */
.card--living-record {
  border-style: dashed;
  opacity: 0.8;
}

.card__badge--living-record {
  color: oklch(0.75 0.15 85);
}

.card__excerpt--living-record {
  font-style: italic;
}

.card--collection .card__action {
  color: var(--link);
  font-size: var(--step--1);
  margin-block-start: 0.5rem;
}
```

- [ ] **Step 4: Commit**

```bash
git add templates/components/oral-history-card.html.twig templates/components/collection-card.html.twig public/css/minoo.css
git commit -m "feat(#364): add oral history and collection card components"
```

---

## Task 13: Oral Histories Page Template

**Files:**
- Create: `templates/oral-histories.html.twig`

- [ ] **Step 1: Create the template**

This template handles all three oral history routes via conditional branching, following the teachings.html.twig pattern. Reference `templates/teachings.html.twig` for the exact `{% extends %}`, `{% block %}`, and conditional structure.

The template should have three branches:
1. **Listing** (`path == '/oral-histories'`): hero + collections grid + stories grid with tab labels
2. **Collection detail** (`collection is defined and collection`): breadcrumb + header + protocol notice + story list
3. **Story detail** (`story is defined and story`): breadcrumb + header + protocol notice + player/transcript OR living record placeholder + collection nav

Use `{% include "components/protocol-notice.html.twig" with { entity: ... } %}` for protocol notices, `{% include "components/oral-history-card.html.twig" with { ... } %}` for story cards, and `{% include "components/collection-card.html.twig" with { ... } %}` for collection cards.

For the audio player section (story detail, non-living-record):
```twig
{% if story.media_format is defined and story.media_format and not story.is_living_record %}
  <div class="audio-player">
    {% if story.media_type == 'self_hosted' and story.media_path %}
      {% if story.media_format == 'audio' %}
        <audio controls preload="metadata">
          <source src="/{{ story.media_path }}" type="audio/mpeg">
        </audio>
      {% elseif story.media_format == 'video' %}
        <video controls preload="metadata">
          <source src="/{{ story.media_path }}">
        </video>
      {% endif %}
    {% elseif story.media_type == 'external' and story.media_url %}
      <iframe src="{{ story.media_url }}" allowfullscreen></iframe>
    {% endif %}
  </div>
{% endif %}
```

- [ ] **Step 2: Add audio player CSS**

Add inside `@layer components` in `public/css/minoo.css`:

```css
/* Audio/video player */
.audio-player {
  background: var(--surface-2);
  border-radius: 0.5rem;
  padding: 1rem;
  margin-block-end: 1.5rem;
}

.audio-player audio,
.audio-player video {
  inline-size: 100%;
}

.audio-player iframe {
  inline-size: 100%;
  aspect-ratio: 16 / 9;
  border: none;
  border-radius: 0.375rem;
}
```

- [ ] **Step 3: Commit**

```bash
git add templates/oral-histories.html.twig public/css/minoo.css
git commit -m "feat(#364): add oral histories page template with listing, collection, and detail views"
```

---

## Task 14: Contributor Profile Template

**Files:**
- Create: `templates/contributors.html.twig`

- [ ] **Step 1: Create stub profile template**

```twig
{% extends "base.html.twig" %}

{% block title %}{{ contributor.name }} — Minoo{% endblock %}

{% block content %}
<div class="content-well">
  <nav class="breadcrumb">
    <a href="/oral-histories">Oral Histories</a> →
    <span>{{ contributor.name }}</span>
  </nav>

  <header class="detail-hero">
    {% if contributor.role is defined and contributor.role %}
      <span class="badge">{{ contributor.role }}</span>
    {% endif %}
    <h1>{{ contributor.name }}</h1>
    {% if contributor.community_name is defined and contributor.community_name %}
      <p class="detail-hero__meta">{{ contributor.community_name }}</p>
    {% endif %}
  </header>

  {% if contributor.bio is defined and contributor.bio %}
    <div class="prose">
      {% for paragraph in contributor.bio|split('\n\n') %}
        <p>{{ paragraph }}</p>
      {% endfor %}
    </div>
  {% endif %}
</div>
{% endblock %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/contributors.html.twig
git commit -m "feat(#367): add stub contributor profile template"
```

---

## Task 15: Navigation + Final Integration

**Files:**
- Modify: `templates/base.html.twig`

- [ ] **Step 1: Add nav link**

In `templates/base.html.twig`, add "Our Stories" link to the main navigation. Insert after the Events `<li>` (line 37) and before the Programs dropdown (line 38):

```html
            <li><a href="{{ lang_url('/oral-histories') }}"{% if path is defined and path starts with '/oral-histories' %} aria-current="page"{% endif %}>{{ trans('nav.oral_histories') }}</a></li>
```

This matches the exact pattern used by all other nav links — `lang_url()`, `aria-current`, `trans()`, wrapped in `<li>`.

**Note:** You'll also need to add the `nav.oral_histories` translation key (value: "Our Stories") to the translation files. Check the existing translation files for the pattern.

**Note on table creation:** The Waaseyaa framework auto-creates tables from EntityType fieldDefinitions when the entity is first accessed. No explicit CREATE TABLE migrations are needed for `oral_histories`, `oral_history_collections`, or `oral_history_types`. If auto-creation does not trigger, run `bin/waaseyaa schema:check` and create migrations as needed.

- [ ] **Step 2: Delete stale manifest and run full test suite**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 3: Start dev server and manually verify**

```bash
php -S localhost:8081 -t public
```

Visit:
- `http://localhost:8081/oral-histories` — should render (may be empty without seed data)
- `http://localhost:8081/oral-histories/nonexistent` — should 404

- [ ] **Step 4: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat(#364): add Our Stories nav link for oral histories"
```

---

## Task 16: Seed Data

**Files:**
- Modify or create seed data for oral history types

- [ ] **Step 1: Create seed data for oral history types**

Add a static method to the appropriate seeder (or create `OralHistorySeedData.php`) that returns the 5 type values:

```php
public static function oralHistoryTypes(): array
{
    return [
        ['type' => 'creation_story', 'name' => 'Creation Story', 'description' => 'Origin and creation narratives.'],
        ['type' => 'historical_account', 'name' => 'Historical Account', 'description' => 'Historical events and experiences.'],
        ['type' => 'personal_narrative', 'name' => 'Personal Narrative', 'description' => 'Individual life stories and memories.'],
        ['type' => 'land_teaching', 'name' => 'Land Teaching', 'description' => 'Teachings connected to specific places and the land.'],
        ['type' => 'family_story', 'name' => 'Family Story', 'description' => 'Stories passed down within families.'],
    ];
}
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat(#352): add oral history type seed data"
```

---

## Task 17: Playwright Smoke Test

- [ ] **Step 1: Start the dev server**

```bash
php -S localhost:8081 -t public &
```

- [ ] **Step 2: Use Playwright MCP to verify pages**

Take snapshots of:
- `/oral-histories` — listing page renders
- Navigate to a story detail if seed data exists
- Verify nav link "Our Stories" appears in header

- [ ] **Step 3: Stop dev server and commit any fixes**

```bash
kill %1
```

Fix any issues discovered during smoke testing, commit each fix separately.
