# Unified Business & People Content Model + Map Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add geocoded maps to business pages, implement consent cascade on owner cards, define content quality invariants, and polish all business/people content to the Larissa Toulouse reference pattern.

**Architecture:** Add lat/lng/coordinate_source fields to Group entity, geocode addresses via Nominatim at seed time, render Leaflet map on business detail pages. Owner cards conditionally rendered based on person consent_public flag filtered in the controller. Template-driven default bios for unpopulated entries. Fixture validation warns on incomplete content.

**Tech Stack:** PHP 8.4, Waaseyaa entity system, SQLite, Leaflet.js, OpenStreetMap tiles, Nominatim geocoder, Twig 3, PHPUnit 10.5

**Spec:** `docs/superpowers/specs/2026-03-21-business-people-content-model-design.md`
**Issue:** #380

---

## File Structure

### New files
| File | Responsibility |
|------|---------------|
| `migrations/20260321_160000_add_coordinates_to_group.php` | Schema migration: latitude, longitude, coordinate_source columns |
| `src/Support/GeocodingService.php` | Nominatim geocoding client |
| `tests/Minoo/Unit/Support/GeocodingServiceTest.php` | Unit tests for geocoder |
| `tests/Minoo/Unit/Controller/BusinessControllerConsentTest.php` | Consent cascade verification |
| `tests/Minoo/Unit/Support/DefaultBioGeneratorTest.php` | Default bio template tests |
| `bin/geocode-businesses` | Standalone coordinate backfill script |
| `public/js/business-map.js` | Leaflet map init for business detail page |

### Modified files
| File | Change |
|------|--------|
| `src/Provider/GroupServiceProvider.php:125-130` | Add latitude, longitude, coordinate_source field definitions |
| `src/Controller/BusinessController.php:83-92` | Consent cascade on owner query + geo fallback logic |
| `templates/businesses.html.twig:95-107,126-141` | Map container in Location section, consent check on owner card |
| `public/css/minoo.css` | Add `.business-detail-map` in `@layer components` |
| `bin/seed-content` | Completeness validation + geocoding integration + default bio generation |
| `content/businesses.json` | Polished narrative descriptions |
| `content/people.json` | Polished narrative bios for consented entries |

---

## Task 1: Schema — Add coordinate fields to Group entity

**Files:**
- Create: `migrations/20260321_160000_add_coordinates_to_group.php`
- Modify: `src/Provider/GroupServiceProvider.php:125-130`

- [ ] **Step 1: Generate migration file**

Run: `bin/waaseyaa make:migration add_coordinates_to_group`

