# Featured Across Turtle Island Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Featured Across Turtle Island" section to the homepage powered by a FeaturedItem config entity with time-bounded display, and fix two homepage routing bugs.

**Architecture:** A new `featured_item` config entity stores editorial references to any entity type with time windows and weight-based ordering. HomeController queries active featured items and passes resolved entities to the template. A new section renders above the existing tab navigation. Two bug fixes ensure businesses route correctly and don't leak into the Groups tab.

**Tech Stack:** PHP 8.3 (Waaseyaa framework), Twig 3, vanilla CSS, PHPUnit 10.5

**Spec:** `docs/superpowers/specs/2026-03-17-featured-across-turtle-island-design.md`

---

## File Structure

### New Files
| File | Responsibility |
|------|----------------|
| `src/Entity/FeaturedItem.php` | Config entity class (extends ConfigEntityBase) |
| `src/Provider/FeaturedItemServiceProvider.php` | Register entity type with field definitions |
| `src/Access/FeaturedItemAccessPolicy.php` | Admin-only access policy |
| `tests/Minoo/Unit/Entity/FeaturedItemTest.php` | Entity unit tests |
| `scripts/populate_featured.php` | Seed LNHL + Crystal Shawanda as featured items |

### Modified Files
| File | Change |
|------|--------|
| `composer.json` | Add `FeaturedItemServiceProvider` to providers list |
| `src/Controller/HomeController.php` | Load featured items, fix `loadGroups()` business filter |
| `templates/page.html.twig` | Add featured section, fix business card URLs |
| `public/css/minoo.css` | Add `.featured-section` and `.featured-grid` styles |
| `resources/lang/en.php` | Add `featured.section_title` translation key |
| `resources/lang/oj.php` | Add `featured.section_title` Anishinaabemowin translation |

---

## Task 1: FeaturedItem Entity + Provider + Access Policy

**Files:**
- Create: `src/Entity/FeaturedItem.php`
- Create: `src/Provider/FeaturedItemServiceProvider.php`
- Create: `src/Access/FeaturedItemAccessPolicy.php`
- Modify: `composer.json:72` (add provider)

- [ ] **Step 1: Create FeaturedItem entity class**

Create `src/Entity/FeaturedItem.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class FeaturedItem extends ConfigEntityBase
{
    protected string $entityTypeId = 'featured_item';

    protected array $entityKeys = [
        'id' => 'fid',
        'label' => 'headline',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('weight', $values)) {
            $values['weight'] = 0;
        }
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 2: Create FeaturedItemServiceProvider**

Create `src/Provider/FeaturedItemServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\FeaturedItem;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class FeaturedItemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'featured_item',
            label: 'Featured Item',
            class: FeaturedItem::class,
            keys: ['id' => 'fid', 'label' => 'headline'],
            group: 'editorial',
            fieldDefinitions: [
                'entity_type' => [
                    'type' => 'string',
                    'label' => 'Entity Type',
                    'description' => 'Referenced entity type (event, teaching, group, resource_person).',
                    'weight' => 1,
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'label' => 'Entity ID',
                    'description' => 'Referenced entity ID.',
                    'weight' => 2,
                ],
                'headline' => [
                    'type' => 'string',
                    'label' => 'Headline',
                    'description' => 'Display headline (overrides entity title when set).',
                    'weight' => 3,
                ],
                'subheadline' => [
                    'type' => 'string',
                    'label' => 'Subheadline',
                    'description' => 'Optional subtitle or context line.',
                    'weight' => 4,
                ],
                'weight' => [
                    'type' => 'integer',
                    'label' => 'Weight',
                    'description' => 'Sort order (higher = more prominent).',
                    'default' => 0,
                    'weight' => 10,
                ],
                'starts_at' => [
                    'type' => 'datetime',
                    'label' => 'Starts At',
                    'description' => 'When this item begins appearing.',
                    'weight' => 20,
                ],
                'ends_at' => [
                    'type' => 'datetime',
                    'label' => 'Ends At',
                    'description' => 'When this item stops appearing.',
                    'weight' => 21,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'default' => 1,
                    'weight' => 30,
                ],
            ],
        ));
    }
}
```

- [ ] **Step 3: Create FeaturedItemAccessPolicy**

Create `src/Access/FeaturedItemAccessPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'featured_item')]
final class FeaturedItemAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'featured_item';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => (int) $entity->get('status') === 1
                ? AccessResult::allowed('Published featured item.')
                : AccessResult::neutral('Cannot view unpublished featured item.'),
            default => AccessResult::neutral('Non-admin cannot modify featured items.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create featured items.');
    }
}
```

- [ ] **Step 4: Register provider in composer.json**

In `composer.json`, add `"Minoo\\Provider\\FeaturedItemServiceProvider"` to the `extra.waaseyaa.providers` array (after `ChatServiceProvider` on line 72):

```json
"Minoo\\Provider\\ChatServiceProvider",
"Minoo\\Provider\\FeaturedItemServiceProvider"
```

- [ ] **Step 5: Clear stale manifest and run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All existing tests pass. The new entity type is registered.

- [ ] **Step 6: Commit**

```bash
git add src/Entity/FeaturedItem.php src/Provider/FeaturedItemServiceProvider.php src/Access/FeaturedItemAccessPolicy.php composer.json
git commit -m "feat: add FeaturedItem config entity with provider and access policy"
```

---

## Task 2: FeaturedItem Unit Tests

**Files:**
- Create: `tests/Minoo/Unit/Entity/FeaturedItemTest.php`

- [ ] **Step 1: Write unit tests**

Create `tests/Minoo/Unit/Entity/FeaturedItemTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\FeaturedItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeaturedItem::class)]
final class FeaturedItemTest extends TestCase
{
    #[Test]
    public function constructor_sets_defaults(): void
    {
        $item = new FeaturedItem([
            'fid' => 1,
            'headline' => 'Little NHL 2026',
        ]);

        $this->assertSame(0, $item->get('weight'));
        $this->assertSame(1, $item->get('status'));
    }

