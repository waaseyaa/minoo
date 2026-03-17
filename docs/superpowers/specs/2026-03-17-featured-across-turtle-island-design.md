# Featured Across Turtle Island — Design Spec

**Date:** 2026-03-17
**Status:** Approved

## Problem

Minoo's homepage ranks content by **proximity → recency → relevance**. National-scale Indigenous events like the Little NHL break this model — they're culturally significant to every community in Canada, but the proximity filter hides them from 99% of users.

The homepage needs a **fourth dimension**: national cultural signals — content that matters everywhere, not just nearby.

## Solution

A new **"Featured Across Turtle Island"** section on the homepage, powered by a `FeaturedItem` config entity. This is the "playlist model" — editorial control over what appears nationally, with time-bounded display and weight-based ordering.

## Non-Goals

- Admin UI for managing featured items (future — CLI scripts for v1)
- Regional/provincial targeting (future — add `scope` field when needed)
- Location bar fix (tracked in #280, depends on NorthCloud API)
- Automated featuring based on engagement or algorithm

---

## FeaturedItem Entity

A new config entity `featured_item` registered via `FeaturedItemServiceProvider`.

| Field | Type | Purpose |
|-------|------|---------|
| `fid` | auto ID | Primary key |
| `entity_type` | string | Referenced entity type (`event`, `teaching`, `group`, `resource_person`) |
| `entity_id` | integer | Referenced entity ID |
| `headline` | string | Display headline (overrides entity title when set) |
| `subheadline` | string | Optional subtitle/context line |
| `weight` | integer | Sort order (higher = more prominent, default 0) |
| `starts_at` | datetime | When this item begins appearing |
| `ends_at` | datetime | When this item stops appearing |
| `status` | boolean | Published toggle (default 1) |

### Design Rationale

- **`entity_type` + `entity_id`** is the correct polymorphic reference pattern — avoids schema changes per entity type, same pattern as Drupal entity references and Laravel morphs.
- **`headline`/`subheadline`** give editorial override without forcing it — fall back to entity title when unset.
- **`weight`** is essential for ordering when multiple items are active simultaneously.
- **`starts_at`/`ends_at`** provide time-bounded display — items appear and disappear automatically, no deploys or cleanup needed.
- **No targeting fields in v1** — all featured items show to all users. Regional targeting can be added later via a `scope` enum without breaking anything.

### Entity Registration

```
Provider: FeaturedItemServiceProvider
Entity class: FeaturedItem (extends ConfigEntityBase)
Keys: id=fid, label=headline
Group: editorial
Access policy: FeaturedItemAccessPolicy (admin-only)
```

---

## Homepage Integration

### Layout

The "Featured Across Turtle Island" section appears **above** the tab navigation, **below** the hero/search bar. It renders only when active featured items exist.

```
┌─────────────────────────────────────────┐
│ Hero (search bar, "Near Sagamok...")    │
├─────────────────────────────────────────┤
│ Featured Across Turtle Island           │  ← NEW
│ ┌───────────┐ ┌───────────┐            │
│ │ LNHL 2026 │ │ Crystal   │            │
│ │ Mar 15-19 │ │ Shawanda  │            │
│ └───────────┘ └───────────┘            │
├─────────────────────────────────────────┤
│ [Nearby] [Events] [People] [Groups]    │  ← existing, unchanged
│  ... proximity content ...              │
├─────────────────────────────────────────┤
│ Communities pill links                  │
└─────────────────────────────────────────┘
```

### Query Logic

```php
$featuredIds = $featuredStorage->getQuery()
    ->condition('status', 1)
    ->condition('starts_at', date('Y-m-d H:i:s'), '<=')
    ->condition('ends_at', date('Y-m-d H:i:s'), '>=')
    ->sort('weight', 'DESC')
    ->execute();
```

Then resolve each referenced entity via `entity_type` + `entity_id` to get titles, slugs, descriptions. Pass the resolved list to the template as `featured_items`.

### Card Rendering

Featured cards use the existing `homepage-card.html.twig` component with correct URL routing based on `entity_type`:

| entity_type | Route |
|-------------|-------|
| `event` | `/events/{slug}` |
| `teaching` | `/teachings/{slug}` |
| `group` (type=business) | `/businesses/{slug}` |
| `group` (other) | `/groups/{slug}` |
| `resource_person` | `/people/{slug}` |

### Empty State

When no featured items are active, the section does not render. No placeholder, no "nothing featured" message. The homepage looks identical to today.

### Template

New section in `page.html.twig` between the hero and the tab navigation:

```twig
{% if featured_items is defined and featured_items|length > 0 %}
  <section class="featured-section">
    <h2>Featured Across Turtle Island</h2>
    <div class="featured-grid">
      {% for item in featured_items %}
        {# render featured card with headline, subheadline, correct URL #}
      {% endfor %}
    </div>
  </section>
{% endif %}
```

### CSS

New styles in `@layer components`:
- `.featured-section` — full-width section with slight background differentiation
- `.featured-grid` — horizontal card layout (scrollable on mobile, grid on desktop)
- Featured cards use existing card tokens with a distinct accent

---

## Bug Fixes (Same Sprint)

### 1. Homepage business card links

**Problem:** Cards for `type=business` groups link to `/groups/{slug}` instead of `/businesses/{slug}`.

**Fix:** In `homepage-card.html.twig` or `HomeController`, check entity type when building URLs. If the entity is a group with `type=business`, route to `/businesses/{slug}`.

### 2. Homepage Groups tab includes businesses

**Problem:** `HomeController::buildNearbyMixed()` loads groups without filtering out `type=business`. GroupController already has this filter, but the homepage does not.

**Fix:** Add `->condition('type', 'business', '!=')` to the homepage group query in `HomeController`.

---

## Initial Seed Data

Two featured items created via a population script:

### Little NHL 2026
- `entity_type`: `event`
- `entity_id`: (LNHL 2026 event ID)
- `headline`: "Little NHL 2026"
- `subheadline`: "271 teams, 4,500+ players — Markham, Ontario · March 15–19"
- `weight`: 100
- `starts_at`: 2026-03-10
- `ends_at`: 2026-03-21
- `status`: 1

### Crystal Shawanda
- `entity_type`: `resource_person`
- `entity_id`: (Crystal Shawanda person ID)
- `headline`: "Crystal Shawanda at Little NHL"
- `subheadline`: "Ojibwe country/blues artist drove from Nashville for the tournament"
- `weight`: 50
- `starts_at`: 2026-03-15
- `ends_at`: 2026-03-31
- `status`: 1

---

## Translation Keys

```
featured.section_title = "Featured Across Turtle Island"
```

Anishinaabemowin:
```
featured.section_title = "Maamawi-gizhendaagwak Misi-minis-akiing"
```
(Approximate: "Important things across Turtle Island")

---

## Schema Migration (Production)

After deploy, the `featured_item` table is auto-created by the framework on first access (config entity). No manual ALTER TABLE needed.

The population script handles entity creation.

---

## Future Extensions (Not in Scope)

- **Admin UI** — CRUD interface for managing featured items (when multiple editors exist)
- **Regional targeting** — `scope` enum (`national`, `province`, `community`) + optional `province_code`/`community_id` fields
- **Image override** — `media_id` field on FeaturedItem for custom hero images
- **Featured collections** — Group featured items into named collections (e.g., "LNHL Week", "September Awareness")
