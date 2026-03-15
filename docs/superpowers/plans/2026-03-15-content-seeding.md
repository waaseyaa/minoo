# Content Seeding Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Seed minoo.live with curated launch content — real businesses, people, and events from the North Shore corridor.

**Architecture:** Add new fields to Group/ResourcePerson/Event providers, add "business" group type, create JSON fixture files in `content/`, and rewrite `bin/seed-content` to validate and upsert from those fixtures. TDD throughout.

**Tech Stack:** PHP 8.3, PHPUnit 10.5, Waaseyaa entity storage (SQLite), JSON fixtures

**Spec:** `docs/superpowers/specs/2026-03-15-content-seeding-design.md`

---

## File Structure

**New files:**
- `src/Support/FixtureLoader.php` — reads JSON fixtures, validates required fields and formats
- `src/Support/FixtureResolver.php` — resolves community names → IDs, taxonomy terms → IDs, linked_group slugs → IDs
- `tests/Minoo/Unit/Support/FixtureLoaderTest.php` — validation tests
- `tests/Minoo/Unit/Support/FixtureResolverTest.php` — resolution logic tests
- `tests/Minoo/Unit/Seed/ConfigSeederTest.php` — business group type test
- `content/businesses.json` — Group (type: business) fixture data
- `content/people.json` — ResourcePerson fixture data
- `content/events.json` — Event fixture data

**Modified files:**
- `src/Provider/GroupServiceProvider.php` — add phone, email, address, booking_url, source, verified_at fields
- `src/Provider/PeopleServiceProvider.php` — add linked_group_id, source, verified_at fields
- `src/Provider/EventServiceProvider.php` — add source, verified_at fields
- `src/Seed/ConfigSeeder.php` — add business group type
- `src/Seed/TaxonomySeeder.php` — add new role/offering terms for launch content
- `bin/seed-content` — rewrite to read from JSON fixtures with dry-run/apply modes
- `tests/Minoo/Unit/Seed/TaxonomySeederTest.php` — update term counts

---

## Chunk 1: Schema & Config Changes

### Task 1: Add business group type to ConfigSeeder

**Files:**
- Modify: `src/Seed/ConfigSeeder.php`
- Create: `tests/Minoo/Unit/Seed/ConfigSeederTest.php`