    #[Test]
    public function constructor_accepts_all_fields(): void
    {
        $item = new FeaturedItem([
            'fid' => 1,
            'entity_type' => 'event',
            'entity_id' => 13,
            'headline' => 'Little NHL 2026',
            'subheadline' => '271 teams — Markham, Ontario',
            'weight' => 100,
            'starts_at' => '2026-03-10',
            'ends_at' => '2026-03-21',
            'status' => 1,
        ]);

        $this->assertSame(1, $item->id());
        $this->assertSame('Little NHL 2026', $item->label());
        $this->assertSame('event', $item->get('entity_type'));
        $this->assertSame(13, $item->get('entity_id'));
        $this->assertSame('271 teams — Markham, Ontario', $item->get('subheadline'));
        $this->assertSame(100, $item->get('weight'));
        $this->assertSame('2026-03-10', $item->get('starts_at'));
        $this->assertSame('2026-03-21', $item->get('ends_at'));
    }

    #[Test]
    public function weight_defaults_to_zero(): void
    {
        $item = new FeaturedItem(['fid' => 1, 'headline' => 'Test']);
        $this->assertSame(0, $item->get('weight'));
    }

    #[Test]
    public function status_defaults_to_published(): void
    {
        $item = new FeaturedItem(['fid' => 1, 'headline' => 'Test']);
        $this->assertSame(1, $item->get('status'));
    }
}
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/FeaturedItemTest.php
```

Expected: 4 tests, all pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Minoo/Unit/Entity/FeaturedItemTest.php
git commit -m "test: add FeaturedItem entity unit tests"
```

---

## Task 3: Homepage Bug Fixes (Business URLs + Groups Filter)

**Files:**
- Modify: `src/Controller/HomeController.php:130-138` (add business filter to `loadGroups()`)
- Modify: `templates/page.html.twig:45-52,116-123` (fix business card URLs)

- [ ] **Step 1: Fix loadGroups() to exclude businesses**

In `src/Controller/HomeController.php`, modify `loadGroups()` (line 133). Add `->condition('type', 'business', '!=')` to the query:

```php
private function loadGroups(int $limit): array
{
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->condition('type', 'business', '!=')
        ->range(0, $limit)
        ->execute();

    return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
}
```

- [ ] **Step 2: Fix business card URLs in nearby panel**

