# Homepage Feed Redesign

**Date:** 2026-03-21
**Status:** Draft
**Replaces:** Tab-based homepage (`page.html.twig`, `HomeController::index()`)

## Overview

Replace the current tab-based homepage with a single-column, infinite-scroll feed. The feed interleaves events, groups, businesses, people, and featured items in a familiar social-media format. Users filter by type via horizontal chips. Synthetic items (welcome card, communities card) flow through the same pipeline as entity-backed items.

## Motivation

The current homepage uses a tab UI (Nearby / Events / People / Groups) that hides content behind clicks. A unified feed is:

- **Familiar** — users expect a scrollable feed from Facebook, Instagram, etc.
- **Discoverable** — all content types visible without interaction
- **Extensible** — user posts (planned milestone) slot in naturally
- **Mobile-first** — single column, thumb-friendly scrolling

## Data Model

### FeedContext

Immutable value object holding all inputs for a feed request.

```
FeedContext {
    latitude: ?float
    longitude: ?float
    activeFilter: string              // 'all' | 'event' | 'group' | 'business' | 'person'
    requestedTypes: string[]          // for future multi-select filters
    cursor: ?string                   // opaque cursor for pagination
    limit: int                        // items per page (default 20)
    isFirstVisit: bool                // cookie-based
    isAuthenticated: bool             // for conditional feed items
}
```

### FeedItem

Unified wrapper for every item in the feed — entities and synthetic items alike.

```
FeedItem {
    id: string                        // 'event:42', 'welcome:global', 'communities:global'
    type: string                      // 'event' | 'group' | 'business' | 'person' | 'featured' | 'communities' | 'welcome'
    entity: ?ContentEntityBase        // null for synthetic items
    title: string
    subtitle: ?string
    url: string
    badge: string                     // display label ("Event", "Business", etc.)
    date: ?string                     // for events
    distance: ?float                  // km, when location available
    communityName: ?string
    meta: ?string                     // truncated description, role, etc.
    weight: int                       // sort weight (featured = 1000, welcome = 999, communities = 500, normal = 0)
    createdAt: DateTimeImmutable      // for recency tiebreaker
    sortKey: string                   // precomputed canonical sort key
    payload: array<string, mixed>     // safe extensibility without polluting top-level contract
}
```

### FeedResponse

```
FeedResponse {
    items: FeedItem[]
    nextCursor: ?string
    activeFilter: string
    totalHint: ?int                   // approximate count for UI (optional)
}
```

### Cursor Encoding

Base64-encoded JSON: `{lastSortKey, lastType, lastId}`. Storing `lastSortKey` rather than individual fields guarantees stable pagination even if sort logic evolves. `lastType` eliminates edge-case ordering collisions. The API returns `nextCursor` — null when the feed is exhausted.

## FeedAssembler Pipeline

`FeedAssembler` implements `FeedAssemblerInterface` and takes a `FeedContext`, returning a `FeedResponse`. Six stages, each a pure function testable independently:

### 1. Gather

Load raw entities from each source as typed collections (events, groups, businesses, people, featured). Entity loaders are extracted into a dedicated `EntityLoaderService` for reuse. Returns separate arrays to prevent accidental mixing.

### 2. Transform

`FeedItemFactory` maps each entity → `FeedItem`. Computes distance, badge, meta, URL, payload, id, typeSlot, and sortKey. Synthetic items (welcome, communities) are also created through the factory for consistency.

### 3. Inject

Insert synthetic items if applicable:
- If `isFirstVisit`: add welcome card (`id: 'welcome:global'`, weight 999)
- Always: add communities card (`id: 'communities:global'`, weight 500)

Both are first-class FeedItems with stable IDs for deterministic cursoring.

### 4. Filter

Apply `activeFilter` / `requestedTypes`. Synthetic items always pass. Everything else filtered by type.

### 5. Sort

Deterministic sort using precomputed `sortKey`:

```
sortKey = sprintf('%04d:%010.2f:%02d:%020d:%s',
    9999 - $weight,                              // weight DESC (inverted)
    $distance ?? 99999.99,                       // distance ASC
    $typeSlot,                                   // precomputed round-robin slot
    PHP_INT_MAX - $createdAt->getTimestamp(),     // recency DESC (inverted)
    $id                                          // stable tiebreaker ASC
)
```

`typeSlot` is assigned during Transform (precomputed, not post-hoc). Sort is a single `usort` on `sortKey` — deterministic, no hidden heuristics. Golden file tests lock the algorithm. Max expected distance is ~2000 km (North Shore coverage area); the `%010.2f` format supports up to `99999.99` km which is well beyond this.