- [ ] **Step 1: Write the failing test**

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
    public function groupTypesIncludesBusiness(): void
    {
        $types = ConfigSeeder::groupTypes();
        $typeIds = array_column($types, 'type');

        $this->assertContains('business', $typeIds);
    }

    #[Test]
    public function businessGroupTypeHasName(): void
    {
        $types = ConfigSeeder::groupTypes();
        $business = null;
        foreach ($types as $type) {
            if ($type['type'] === 'business') {
                $business = $type;
                break;
            }
        }

        $this->assertNotNull($business);
        $this->assertSame('Local Business', $business['name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Seed/ConfigSeederTest.php -v`
Expected: FAIL — 'business' not found in group types

- [ ] **Step 3: Add business to ConfigSeeder::groupTypes()**

In `src/Seed/ConfigSeeder.php`, add to the `groupTypes()` return array:

```php
['type' => 'business', 'name' => 'Local Business'],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Seed/ConfigSeederTest.php -v`
Expected: PASS (2 tests, 2 assertions)

- [ ] **Step 5: Commit**

```bash
git add src/Seed/ConfigSeeder.php tests/Minoo/Unit/Seed/ConfigSeederTest.php
git commit -m "feat: add business group type to ConfigSeeder"
```

---

### Task 2: Add new fields to GroupServiceProvider

**Files:**
- Modify: `src/Provider/GroupServiceProvider.php`

- [ ] **Step 1: Run existing tests to confirm green baseline**

Run: `./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: All tests pass

- [ ] **Step 2: Add 6 new fields to GroupServiceProvider fieldDefinitions**

In `src/Provider/GroupServiceProvider.php`, add these fields after the `community_id` field (weight 16) and before `media_id`. Also bump `media_id` weight from 20 to 21 since `booking_url` now uses weight 20:

```php
'phone' => [
    'type' => 'string',
    'label' => 'Phone',
    'description' => 'Business phone number in E.164 format.',
    'weight' => 17,
],
'email' => [
    'type' => 'string',
    'label' => 'Email',
    'weight' => 18,
],
'address' => [
    'type' => 'string',
    'label' => 'Address',
    'description' => 'Physical address.',
    'weight' => 19,
],
'booking_url' => [
    'type' => 'uri',
    'label' => 'Booking URL',
    'description' => 'External booking link.',
    'weight' => 20,
],
'source' => [
    'type' => 'string',
    'label' => 'Source',
    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
    'weight' => 95,
],
'verified_at' => [
    'type' => 'datetime',
    'label' => 'Verified At',
    'description' => 'When this record was last verified.',
    'weight' => 96,
],
```

- [ ] **Step 3: Run tests to confirm nothing broke**

Run: `./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add src/Provider/GroupServiceProvider.php
git commit -m "feat: add phone, email, address, booking_url, source, verified_at fields to Group"
```

---

### Task 3: Add new fields to PeopleServiceProvider

**Files:**
- Modify: `src/Provider/PeopleServiceProvider.php`

- [ ] **Step 1: Add 3 new fields to PeopleServiceProvider fieldDefinitions**

In `src/Provider/PeopleServiceProvider.php`, add after the `website` field (weight 26) and before `media_id` (weight 28):

```php
'linked_group_id' => [
    'type' => 'entity_reference',
    'label' => 'Linked Business',
    'description' => 'The business group this person is associated with.',
    'settings' => ['target_type' => 'group'],
    'weight' => 27,
],
```

Add after `consent_ai_training` (weight 29) and before `status` (weight 30):

```php
'source' => [
    'type' => 'string',
    'label' => 'Source',
    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
    'weight' => 95,
],
'verified_at' => [
    'type' => 'datetime',
    'label' => 'Verified At',
    'description' => 'When this record was last verified.',
    'weight' => 96,
],
```

- [ ] **Step 2: Run tests to confirm nothing broke**

Run: `./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Provider/PeopleServiceProvider.php
git commit -m "feat: add linked_group_id, source, verified_at fields to ResourcePerson"
```

---

### Task 4: Add new fields to EventServiceProvider

**Files:**
- Modify: `src/Provider/EventServiceProvider.php`

- [ ] **Step 1: Add 2 new fields to EventServiceProvider fieldDefinitions**

In `src/Provider/EventServiceProvider.php`, add after `consent_ai_training` (weight 29) and before `status` (weight 30):

```php
'source' => [
    'type' => 'string',
    'label' => 'Source',
    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
    'weight' => 95,
],
'verified_at' => [
    'type' => 'datetime',
    'label' => 'Verified At',
    'description' => 'When this record was last verified.',
    'weight' => 96,
],
```

- [ ] **Step 2: Run tests to confirm nothing broke**

Run: `./vendor/bin/phpunit --testsuite MinooUnit -v`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Provider/EventServiceProvider.php
git commit -m "feat: add source, verified_at fields to Event"
```

---

### Task 5: Add new taxonomy terms for launch content

**Files:**
- Modify: `src/Seed/TaxonomySeeder.php`
- Modify: `tests/Minoo/Unit/Seed/TaxonomySeederTest.php`

The existing `person_roles` vocabulary has 12 terms but is missing roles for the launch content (e.g., Artist, Web Developer). The `person_offerings` vocabulary has 10 terms but is missing offerings like Hair Services, Esthetics.

- [ ] **Step 1: Write failing test for new terms**

In `tests/Minoo/Unit/Seed/TaxonomySeederTest.php`, add or update test methods:

```php
#[Test]
public function personRolesIncludesArtist(): void
{
    $data = TaxonomySeeder::personRolesVocabulary();
    $names = array_column($data['terms'], 'name');
    $this->assertContains('Artist', $names);
}

#[Test]
public function personOfferingsIncludesHairServices(): void
{
    $data = TaxonomySeeder::personOfferingsVocabulary();
    $names = array_column($data['terms'], 'name');
    $this->assertContains('Hair Services', $names);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Seed/TaxonomySeederTest.php -v`
Expected: FAIL — 'Artist' not found

- [ ] **Step 3: Add new terms to TaxonomySeeder**

In `src/Seed/TaxonomySeeder.php`:

Add to `personRolesVocabulary()` terms array:
```php
['name' => 'Artist', 'vid' => 'person_roles'],
['name' => 'Web Developer', 'vid' => 'person_roles'],
['name' => 'Healer', 'vid' => 'person_roles'],
```

Add to `personOfferingsVocabulary()` terms array:
```php
['name' => 'Hair Services', 'vid' => 'person_offerings'],
['name' => 'Esthetics', 'vid' => 'person_offerings'],
['name' => 'Massage', 'vid' => 'person_offerings'],
['name' => 'Nail Services', 'vid' => 'person_offerings'],
['name' => 'Web Development', 'vid' => 'person_offerings'],
```

- [ ] **Step 4: Update existing term count assertions if they exist**

Check `tests/Minoo/Unit/Seed/TaxonomySeederTest.php` for any `assertCount` calls on person_roles (was 12, now 15) and person_offerings (was 10, now 15). Update them.

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Seed/TaxonomySeederTest.php -v`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add src/Seed/TaxonomySeeder.php tests/Minoo/Unit/Seed/TaxonomySeederTest.php
git commit -m "feat: add Artist, Web Developer, Healer roles and service offering terms"
```

---

### Task 6: Clear manifest and run full test suite

- [ ] **Step 1: Delete stale manifest cache**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass (253+ tests). New field definitions don't break existing tests because they're nullable.

- [ ] **Step 3: Run schema check**

```bash
bin/waaseyaa schema:check
```

Expected: May report drift for new columns on existing tables — this is expected. On dev with fresh SQLite, tables will be created with all columns.

- [ ] **Step 4: Commit if any cleanup was needed**

---

## Chunk 2: Fixture Infrastructure

### Task 7: Create FixtureLoader with validation

**Files:**
- Create: `src/Support/FixtureLoader.php`
- Create: `tests/Minoo/Unit/Support/FixtureLoaderTest.php`

- [ ] **Step 1: Write failing tests for FixtureLoader**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\FixtureLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixtureLoader::class)]
final class FixtureLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/minoo-fixtures-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }

    #[Test]
    public function loadsValidBusinessesJson(): void
    {
        file_put_contents($this->tempDir . '/businesses.json', json_encode([
            [
                'name' => 'Test Business',
                'slug' => 'test-business',
                'type' => 'business',
                'community' => 'Test Town',
            ],
        ]));

        $loader = new FixtureLoader($this->tempDir);
        $result = $loader->load('businesses');

        $this->assertCount(1, $result);
        $this->assertSame('test-business', $result[0]['slug']);
    }

    #[Test]
    public function rejectsInvalidJson(): void
    {
        file_put_contents($this->tempDir . '/businesses.json', 'not json');

        $loader = new FixtureLoader($this->tempDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $loader->load('businesses');
    }

    #[Test]
    public function returnsMissingFileAsEmptyArray(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $result = $loader->load('nonexistent');

        $this->assertSame([], $result);
    }

    #[Test]
    public function validateBusinessRequiredFields(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([['name' => 'No Slug']], 'businesses');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('slug', $errors[0]);
    }

    #[Test]
    public function validatePeopleRequiredFields(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([['slug' => 'has-slug']], 'people');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name', $errors[0]);
    }

    #[Test]
    public function validateEventsRequiredFields(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['title' => 'Event', 'slug' => 'event', 'type' => 'gathering', 'community' => 'Town'],
        ], 'events');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('starts_at', $errors[0]);
    }

    #[Test]
    public function validateRejectsDuplicateSlugs(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['name' => 'A', 'slug' => 'same-slug', 'type' => 'business', 'community' => 'Town'],
            ['name' => 'B', 'slug' => 'same-slug', 'type' => 'business', 'community' => 'Town'],
        ], 'businesses');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate', strtolower($errors[0]));
    }

    #[Test]
    public function validatePhoneFormatWhenPresent(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['name' => 'Biz', 'slug' => 'biz', 'type' => 'business', 'community' => 'Town', 'phone' => 'not-a-phone'],
        ], 'businesses');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('phone', strtolower($errors[0]));
    }

    #[Test]
    public function validateAcceptsE164Phone(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['name' => 'Biz', 'slug' => 'biz', 'type' => 'business', 'community' => 'Town', 'phone' => '+17058698163'],
        ], 'businesses');

        $this->assertSame([], $errors);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/FixtureLoaderTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Implement FixtureLoader**

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

