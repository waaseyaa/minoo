# Events Page Refactor — Design

**Date:** 2026-04-14
**Scope:** `/events` (listing) and `/events/{slug}` (detail)
**Status:** Design approved; awaiting implementation plan

## Problem

The current `/events` page is a flat, chronological card grid driven by a single query:

```php
$storage->getQuery()->condition('status', 1)->sort('starts_at', 'DESC')->execute();
```

Concrete UX problems:

1. **Past events dominate.** `DESC` sort puts the most-recently-ended event first, so the page opens on content the user cannot act on.
2. **No upcoming/past split.** Active and expired events mix in the same grid.
3. **No affordances to narrow the set.** No filter, search, sort, view toggle, or calendar view.
4. **Not location-aware.** Minoo has `LocationContext` and Haversine `CommunityFinder`, but events ignore both.
5. **Does not scale.** With a 2-year event horizon, users cannot see beyond the next few cards without infinite scrolling.
6. **Detail page** lacks a calendar export, a map, or a "similar upcoming events" affordance.

## Goal

Refactor `/events` into a discovery-first, mixed-intent page that stays useful across a long (24-month) event horizon. Default view curates a balanced mix; filters collapse to a flat chronological list when the user expresses intent. Add list and calendar views for planning-oriented users. Preserve existing templating conventions (`base.html.twig` extension, components under `templates/components/`, single `minoo.css`).

## Non-goals

- Event creation/editing UI (out of scope; admin is separate).
- Ticketing, RSVP, or engagement counts on the listing (engagement lives on the feed).
- Full-text search infrastructure changes — we use a simple `LIKE` against title/description/location for the `q` param.
- Redesign of the detail page beyond the additions listed below.

## Architecture

Introduce a bounded `Domain/Events/` context that mirrors the existing `Domain/Geo/` pattern.

### New classes

- `src/Domain/Events/Service/EventFeedBuilder.php` — orchestrator. Given an `EventFilters` and optional `LocationContext`, returns an `EventFeedResult`. Owns the smart-mix algorithm; keeps the controller thin and the algorithm unit-testable.
- `src/Domain/Events/Service/EventFeedRanker.php` — pure function from event + context → score. Used by the builder for the "on the horizon" section.
- `src/Domain/Events/ValueObject/EventFilters.php` — immutable, parsed from the query string. Fields: `type: array`, `communityId: ?string`, `when: string` (`all` default, `week`, `month`, `3mo`, `past`), `near: bool`, `q: ?string`, `view: string` (`feed` default, `list`, `calendar`), `month: ?string` (calendar nav, `YYYY-MM`), `sort: string` (`soonest` default, `latest`), `page: int`. `fromRequest(Request)` parses + whitelists values; `isActive(): bool` indicates whether any narrowing filter is set.
- `src/Domain/Events/ValueObject/EventFeedResult.php` — immutable. Fields:
  - `featured: list<EntityInterface>`
  - `happeningNow: list<EntityInterface>`
  - `thisWeek: list<EntityInterface>`
  - `comingUp: list<EntityInterface>`
  - `onTheHorizon: list<EntityInterface>`
  - `flatList: list<EntityInterface>` (populated only when filters are active or `view=list`)
  - `calendarMonth: ?CalendarMonth` (populated only when `view=calendar`)
  - `communities: array<string, array>` (lookup for card rendering)
  - `totalUpcoming: int`
  - `activeFilters: EventFilters`
  - `availableFilters: array` (distinct types + communities present among upcoming events)
  - `pagination: ?Pagination` (list view only)
- `src/Domain/Events/ValueObject/CalendarMonth.php` — immutable month grid: `year`, `month`, `weeks: list<list<CalendarDay>>`, `prevMonth`, `nextMonth`.
- `src/Domain/Events/ValueObject/CalendarDay.php` — `date`, `inMonth: bool`, `events: list<EntityInterface>`, `isToday: bool`.

All new classes are `final` and use `declare(strict_types=1)`. Value objects are constructor-promoted and have no mutators. Services are registered as singletons in `AppServiceProvider` and auto-injected into `EventController` via the existing DI pattern.

### Algorithm — "smart mix" default

When `EventFilters::isActive()` is false and `view=feed`:

1. **Happening now** — `starts_at <= now <= ends_at`. All of them, sorted by `starts_at` ASC.
2. **This week** — `now < starts_at <= now + 7d`. All of them, ASC.
3. **Coming up (next 30 days)** — `now + 7d < starts_at <= now + 30d`. Capped at 12. Diversity rules applied after chronological sort:
   - No more than 3 events of the same `type` in a row — if violated, bump the offender down one slot.
   - No more than 2 events from the same `community_id` appear in the top 6 — if violated, swap out for the next event that doesn't.
4. **On the horizon (1 month–24 months out)** — `now + 30d < starts_at <= now + 730d`. Scored by `EventFeedRanker`:
   - `+3` if a `featured_item` entity references this event, is `status=1`, and `starts_at <= now <= ends_at` on the featured item window.
   - `+2` if `LocationContext` is present and the event's community is within 150 km (Haversine via `CommunityFinder`).
   - `+1` if `type` is `ceremony` or `powwow`.
   - Tiebreak: earliest `starts_at`.
   - Take top 6.
