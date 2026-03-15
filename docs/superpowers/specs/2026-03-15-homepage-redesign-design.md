# Homepage Redesign — Design Spec

**Date:** 2026-03-15
**Status:** Draft
**Goal:** Redesign the Minoo homepage as a location-aware dashboard hub that surfaces real seeded content (businesses, people, events) to show the community is alive, while explaining the platform to newcomers.

## Executive Summary

Replace the current hero + explore grid + "who is this for" homepage with a compact dashboard layout:

1. Compact hero with search placeholder and location context
2. Sticky tab row (Nearby / Events / People / Groups) with progressive enhancement
3. Unified content cards showing real seeded data, sorted by proximity
4. Communities pill row linking to nearby communities
5. Compact "What is Minoo?" section, prominent for newcomers

All SSR with Twig, no new JS frameworks. ~15 lines of vanilla JS for tab switching with full no-JS fallback.

## 1. Compact Hero

### 1.1 Structure

Two-zone horizontal layout:

- **Left zone:** One-line tagline (Charter serif, `text-xl`) + location context line
- **Right zone:** Search form — type selector dropdown + text input + "Explore" button

### 1.2 Height

~120px maximum. The tab row and first content cards must be visible without scrolling on 1366x768 screens.

### 1.3 Background

Forest gradient: `--color-forest-700` to `oklch(0.25 0.06 155)` (darker forest, not a named token — inline in the gradient only).

### 1.4 Location Context

| State | Left zone shows |
|-------|-----------------|
| Location known | "Near {communityName}" |
| Location unknown | "Explore communities across the North Shore" |

### 1.5 Search Form

```html
<form class="hero-search" action="/explore" method="get" role="search">
  <select name="type">
    <option value="all">All</option>
    <option value="businesses">Businesses</option>
    <option value="people">People</option>
    <option value="events">Events</option>
  </select>
  <input name="q" type="search" placeholder="What are you looking for? e.g., beadwork, salon, powwow" />
  <button type="submit">Explore</button>
</form>
```

**Phase 1 behavior:** The homepage controller handles the `/explore` route directly (no separate controller needed). It reads `type` and `q`, then redirects:

| `type` value | Redirect target |
|-------------|-----------------|
| `businesses` | `/groups?q={q}` |
| `people` | `/people?q={q}` |
| `events` | `/events?q={q}` |
| `all` (default) | `/groups?q={q}` |
| missing/invalid | `/groups` |

If `q` is empty, redirect to the section landing page without `?q=`. Target pages ignore `q` for now — ready for future search API. The route is registered in the homepage controller's `routes()` method.

### 1.6 Mobile

Stacks vertically: tagline → location line → search form. Full-width input.

## 2. Sticky Tab Row

### 2.1 Tabs

| Tab | Label | Content |
|-----|-------|---------|
| 1 | Nearby | Mixed businesses + events + people, sorted by proximity |
| 2 | Events | Upcoming events sorted by `starts_at` |
| 3 | People | People with `consent_public: true`, sorted by proximity |
| 4 | Groups | Businesses/groups sorted by proximity |

Default active tab: **Nearby**.

### 2.2 Visual Design

- Active tab: bottom border in `--color-forest-500`, `--color-earth-900` text
- Inactive tabs: `--color-earth-700` text, no border
- Background: `--color-earth-50`
- Sticky: `position: sticky; top: 0; z-index: 10`

### 2.3 Progressive Enhancement

**SSR (no JS):**
- All four tab panels rendered in HTML
- Active panel: visible (no `hidden` attribute)
- Inactive panels: `hidden` attribute (HTML standard, removes from rendering and accessibility tree)
- Tab links are anchor elements with `href` to section pages (`/events`, `/people`, `/groups`)
- `data-tab` attribute on each tab and `data-panel` on each panel for JS binding

