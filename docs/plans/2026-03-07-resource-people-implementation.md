# Resource People Directory Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a `resource_person` entity type with seeded role/offering taxonomies, SSR listing+detail page at `/people`, and admin CRUD support.

**Architecture:** New People domain — entity class, service provider, access policy, two taxonomy vocabularies, one SSR template with card component. Follows existing Speaker/Language patterns exactly.

**Tech Stack:** PHP 8.3, PHPUnit 10.5, Twig 3, vanilla CSS

---

## Phase 1: GitHub Setup

### Task 1: Create issue and branch

**Step 1: Create the v0.4 issue**

```bash
gh issue create \
  --title "feat: Resource People directory" \
  --body "Add resource_person entity with seeded role/offering taxonomies, SSR listing+detail at /people, and admin CRUD.

Design doc: docs/plans/2026-03-07-resource-people-design.md" \
  --milestone "v0.4"
```

Note the issue number N.

**Step 2: Create branch**

```bash
git checkout -b feat/resource-people
```

---

## Phase 2: Entity + Provider + Policy

### Task 2: Create ResourcePerson entity class

**Files:**
- Create: `src/Entity/ResourcePerson.php`
- Create: `tests/Minoo/Unit/Entity/ResourcePersonTest.php`

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\ResourcePerson;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourcePerson::class)]
final class ResourcePersonTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $person = new ResourcePerson([
            'name' => 'Mary Trudeau',
            'slug' => 'mary-trudeau',
        ]);

        $this->assertSame('Mary Trudeau', $person->get('name'));
        $this->assertSame('mary-trudeau', $person->get('slug'));
        $this->assertSame('resource_person', $person->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_status_to_published(): void
    {
        $person = new ResourcePerson(['name' => 'Test']);

        $this->assertSame(1, $person->get('status'));
    }

    #[Test]
    public function it_supports_all_optional_fields(): void
    {
        $person = new ResourcePerson([
            'name' => 'John Beaucage',
            'slug' => 'john-beaucage',
            'bio' => 'Elder and knowledge keeper.',
            'community' => 'Sagamok Anishnawbek',
            'email' => 'john@example.com',
            'phone' => '705-555-1234',
            'business_name' => 'Beaucage Consulting',
            'media_id' => 5,
        ]);

        $this->assertSame('Elder and knowledge keeper.', $person->get('bio'));
        $this->assertSame('Sagamok Anishnawbek', $person->get('community'));
        $this->assertSame('john@example.com', $person->get('email'));
        $this->assertSame('705-555-1234', $person->get('phone'));
        $this->assertSame('Beaucage Consulting', $person->get('business_name'));
        $this->assertSame(5, $person->get('media_id'));
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/ResourcePersonTest.php
```

Expected: Error — class `ResourcePerson` not found.

**Step 3: Write the entity class**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class ResourcePerson extends ContentEntityBase
{
    protected string $entityTypeId = 'resource_person';

    protected array $entityKeys = [
        'id' => 'rpid',
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

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/ResourcePersonTest.php
```

Expected: 3 tests, all passing.

**Step 5: Commit**

```bash
git add src/Entity/ResourcePerson.php tests/Minoo/Unit/Entity/ResourcePersonTest.php
git commit -m "feat(#N): add ResourcePerson entity class"
```

---

### Task 3: Create PeopleServiceProvider

**Files:**
- Create: `src/Provider/PeopleServiceProvider.php`

**Step 1: Write the provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\ResourcePerson;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'resource_person',
            label: 'Resource Person',
            class: ResourcePerson::class,
            keys: ['id' => 'rpid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            fieldDefinitions: [
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 5],
                'community' => ['type' => 'string', 'label' => 'Community', 'description' => 'Community affiliation (e.g. Sagamok Anishnawbek).', 'weight' => 10],
                'roles' => ['type' => 'entity_reference', 'label' => 'Roles', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_roles'], 'cardinality' => -1, 'weight' => 15],
                'offerings' => ['type' => 'entity_reference', 'label' => 'Offerings', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_offerings'], 'cardinality' => -1, 'weight' => 16],
                'email' => ['type' => 'string', 'label' => 'Email', 'weight' => 20],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 21],
                'business_name' => ['type' => 'string', 'label' => 'Business Name', 'weight' => 25],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 28],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }
}
```

**Step 2: Delete stale manifest cache and run full tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass (new provider discovered via PSR-4 scan).

**Step 3: Commit**

```bash
git add src/Provider/PeopleServiceProvider.php
git commit -m "feat(#N): add PeopleServiceProvider with field definitions"
```

---

### Task 4: Create PeopleAccessPolicy

**Files:**
- Create: `src/Access/PeopleAccessPolicy.php`
- Create: `tests/Minoo/Unit/Access/PeopleAccessPolicyTest.php`

**Step 1: Write the test**

Follow the pattern from existing access policy tests in the repo. Check for an existing access policy test file to match the exact pattern:

```bash
ls tests/Minoo/Unit/Access/
```

Then write the test matching that pattern:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\PeopleAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessResult;

#[CoversClass(PeopleAccessPolicy::class)]
final class PeopleAccessPolicyTest extends TestCase
{
    private PeopleAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PeopleAccessPolicy();
    }

    #[Test]
    public function it_applies_to_resource_person(): void
    {
        $this->assertTrue($this->policy->appliesTo('resource_person'));
    }

    #[Test]
    public function it_does_not_apply_to_other_types(): void
    {
        $this->assertFalse($this->policy->appliesTo('event'));
        $this->assertFalse($this->policy->appliesTo('speaker'));
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Access/PeopleAccessPolicyTest.php
```