5. **Featured strip** — any `featured_item` targeting an event whose window is active. Rendered as 1–3 prominent cards above the sections. Can overlap with section content; dedupe when rendering the sections.

When `EventFilters::isActive()` is true:

- Skip all sectioning. Return a single `flatList` from a query with the filter conditions applied, sorted by `sort` (default `soonest` → `starts_at ASC`), with a `past` branch that flips to `starts_at DESC` and constrains `ends_at < now`. Paginate at 30/page.

When `view=list` (filters off):

- Same flat query as the filter path but without narrowing; chronological, paginated.

When `view=calendar`:

- Determine target month from `filters.month` (default: current month). Load events whose `[starts_at, ends_at]` window overlaps the month's visible grid range (6 weeks shown). Build `CalendarMonth` with events placed under each day they span.

### Controller

`EventController::list` becomes:

```php
public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $filters  = EventFilters::fromRequest($request);
    $location = $this->locationService->resolve($request, $account);
    $result   = $this->eventFeedBuilder->build($filters, $location);

    $html = $this->twig->render('events.html.twig', LayoutTwigContext::withAccount($account, [
        'path'   => $request->getPathInfo(),
        'filters' => $filters,
        'feed'   => $result,
    ]));
    return new Response($html);
}
```

Constructor gains `EventFeedBuilder` and `LocationService`. The `.ics` export is a second controller method:

```php
public function ics(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
```

Routes registered in `AppServiceProvider`:

- `/events` → `list` (existing)
- `/events/{slug}` → `show` (existing)
- `/events/{slug}.ics` → `ics` (new, `allowAll()`, respects `status=1`)

### Templates

`templates/events.html.twig` listing branch is rewritten to switch on `filters.view`:

- `feed` → render `components/event-feed.html.twig` (featured strip + sections in order, omitting empty sections, with "See all upcoming →" CTA linking to `?view=list`).
- `list` → render `components/event-list.html.twig` (month-grouped flat list + pagination).
- `calendar` → render `components/event-calendar.html.twig` (CSS-grid month view with prev/next navigation).

All three views sit under the same filter bar: `components/event-filters.html.twig`.

New components:

- `components/event-filters.html.twig` — GET form. Time chips (`All upcoming | This week | This month | Next 3 months | Past`), type multiselect, community dropdown, "Near me" toggle (rendered only when `LocationContext` resolved), text search `q`, view toggle (`Feed | List | Calendar`). Active filter chips rendered below with dismiss links that remove a single param.
- `components/event-feed.html.twig` — loops over sections, each section is `<section>` with `h2` + `card-grid`. Uses `event-feed-section.html.twig` for the repeating block.
- `components/event-feed-section.html.twig` — section header (title + count) + card grid. Skips rendering when empty.
- `components/event-list.html.twig` — flat list grouped by `YYYY-MM` with month headers + pagination links.
- `components/event-calendar.html.twig` — `<table role="grid">` month view. Prev/next month links. Days with events show type-colored dots; clicking a day updates the URL to `?view=list&when=day&date=YYYY-MM-DD` (day filter is the simplest no-JS implementation).

Card upgrades (`components/event-card.html.twig`):

- Relative time label derived from `starts_at` via new `|relative_date` filter (registered in the existing Twig extension — if no extension exists, add one alongside existing filters).
- Distance badge when `LocationContext` and event's community have coordinates (e.g. `42 km away`).
- Type-colored left border using a `--event-type-{type}` oklch token per type.
- Thumbnail when `media_id` resolves (reuse `EventController::resolvePhotoUrls` logic, lifted into a small helper on the builder).
- "Happening now" cards get an accent class for stronger visual weight.

Detail page additions (`templates/events.html.twig` show branch):

- "Add to calendar" button → `/events/{slug}.ics`.
- "Similar upcoming events" `related-section`: same `type`, `starts_at` within next 60 days, excluding the current event, cap 3.
- Explicit hero-level status badge — already present; keep.

### CSS

Additions in `public/css/minoo.css` under `@layer components` (single-file convention preserved):

- `.event-filters` — filter bar layout, chip styling, responsive wrap.
- `.event-feed__section` — section header, spacing between sections.
- `.event-list` — month header + dense list treatment.
- `.event-calendar` — 7-column grid, day cell with event dots, responsive behaviour (falls back to list view under ~640px with a CSS `@media` query using `display: block`).
- Type color tokens in `@layer tokens`: `--event-powwow`, `--event-gathering`, `--event-ceremony`, `--event-tournament`.
- Bump `?v=` query on `minoo.css` in `base.html.twig` per the CSS cache-bust convention.

### Internationalization

All strings go through `trans()` and live in `resources/lang/en.php` under a new `events.*` namespace grouping:

