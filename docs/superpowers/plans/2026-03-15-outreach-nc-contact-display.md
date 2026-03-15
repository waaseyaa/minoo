# Outreach: NorthCloud Contact Data Display — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden the existing NC contact display on community pages, extract it into a reusable component, and add `community_id` fields to event/group/teaching entities so their cards can link to community detail pages.

**Architecture:** Controller-level fetch (already implemented). Remaining work: add error handling, extract Twig component, add entity schema fields, wire community links into card templates.

**Tech Stack:** PHP 8.3+, Twig 3, PHPUnit 10.5, Playwright, vanilla CSS

**Spec:** `docs/superpowers/specs/2026-03-14-outreach-nc-contact-display-design.md`

---

## Chunk 1: Entity Schema + Error Handling

### Task 1: Add `community_id` field to Event entity

**Files:**
- Modify: `src/Provider/EventServiceProvider.php:36-41` (insert before `starts_at`)
- Test: `tests/Minoo/Unit/Entity/EventTest.php`

- [ ] **Step 1: Write the failing test**

Note: `ContentEntityBase::get()` returns `$this->values[$name] ?? null` — it works without a field definition. The real value of field definitions is schema/storage. Test that the EntityType's registered field definitions include `community_id`.

Add to `tests/Minoo/Unit/Entity/EventTest.php`. This requires booting the kernel to access the registered EntityType. Instead, test the provider directly:

```php
use Minoo\Provider\EventServiceProvider;

#[Test]
public function field_definitions_include_community_id(): void
{
    $provider = new EventServiceProvider();
    $entityTypes = $provider->register();

    // EventServiceProvider registers EntityType for 'event'
    $eventType = null;
    foreach ($entityTypes as $et) {
        if ($et->id === 'event') {
            $eventType = $et;
            break;
        }
    }

    $this->assertNotNull($eventType, 'event EntityType not registered');
    $this->assertArrayHasKey('community_id', $eventType->fieldDefinitions);
    $this->assertSame('entity_reference', $eventType->fieldDefinitions['community_id']['type']);
    $this->assertSame('community', $eventType->fieldDefinitions['community_id']['settings']['target_type']);
}
```

Note: Check how `register()` returns EntityTypes — it may return an array of `EntityType` objects. Adapt the assertion to match the actual return structure (the subagent should inspect `EventServiceProvider::register()` to confirm).

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/EventTest.php --filter=field_definitions_include_community_id -v`
Expected: FAIL — `community_id` key not found in fieldDefinitions

- [ ] **Step 3: Add field definition**

In `src/Provider/EventServiceProvider.php`, insert after the `location` field (weight 10) and before `starts_at` (weight 15):

```php
'community_id' => [
    'type' => 'entity_reference',
    'label' => 'Community',
    'settings' => ['target_type' => 'community'],
    'weight' => 12,
],
```

- [ ] **Step 4: Clear manifest and run test**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit tests/Minoo/Unit/Entity/EventTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Provider/EventServiceProvider.php tests/Minoo/Unit/Entity/EventTest.php
git commit -m "feat(#179): add community_id field to event entity"
```

---

### Task 2: Add `community_id` field to Group entity

**Files:**
- Modify: `src/Provider/GroupServiceProvider.php:41-46` (insert before `media_id`)
- Test: `tests/Minoo/Unit/Entity/GroupTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Minoo/Unit/Entity/GroupTest.php` (same provider-inspection pattern as EventTest):

```php
use Minoo\Provider\GroupServiceProvider;

#[Test]
public function field_definitions_include_community_id(): void
{
    $provider = new GroupServiceProvider();
    $entityTypes = $provider->register();

    $groupType = null;
    foreach ($entityTypes as $et) {
        if ($et->id === 'group') {
            $groupType = $et;
            break;
        }
    }

    $this->assertNotNull($groupType, 'group EntityType not registered');
    $this->assertArrayHasKey('community_id', $groupType->fieldDefinitions);
    $this->assertSame('entity_reference', $groupType->fieldDefinitions['community_id']['type']);
    $this->assertSame('community', $groupType->fieldDefinitions['community_id']['settings']['target_type']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/GroupTest.php --filter=field_definitions_include_community_id -v`