**Step 3: Write the access policy**

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'resource_person')]
final class PeopleAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'resource_person';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => (int) $entity->get('status') === 1 && $account->hasPermission('access content')
                ? AccessResult::allowed('Published and user has access content.')
                : AccessResult::neutral('Cannot view unpublished people content.'),
            default => AccessResult::neutral('Non-admin cannot modify people content.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create people content.');
    }
}
```

**Step 4: Delete stale manifest and run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit tests/Minoo/Unit/Access/PeopleAccessPolicyTest.php
```

Expected: 2 tests passing.

**Step 5: Commit**

```bash
git add src/Access/PeopleAccessPolicy.php tests/Minoo/Unit/Access/PeopleAccessPolicyTest.php
git commit -m "feat(#N): add PeopleAccessPolicy for resource_person"
```

---

## Phase 3: Taxonomy Seeders

### Task 5: Add person_roles and person_offerings vocabularies

**Files:**
- Modify: `src/Seed/TaxonomySeeder.php`
- Modify: `tests/Minoo/Unit/Seed/TaxonomySeederTest.php`

**Step 1: Write the tests**

Add two new test methods to `TaxonomySeederTest.php`:

```php
#[Test]
public function it_provides_person_roles_vocabulary_with_terms(): void
{
    $data = TaxonomySeeder::personRolesVocabulary();

    $this->assertSame('person_roles', $data['vocabulary']['vid']);
    $this->assertSame('Person Roles', $data['vocabulary']['name']);
    $this->assertCount(12, $data['terms']);
    $this->assertSame('Elder', $data['terms'][0]['name']);
}

#[Test]
public function it_provides_person_offerings_vocabulary_with_terms(): void
{
    $data = TaxonomySeeder::personOfferingsVocabulary();

    $this->assertSame('person_offerings', $data['vocabulary']['vid']);
    $this->assertSame('Person Offerings', $data['vocabulary']['name']);
    $this->assertCount(10, $data['terms']);
    $this->assertSame('Food', $data['terms'][0]['name']);
}
```

**Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Seed/TaxonomySeederTest.php
```

**Step 3: Add the vocabulary methods to TaxonomySeeder**

Add to `src/Seed/TaxonomySeeder.php`:

```php
/** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
public static function personRolesVocabulary(): array
{
    return [
        'vocabulary' => ['vid' => 'person_roles', 'name' => 'Person Roles', 'description' => 'Community roles for resource people.'],
        'terms' => [
            ['name' => 'Elder', 'vid' => 'person_roles'],
            ['name' => 'Knowledge Keeper', 'vid' => 'person_roles'],
            ['name' => 'Dancer', 'vid' => 'person_roles'],
            ['name' => 'Drummer', 'vid' => 'person_roles'],
            ['name' => 'Language Speaker', 'vid' => 'person_roles'],
            ['name' => 'Regalia Maker', 'vid' => 'person_roles'],
            ['name' => 'Caterer', 'vid' => 'person_roles'],
            ['name' => 'Crafter', 'vid' => 'person_roles'],
            ['name' => 'Workshop Facilitator', 'vid' => 'person_roles'],
            ['name' => 'Small Business Owner', 'vid' => 'person_roles'],
            ['name' => 'Youth Worker', 'vid' => 'person_roles'],
            ['name' => 'Cedar Harvester', 'vid' => 'person_roles'],
        ],
    ];
}

/** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
public static function personOfferingsVocabulary(): array
{
    return [
        'vocabulary' => ['vid' => 'person_offerings', 'name' => 'Person Offerings', 'description' => 'Services and products offered by resource people.'],
        'terms' => [
            ['name' => 'Food', 'vid' => 'person_offerings'],
            ['name' => 'Regalia', 'vid' => 'person_offerings'],
            ['name' => 'Crafts', 'vid' => 'person_offerings'],
            ['name' => 'Teachings', 'vid' => 'person_offerings'],
            ['name' => 'Workshops', 'vid' => 'person_offerings'],
            ['name' => 'Cultural Services', 'vid' => 'person_offerings'],
            ['name' => 'Performances', 'vid' => 'person_offerings'],
            ['name' => 'Cedar Products', 'vid' => 'person_offerings'],
            ['name' => 'Beadwork', 'vid' => 'person_offerings'],
            ['name' => 'Traditional Medicine', 'vid' => 'person_offerings'],
        ],
    ];
}
```

**Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Seed/TaxonomySeederTest.php
```

Expected: 4 tests passing.

**Step 5: Commit**

```bash
git add src/Seed/TaxonomySeeder.php tests/Minoo/Unit/Seed/TaxonomySeederTest.php
git commit -m "feat(#N): add person_roles and person_offerings vocabularies"
```

---

## Phase 4: SSR Templates + CSS

### Task 6: Create people.html.twig and resource-person-card component

**Files:**
- Create: `templates/people.html.twig`
- Create: `templates/components/resource-person-card.html.twig`

**Step 1: Create the card component**

```twig
<article class="card">
  <span class="card__badge card__badge--person">{{ role }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ name }}</a></h3>
  {% if community is defined and community %}
    <p class="card__meta">{{ community }}</p>
  {% endif %}
  {% if offerings is defined and offerings %}
    <div class="card__tags">
      {% for offering in offerings %}
        <span class="card__tag">{{ offering }}</span>
      {% endfor %}
    </div>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
</article>
```

**Step 2: Create the page template**

```twig
{% extends "base.html.twig" %}

{% block title %}
  {%- if path == '/people' -%}
    People — Minoo
  {%- elseif path starts with '/people/' -%}
    Person — Minoo
  {%- else -%}
    Person Not Found — Minoo
  {%- endif -%}
{% endblock %}

{% block content %}
  {% set people = [
    {
      slug: "mary-trudeau",
      name: "Mary Trudeau",
      role: "Caterer",
      roles: ["Caterer", "Small Business Owner"],
      community: "Sagamok Anishnawbek",
      offerings: ["Food"],
      business_name: "Mary's Bannock & Catering",
      excerpt: "Traditional and contemporary Indigenous catering for community events, feasts, and celebrations.",
      bio: "Mary has been catering community events in Sagamok for over fifteen years. She specializes in traditional dishes including bannock, wild rice soup, and smoked fish, as well as contemporary meals for large gatherings.\n\nHer business, Mary's Bannock & Catering, serves events ranging from small family celebrations to community-wide feasts. She is passionate about keeping traditional food practices alive while making them accessible for modern events.",
      email: "mary@example.com",
      phone: "705-555-0101"
    },
    {
      slug: "john-beaucage",
      name: "John Beaucage",
      role: "Elder",
      roles: ["Elder", "Knowledge Keeper"],
      community: "Sagamok Anishnawbek",
      offerings: ["Teachings", "Cultural Services"],
      business_name: "",
      excerpt: "Elder available for opening prayers, teachings on governance and treaty rights, and cultural guidance for community programs.",
      bio: "John is a respected Elder in the Sagamok community with deep knowledge of Anishinaabe governance traditions and treaty history. He is frequently called upon to provide opening prayers and cultural guidance for community events and government meetings.\n\nJohn is available for teachings on treaty rights, traditional governance, and land-based education. He has mentored many young leaders in the community over the past three decades.",
      email: "john@example.com",
      phone: "705-555-0102"
    },
    {
      slug: "sarah-owl",
      name: "Sarah Owl",
      role: "Regalia Maker",
      roles: ["Regalia Maker", "Crafter", "Workshop Facilitator"],
      community: "Garden River First Nation",
      offerings: ["Regalia", "Beadwork", "Workshops"],
      business_name: "Owl Designs",
      excerpt: "Custom regalia, beadwork, and crafting workshops. Specializing in jingle dress and fancy shawl regalia.",
      bio: "Sarah is a skilled regalia maker and beadwork artist from Garden River First Nation. She creates custom jingle dresses, fancy shawl regalia, and beadwork pieces for powwow dancers and community members across the region.\n\nThrough her business Owl Designs, Sarah also runs regular workshops teaching beadwork fundamentals, ribbon skirt making, and regalia construction. She believes in passing traditional crafting skills to the next generation.",
      email: "sarah@example.com",
      phone: "705-555-0103"
    },
    {
      slug: "mike-abitong",
      name: "Mike Abitong",
      role: "Cedar Harvester",
      roles: ["Cedar Harvester", "Youth Worker"],
      community: "Atikameksheng Anishnawbek",
      offerings: ["Cedar Products", "Workshops", "Cultural Services"],
      business_name: "",
      excerpt: "Cedar harvesting, land-based youth programming, and cultural workshops connecting young people to traditional land practices.",
      bio: "Mike leads land-based youth programs in Atikameksheng, teaching young people traditional harvesting practices including cedar picking, medicine gathering, and seasonal land stewardship.\n\nHe provides cedar bundles and other harvested materials for community ceremonies and events. Mike also runs week-long summer camps focused on reconnecting youth with the land through hands-on traditional activities.",
      email: "mike@example.com",
      phone: "705-555-0104"
    }
  ] %}

  {% set slug = path|replace({'/people/': '', '/people': ''})|trim('/') %}
  {% set current_person = null %}
  {% for p in people %}
    {% if p.slug == slug %}
      {% set current_person = p %}
    {% endif %}
  {% endfor %}

  {% if path == '/people' %}
    <div class="flow-lg">
      <h1>People</h1>
      <p>Community members, knowledge keepers, and service providers.</p>

      <div class="card-grid">
        {% for person in people %}
          {% include "components/resource-person-card.html.twig" with {
            name: person.name,
            role: person.role,
            community: person.community,
            offerings: person.offerings,
            excerpt: person.excerpt,
            url: "/people/" ~ person.slug
          } %}
        {% endfor %}
      </div>
    </div>
  {% elseif current_person %}
    <div class="flow-lg detail">
      <a href="/people" class="detail__back">People</a>
      <div class="detail__header">
        {% for r in current_person.roles %}
          <span class="card__badge card__badge--person">{{ r }}</span>
        {% endfor %}
        <h1>{{ current_person.name }}</h1>
        <div class="detail__meta">
          {% if current_person.community %}
            <span>{{ current_person.community }}</span>
          {% endif %}
          {% if current_person.business_name %}
            <span>{{ current_person.business_name }}</span>
          {% endif %}
        </div>
      </div>

      {% if current_person.offerings %}
        <div class="card__tags">
          {% for offering in current_person.offerings %}
            <span class="card__tag">{{ offering }}</span>
          {% endfor %}
        </div>
      {% endif %}

      <div class="detail__body flow">
        {% for paragraph in current_person.bio|split("\n\n") %}
          <p>{{ paragraph }}</p>
        {% endfor %}
      </div>

      {% if current_person.email or current_person.phone %}
        <div class="detail__contact">
          {% if current_person.email %}
            <a href="mailto:{{ current_person.email }}">{{ current_person.email }}</a>
          {% endif %}
          {% if current_person.phone %}
            <span>{{ current_person.phone }}</span>
          {% endif %}
        </div>
      {% endif %}
    </div>
  {% else %}
    <div class="flow-lg">
      <h1>Person Not Found</h1>
      <p>The person at <code>{{ path }}</code> could not be found.</p>
      <p><a href="/people">Browse all people</a></p>
    </div>
  {% endif %}
{% endblock %}
```

**Step 3: Smoke test** — start dev server and visit `/people` and `/people/mary-trudeau`:

```bash
php -S localhost:8081 -t public
```

Expected: listing page shows 4 cards, detail page shows full profile with roles, offerings, bio, contact.

**Step 4: Commit**

```bash
git add templates/people.html.twig templates/components/resource-person-card.html.twig
git commit -m "feat(#N): add people listing and detail templates"
```

---

### Task 7: Add person badge CSS

**Files:**
- Modify: `public/css/minoo.css`

**Step 1: Find the existing badge variants block**

Search for `.card__badge--teaching` in `public/css/minoo.css` and add the person variant after it.

**Step 2: Add the person badge**

```css
.card__badge--person {
  background: var(--color-earth-100);
  color: var(--color-earth-700);
}
```

If `--color-earth-100` and `--color-earth-700` don't exist, check what earth tokens are available (the design uses oklch earth palette). Use the closest available tokens — the teaching badge uses earth tones, so person may use a different palette. Check existing tokens and pick an unused color. If the sage palette exists, use sage. Otherwise use a variation:

```css
.card__badge--person {
  background: oklch(0.95 0.02 160);
  color: oklch(0.35 0.06 160);
}
```

Also add a `.detail__contact` style in the detail section:

```css
.detail__contact {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-sm);
  font-size: var(--text-sm);
  color: var(--text-secondary);
}
```

**Step 3: Verify visually** — reload `/people` and check badge color renders correctly.

**Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#N): add person badge variant and contact styles"
```

---

## Phase 5: Full Test Suite + PR

### Task 8: Run full test suite and verify

**Step 1: Delete stale manifest cache**

```bash
rm -f storage/framework/packages.php
```

**Step 2: Run all tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass (previous 113 + new entity test 3 + policy test 2 + seeder test 2 = ~120 tests).

**Step 3: Run integration test specifically**

```bash
./vendor/bin/phpunit --testsuite MinooIntegration
```

Expected: Integration test passes (boots kernel with new provider/policy).

---

### Task 9: Push and open PR

**Step 1: Push branch**

```bash
git push -u origin feat/resource-people
```

**Step 2: Create PR**

```bash
gh pr create \
  --title "feat(#N): Resource People directory" \
  --body "$(cat <<'EOF'
Closes #N

## Summary

- Add `resource_person` entity type (People domain)
- Add `PeopleServiceProvider` with 12 field definitions
- Add `PeopleAccessPolicy` (public read, admin write)
- Add `person_roles` (12 terms) and `person_offerings` (10 terms) seeded vocabularies
- Add SSR listing page at `/people` and detail at `/people/{slug}`
- Add `resource-person-card.html.twig` component
- Add `.card__badge--person` CSS variant

## Checklist

- [x] `Closes #N` above references the issue this PR resolves
- [x] The issue is assigned to a milestone
- [x] PR title includes the issue number (e.g. `feat(#42): description`)
EOF
)"
```

Replace all `N` with the actual issue number from Task 1.