### 6. Paginate

Find cursor position by `lastSortKey` + `lastId`, slice `$limit` items forward, encode next cursor. Cursor-based pagination prevents drift as content is seeded.

## Controller & API Layer

### FeedController

Thin controller with three routes:

| Route | Method | Handler | Purpose |
|---|---|---|---|
| `/` | GET | `FeedController::index()` | SSR full page |
| `/api/feed` | GET | `FeedController::api()` | JSON for infinite scroll |
| `/explore` | GET | `FeedController::explore()` | Search redirect (moved from HomeController) |

### index() Flow

1. Build `FeedContext` from request (`minoo_loc` location cookie, `minoo_fv` first-visit cookie, `filter=all`, no cursor)
2. Call `FeedAssembler::assemble($ctx)`
3. Render `feed.html.twig` with `FeedResponse`
4. Embed `data-next-cursor` and `data-active-filter` on feed container for JS hydration
5. If `isFirstVisit`, set first-visit cookie in response

### api() Flow

1. Build `FeedContext` from cookies + query params (`cursor`, `filter`, `limit`)
2. Call `FeedAssembler::assemble($ctx)`
3. Return JSON: `{ items: [...], nextCursor: ?string, activeFilter: string }` — each item includes an `html` field with server-rendered card HTML (Twig fragment) so rendering logic stays in one place

### JSON Item Shape

```json
{
  "id": "event:42",
  "type": "event",
  "badge": "Event",
  "title": "Language Circle",
  "subtitle": "Tomorrow at 6:00 PM",
  "url": "/events/language-circle",
  "distance": 2.3,
  "communityName": "Sagamok Anishnawbek",
  "meta": "Community Centre",
  "date": "2026-03-22T18:00:00",
  "payload": {},
  "html": "<article class=\"feed-card feed-card--event\" data-id=\"event:42\">..."
}
```

Internal fields (`weight`, `sortKey`, `typeSlot`) stay server-side. Optionally expose `sortKey` behind a debug flag.

### Filter Chips

Use query params: `/api/feed?filter=event&cursor=abc123`. SSR page loads with `filter=all`. JS hydrates from `data-active-filter`.

## Template & Frontend

### feed.html.twig

Extends `base.html.twig`. Structure:

1. **Compact sticky header** — logo + search input (shrinks on scroll)
2. **Filter chip row** — All / Events / Groups / Businesses / People (`data-type` attributes)
3. **Feed container** — server-rendered initial items (`data-next-cursor`, `data-active-filter`)
4. **Loading sentinel** — Intersection Observer target for infinite scroll

### feed-card.html.twig

Single unified component with type-based variants. All items (entity-backed and synthetic) render through the same template.

```twig
<article class="feed-card feed-card--{{ item.type }}" data-id="{{ item.id }}">
  <span class="feed-badge feed-badge--{{ item.type }}">{{ item.badge }}</span>
  <h3 class="feed-card__title"><a href="{{ item.url }}">{{ item.title }}</a></h3>
  {% if item.subtitle %}<p class="feed-card__subtitle">{{ item.subtitle }}</p>{% endif %}
  {% if item.distance is not null %}<span class="feed-card__distance">{{ item.distance|round(1) }} km</span>{% endif %}
  {% if item.communityName %}<p class="feed-card__community">{{ item.communityName }}</p>{% endif %}
  {% if item.meta %}<p class="feed-card__meta">{{ item.meta }}</p>{% endif %}
</article>
```

Special variants:
- `feed-card--welcome` — intro card with CTA link to `/about`
- `feed-card--communities` — renders community pill row inline
- `feed-card--featured` — accent border, star badge, elevated visual weight

### Infinite Scroll JS

Progressive enhancement, no framework, vanilla JS:

1. Intersection Observer watches the loading sentinel
2. When visible, fetch `/api/feed?cursor={nextCursor}&filter={activeFilter}`
3. `AbortController` cancels in-flight requests; monotonic fetch token prevents race conditions
4. Parse JSON, render items via `insertAdjacentHTML` with `data-id` dedup check
5. Update `nextCursor`; if null, remove sentinel and show "You're all caught up"

### Filter Chip Interaction

- Click a chip → update `activeFilter`, reset cursor to null
- Clear feed container, fetch `/api/feed?filter={type}`
- Active chip gets visual highlight via CSS class

### CSS Changes