In `templates/page.html.twig`, update the group card in the nearby panel (lines 46-52). Change the URL to check for business type:

```twig
{% if item.type == 'group' %}
  {% set is_business = item.entity.get('type') == 'business' %}
  {% include "components/homepage-card.html.twig" with {
    type: is_business ? 'business' : 'group',
    type_label: is_business ? trans('page.type_business') : trans('page.type_group'),
    title: item.entity.get('name'),
    url: lang_url((is_business ? '/businesses/' : '/groups/') ~ item.entity.get('slug')),
    meta: (item.entity.get('description')|default('')|length > 60 ? item.entity.get('description')|slice(0, 60) ~ '…' : item.entity.get('description')|default(''))
  } only %}
```

- [ ] **Step 3: Fix business card URLs in groups tab**

In `templates/page.html.twig`, update the groups tab panel (lines 116-123). Same pattern:

```twig
{% for g in tab_groups %}
  {% set is_business = g.get('type') == 'business' %}
  {% include "components/homepage-card.html.twig" with {
    type: is_business ? 'business' : 'group',
    type_label: is_business ? trans('page.type_business') : trans('page.type_group'),
    title: g.get('name'),
    url: lang_url((is_business ? '/businesses/' : '/groups/') ~ g.get('slug')),
    meta: (g.get('description')|default('')|length > 60 ? g.get('description')|slice(0, 60) ~ '…' : g.get('description')|default(''))
  } only %}
{% endfor %}
```

- [ ] **Step 4: Add `page.type_group` translation key if missing**

Check `resources/lang/en.php` for `page.type_group`. If missing, add it near the existing `page.type_business` key:

```php
'page.type_group' => 'Group',
```

And in `resources/lang/oj.php`:

```php
'page.type_group' => 'Anokiiwin',
```