- Filter labels and chip text
- Section headings ("Happening now", "This week", "Coming up", "On the horizon", "Featured")
- View toggle labels
- Empty state copy (tone-guide compliant: first/second person, capitalized "Teachings"/"Elder"/"Knowledge Keeper")
- Distance + relative time formats

### Accessibility

- Filter bar: labeled `<fieldset>`/`<legend>`, `for`/`id` pairing, visible focus rings.
- Chips are real `<button type="submit">` elements with `aria-pressed` for toggles.
- Calendar: `<table role="grid">` with `<th scope="col">` for weekday headers, `aria-label` on each day cell including event count.
- Relative time also exposes absolute date via `<time datetime="...">` for screen readers.
- Empty states have descriptive text, not just icons.

### Testing

Unit tests:

- `tests/App/Unit/Domain/Events/EventFiltersTest.php` — query-string parsing, whitelist enforcement, `isActive()` truth table.
- `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php` — fixture set of 50 events spanning 24 months across 4 types and 5 communities. Assertions:
  - Sections populate per time-bucket definitions.
  - Diversity rule: no more than 3 of the same type in a row in "Coming up".
  - Diversity rule: no more than 2 from same community in top 6 of "Coming up".
  - Horizon ranker: featured + near + culturally significant score correctly.
  - Filter path short-circuits sectioning and returns `flatList`.
  - `past` filter flips to DESC and excludes future events.
- `tests/App/Unit/Domain/Events/EventFeedRankerTest.php` — scoring isolation.
- `tests/App/Unit/Domain/Events/CalendarMonthTest.php` — month grid math across leap years, week-start boundaries, DST.

Integration:

- `tests/App/Integration/EventListingTest.php` — boot kernel, seed events, hit `/events` in each view mode and with representative filters, assert HTML structure.

Playwright (`tests/playwright/events.spec.ts`, new):

- Filter chip round-trip (click chip → URL updates → results narrow → dismiss chip → results restore).
- View toggle persists in URL.
- Calendar prev/next month navigation.
- `.ics` download returns valid MIME.

## Rollout / delivery order

1. **`EventFilters` value object** — parsing + whitelisting + tests. No UI wiring yet.
2. **`EventFeedRanker` + `EventFeedBuilder` + `EventFeedResult`** — algorithm and tests in isolation; controller not yet switched over.
3. **Controller switch** — replace the flat query with `EventFeedBuilder`; wire into `events.html.twig` using the feed view only. Existing card grid still works at this point.
4. **Filter bar component** — ship the filter UI; flat-list fallback when `isActive()`.
5. **Card upgrades** — relative time filter, distance badge, type-color border, thumbnail.
6. **List view + pagination** — month-grouped list view.
7. **Calendar view** — month grid + navigation.
8. **Detail page additions** — `.ics` export route + "Similar upcoming" section.
9. **Playwright coverage + a11y pass + CSS cache bump.**

Each step is independently deployable; after step 3, `/events` is already materially better than today.

## Files

**New**

- `src/Domain/Events/Service/EventFeedBuilder.php`
- `src/Domain/Events/Service/EventFeedRanker.php`
- `src/Domain/Events/ValueObject/EventFilters.php`
- `src/Domain/Events/ValueObject/EventFeedResult.php`
- `src/Domain/Events/ValueObject/CalendarMonth.php`
- `src/Domain/Events/ValueObject/CalendarDay.php`
- `templates/components/event-filters.html.twig`
- `templates/components/event-feed.html.twig`
- `templates/components/event-feed-section.html.twig`
- `templates/components/event-list.html.twig`
- `templates/components/event-calendar.html.twig`
- `tests/App/Unit/Domain/Events/EventFiltersTest.php`
- `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php`
- `tests/App/Unit/Domain/Events/EventFeedRankerTest.php`
- `tests/App/Unit/Domain/Events/CalendarMonthTest.php`
- `tests/App/Integration/EventListingTest.php`
- `tests/playwright/events.spec.ts`

**Modified**

- `src/Controller/EventController.php` — new constructor deps; `list` switches to builder; new `ics` method.
- `src/Provider/AppServiceProvider.php` — register `EventFeedBuilder`, `EventFeedRanker` as singletons; register `/events/{slug}.ics` route.
- `templates/events.html.twig` — listing branch re-authored around view switch + filters bar; detail branch adds `.ics` button and "Similar upcoming".
- `templates/components/event-card.html.twig` — relative time, distance, type-color border, thumbnail, happening-now accent.
- `public/css/minoo.css` — new component styles + type color tokens; bump cache-bust.
- `resources/lang/en.php` — new `events.*` translation keys.
- Twig extension registering the `|relative_date` filter (existing extension if present, or new one under `src/Support/` consistent with current patterns).

## Open questions

None blocking. Two deferred items noted so they don't surface mid-implementation:

- **Map embed on detail page** — deferred. No existing map component in the codebase; introducing one is its own design.
- **Day-filter URL shape** — `?view=list&when=day&date=YYYY-MM-DD` chosen because it reuses the existing `when` whitelist cleanly. If the plan phase uncovers a simpler shape, revisit without expanding scope.