All additions in `@layer components` in `minoo.css`:

- `feed-card`, `feed-badge`, type-specific variants
- `feed-header` (compact sticky)
- `feed-chips` (horizontal scrollable filter row)
- `feed-sentinel` (loading indicator / skeleton)
- `feed-card--loading` skeleton state with CSS animation
- Type color custom properties: event=amber, business=coral, group=teal, person=violet, featured=sky

No build step — stays vanilla CSS + vanilla JS.

## Migration Plan

### Files Removed

| File | Reason |
|---|---|
| `src/Controller/HomeController.php` | Replaced by `FeedController` |
| `templates/page.html.twig` | Replaced by `feed.html.twig` |
| `templates/components/homepage-card.html.twig` | Replaced by `feed-card.html.twig` |
| Tab-switching `<script>` in `page.html.twig` | No longer needed |
| `homepage-*` CSS classes in `minoo.css` | Deprecated, removed after verification |

Verify no other templates reference `homepage-*` classes before removal.

### Files Added

| File | Purpose |
|---|---|
| `src/Controller/FeedController.php` | Thin controller (index + api + explore) |
| `src/Feed/FeedContext.php` | Immutable value object |
| `src/Feed/FeedItem.php` | Unified feed item |
| `src/Feed/FeedResponse.php` | Assembler output |
| `src/Feed/FeedAssemblerInterface.php` | Interface for DI |
| `src/Feed/FeedAssembler.php` | 6-stage pipeline |
| `src/Feed/FeedItemFactory.php` | Entity → FeedItem transform |
| `src/Feed/FeedCursor.php` | Cursor encode/decode |
| `src/Feed/EntityLoaderService.php` | Extracted entity loading logic |
| `src/Provider/FeedServiceProvider.php` | Registers FeedAssembler + FeedController |
| `templates/feed.html.twig` | New homepage |
| `templates/components/feed-card.html.twig` | Unified card component |
| `templates/about.html.twig` | Dedicated about page (copy per `docs/content-tone-guide.md`) |

### Files Reused

- Entity loading logic (extracted from `HomeController` into `EntityLoaderService`)
- `LocationService`, `CommunityFinder`, `GeoDistance` — unchanged
- `base.html.twig` — feed template extends it
- All existing CSS layers except `homepage-*` components

### Route Changes

| Route | Handler | Notes |
|---|---|---|
| `/` | `FeedController::index()` | Replaces `HomeController::index()` |
| `/api/feed` | `FeedController::api()` | New |
| `/explore` | `FeedController::explore()` | Moved from HomeController |
| `/about` | Framework path routing | New template auto-served |
| `/home` | Redirect to `/` | Temporary alias |

Hash fragments (`/#events`, `/#people`) can be silently ignored. Optionally strip on load.

### Test Suite

| Test | Coverage |
|---|---|
| `FeedContextTest` | Value object construction, defaults, validation |
| `FeedItemFactoryTest` | Entity → FeedItem mapping for each type, synthetic items |
| `FeedAssemblerTest` | Full pipeline: gather → transform → inject → filter → sort → paginate |
| `FeedCursorTest` | Encode/decode roundtrip, edge cases |
| `FeedControllerTest` | SSR response, API JSON response, filter params |
| `EntityLoaderServiceTest` | Entity loading queries, filtering |
| Golden file sort tests | Lock sort algorithm against regression |

## Cookie Names

| Cookie | Purpose | Value | Expiry |
|---|---|---|---|
| `minoo_loc` | User location (lat/lon/community) | JSON `{lat,lon,community}` | 30 days |
| `minoo_fv` | First-visit flag | `1` (set after first visit) | 1 year |

## Architectural Boundaries

- `FeedAssembler` depends on `EntityTypeManager` (framework) and `LocationService` (Minoo geo domain) — no other coupling
- `FeedController` depends only on `FeedAssemblerInterface` and `Twig\Environment`
- `FeedItemFactory` is a pure mapper with no side effects
- `FeedCursor` is a standalone utility with no dependencies
- Entity loading logic is extracted but not duplicated — single source of truth in `EntityLoaderService`

## Future Considerations

- **User posts:** When the user posts milestone lands, `post` becomes a new FeedItem type flowing through the same pipeline
- **Multi-select filters:** `requestedTypes` in FeedContext is ready for this
- **Authenticated feed items:** `isAuthenticated` in FeedContext enables conditional content
- **Real-time updates:** WebSocket or polling can push new items to the top of the feed using the same FeedItem contract