**With JS (~15 lines):**
- Intercepts tab clicks
- Toggles `hidden` attribute on panels and `aria-selected` on tabs
- Updates URL hash (`#events`, `#people`, `#groups`) for direct linking
- On page load, reads hash to activate correct tab

**Markup pattern:**
```html
<nav class="homepage-tabs" role="tablist">
  <a href="/" role="tab" data-tab="nearby" aria-selected="true" class="homepage-tab active">Nearby</a>
  <a href="/events" role="tab" data-tab="events" aria-selected="false" class="homepage-tab">Events</a>
  <a href="/people" role="tab" data-tab="people" aria-selected="false" class="homepage-tab">People</a>
  <a href="/groups" role="tab" data-tab="groups" aria-selected="false" class="homepage-tab">Groups</a>
</nav>

<div role="tabpanel" data-panel="nearby" id="panel-nearby">
  <!-- 6 unified cards -->
</div>
<div role="tabpanel" data-panel="events" id="panel-events" hidden>
  <!-- 6 event cards -->
</div>
<!-- etc. -->
```

### 2.4 Mobile

Tabs scroll horizontally if needed. Same sticky behavior.

## 3. Unified Content Cards

### 3.1 Card Structure

All entity types (business, event, person) use a single card component with a type-colored left border.

```html
<article class="homepage-card homepage-card--{type}">
  <span class="homepage-badge">{Type}</span>
  <h3 class="homepage-title"><a href="{url}">{title/name}</a></h3>
  <p class="homepage-meta">{meta line}</p>
</article>
```

### 3.2 Type Colors

| Type | Left border | Badge color |
|------|------------|-------------|
| Business/Group | `--color-forest-500` | `--color-forest-500` |
| Event | `--color-water-600` | `--color-water-600` |
| Person | `--domain-people` | `--domain-people` |

### 3.3 Meta Line Content

| Type | Meta format |
|------|-------------|
| Business | "{description snippet} · {community}" |
| Event | "{date formatted} · {location}" |
| Person | "{roles} · {community}" |

### 3.4 Card Grid

`display: grid; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); gap: var(--space-sm)`

Up to 6 cards per tab panel. If fewer than 6 available, show what exists.

### 3.5 Content Selection Logic

**Nearby tab:** Query up to 3 from each entity type (groups, events, people), sorted by proximity within each type. Merge into a single list using round-robin interleaving: pick 1 group, 1 event, 1 person, repeat until 6 total or all sources exhausted. If a type has fewer than 2 results, the remaining slots go to the other types. Events with `starts_at` in the past are excluded.

**Events tab:** Query events where `starts_at > now()`, sort ascending by `starts_at`, limit to 6.

**People tab:** Query resource_persons where `consent_public = 1`, sort by proximity, limit to 6.

**Groups tab:** Query groups, sort by proximity, limit to 6.

**Proximity sorting:** Entities are sorted by the distance from the user's location to their associated community. Groups and events have a `community_id` field pointing to a community entity with lat/lon coordinates. People have a `community` string field — match it to a community entity by name to get coordinates. If the name doesn't match any community, that person sorts last (treated as infinite distance). When location is unknown, fall back to: events by date, people/groups alphabetically.

### 3.6 Empty States

If a tab has zero content, show a message following the content tone guide:

"We're just getting started in this area. Know a business, event, or community leader we should include? Let us know."

This is the final copy for all empty tab panels. No per-tab variation for Phase 1.

## 4. Communities Row

### 4.1 Structure

Horizontal row of pill-shaped links below the tab content area.

- **Heading:** "Communities"
- **Content:** 4-6 community names as rounded pill links
- **Links:** `/communities/{slug}`

### 4.2 Content Selection

| State | Communities shown |
|-------|------------------|
| Location known | Nearest 6 communities by distance |
| Location unknown | Curated defaults: Sagamok Anishnawbek, Espanola, Elliot Lake, Spanish, Massey |

### 4.3 Style

Pill style: `--color-earth-100` background, `--color-earth-700` text, `border-radius: var(--radius-lg)`, hover lifts to `--color-earth-200`.