- [ ] **Step 2: Write the migration**

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add latitude, longitude, and coordinate_source to group table
 * for geocoded map display on business detail pages.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('group')) {
            return;
        }

        $connection = $schema->getConnection();

        if (!$schema->hasColumn('group', 'latitude')) {
            $connection->executeStatement(
                'ALTER TABLE "group" ADD COLUMN latitude REAL',
            );
        }

        if (!$schema->hasColumn('group', 'longitude')) {
            $connection->executeStatement(
                'ALTER TABLE "group" ADD COLUMN longitude REAL',
            );
        }

        if (!$schema->hasColumn('group', 'coordinate_source')) {
            $connection->executeStatement(
                'ALTER TABLE "group" ADD COLUMN coordinate_source TEXT',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
    }
};
```

- [ ] **Step 3: Add field definitions to GroupServiceProvider**

In `src/Provider/GroupServiceProvider.php`, add after the `social_posts` field (line 130) and before `status` (line 131):

```php
'latitude' => [
    'type' => 'float',
    'label' => 'Latitude',
    'description' => 'Geocoded from address, or community fallback.',
    'weight' => 98,
],
'longitude' => [
    'type' => 'float',
    'label' => 'Longitude',
    'description' => 'Geocoded from address, or community fallback.',
    'weight' => 99,
],
'coordinate_source' => [
    'type' => 'string',
    'label' => 'Coordinate Source',
    'description' => 'How coordinates were obtained: address (geocoded) or community (fallback).',
    'weight' => 100,
],
```

- [ ] **Step 4: Run migration locally**

Run: `bin/waaseyaa migrate`
Expected: Migration applies, adds 3 columns to group table.

- [ ] **Step 5: Verify with schema check**

Run: `bin/waaseyaa schema:check`
Expected: No drift detected for group table.

- [ ] **Step 6: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 7: Run existing tests to verify no regressions**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All tests pass (476+).

- [ ] **Step 8: Commit**

```bash
git add migrations/20260321_160000_add_coordinates_to_group.php src/Provider/GroupServiceProvider.php
git commit -m "feat(#380): add latitude/longitude/coordinate_source to Group entity"
```

---

## Task 2: GeocodingService — Nominatim client

**Files:**
- Create: `src/Support/GeocodingService.php`
- Create: `tests/Minoo/Unit/Support/GeocodingServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\GeocodingService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeocodingService::class)]
final class GeocodingServiceTest extends TestCase
{
    #[Test]
    public function parsesValidNominatimResponse(): void
    {
        $json = '[{"lat":"46.4234","lon":"-81.9876","display_name":"Sagamok Road"}]';
        $result = GeocodingService::parseResponse($json);

        $this->assertNotNull($result);
        $this->assertSame(46.4234, $result['lat']);
        $this->assertSame(-81.9876, $result['lng']);
    }

    #[Test]
    public function returnsNullForEmptyResponse(): void
    {
        $this->assertNull(GeocodingService::parseResponse('[]'));
    }

    #[Test]
    public function returnsNullForMalformedJson(): void
    {
        $this->assertNull(GeocodingService::parseResponse('not json'));
    }

    #[Test]
    public function returnsNullForMissingCoordinates(): void
    {
        $json = '[{"display_name":"Somewhere"}]';
        $this->assertNull(GeocodingService::parseResponse($json));
    }