final class FixtureLoader
{
    private const REQUIRED_FIELDS = [
        'businesses' => ['name', 'slug', 'type', 'community'],
        'people' => ['name', 'slug', 'community'],
        'events' => ['title', 'slug', 'type', 'community', 'starts_at'],
    ];

    public function __construct(private readonly string $contentDir) {}

    /** @return list<array<string, mixed>> */
    public function load(string $fixtureType): array
    {
        $path = $this->contentDir . '/' . $fixtureType . '.json';

        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in {$path}");
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string> Validation error messages
     */
    public function validate(array $records, string $fixtureType): array
    {
        $errors = [];
        $requiredFields = self::REQUIRED_FIELDS[$fixtureType] ?? [];
        $slugs = [];

        foreach ($records as $index => $record) {
            $label = $record['slug'] ?? $record['name'] ?? "record {$index}";

            foreach ($requiredFields as $field) {
                if (empty($record[$field])) {
                    $errors[] = "{$label}: missing required field '{$field}'";
                }
            }

            if (isset($record['slug'])) {
                if (isset($slugs[$record['slug']])) {
                    $errors[] = "{$label}: duplicate slug '{$record['slug']}'";
                }
                $slugs[$record['slug']] = true;
            }

            if (isset($record['phone']) && !preg_match('/^\+[1-9]\d{6,14}$/', $record['phone'])) {
                $errors[] = "{$label}: invalid phone format (expected E.164)";
            }

            if (isset($record['email']) && !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$label}: invalid email format";
            }

            if (isset($record['booking_url']) && !filter_var($record['booking_url'], FILTER_VALIDATE_URL)) {
                $errors[] = "{$label}: invalid booking_url format";
            }

            if (isset($record['url']) && !filter_var($record['url'], FILTER_VALIDATE_URL)) {
                $errors[] = "{$label}: invalid url format";
            }
        }

        return $errors;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/FixtureLoaderTest.php -v`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Support/FixtureLoader.php tests/Minoo/Unit/Support/FixtureLoaderTest.php
git commit -m "feat: add FixtureLoader with validation for content fixtures"
```

---

### Task 8: Create FixtureResolver

**Files:**
- Create: `src/Support/FixtureResolver.php`
- Create: `tests/Minoo/Unit/Support/FixtureResolverTest.php`

The resolver handles three lookups: community name → ID, taxonomy term name → ID, linked_group slug → ID. Since these need entity storage, the unit test will mock the storage interface.

- [ ] **Step 1: Write failing tests for FixtureResolver**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\FixtureResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Entity\Query\EntityQueryInterface;

#[CoversClass(FixtureResolver::class)]
final class FixtureResolverTest extends TestCase
{
    #[Test]
    public function resolvesCommunityByName(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([42]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveCommunity('Sagamok Anishnawbek');

        $this->assertSame(42, $result);
    }

    #[Test]
    public function returnsNullForUnknownCommunity(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveCommunity('Nonexistent Town');

        $this->assertNull($result);
    }

    #[Test]
    public function resolvesGroupSlugToId(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([7]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('group')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $result = $resolver->resolveGroupSlug('nginaajiiw-salon-spa');

        $this->assertSame(7, $result);
    }

    #[Test]
    public function resolvesTaxonomyTermsByName(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturnOnConsecutiveCalls([101], [102], []);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('taxonomy_term')->willReturn($storage);

        $resolver = new FixtureResolver($etm);
        $warnings = [];
        $result = $resolver->resolveTaxonomyTerms(['Artist', 'Crafter', 'Unknown'], 'person_roles', $warnings);

        $this->assertSame([101, 102], $result);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Unknown', $warnings[0]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/FixtureResolverTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Implement FixtureResolver**

Check first if `Waaseyaa\Entity\EntityTypeManagerInterface` and `Waaseyaa\Entity\Storage\EntityStorageInterface` and `Waaseyaa\Entity\Query\EntityQueryInterface` exist in the framework. If not, use the concrete classes. The existing `bin/` scripts use `$kernel->getEntityTypeManager()` which returns the concrete `EntityTypeManager`. Adapt the test mocks accordingly if interfaces don't exist.

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

use Waaseyaa\Entity\EntityTypeManager;

final class FixtureResolver
{
    /** @var array<string, int|null> */
    private array $communityCache = [];

    /** @var array<string, int|null> */
    private array $groupSlugCache = [];

    public function __construct(private readonly EntityTypeManager $entityTypeManager) {}

    public function resolveCommunity(string $name): ?int
    {
        if (array_key_exists($name, $this->communityCache)) {
            return $this->communityCache[$name];
        }

        $storage = $this->entityTypeManager->getStorage('community');

        // Exact match first
        $ids = $storage->getQuery()->condition('name', $name)->execute();

        if ($ids === []) {
            // Case-insensitive fallback — query all and match
            $allIds = $storage->getQuery()->execute();
            foreach ($allIds as $id) {
                $entity = $storage->load($id);
                if ($entity !== null && strcasecmp($entity->get('name'), $name) === 0) {
                    $this->communityCache[$name] = $id;
                    return $id;
                }
            }
            $this->communityCache[$name] = null;
            return null;
        }

        $id = reset($ids);
        $this->communityCache[$name] = $id;
        return $id;
    }

    public function resolveGroupSlug(string $slug): ?int
    {
        if (array_key_exists($slug, $this->groupSlugCache)) {
            return $this->groupSlugCache[$slug];
        }

        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()->condition('slug', $slug)->execute();

        $id = $ids !== [] ? reset($ids) : null;
        $this->groupSlugCache[$slug] = $id;
        return $id;
    }

    /**
     * Resolve taxonomy term names to IDs for a vocabulary.
     *
     * @param list<string> $names
     * @return list<int> Resolved term IDs (unresolved names are skipped)
     * @return-out list<string> $warnings
     */
    public function resolveTaxonomyTerms(array $names, string $vocabulary, array &$warnings = []): array
    {
        $storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $ids = [];

        foreach ($names as $name) {
            $termIds = $storage->getQuery()
                ->condition('vid', $vocabulary)
                ->condition('name', $name)
                ->execute();

            if ($termIds !== []) {
                $ids[] = reset($termIds);
            } else {
                $warnings[] = "Taxonomy term '{$name}' not found in vocabulary '{$vocabulary}'";
            }
        }

        return $ids;
    }
}
```

**Note:** If the framework uses concrete classes instead of interfaces, update the test mocks to use the concrete `EntityTypeManager`, `EntityStorage`, and `EntityQuery` classes. The implementer should check what classes exist in `vendor/waaseyaa/framework/src/Entity/` and adapt.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/FixtureResolverTest.php -v`
Expected: PASS (4 tests). Adapt mock setup if framework uses concrete classes instead of interfaces.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FixtureResolver.php tests/Minoo/Unit/Support/FixtureResolverTest.php
git commit -m "feat: add FixtureResolver for community, group, and taxonomy lookups"
```

---

### Task 9: Rewrite bin/seed-content CLI

**Files:**
- Modify: `bin/seed-content`

This rewrites the existing script to read from `content/*.json` fixtures instead of `ContentSeeder` arrays. It supports `--apply` (default is dry-run) and `--file <type>` to seed a single type.

- [ ] **Step 1: Rewrite bin/seed-content**

```php
#!/usr/bin/env php
<?php

/**
 * Seed launch content from JSON fixtures into Minoo's entity storage.
 *
 * Usage:
 *   bin/seed-content                    # dry-run (default)
 *   bin/seed-content --apply            # write to database
 *   bin/seed-content --apply --verbose  # write + per-record detail
 *   bin/seed-content --file businesses  # seed one fixture type only
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Minoo\Entity\Event;
use Minoo\Entity\Group;
use Minoo\Entity\ResourcePerson;
use Minoo\Support\FixtureLoader;
use Minoo\Support\FixtureResolver;

// --- Parse arguments ---
$apply = in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$fileIndex = array_search('--file', $argv, true);
$fileFilter = ($fileIndex !== false && isset($argv[$fileIndex + 1])) ? $argv[$fileIndex + 1] : null;

// --- Boot kernel ---
$kernel = new Waaseyaa\Foundation\Kernel\ConsoleKernel(dirname(__DIR__));
(new ReflectionMethod($kernel, 'boot'))->invoke($kernel);
$etm = $kernel->getEntityTypeManager();
$now = time();

$loader = new FixtureLoader(dirname(__DIR__) . '/content');
$resolver = new FixtureResolver($etm);

$mode = $apply ? 'APPLY' : 'DRY-RUN';
fprintf(STDOUT, "Seed Content (%s)\n%s\n", $mode, str_repeat('━', 50));

// --- Seed order: businesses → people → events ---
$seedTypes = [
    'businesses' => [
        'entity_type_id' => 'group',
        'class' => Group::class,
        'label_field' => 'name',
    ],
    'people' => [
        'entity_type_id' => 'resource_person',
        'class' => ResourcePerson::class,
        'label_field' => 'name',
    ],
    'events' => [
        'entity_type_id' => 'event',
        'class' => Event::class,
        'label_field' => 'title',
    ],
];

$totalErrors = 0;

foreach ($seedTypes as $fixtureType => $config) {
    if ($fileFilter !== null && $fileFilter !== $fixtureType) {
        continue;
    }

    $records = $loader->load($fixtureType);
    if ($records === []) {
        fprintf(STDOUT, "\n%s: no fixture file found, skipping.\n", $fixtureType);
        continue;
    }

    // Validate
    $errors = $loader->validate($records, $fixtureType);

    // Validate bundle types against config entities
    if ($fixtureType === 'businesses') {
        $validTypes = array_map(fn($id) => $etm->getStorage('group_type')->load($id)?->get('type'),
            $etm->getStorage('group_type')->getQuery()->execute());
        foreach ($records as $i => $r) {
            if (isset($r['type']) && !in_array($r['type'], $validTypes, true)) {
                $errors[] = ($r['slug'] ?? "record {$i}") . ": invalid group type '{$r['type']}'";
            }
        }
    } elseif ($fixtureType === 'events') {
        $validTypes = array_map(fn($id) => $etm->getStorage('event_type')->load($id)?->get('type'),
            $etm->getStorage('event_type')->getQuery()->execute());
        foreach ($records as $i => $r) {
            if (isset($r['type']) && !in_array($r['type'], $validTypes, true)) {
                $errors[] = ($r['slug'] ?? "record {$i}") . ": invalid event type '{$r['type']}'";
            }
        }
    }

    if ($errors !== []) {
        fprintf(STDERR, "\n%s: validation errors:\n", $fixtureType);
        foreach ($errors as $error) {
            fprintf(STDERR, "  ✗ %s\n", $error);
        }
        $totalErrors += count($errors);
        continue;
    }

    $storage = $etm->getStorage($config['entity_type_id']);
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errored = 0;
    $warnings = [];

    foreach ($records as $record) {
        $slug = $record['slug'];
        $values = $record;

        // --- Resolve community ---
        if ($fixtureType === 'people') {
            // ResourcePerson has a string community field — write directly
        } else {
            // Group and Event have community_id entity_reference
            if (isset($values['community'])) {
                $communityId = $resolver->resolveCommunity($values['community']);
                if ($communityId === null) {
                    $warnings[] = "Community '{$values['community']}' not found, skipping {$slug}";
                    $skipped++;
                    continue;
                }
                $values['community_id'] = $communityId;
                unset($values['community']);
            }
        }

        // --- Resolve linked_group (people only) ---
        if (isset($values['linked_group'])) {
            $groupId = $resolver->resolveGroupSlug($values['linked_group']);
            if ($groupId === null) {
                $warnings[] = "Linked group '{$values['linked_group']}' not found for {$slug}";
            } else {
                $values['linked_group_id'] = $groupId;
            }
            unset($values['linked_group']);
        }

        // --- Resolve taxonomy terms (people only) ---
        if (isset($values['roles']) && is_array($values['roles'])) {
            $termWarnings = [];
            $values['roles'] = $resolver->resolveTaxonomyTerms($values['roles'], 'person_roles', $termWarnings);
            $warnings = array_merge($warnings, $termWarnings);
        }
        if (isset($values['offerings']) && is_array($values['offerings'])) {
            $termWarnings = [];
            $values['offerings'] = $resolver->resolveTaxonomyTerms($values['offerings'], 'person_offerings', $termWarnings);
            $warnings = array_merge($warnings, $termWarnings);
        }

        // --- Upsert ---
        $existingIds = $storage->getQuery()->condition('slug', $slug)->execute();

        if ($existingIds !== []) {
            // Update — only fields present in fixture
            if ($apply) {
                $entity = $storage->load(reset($existingIds));
                if ($entity !== null) {
                    foreach ($values as $key => $value) {
                        if ($key !== 'created_at' && $key !== 'slug') {
                            $entity->set($key, $value);
                        }
                    }
                    $entity->set('updated_at', $now);
                    $storage->save($entity);
                }
            }
            $updated++;
            if ($verbose) {
                fprintf(STDOUT, "  [UPDATE] %s\n", $slug);
            }
        } else {
            // Create
            $values['created_at'] = $now;
            $values['updated_at'] = $now;

            if ($apply) {
                $class = $config['class'];
                $entity = new $class($values);
                $storage->save($entity);
            }
            $created++;
            if ($verbose) {
                fprintf(STDOUT, "  [CREATE] %s\n", $slug);
            }
        }
    }

    fprintf(STDOUT, "\n%-12s %d create  %d update  %d skip  %d error\n",
        $fixtureType . ':',
        $created, $updated, $skipped, $errored
    );

    foreach ($warnings as $warning) {
        fprintf(STDOUT, "  ⚠ %s\n", $warning);
    }
}

if (!$apply) {
    fprintf(STDOUT, "\nRun with --apply to persist changes.\n");
}

fprintf(STDOUT, "\nDone.\n");
exit($totalErrors > 0 ? 1 : 0);
```

- [ ] **Step 2: Make it executable**

```bash
chmod +x bin/seed-content
```

- [ ] **Step 3: Run in dry-run mode to verify it boots and loads (with empty fixtures)**

```bash
mkdir -p content
bin/seed-content
```

Expected: Should boot, report "no fixture file found" for each type, exit 0.

- [ ] **Step 4: Commit**

```bash
git add bin/seed-content
git commit -m "feat: rewrite bin/seed-content to use JSON fixtures with dry-run/apply"
```

---

## Chunk 3: Content Research & Fixtures

### Task 10: Research and create businesses.json

**Files:**
- Create: `content/businesses.json`

- [ ] **Step 1: Research local businesses**

Use web search to find businesses in Sagamok Anishnawbek, Massey, Espanola, Elliot Lake, and Spanish. Target 10-20 businesses. Focus on:
- Indigenous-owned businesses first
- Community-serving (health, food, services)
- Small/local businesses

Sources to search:
- YellowPages.ca for Sagamok First Nation, Massey ON, Espanola ON, Elliot Lake ON, Spanish ON
- Municipal directories (espanola.ca, centralmanitoulin.ca, westmanitoulin.com)
- Google Maps for each town

- [ ] **Step 2: Create content/businesses.json**

Write a JSON array of researched businesses. Include for each:
- `name`, `slug`, `type` ("business"), `description` (1-2 sentences)
- `community` (match existing community entity names exactly)
- `phone` (E.164 if available), `email` (if public), `address`, `url`, `booking_url`
- `source` (provenance tag), `verified_at` (today's date)

Start with known business from the spec:
```json
{
  "name": "Nginaajiiw Salon & Spa",
  "slug": "nginaajiiw-salon-spa",
  "type": "business",
  "description": "Full-service salon and spa in Sagamok Anishnawbek offering hair, esthetics, massage, and nail services.",
  "phone": "+17058698163",
  "email": "nginaajiiwsalonandspa@hotmail.com",
  "address": "610-7 Sagamok Road, Sagamok Anishnawbek, ON P0P 2L0",
  "url": "https://www.instagram.com/nginaajiiw.salonandspa",
  "booking_url": "https://nginaajiiw-salon-spa.square.site",
  "community": "Sagamok Anishnawbek",
  "source": "manual:russell:2026-03-15",
  "verified_at": "2026-03-15T00:00:00Z"
}
```

- [ ] **Step 3: Validate the fixture**

```bash
bin/seed-content --file businesses
```

Expected: Dry-run shows N creates, 0 errors. Community resolution warnings are acceptable if communities haven't been synced for some towns.

- [ ] **Step 4: Commit**

```bash
git add content/businesses.json
git commit -m "content: add launch business fixtures for North Shore corridor"
```

---

### Task 11: Create people.json

**Files:**
- Create: `content/people.json`

- [ ] **Step 1: Create content/people.json**

Write a JSON array of people. Start with known people:

```json
[
  {
    "name": "Russell Jones",
    "slug": "russell-jones",
    "bio": "Web developer and community builder from Sagamok Anishnawbek. Creator of minoo.live — an Indigenous knowledge and community platform.",
    "roles": ["Web Developer"],
    "offerings": ["Web Development"],
    "community": "Sagamok Anishnawbek",
    "consent_public": true,
    "source": "manual:russell:2026-03-15",
    "verified_at": "2026-03-15T00:00:00Z"
  },
  {
    "name": "Charlotte Southwind",
    "slug": "charlotte-southwind",
    "bio": "Beadwork artist from Sagamok Anishnawbek, creating handmade beaded earrings and accessories.",
    "roles": ["Artist", "Crafter"],
    "offerings": ["Beadwork", "Crafts"],
    "community": "Sagamok Anishnawbek",
    "consent_public": false,
    "source": "manual:russell:2026-03-15",
    "verified_at": "2026-03-15T00:00:00Z"
  }
]
```

Add business owners from the researched businesses as ResourcePerson entries with `linked_group` references.

**Important:** Set `consent_public: false` by default for all people. Only Russell (confirmed) and business owners with public-facing roles get `consent_public: true`.

- [ ] **Step 2: Validate the fixture**

```bash
bin/seed-content --file people
```

Expected: Dry-run shows N creates. Linked group warnings are OK if businesses haven't been seeded yet (they will resolve when run in order).

- [ ] **Step 3: Commit**

```bash
git add content/people.json
git commit -m "content: add launch people fixtures"
```

---

### Task 12: Research and create events.json

**Files:**
- Create: `content/events.json`

- [ ] **Step 1: Research upcoming events**

Search for community events in the North Shore corridor:
- Community Facebook pages for Sagamok, Espanola, Elliot Lake
- Band office event calendars
- Municipal event listings
- Powwow calendars for Ontario 2026

Target 10-15 events with real dates. Include recurring annual events (powwows, National Indigenous Peoples Day, etc.) with 2026 dates.

- [ ] **Step 2: Create content/events.json**

Write a JSON array of events. Include for each:
- `title`, `slug`, `type` (powwow/gathering/ceremony), `description`
- `community` (match existing community names)
- `starts_at`, `ends_at` (ISO 8601 UTC)
- `location` (venue + town)
- `source`, `verified_at`

Example:
```json
{
  "title": "National Indigenous Peoples Day Celebration",
  "slug": "nipd-sagamok-2026",
  "type": "gathering",
  "description": "Annual celebration of Indigenous cultures, traditions, and contributions at Sagamok Anishnawbek.",
  "location": "Sagamok Community Centre, Sagamok Anishnawbek",
  "community": "Sagamok Anishnawbek",
  "starts_at": "2026-06-21T10:00:00Z",
  "ends_at": "2026-06-21T20:00:00Z",
  "source": "web:community-calendar:2026-03-15",
  "verified_at": "2026-03-15T00:00:00Z"
}
```

- [ ] **Step 3: Validate the fixture**

```bash
bin/seed-content --file events
```

Expected: Dry-run shows N creates, 0 errors.

- [ ] **Step 4: Commit**

```bash
git add content/events.json
git commit -m "content: add launch event fixtures for North Shore corridor"
```

---

## Chunk 4: Integration Testing & Verification

### Task 13: Integration test for seed-content workflow

**Files:**
- Create: `tests/Minoo/Integration/SeedContentTest.php`

- [ ] **Step 1: Write integration test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Entity\Group;
use Minoo\Entity\ResourcePerson;
use Minoo\Support\FixtureLoader;
use Minoo\Support\FixtureResolver;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class SeedContentTest extends TestCase
{
    private static $etm;

    public static function setUpBeforeClass(): void
    {
        $packagesFile = dirname(__DIR__, 3) . '/storage/framework/packages.php';
        if (file_exists($packagesFile)) {
            unlink($packagesFile);
        }

        putenv('WAASEYAA_DB=:memory:');
        $kernel = new HttpKernel(dirname(__DIR__, 3));
        (new \ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
        self::$etm = $kernel->getEntityTypeManager();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');
        $packagesFile = dirname(__DIR__, 3) . '/storage/framework/packages.php';
        if (file_exists($packagesFile)) {
            unlink($packagesFile);
        }
    }

    #[Test]
    public function fixtureLoaderValidatesBusinessFixtures(): void
    {
        $loader = new FixtureLoader(dirname(__DIR__, 3) . '/content');
        $records = $loader->load('businesses');

        $this->assertNotEmpty($records, 'businesses.json should contain records');

        $errors = $loader->validate($records, 'businesses');
        $this->assertSame([], $errors, 'Business fixtures should have no validation errors');
    }

    #[Test]
    public function fixtureLoaderValidatesPeopleFixtures(): void
    {
        $loader = new FixtureLoader(dirname(__DIR__, 3) . '/content');
        $records = $loader->load('people');

        $this->assertNotEmpty($records, 'people.json should contain records');

        $errors = $loader->validate($records, 'people');
        $this->assertSame([], $errors, 'People fixtures should have no validation errors');
    }

    #[Test]
    public function fixtureLoaderValidatesEventFixtures(): void
    {
        $loader = new FixtureLoader(dirname(__DIR__, 3) . '/content');
        $records = $loader->load('events');

        $this->assertNotEmpty($records, 'events.json should contain records');

        $errors = $loader->validate($records, 'events');
        $this->assertSame([], $errors, 'Event fixtures should have no validation errors');
    }

    #[Test]
    public function canCreateAndLoadBusinessEntity(): void
    {
        $storage = self::$etm->getStorage('group');

        $group = new Group([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'type' => 'business',
            'description' => 'A test business.',
            'phone' => '+17055551234',
            'email' => 'test@example.com',
            'address' => '123 Main St, Espanola, ON',
            'source' => 'test:unit:2026-03-15',
        ]);

        $storage->save($group);
        $id = $group->id();
        $this->assertNotNull($id);

        $loaded = $storage->load($id);
        $this->assertSame('Test Business', $loaded->get('name'));
        $this->assertSame('+17055551234', $loaded->get('phone'));
        $this->assertSame('test@example.com', $loaded->get('email'));
        $this->assertSame('123 Main St, Espanola, ON', $loaded->get('address'));
        $this->assertSame('test:unit:2026-03-15', $loaded->get('source'));
    }

    #[Test]
    public function canCreateResourcePersonWithLinkedGroup(): void
    {
        $groupStorage = self::$etm->getStorage('group');
        $personStorage = self::$etm->getStorage('resource_person');

        // Create a group first
        $group = new Group([
            'name' => 'Linked Business',
            'slug' => 'linked-business',
            'type' => 'business',
        ]);
        $groupStorage->save($group);
        $groupId = $group->id();

        // Create person linked to group
        $person = new ResourcePerson([
            'name' => 'Test Person',
            'slug' => 'test-person',
            'community' => 'Test Town',
            'linked_group_id' => $groupId,
            'source' => 'test:unit:2026-03-15',
        ]);
        $personStorage->save($person);

        $loaded = $personStorage->load($person->id());
        $this->assertSame($groupId, $loaded->get('linked_group_id'));
        $this->assertSame('test:unit:2026-03-15', $loaded->get('source'));
    }

    #[Test]
    public function upsertBySlugUpdatesExistingRecord(): void
    {
        $storage = self::$etm->getStorage('group');

        // Create initial
        $group = new Group([
            'name' => 'Original Name',
            'slug' => 'upsert-test',
            'type' => 'business',
        ]);
        $storage->save($group);
        $originalId = $group->id();

        // Upsert — find by slug, update
        $ids = $storage->getQuery()->condition('slug', 'upsert-test')->execute();
        $this->assertNotEmpty($ids);

        $existing = $storage->load(reset($ids));
        $existing->set('name', 'Updated Name');
        $existing->set('phone', '+17055559999');
        $storage->save($existing);

        // Verify
        $reloaded = $storage->load($originalId);
        $this->assertSame('Updated Name', $reloaded->get('name'));
        $this->assertSame('+17055559999', $reloaded->get('phone'));
    }
}
```

- [ ] **Step 2: Run integration test**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/SeedContentTest.php -v`
Expected: PASS (6 tests). The fixture validation tests confirm the actual JSON files are well-formed.

- [ ] **Step 3: Commit**

```bash
git add tests/Minoo/Integration/SeedContentTest.php
git commit -m "test: add integration tests for content seeding workflow"
```

---

### Task 14: Full test suite and dry-run verification

- [ ] **Step 1: Clear manifest and run full test suite**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit -v
```

Expected: All tests pass (260+ tests).

- [ ] **Step 2: Run full dry-run**

```bash
bin/seed-content
```

Expected: Shows create counts for all three fixture types, any community resolution warnings, exit 0.

- [ ] **Step 3: Run on dev with --apply**

```bash
bin/seed-content --apply --verbose
```

Expected: Creates all records, shows per-record detail. Verify site at localhost:8081 shows new content on /groups, /people, /events pages.

- [ ] **Step 4: Run again to verify idempotency**

```bash
bin/seed-content --apply --verbose
```

Expected: Shows 0 creates, N updates (or 0 updates if nothing changed). No duplicates.

- [ ] **Step 5: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: address issues found during seed-content verification"
```

---

## Production Deployment

After all tasks pass:

1. Deploy code to production (push to main → GitHub Actions CI/CD)
2. SSH to production: `ALTER TABLE` for new columns on existing tables
3. Run `bin/seed-content --apply` on production
4. Verify content on minoo.live