## 5. What is Minoo

### 5.1 Structure

Compact section at the page bottom.

- **Heading:** "What is Minoo?"
- **Body:** 2-3 sentences, first-person plural voice per content tone guide
- **CTA:** "Learn more" → `/how-it-works`

### 5.2 Copy

"We're building a place where Indigenous communities, businesses, and Knowledge Keepers are visible and connected. Browse local businesses, find upcoming events, or connect with people in your area."

### 5.3 Visibility Rules

| State | Behavior |
|-------|----------|
| Location unknown, no nearby content | Full section with heading, body, CTA |
| Location known, content showing | Single-line mention: "Minoo connects Indigenous communities. Learn more" |

### 5.4 Style

Background: `--color-earth-100`. Padding: `var(--space-lg)` block.

## 6. Server-Side Data Assembly

### 6.1 Controller Changes

A new `HomepageController` in `src/Controller/HomepageController.php` handles both the homepage view and the `/explore` search redirect. Registered via a new route in the existing `PageServiceProvider` (or a dedicated provider if the controller needs its own). The controller assembles data for all four tab panels:

```php
// Pseudocode for homepage data assembly
$location = $this->resolveLocation($request);

$nearby = $this->getNearbyMixed($location, limit: 6);
$events = $this->getUpcomingEvents($location, limit: 6);
$people = $this->getPublicPeople($location, limit: 6);
$groups = $this->getNearbyGroups($location, limit: 6);
$communities = $this->getNearbyCommunities($location, limit: 6);
```

### 6.2 Query Patterns

Use existing entity storage queries:

- Events: `$storage->getQuery()->condition('starts_at', date('Y-m-d'), '>=')->sort('starts_at', 'ASC')->range(0, 6)->execute()`
- People: `$storage->getQuery()->condition('consent_public', 1)->range(0, 6)->execute()`
- Groups: `$storage->getQuery()->range(0, 6)->execute()`

Proximity sorting can use the existing `CommunityFinder::findNearby()` pattern or simple in-PHP distance calculation over the small result set.

### 6.3 Caching

No caching needed at this scale. If performance becomes an issue, cache the assembled data per-community for 5 minutes.

## 7. What Is NOT Changing

- Navigation header/footer — untouched
- Location bar component — untouched
- CSS layer architecture — new components added to `@layer components`
- Entity types and storage — no schema changes
- Other pages (events, people, groups, communities) — untouched

## 8. What Is Being Removed

- The 5-card explore grid (Communities, People, Teachings, Events, Elder Support)
- The "Who is this for?" audience section (Elders, Youth, Knowledge Keepers, Families, Volunteers)
- The old static hero with two CTA buttons

These are replaced by the more dynamic, content-driven sections above.

## 9. Accessibility

- Tab row uses `role="tablist"`, `role="tab"`, `role="tabpanel"`, `aria-selected`
- Search form uses `role="search"`, labels on all inputs
- Cards are `<article>` elements with linked headings
- Keyboard navigation: tabs focusable, arrow keys switch tabs (JS enhancement)
- Color contrast: all badge/border colors checked against their backgrounds
- Skip link already exists in `base.html.twig`

## 10. Mobile Considerations

- Hero stacks vertically (tagline → location → search)
- Tabs scroll horizontally, remain sticky
- Card grid collapses to single column below 40em
- Communities pills wrap naturally
- "What is Minoo" section is always full-width

## 11. Testing

**Playwright tests:**
- Homepage loads without error (200 status)
- Hero displays location context when location cookie set
- Tab switching works (JS enabled)
- Cards link to correct detail pages
- Search form redirects to section pages
- No personal contact shown for `consent_public: false` people

**PHPUnit tests:**
- Homepage controller returns correct data structure
- Nearby content selection logic (proximity sorting, type mixing)
- Search redirect routing
- Consent filtering on people tab