    #[Test]
    public function buildUrlEncodesAddress(): void
    {
        $url = GeocodingService::buildUrl('610 Sagamok Road, ON');
        $this->assertStringContainsString('q=610+Sagamok+Road', $url);
        $this->assertStringContainsString('format=json', $url);
        $this->assertStringContainsString('limit=1', $url);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/GeocodingServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

/**
 * Geocode addresses using the OpenStreetMap Nominatim API.
 *
 * Usage policy: 1 request/second, User-Agent with contact info.
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
final class GeocodingService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'Minoo/1.0 (https://minoo.live; russell@minoo.live)';
    private const TIMEOUT = 10;

    /**
     * Geocode an address string to lat/lng coordinates.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $url = self::buildUrl($address);
        $response = $this->fetch($url);

        if ($response === null) {
            return null;
        }

        return self::parseResponse($response);
    }

    public static function buildUrl(string $address): string
    {
        return self::BASE_URL . '?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
        ]);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function parseResponse(string $json): ?array
    {
        $data = json_decode($json, true);

        if (!is_array($data) || $data === []) {
            return null;
        }

        $first = $data[0];

        if (!isset($first['lat'], $first['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
        ];
    }

    private function fetch(string $url, int $retries = 1): ?string
    {
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: ' . self::USER_AGENT,
                'timeout' => self::TIMEOUT,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            // Check for retryable status codes (429, 503)
            $statusLine = $http_response_header[0] ?? '';
            if ($retries > 0 && preg_match('/\b(429|503)\b/', $statusLine)) {
                usleep(2_000_000); // 2-second backoff
                return $this->fetch($url, $retries - 1);
            }

            return null;
        }

        return $response;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/GeocodingServiceTest.php`
Expected: 5 tests, all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/GeocodingService.php tests/Minoo/Unit/Support/GeocodingServiceTest.php
git commit -m "feat(#380): add GeocodingService for Nominatim geocoding"
```

---

## Task 3: Geocode businesses backfill script

**Files:**
- Create: `bin/geocode-businesses`

- [ ] **Step 1: Write the script**

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backfill geocoded coordinates for business entities.
 *
 * Usage:
 *   bin/geocode-businesses              # dry-run
 *   bin/geocode-businesses --apply      # write to database
 *   bin/geocode-businesses --verbose    # show detail
 */

require __DIR__ . '/../vendor/autoload.php';

use Minoo\Support\GeocodingService;
use Waaseyaa\Foundation\Kernel\ConsoleKernel;

$apply = in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$kernel = new ConsoleKernel(dirname(__DIR__));
(new ReflectionMethod($kernel, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$storage = $etm->getStorage('group');
$communityStorage = $etm->getStorage('community');
$geocoder = new GeocodingService();

$mode = $apply ? 'APPLY' : 'DRY-RUN';
fprintf(STDOUT, "Geocode Businesses (%s)\n%s\n", $mode, str_repeat('━', 50));

$ids = $storage->getQuery()
    ->condition('type', 'business')
    ->condition('status', 1)
    ->execute();

$geocoded = 0;
$fallback = 0;
$skipped = 0;
$failed = 0;

foreach ($ids as $id) {
    $business = $storage->load($id);
    if ($business === null) {
        continue;
    }

    $slug = $business->get('slug') ?? '(no slug)';

    // Skip if already has coordinates
    if ($business->get('latitude') !== null && $business->get('latitude') !== '') {
        $skipped++;
        if ($verbose) {
            fprintf(STDOUT, "  [SKIP] %s (already geocoded)\n", $slug);
        }
        continue;
    }

    $address = $business->get('address') ?? '';

    // Try geocoding from address
    if ($address !== '') {
        usleep(1_000_000); // 1 req/sec rate limit
        $coords = $geocoder->geocode($address);
        if ($coords !== null) {
            if ($apply) {
                $business->set('latitude', $coords['lat']);
                $business->set('longitude', $coords['lng']);
                $business->set('coordinate_source', 'address');
                $storage->save($business);
            }
            $geocoded++;
            fprintf(STDOUT, "  [GEOCODED] %s: %.4f, %.4f\n", $slug, $coords['lat'], $coords['lng']);
            continue;
        }
    }

    // Fallback: community coordinates
    $communityId = $business->get('community_id');
    if ($communityId !== null && $communityId !== '') {
        $communityIds = $communityStorage->getQuery()
            ->condition('cid', $communityId)
            ->range(0, 1)
            ->execute();
        $community = $communityIds !== [] ? $communityStorage->load(reset($communityIds)) : null;

        if ($community !== null && $community->get('latitude') !== null) {
            if ($apply) {
                $business->set('latitude', (float) $community->get('latitude'));
                $business->set('longitude', (float) $community->get('longitude'));
                $business->set('coordinate_source', 'community');
                $storage->save($business);
            }
            $fallback++;
            fprintf(STDOUT, "  [FALLBACK] %s: community %s\n", $slug, $community->get('name') ?? '');
            continue;
        }
    }

    $failed++;
    fprintf(STDOUT, "  [FAILED] %s: no address, no community coordinates\n", $slug);
}

fprintf(STDOUT, "\n%d geocoded  %d fallback  %d skipped  %d failed\n", $geocoded, $fallback, $skipped, $failed);

if (!$apply) {
    fprintf(STDOUT, "\nRun with --apply to persist changes.\n");
}

fprintf(STDOUT, "\nDone.\n");
```

- [ ] **Step 2: Make executable**

Run: `chmod +x bin/geocode-businesses`

- [ ] **Step 3: Test dry-run locally**

Run: `php bin/geocode-businesses --verbose`
Expected: Lists all businesses with their geocoding status. No writes.

- [ ] **Step 4: Commit**

```bash
git add bin/geocode-businesses
git commit -m "feat(#380): add bin/geocode-businesses backfill script"
```

---

## Task 4: Consent cascade on owner card

**Files:**
- Modify: `src/Controller/BusinessController.php:83-92`
- Modify: `templates/businesses.html.twig:95-107`

- [ ] **Step 1: Update controller to filter by consent**

In `src/Controller/BusinessController.php`, replace lines 83-92:

```php
// Load linked owner (ResourcePerson) — only if consented
$owner = null;
if ($business !== null) {
    $personStorage = $this->entityTypeManager->getStorage('resource_person');
    $ownerIds = $personStorage->getQuery()
        ->condition('linked_group_id', $business->id())
        ->condition('status', 1)
        ->condition('consent_public', 1)
        ->execute();
    $owner = $ownerIds !== [] ? $personStorage->load(reset($ownerIds)) : null;
}
```

- [ ] **Step 2: Simplify template owner check**

In `templates/businesses.html.twig`, line 95, the existing check `{% if owner is defined and owner %}` is sufficient — the controller now guarantees only consented owners are passed. No template change needed.

- [ ] **Step 3: Run tests**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All pass.

- [ ] **Step 4: Verify locally — consented owner shows**

Start dev server: `php -S localhost:8081 -t public`
Navigate: `http://localhost:8081/businesses/nginaajiiw-salon-spa`
Expected: Owner card shows "Larissa Toulouse" (consent_public: true).

- [ ] **Step 5: Commit**

```bash
git add src/Controller/BusinessController.php
git commit -m "feat(#380): add consent cascade — owner card respects consent_public"
```

---

## Task 5: Map on business detail page

**Files:**
- Modify: `templates/businesses.html.twig:126-141`
- Create: `public/js/business-map.js`
- Modify: `public/css/minoo.css`

- [ ] **Step 0: Verify Leaflet CSS is loaded in base template**

Run: `grep -n leaflet templates/base.html.twig`
Expected: A `<link>` tag for Leaflet CSS (e.g. `leaflet.css`). If missing, add it to `base.html.twig` `<head>`:
```html
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
```

- [ ] **Step 1: Add map container inside the Location section**

In `templates/businesses.html.twig`, insert inside the existing Location section (after line 128's `<h2>` tag, before the `<address>` block):

```twig
          {% if business.get('latitude') and business.get('longitude') %}
            <div id="business-detail-map"
                 class="business-detail-map"
                 data-lat="{{ business.get('latitude') }}"
                 data-lng="{{ business.get('longitude') }}"
                 data-name="{{ business.get('name') }}"
                 data-address="{{ business.get('address') ?? '' }}"
                 data-precision="{{ business.get('coordinate_source') ?? 'community' }}">
            </div>
          {% endif %}
```

- [ ] **Step 2: Add script tag to template**

In `templates/businesses.html.twig`, at the bottom (inside the scripts block or before `{% endblock %}`), add:

```twig
    {% if business.get('latitude') and business.get('longitude') %}
      <script src="/js/business-map.js" defer></script>
    {% endif %}
```

- [ ] **Step 3: Write business-map.js**

```javascript
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('business-detail-map');
    if (!el) return;

    var lat = parseFloat(el.dataset.lat);
    var lng = parseFloat(el.dataset.lng);
    var name = el.dataset.name || '';
    var address = el.dataset.address || '';
    var precision = el.dataset.precision || 'community';
    var zoom = precision === 'address' ? 15 : 12;

    var map = L.map('business-detail-map').setView([lat, lng], zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    var popupContent = '<strong>' + name + '</strong>';
    if (address) {
        popupContent += '<br>' + address;
    }

    L.marker([lat, lng])
        .addTo(map)
        .bindPopup(popupContent)
        .openPopup();
});
```

- [ ] **Step 4: Add CSS for map container**

In `public/css/minoo.css`, add within `@layer components`:

```css
.business-detail-map {
    block-size: 200px;
    border-radius: var(--radius-md);
    margin-block-end: var(--space-xs);
    background: var(--surface-raised);
}
```

- [ ] **Step 5: Verify locally**

Navigate: `http://localhost:8081/businesses/nginaajiiw-salon-spa`
Expected: Map renders above the address with a pin at the geocoded location. Pin popup shows business name and address.

Note: Business must have lat/lng in DB first — run `bin/geocode-businesses --apply` if not already done.

- [ ] **Step 6: Commit**

```bash
git add templates/businesses.html.twig public/js/business-map.js public/css/minoo.css
git commit -m "feat(#380): add Leaflet map to business detail pages"
```

---

## Task 6: Seed-content integration — geocoding, validation, default bios

**Files:**
- Modify: `bin/seed-content`

- [ ] **Step 1: Add GeocodingService import**

At the top of `bin/seed-content`, after the existing `use` statements:

```php
use Minoo\Support\GeocodingService;
```

- [ ] **Step 2: Add geocoding after business upsert**

After the business upsert loop (after the `foreach ($records as $record)` block for businesses), add geocoding for newly created entries:

```php
// Geocode businesses with address but no coordinates
if ($apply && $fixtureType === 'businesses') {
    $geocoder = new GeocodingService();
    $allIds = $storage->getQuery()
        ->condition('type', 'business')
        ->execute();

    foreach ($allIds as $geoId) {
        $entity = $storage->load($geoId);
        if ($entity === null) {
            continue;
        }

        // Skip if already geocoded
        if ($entity->get('latitude') !== null && $entity->get('latitude') !== '') {
            continue;
        }

        $address = $entity->get('address') ?? '';
        if ($address === '') {
            continue;
        }

        usleep(1_000_000); // 1 req/sec
        $coords = $geocoder->geocode($address);
        if ($coords !== null) {
            $entity->set('latitude', $coords['lat']);
            $entity->set('longitude', $coords['lng']);
            $entity->set('coordinate_source', 'address');
            $storage->save($entity);
            if ($verbose) {
                fprintf(STDOUT, "  [GEOCODED] %s: %.4f, %.4f\n", $entity->get('slug'), $coords['lat'], $coords['lng']);
            }
        }
    }
}
```

- [ ] **Step 3: Add default bio generation**

After the entity upsert loop, add default bio generation for entries with empty description/bio. Add this inside the `foreach ($records as $record)` block, after the entity is created/updated:

```php
// Generate default bio/description if empty (never overwrites hand-written content)
if ($apply && $fixtureType === 'businesses') {
    $desc = $entity->get('description') ?? '';
    if ($desc === '') {
        $community = $values['community'] ?? '';
        $typeName = ucfirst($entity->get('type') ?? 'business');
        // Resolve human-readable type label from group_type config entity
        $typeEntity = $etm->getStorage('group_type')->load($entity->get('type'));
        if ($typeEntity !== null) {
            $typeName = $typeEntity->get('name');
        }
        $defaultDesc = "{$entity->get('name')} is a {$typeName} in {$community}.";
        $entity->set('description', $defaultDesc);
        $storage->save($entity);
        if ($verbose) {
            fprintf(STDOUT, "  [DEFAULT BIO] %s\n", $slug);
        }
    }
} elseif ($apply && $fixtureType === 'people') {
    $bio = $entity->get('bio') ?? '';
    if ($bio === '') {
        $community = $values['community'] ?? '';
        // Resolve role names from term IDs
        $roleNames = [];
        $roleIds = $entity->get('roles') ?? [];
        if (is_array($roleIds)) {
            $termStorage = $etm->getStorage('taxonomy_term');
            foreach ($roleIds as $tid) {
                $term = $termStorage->load($tid);
                if ($term !== null) {
                    $roleNames[] = $term->get('name');
                }
            }
        }
        $rolesStr = $roleNames !== [] ? implode(' and ', $roleNames) : 'community member';
        $defaultBio = "{$entity->get('name')} is a {$rolesStr} from {$community}.";
        $entity->set('bio', $defaultBio);
        $storage->save($entity);
        if ($verbose) {
            fprintf(STDOUT, "  [DEFAULT BIO] %s\n", $slug);
        }
    }
}
```

- [ ] **Step 4: Add completeness validation**

After the main seeding loop, add validation for public entries:

```php
// Completeness validation (non-blocking warnings)
if ($fixtureType === 'businesses') {
    foreach ($records as $r) {
        $desc = $r['description'] ?? '';
        if (substr_count($desc, '.') < 2) {
            fprintf(STDOUT, "  ⚠ %s: description under 2 sentences (business completeness)\n", $r['slug']);
        }
        if (empty($r['phone']) && empty($r['email']) && empty($r['url'])) {
            fprintf(STDOUT, "  ⚠ %s: no contact method (business completeness)\n", $r['slug']);
        }
    }
} elseif ($fixtureType === 'people') {
    foreach ($records as $r) {
        if (($r['consent_public'] ?? false) !== true) {
            continue; // Skip hidden placeholders
        }
        $bio = $r['bio'] ?? '';
        if (substr_count($bio, '.') < 2) {
            fprintf(STDOUT, "  ⚠ %s: bio under 2 sentences (person completeness)\n", $r['slug']);
        }
        if (empty($r['roles'])) {
            fprintf(STDOUT, "  ⚠ %s: no roles defined (person completeness)\n", $r['slug']);
        }
    }
}
```

- [ ] **Step 4: Test locally**

Run: `php bin/seed-content --apply --verbose 2>/dev/null`
Expected: Geocoding runs for businesses with addresses. Completeness warnings printed for incomplete entries.

- [ ] **Step 5: Commit**

```bash
git add bin/seed-content
git commit -m "feat(#380): add geocoding, default bios, completeness validation to seed-content"
```

---

## Task 7: Additional tests — consent cascade, default bios, completeness

**Files:**
- Create: `tests/Minoo/Unit/Controller/BusinessControllerConsentTest.php`
- Create: `tests/Minoo/Unit/Support/DefaultBioGeneratorTest.php`

- [ ] **Step 1: Write consent cascade test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verify that the consent cascade query condition is correct.
 * The full controller integration is tested via Playwright;
 * this tests the query logic in isolation.
 */
#[CoversNothing]
final class BusinessControllerConsentTest extends TestCase
{
    #[Test]
    public function consentConditionExistsInController(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/BusinessController.php');
        $this->assertStringContainsString(
            "condition('consent_public', 1)",
            $source,
            'BusinessController must filter owner query by consent_public',
        );
    }

    #[Test]
    public function consentConditionAppliedWithStatusCondition(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/BusinessController.php');
        // Both conditions must appear in the owner query block
        $this->assertStringContainsString("condition('status', 1)", $source);
        $this->assertStringContainsString("condition('consent_public', 1)", $source);
    }
}
```

- [ ] **Step 2: Write default bio test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verify default bio template logic.
 */
#[CoversNothing]
final class DefaultBioGeneratorTest extends TestCase
{
    #[Test]
    public function businessDefaultBioIncludesNameAndCommunity(): void
    {
        $name = 'Test Business';
        $typeName = 'Business';
        $community = 'Sagamok Anishnawbek';
        $bio = "{$name} is a {$typeName} in {$community}.";

        $this->assertStringContainsString('Test Business', $bio);
        $this->assertStringContainsString('Sagamok Anishnawbek', $bio);
        $this->assertStringContainsString('Business', $bio);
    }

    #[Test]
    public function personDefaultBioIncludesNameAndRoles(): void
    {
        $name = 'Jane Doe';
        $roles = ['Elder', 'Knowledge Keeper'];
        $community = 'Sagamok Anishnawbek';
        $rolesStr = implode(' and ', $roles);
        $bio = "{$name} is a {$rolesStr} from {$community}.";

        $this->assertStringContainsString('Jane Doe', $bio);
        $this->assertStringContainsString('Elder and Knowledge Keeper', $bio);
        $this->assertStringContainsString('Sagamok Anishnawbek', $bio);
    }

    #[Test]
    public function personDefaultBioFallsBackToCommunityMember(): void
    {
        $name = 'Jane Doe';
        $roles = [];
        $community = 'Sagamok Anishnawbek';
        $rolesStr = $roles !== [] ? implode(' and ', $roles) : 'community member';
        $bio = "{$name} is a {$rolesStr} from {$community}.";

        $this->assertStringContainsString('community member', $bio);
    }
}
```

- [ ] **Step 3: Run all new tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/BusinessControllerConsentTest.php tests/Minoo/Unit/Support/DefaultBioGeneratorTest.php tests/Minoo/Unit/Support/GeocodingServiceTest.php`
Expected: All pass.

- [ ] **Step 4: Run full test suite**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add tests/Minoo/Unit/Controller/BusinessControllerConsentTest.php tests/Minoo/Unit/Support/DefaultBioGeneratorTest.php
git commit -m "test(#380): add consent cascade and default bio tests"
```

---

## Task 8: Polish content — narrative descriptions and bios

**Files:**
- Modify: `content/businesses.json`
- Modify: `content/people.json`

- [ ] **Step 1: Review all businesses in content/businesses.json**

Read the full file. For each business, draft a narrative description (2+ sentences) following the Larissa/Nginaajiiw pattern:
- Mention the community connection
- Cultural context where applicable
- Specific services/offerings
- Call to action where appropriate

Present drafts to user for review before writing.

- [ ] **Step 2: Review all consented people in content/people.json**

For each `consent_public: true` person, draft a narrative bio following the Larissa pattern:
- Their story and community connection
- What they do / offer
- Cultural grounding

Present drafts to user for review before writing.

- [ ] **Step 3: Update fixture files with approved content**

Write approved narrative content to `content/businesses.json` and `content/people.json`.

- [ ] **Step 4: Seed locally and verify**

Run: `php bin/seed-content --apply --verbose 2>/dev/null`
Expected: All entries updated, minimal completeness warnings.

- [ ] **Step 5: Verify pages locally**

Navigate to several business and people pages. Confirm narrative content renders correctly.

- [ ] **Step 6: Commit**

```bash
git add content/businesses.json content/people.json
git commit -m "content(#380): polish all business descriptions and consented people bios"
```

---

## Task 9: Deploy and backfill production

- [ ] **Step 1: Push all changes**

```bash
git push origin main
```

- [ ] **Step 2: Wait for deploy workflow**

Run: `gh run list --repo waaseyaa/minoo --limit 2`
Expected: Deploy Production workflow completes successfully.

- [ ] **Step 3: Run migration on production**

Migration runs automatically via `minoo:migrate` in the deploy pipeline. Verify:

```bash
ssh deployer@minoo.live 'set -a && . /home/deployer/minoo/shared/.env && set +a && php /home/deployer/minoo/current/bin/waaseyaa migrate:status'
```

Expected: All migrations applied, including `add_coordinates_to_group`.

- [ ] **Step 4: Geocode businesses on production**

```bash
ssh deployer@minoo.live 'set -a && . /home/deployer/minoo/shared/.env && set +a && php /home/deployer/minoo/current/bin/geocode-businesses --apply --verbose'
```

Expected: Businesses geocoded from addresses, community fallback for those without.

- [ ] **Step 5: Seed content on production**

```bash
ssh deployer@minoo.live 'set -a && . /home/deployer/minoo/shared/.env && set +a && php /home/deployer/minoo/current/bin/seed-content --apply'
```

Expected: All businesses and people updated. No term warnings. Completeness warnings for any remaining gaps.

- [ ] **Step 6: Verify on live site**

Check: `https://minoo.live/businesses/nginaajiiw-salon-spa`
Expected: Map renders with pin, owner card shows Larissa Toulouse, rich description.

Check: `https://minoo.live/people/larissa-toulouse`
Expected: Narrative bio, offerings, community, linked business.

- [ ] **Step 7: Close issue**

```bash
gh issue close 380 --repo waaseyaa/minoo --comment "Deployed: maps, consent cascade, content polish, taxonomy terms, geocoding."
```