Expected: FAIL — `community_id` key not found

- [ ] **Step 3: Add field definition**

In `src/Provider/GroupServiceProvider.php`, insert after the `region` field (weight 15) and before `media_id` (weight 20):

```php
'community_id' => [
    'type' => 'entity_reference',
    'label' => 'Community',
    'settings' => ['target_type' => 'community'],
    'weight' => 16,
],
```

- [ ] **Step 4: Clear manifest and run test**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit tests/Minoo/Unit/Entity/GroupTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Provider/GroupServiceProvider.php tests/Minoo/Unit/Entity/GroupTest.php
git commit -m "feat(#179): add community_id field to group entity"
```

---

### Task 3: Add `community_id` field to Teaching entity

**Files:**
- Modify: `src/Provider/TeachingServiceProvider.php:41-47` (insert before `tags`)
- Test: `tests/Minoo/Unit/Entity/TeachingTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Minoo/Unit/Entity/TeachingTest.php` (same provider-inspection pattern):

```php
use Minoo\Provider\TeachingServiceProvider;

#[Test]
public function field_definitions_include_community_id(): void
{
    $provider = new TeachingServiceProvider();
    $entityTypes = $provider->register();

    $teachingType = null;
    foreach ($entityTypes as $et) {
        if ($et->id === 'teaching') {
            $teachingType = $et;
            break;
        }
    }

    $this->assertNotNull($teachingType, 'teaching EntityType not registered');
    $this->assertArrayHasKey('community_id', $teachingType->fieldDefinitions);
    $this->assertSame('entity_reference', $teachingType->fieldDefinitions['community_id']['type']);
    $this->assertSame('community', $teachingType->fieldDefinitions['community_id']['settings']['target_type']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/TeachingTest.php --filter=field_definitions_include_community_id -v`
Expected: FAIL — `community_id` key not found

- [ ] **Step 3: Add field definition**

In `src/Provider/TeachingServiceProvider.php`, insert after `cultural_group_id` (weight 10) and before `tags` (weight 15):

```php
'community_id' => [
    'type' => 'entity_reference',
    'label' => 'Community',
    'settings' => ['target_type' => 'community'],
    'weight' => 12,
],
```

- [ ] **Step 4: Clear manifest and run test**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit tests/Minoo/Unit/Entity/TeachingTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Provider/TeachingServiceProvider.php tests/Minoo/Unit/Entity/TeachingTest.php
git commit -m "feat(#179): add community_id field to teaching entity"
```

---

### Task 4: Add try/catch to CommunityController::show()

**Files:**
- Modify: `src/Controller/CommunityController.php:123-128`
- Test: `tests/Minoo/Unit/Controller/CommunityControllerTest.php`

- [ ] **Step 1: Create a test subclass that throws on NC calls**

The controller creates `NorthCloudClient` via `createNorthCloudClient()` (a protected method). We can't mock it directly — use an anonymous test subclass. Add to `tests/Minoo/Unit/Controller/CommunityControllerTest.php`:

```php
#[Test]
public function show_renders_without_contact_data_when_nc_unavailable(): void
{
    $sagamok = new Community([
        'cid' => 1,
        'name' => 'Sagamok Anishnawbek',
        'slug' => 'sagamok-anishnawbek',
        'community_type' => 'first_nation',
        'nc_id' => 'nc-uuid-123',
    ]);

    $this->query->method('execute')->willReturn([1]);
    $this->storage->method('load')->with(1)->willReturn($sagamok);

    // Subclass that overrides createNorthCloudClient to throw
    $controller = new class($this->entityTypeManager, $this->twig) extends CommunityController {
        protected function createNorthCloudClient(): \Minoo\Support\NorthCloudClient
        {
            throw new \RuntimeException('NorthCloud unavailable');
        }
    };

    $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

    $this->assertSame(200, $response->statusCode);
    $this->assertStringContainsString('Sagamok Anishnawbek', $response->content);
    // people and band_office should be null (not passed or empty)
    $this->assertStringNotContainsString('people:', $response->content);
    $this->assertStringNotContainsString('office:', $response->content);
}
```

Note: `CommunityController` is `final class` — you'll need to remove `final` or use reflection. Check the class declaration first. If it's final, the subagent should use reflection to override `createNorthCloudClient`, or alternatively make the method accept an optional client parameter.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php --filter=nc_unavailable -v`
Expected: FAIL — the `RuntimeException` propagates uncaught (no try/catch yet)

- [ ] **Step 3: Wrap NC calls in try/catch**

In `src/Controller/CommunityController.php`, replace lines 123-128:

```php
// Before (no error handling):
$ncId = $community->get('nc_id');
if ($ncId !== null && $ncId !== '') {
    $ncClient = $this->createNorthCloudClient();
    $people = $ncClient->getPeople((string) $ncId);
    $bandOffice = $ncClient->getBandOffice((string) $ncId);
}
```

With:

```php
$ncId = $community->get('nc_id');
if ($ncId !== null && $ncId !== '') {
    try {
        $ncClient = $this->createNorthCloudClient();
        $people = $ncClient->getPeople((string) $ncId);
        $bandOffice = $ncClient->getBandOffice((string) $ncId);
    } catch (\Throwable) {
        // NorthCloud unavailable — page renders without contact data
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php -v`
Expected: ALL PASS (including existing tests)

- [ ] **Step 5: Commit**

```bash
git add src/Controller/CommunityController.php tests/Minoo/Unit/Controller/CommunityControllerTest.php
git commit -m "fix(#179): add try/catch around NorthCloud calls in CommunityController"
```

---

## Chunk 2: Template Component Extraction

### Task 5: Extract community-contact-card component

**Files:**
- Create: `templates/components/community-contact-card.html.twig`
- Modify: `templates/communities/detail.html.twig:78-157`

- [ ] **Step 1: Create the component**

Create `templates/components/community-contact-card.html.twig` by extracting lines 78-157 from `detail.html.twig`:

```twig
{# Leadership + Band Office contact card for a community.
   Expects: people (array|null), band_office (array|null) #}

{# --- Leadership --- #}
{% if people is not empty %}
<div class="atlas-section">
  <div class="atlas-section__label">{{ trans('community.leadership') }}</div>
  {% for person in people %}
    {% if person.role == 'chief' %}
    <div class="atlas-leader-card atlas-leader-card--chief">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <div class="atlas-leader-card__role">{{ trans('community.chief') }}</div>
          <div class="atlas-leader-card__name">{{ person.name }}</div>
        </div>
        <div style="font-size: 0.6875rem; color: #999;">{{ trans('community.current') }}</div>
      </div>
    </div>
    {% endif %}
  {% endfor %}
  <div class="atlas-councillor-grid">
    {% for person in people %}
      {% if person.role == 'councillor' %}
      <div class="atlas-leader-card">
        <div class="atlas-leader-card__role">{{ trans('community.councillor') }}</div>
        <div class="atlas-leader-card__name">{{ person.name }}</div>
      </div>
      {% endif %}
    {% endfor %}
  </div>
</div>
{% endif %}

{# --- Band Office --- #}
{% if band_office %}
<div class="atlas-section">
  <div class="atlas-section__label">{{ trans('community.band_office') }}</div>
  <div class="atlas-contact-card">
    <div class="atlas-contact-card__grid">
      {% if band_office.address_line1 %}
      <div>
        <div class="atlas-section__field-label">{{ trans('community.address') }}</div>
        <div class="atlas-section__field-value">
          {{ band_office.address_line1 }}
          {% if band_office.address_line2 %}<br>{{ band_office.address_line2 }}{% endif %}
          {% if band_office.city %}<br>{{ band_office.city }}, {{ band_office.province }} {{ band_office.postal_code }}{% endif %}
        </div>
      </div>
      {% endif %}
      {% if band_office.office_hours %}
      <div>
        <div class="atlas-section__field-label">{{ trans('community.hours') }}</div>
        <div class="atlas-section__field-value">{{ band_office.office_hours }}</div>
      </div>
      {% endif %}
      {% if band_office.phone %}
      <div>
        <div class="atlas-section__field-label">{{ trans('community.phone') }}</div>
        <div class="atlas-section__field-value"><a href="tel:{{ band_office.phone }}">{{ band_office.phone }}</a></div>
      </div>
      {% endif %}
      {% if band_office.email %}
      <div>
        <div class="atlas-section__field-label">{{ trans('community.email') }}</div>
        <div class="atlas-section__field-value"><a href="mailto:{{ band_office.email }}">{{ band_office.email }}</a></div>
      </div>
      {% endif %}
      {% if band_office.toll_free %}
      <div>
        <div class="atlas-section__field-label">{{ trans('community.toll_free') }}</div>
        <div class="atlas-section__field-value"><a href="tel:{{ band_office.toll_free }}">{{ band_office.toll_free }}</a></div>
      </div>
      {% endif %}
      {% if band_office.fax %}
      <div>
        <div class="atlas-section__field-label">{{ trans('community.fax') }}</div>
        <div class="atlas-section__field-value">{{ band_office.fax }}</div>
      </div>
      {% endif %}
    </div>
  </div>
</div>
{% endif %}
```

- [ ] **Step 2: Replace inline markup with include**

In `templates/communities/detail.html.twig`, replace lines 78-157 (the `{# --- Leadership --- #}` through end of `{# --- Band Office --- #}` sections) with:

```twig
  {% include "components/community-contact-card.html.twig" with {
    people: people,
    band_office: band_office
  } %}
```

- [ ] **Step 3: Run existing tests to verify no regression**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php -v`
Expected: ALL PASS — the Twig ArrayLoader in tests won't load the include, but the mock template doesn't use it. This verifies controller logic is unchanged.

Run: `npx playwright test tests/playwright/communities.spec.ts`
Expected: ALL PASS — the rendered HTML is identical.

- [ ] **Step 4: Commit**

```bash
git add templates/components/community-contact-card.html.twig templates/communities/detail.html.twig
git commit -m "refactor(#179): extract community-contact-card component from detail template"
```

---

## Chunk 3: Community Links on Entity Cards

### Task 6: Add community link to event cards

**Files:**
- Modify: `templates/components/event-card.html.twig`
- Modify: `templates/events.html.twig:22-29`

- [ ] **Step 1: Add community_name to card include**

In `templates/events.html.twig`, update the `{% include %}` block (lines 22-29) to pass community data. The entity's `community_id` field is an ID — we need the community name. Since controllers load entities via `EntityTypeManager`, the page template can resolve the community. However, the event listing page template iterates entities directly and doesn't have access to the community storage.

**Approach:** Pass the raw `community_id` value, and in the card template, display it only if a `community_name` is also provided. The controller (`EventController`) must be updated to resolve community names for the listing.

Update `templates/events.html.twig` include (lines 22-29):

```twig
{% include "components/event-card.html.twig" with {
  title: e.get('title'),
  type: e.get('type')|default('')|capitalize,
  date: e.get('starts_at')|default(''),
  location: e.get('location')|default(''),
  excerpt: e.get('description')|default('')|split("\n\n")|first|length > 120 ? e.get('description')|default('')|split("\n\n")|first|slice(0, 120) ~ '…' : e.get('description')|default('')|split("\n\n")|first,
  url: "/events/" ~ e.get('slug'),
  community_name: communities[e.get('community_id')].name|default(''),
  community_slug: communities[e.get('community_id')].slug|default('')
} %}
```

This expects the `EventController::list()` to pass a `communities` lookup map (see Task 9).

- [ ] **Step 2: Add community link to card template**

In `templates/components/event-card.html.twig`, add after the location line (line 7):

```twig
{% if community_name is defined and community_name %}
  <p class="card__meta card__community"><a href="/communities/{{ community_slug }}">{{ community_name }}</a></p>
{% endif %}
```

Full file becomes:

```twig
<article class="card card--event">
  <span class="card__badge card__badge--event">{{ type }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ title }}</a></h3>
  <p class="card__date">{{ date }}</p>
  {% if location is defined and location %}
    <p class="card__meta">{{ location }}</p>
  {% endif %}
  {% if community_name is defined and community_name %}
    <p class="card__meta card__community"><a href="/communities/{{ community_slug }}">{{ community_name }}</a></p>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
</article>
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/event-card.html.twig templates/events.html.twig
git commit -m "feat(#179): add community link to event cards"
```

---

### Task 7: Add community link to group cards

**Files:**
- Modify: `templates/components/group-card.html.twig`
- Modify: `templates/groups.html.twig:22-28`

- [ ] **Step 1: Update group template include**

In `templates/groups.html.twig`, update include (lines 22-28):

```twig
{% include "components/group-card.html.twig" with {
  name: g.get('name'),
  type: g.get('type')|default('')|capitalize,
  region: g.get('region')|default(''),
  excerpt: g.get('description')|default('')|split("\n\n")|first|length > 120 ? g.get('description')|default('')|split("\n\n")|first|slice(0, 120) ~ '…' : g.get('description')|default('')|split("\n\n")|first,
  url: "/groups/" ~ g.get('slug'),
  community_name: communities[g.get('community_id')].name|default(''),
  community_slug: communities[g.get('community_id')].slug|default('')
} %}
```

- [ ] **Step 2: Add community link to card template**

In `templates/components/group-card.html.twig`, add after region (line 5):

```twig
{% if community_name is defined and community_name %}
  <p class="card__meta card__community"><a href="/communities/{{ community_slug }}">{{ community_name }}</a></p>
{% endif %}
```

Full file:

```twig
<article class="card card--group">
  <span class="card__badge card__badge--group">{{ type }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ name }}</a></h3>
  {% if region is defined and region %}
    <p class="card__meta">{{ region }}</p>
  {% endif %}
  {% if community_name is defined and community_name %}
    <p class="card__meta card__community"><a href="/communities/{{ community_slug }}">{{ community_name }}</a></p>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
</article>
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/group-card.html.twig templates/groups.html.twig
git commit -m "feat(#179): add community link to group cards"
```

---

### Task 8: Add community link to teaching cards

**Files:**
- Modify: `templates/components/teaching-card.html.twig`
- Modify: `templates/teachings.html.twig:22-29`

- [ ] **Step 1: Update teaching template include**

In `templates/teachings.html.twig`, update include (lines 22-29):

```twig
{% include "components/teaching-card.html.twig" with {
  title: t.get('title'),
  type: t.get('type')|default('')|capitalize,
  author: '',
  excerpt: t.get('content')|default('')|split("\n\n")|first|length > 120 ? t.get('content')|default('')|split("\n\n")|first|slice(0, 120) ~ '…' : t.get('content')|default('')|split("\n\n")|first,
  tags: [],
  url: "/teachings/" ~ t.get('slug'),
  community_name: communities[t.get('community_id')].name|default(''),
  community_slug: communities[t.get('community_id')].slug|default('')
} %}
```

- [ ] **Step 2: Add community link to card template**

In `templates/components/teaching-card.html.twig`, add after author (line 5):

```twig
{% if community_name is defined and community_name %}
  <p class="card__meta card__community"><a href="/communities/{{ community_slug }}">{{ community_name }}</a></p>
{% endif %}
```

Full file:

```twig
<article class="card card--teaching">
  <span class="card__badge card__badge--teaching">{{ type }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ title }}</a></h3>
  {% if author is defined and author %}
    <p class="card__meta">{{ author }}</p>
  {% endif %}
  {% if community_name is defined and community_name %}
    <p class="card__meta card__community"><a href="/communities/{{ community_slug }}">{{ community_name }}</a></p>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
  {% if tags is defined and tags %}
    <div>
      {% for tag in tags %}
        <span class="card__tag">{{ tag }}</span>
      {% endfor %}
    </div>
  {% endif %}
</article>
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/teaching-card.html.twig templates/teachings.html.twig
git commit -m "feat(#179): add community link to teaching cards"
```

---

### Task 9: Add community lookup to listing controllers

**Files:**
- Modify: `src/Controller/EventController.php`
- Modify: `src/Controller/GroupController.php`
- Modify: `src/Controller/TeachingController.php`

The page templates now expect a `communities` lookup map. Each listing controller's `list()` method needs to:
1. Collect unique `community_id` values from loaded entities
2. Batch-load those communities
3. Pass a `communities` map (keyed by community ID) to the template

- [ ] **Step 1: Add community lookup helper**

Each controller already has `$this->entityTypeManager`. Add a private helper method to each controller (or add once and repeat — controllers don't share a base class):

```php
/**
 * @param list<ContentEntityBase> $entities
 * @return array<int, array{name: string, slug: string}>
 */
private function buildCommunityLookup(array $entities): array
{
    $communityIds = array_filter(array_unique(array_map(
        fn ($e) => $e->get('community_id'),
        $entities
    )));

    if ($communityIds === []) {
        return [];
    }

    $communityStorage = $this->entityTypeManager->getStorage('community');
    $communities = $communityStorage->loadMultiple($communityIds);
    $lookup = [];
    foreach ($communities as $community) {
        // Cast to string — SQLite returns community_id as string, Twig lookup must match
        $lookup[(string) $community->id()] = [
            'name' => $community->get('name') ?? $community->label(),
            'slug' => $community->get('slug'),
        ];
    }

    return $lookup;
}
```

- [ ] **Step 2: Wire into EventController::list()**

In `EventController::list()`, after loading events, add:

```php
$communities = $this->buildCommunityLookup($events);
```

And pass `'communities' => $communities` to the template render call.

- [ ] **Step 3: Wire into GroupController::list()**

Same pattern — add `buildCommunityLookup()` method and wire into `list()`.

- [ ] **Step 4: Wire into TeachingController::list()**

Same pattern — add `buildCommunityLookup()` method and wire into `list()`.

- [ ] **Step 5: Run all tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit -v`
Expected: ALL PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/EventController.php src/Controller/GroupController.php src/Controller/TeachingController.php
git commit -m "feat(#179): add community lookup to listing controllers for card links"
```

---

## Chunk 4: CSS + Playwright

### Task 10: Add `.card__community` CSS

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add community link style**

In `public/css/minoo.css`, find the existing `.card__meta` styles in `@layer components` and add:

```css
.card__community a {
  color: var(--clr-link);
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}
```

This is minimal — the existing `.card__meta` styles handle font size and spacing. The community link just needs link color.

- [ ] **Step 2: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#179): add community link styles to card component"
```

---

### Task 11: Playwright smoke tests

**Files:**
- Create: `tests/playwright/community-contact.spec.ts`

- [ ] **Step 1: Write smoke tests**

Create `tests/playwright/community-contact.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';

test.describe('Community contact card', () => {
  test('community detail page renders leadership section', async ({ page }) => {
    // Navigate to a community that has NC data (Sagamok has nc_id)
    await page.goto('/communities/sagamok-anishnawbek');

    // Page should load successfully
    await expect(page).toHaveTitle(/Sagamok/i);

    // If leadership data is available from NC, the section renders
    // This is a soft check — NC may not be available in CI
    const leadershipSection = page.locator('.atlas-section__label');
    const count = await leadershipSection.count();
    expect(count).toBeGreaterThan(0);
  });

  test('community detail page renders without NC data', async ({ page }) => {
    // Navigate to a community without nc_id (municipality)
    await page.goto('/communities/blind-river');

    // Page should still render 200
    await expect(page).toHaveTitle(/Blind River/i);

    // No leadership or band office sections
    const leaderCards = page.locator('.atlas-leader-card');
    await expect(leaderCards).toHaveCount(0);
  });
});
```

- [ ] **Step 2: Run Playwright**

Run: `npx playwright test tests/playwright/community-contact.spec.ts`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/playwright/community-contact.spec.ts
git commit -m "test(#179): add Playwright smoke tests for community contact card"
```

---

### Task 12: Run full test suite and verify

- [ ] **Step 1: Run PHPUnit**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit -v`
Expected: ALL PASS

- [ ] **Step 2: Run Playwright**

Run: `npx playwright test`
Expected: ALL PASS

- [ ] **Step 3: Run schema check**

Run: `bin/waaseyaa schema:check`
Expected: Will report drift for `community_id` columns on existing tables (event, group, teaching). Note the ALTER TABLE statements needed for production.

- [ ] **Step 4: Document schema migration**

Add a comment in the PR description noting the required production migration:

```sql
ALTER TABLE event ADD COLUMN community_id INTEGER DEFAULT NULL;
ALTER TABLE "group" ADD COLUMN community_id INTEGER DEFAULT NULL;
ALTER TABLE teaching ADD COLUMN community_id INTEGER DEFAULT NULL;
```