- [ ] **Step 5: Run tests and verify**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/HomeController.php templates/page.html.twig resources/lang/en.php resources/lang/oj.php
git commit -m "fix: exclude businesses from homepage groups tab, fix business card URLs"
```

---

## Task 4: Featured Section in HomeController

**Files:**
- Modify: `src/Controller/HomeController.php:44-88` (add featured items loading)

- [ ] **Step 1: Add featured items loading to HomeController::index()**

In `src/Controller/HomeController.php`, add featured item loading at the start of the `index()` method, right after the `$templateVars` initialization (after line 54). Add before the `if ($location->hasLocation())` block:

```php
// Load active featured items
$templateVars['featured_items'] = $this->loadFeaturedItems();
```

- [ ] **Step 2: Add the loadFeaturedItems() method**

Add this private method to `HomeController` (after the existing `loadLocationConfig()` method):

```php
/** @return list<array{featured: mixed, entity: mixed, url: string}> */
private function loadFeaturedItems(): array
{
    try {
        $storage = $this->entityTypeManager->getStorage('featured_item');
    } catch (\Throwable) {
        return [];
    }

    $now = date('Y-m-d H:i:s');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->condition('starts_at', $now, '<=')
        ->condition('ends_at', $now, '>=')
        ->sort('weight', 'DESC')
        ->execute();

    if ($ids === []) {
        return [];
    }

    $items = [];
    foreach ($storage->loadMultiple($ids) as $featured) {
        $entityType = $featured->get('entity_type');
        $entityId = $featured->get('entity_id');

        if ($entityType === null || $entityId === null) {
            continue;
        }

        try {
            $refStorage = $this->entityTypeManager->getStorage($entityType);
            $entity = $refStorage->load((int) $entityId);
        } catch (\Throwable) {
            continue;
        }

        if ($entity === null) {
            continue;
        }

        $slug = $entity->get('slug') ?? '';
        $url = match ($entityType) {
            'event' => '/events/' . $slug,
            'teaching' => '/teachings/' . $slug,
            'resource_person' => '/people/' . $slug,
            'group' => $entity->get('type') === 'business'
                ? '/businesses/' . $slug
                : '/groups/' . $slug,
            default => '/',
        };

        $items[] = [
            'featured' => $featured,
            'entity' => $entity,
            'url' => $url,
        ];
    }

    return $items;
}
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/HomeController.php
git commit -m "feat: load active featured items in HomeController"
```

---

## Task 5: Featured Section Template + CSS + Translations

**Files:**
- Modify: `templates/page.html.twig:30-32` (add featured section between hero and tabs)
- Modify: `public/css/minoo.css` (add featured section styles)
- Modify: `resources/lang/en.php` (add translation key)
- Modify: `resources/lang/oj.php` (add translation key)

- [ ] **Step 1: Add featured section to template**

In `templates/page.html.twig`, add the following between the hero `</section>` (line 30) and the tab nav (line 32). Insert after line 30:

```twig
  {# === Featured Across Turtle Island === #}
  {% if featured_items is defined and featured_items|length > 0 %}
    <section class="featured-section">
      <h2 class="featured-section__title">{{ trans('featured.section_title') }}</h2>
      <div class="featured-grid">
        {% for item in featured_items %}
          <a href="{{ lang_url(item.url) }}" class="featured-card">
            <span class="featured-card__badge">{{ item.featured.get('entity_type')|replace({'_': ' '})|capitalize }}</span>
            <h3 class="featured-card__headline">{{ item.featured.get('headline')|default(item.entity.label()) }}</h3>
            {% if item.featured.get('subheadline') %}
              <p class="featured-card__subheadline">{{ item.featured.get('subheadline') }}</p>
            {% endif %}
          </a>
        {% endfor %}
      </div>
    </section>
  {% endif %}
```

- [ ] **Step 2: Add CSS styles**

In `public/css/minoo.css`, add these styles inside `@layer components` (after the existing `.homepage-hero` styles, before the tab styles):

```css
  /* Featured Across Turtle Island */
  .featured-section {
    padding-block: var(--space-sm);
    padding-inline: var(--space-sm);
    background: linear-gradient(135deg, oklch(0.2 0.02 250), oklch(0.15 0.01 200));
    border-block-end: 1px solid var(--border);
  }

  .featured-section__title {
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-muted);
    margin-block-end: var(--space-xs);
  }

  .featured-grid {
    display: flex;
    gap: var(--space-sm);
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    padding-block-end: var(--space-3xs);
  }

  .featured-card {
    flex: 0 0 min(80vw, 20rem);
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    gap: var(--space-3xs);
    padding: var(--space-sm);
    background-color: var(--surface-card);
    border-radius: 0.5rem;
    text-decoration: none;
    color: var(--text-primary);
    border-inline-start: 3px solid oklch(0.7 0.15 80);
    transition: transform 0.15s ease;
  }

  .featured-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  }

  .featured-card__badge {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: oklch(0.7 0.15 80);
    font-weight: 600;
  }

  .featured-card__headline {
    font-family: var(--font-heading);
    font-size: var(--text-lg);
    line-height: 1.2;
  }

  .featured-card__subheadline {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    line-height: 1.4;
  }

  @media (min-width: 48rem) {
    .featured-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
      overflow-x: visible;
    }

    .featured-card {
      flex: initial;
    }
  }
```

- [ ] **Step 3: Add translation keys**

In `resources/lang/en.php`, add after the businesses section:

```php
    // Featured
    'featured.section_title' => 'Featured Across Turtle Island',
```

In `resources/lang/oj.php`, add in the same location:

```php
    // Featured (Maamawi-gizhendaagwak)
    'featured.section_title' => 'Maamawi-gizhendaagwak Misi-minis-akiing', // "Important things across Turtle Island"
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add templates/page.html.twig public/css/minoo.css resources/lang/en.php resources/lang/oj.php
git commit -m "feat: add Featured Across Turtle Island section to homepage"
```

---

## Task 6: Population Script + Seed Data

**Files:**
- Create: `scripts/populate_featured.php`

- [ ] **Step 1: Write the population script**

Create `scripts/populate_featured.php`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create featured items for LNHL 2026 content.
 * Run: php scripts/populate_featured.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();
$featuredStorage = $entityTypeManager->getStorage('featured_item');

// 1. Find LNHL event
$eventStorage = $entityTypeManager->getStorage('event');
$eventIds = $eventStorage->getQuery()->condition('slug', 'little-nhl-2026')->execute();

if ($eventIds !== []) {
    $eventId = reset($eventIds);
    echo "Found LNHL event (eid: {$eventId})\n";

    // Check if already featured
    $existing = $featuredStorage->getQuery()
        ->condition('entity_type', 'event')
        ->condition('entity_id', $eventId)
        ->execute();

    if ($existing === []) {
        $featured = new \Minoo\Entity\FeaturedItem([
            'entity_type' => 'event',
            'entity_id' => (int) $eventId,
            'headline' => 'Little NHL 2026',
            'subheadline' => '271 teams, 4,500+ players — Markham, Ontario · March 15–19',
            'weight' => 100,
            'starts_at' => '2026-03-10 00:00:00',
            'ends_at' => '2026-03-21 23:59:59',
            'status' => 1,
        ]);
        $featuredStorage->save($featured);
        echo "Created featured item for LNHL (fid: {$featured->id()})\n";
    } else {
        echo "LNHL already featured, skipping.\n";
    }
} else {
    echo "Warning: LNHL event not found. Run populate_lnhl.php first.\n";
}

// 2. Find Crystal Shawanda
$personStorage = $entityTypeManager->getStorage('resource_person');
$personIds = $personStorage->getQuery()->condition('slug', 'crystal-shawanda')->execute();

if ($personIds !== []) {
    $personId = reset($personIds);
    echo "\nFound Crystal Shawanda (rpid: {$personId})\n";

    $existing = $featuredStorage->getQuery()
        ->condition('entity_type', 'resource_person')
        ->condition('entity_id', $personId)
        ->execute();

    if ($existing === []) {
        $featured = new \Minoo\Entity\FeaturedItem([
            'entity_type' => 'resource_person',
            'entity_id' => (int) $personId,
            'headline' => 'Crystal Shawanda at Little NHL',
            'subheadline' => 'Ojibwe country/blues artist drove from Nashville for the tournament',
            'weight' => 50,
            'starts_at' => '2026-03-15 00:00:00',
            'ends_at' => '2026-03-31 23:59:59',
            'status' => 1,
        ]);
        $featuredStorage->save($featured);
        echo "Created featured item for Crystal Shawanda (fid: {$featured->id()})\n";
    } else {
        echo "Crystal Shawanda already featured, skipping.\n";
    }
} else {
    echo "Warning: Crystal Shawanda not found. Run populate_lnhl.php first.\n";
}

echo "\nDone. Visit homepage to verify featured section.\n";
```

- [ ] **Step 2: Verify syntax**

```bash
php -l scripts/populate_featured.php
```

Expected: No syntax errors detected.

- [ ] **Step 3: Commit**

```bash
git add scripts/populate_featured.php
git commit -m "feat: add featured items population script for LNHL content"
```

---

## Task 7: Verification

- [ ] **Step 1: Run full PHPUnit suite**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass (existing + 4 new FeaturedItem tests).

- [ ] **Step 2: Run population script locally**

```bash
php scripts/populate_featured.php
```

Expected: Two featured items created.

- [ ] **Step 3: Start dev server and verify homepage**

```bash
php -S localhost:8081 -t public &
sleep 2
```

Verify with PHP:

```bash
php -r "
\$html = file_get_contents('http://localhost:8081/');
echo 'Has featured section: ' . (strpos(\$html, 'Featured Across Turtle Island') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Has LNHL: ' . (strpos(\$html, 'Little NHL 2026') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Crystal: ' . (strpos(\$html, 'Crystal Shawanda') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'No Nginaajiiw in groups tab: ' . (substr_count(\$html, '/groups/nginaajiiw') === 0 ? 'YES' : 'NO') . PHP_EOL;
"
```

Expected: All YES.

- [ ] **Step 4: Kill dev server**

```bash
kill %1
```

---

## Codebase Conventions (for worker prompts)

- PHP 8.3+, `declare(strict_types=1)` in every file
- Namespace: `Minoo\` for app code, `Minoo\Tests\` for tests
- `final class` by default
- PHPUnit 10.5: `#[Test]`, `#[CoversClass(...)]`
- Controller signature: `public function action(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse`
- CSS: `@layer components`, oklch colors, logical properties, native nesting, design tokens
- Templates: Twig 3, `{% extends "base.html.twig" %}`, path conditionals
- Clear `storage/framework/packages.php` after adding providers/fields
