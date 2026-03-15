# Homepage Redesign Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Minoo's static homepage with a location-aware dashboard hub showing real businesses, people, and events via tabbed navigation.

**Architecture:** Modify existing `HomeController` to assemble 4 tab data sets + explore redirect. Rewrite `page.html.twig` with compact hero, sticky tabs, unified cards. Progressive enhancement: SSR with `hidden` attribute toggling via ~20 lines of vanilla JS (including arrow-key nav).

**Tech Stack:** PHP 8.3, Twig 3, vanilla CSS (oklch tokens), vanilla JS, PHPUnit 10.5, Playwright

**Spec:** `docs/superpowers/specs/2026-03-15-homepage-redesign-design.md`

---

## File Structure

**New files:**
- `templates/components/homepage-card.html.twig` — unified card for business/event/person
- `tests/Minoo/Unit/Controller/HomeControllerDataTest.php` — data assembly unit tests

**Modified files:**
- `src/Controller/HomeController.php` — expand data assembly for 4 tabs + explore redirect
- `src/Provider/CommunityServiceProvider.php` — add `/explore` route
- `templates/page.html.twig` — complete rewrite of `{% block content %}`
- `public/css/minoo.css` — add homepage component styles in `@layer components`
- `resources/lang/en.php` — add new translation keys

---

## Chunk 1: Controller & Data Assembly

### Task 1: Add explore redirect route

**Files:**
- Modify: `src/Provider/CommunityServiceProvider.php`
- Modify: `src/Controller/HomeController.php`

- [ ] **Step 1: Add `/explore` route to CommunityServiceProvider**

In `src/Provider/CommunityServiceProvider.php`, add to the `routes()` method:

```php
$router->addRoute(
    'explore.redirect',
    RouteBuilder::create('/explore')
        ->controller('Minoo\\Controller\\HomeController::explore')
        ->allowAll()
        ->methods('GET')
        ->build(),
);
```

- [ ] **Step 2: Add explore method to HomeController**

In `src/Controller/HomeController.php`, add this method:

```php
public function explore(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $type = $query['type'] ?? 'all';
    $q = trim($query['q'] ?? '');

    $targets = [
        'businesses' => '/groups',
        'people' => '/people',
        'events' => '/events',
        'all' => '/groups',
    ];

    $target = $targets[$type] ?? '/groups';

    if ($q !== '') {
        $target .= '?' . http_build_query(['q' => $q]);
    }

    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $target]);
}
```

- [ ] **Step 3: Run tests to confirm nothing broke**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add src/Provider/CommunityServiceProvider.php src/Controller/HomeController.php
git commit -m "feat: add /explore redirect route for homepage search form"
```

---

### Task 2: Expand HomeController data assembly

**Files:**
- Modify: `src/Controller/HomeController.php`

**Important notes for implementer:**
- The existing `loadUpcomingEvents()` filters by `status=1` and checks `copyright_status` — preserve these filters in the new methods.
- The existing code sorts events by `'date'` field but the entity schema defines `starts_at`. Check which field contains data and use the correct one. The spec uses `starts_at`.
- Add `use Minoo\Support\GeoDistance;` import at the top of the file.

- [ ] **Step 1: Add helper methods for tab data**

Add these private methods to `HomeController`:

```php
private function loadUpcomingEventsFiltered(int $limit): array
{
    $storage = $this->entityTypeManager->getStorage('event');
    $now = date('Y-m-d\TH:i:s');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->condition('starts_at', $now, '>=')
        ->sort('starts_at', 'ASC')
        ->range(0, $limit)
        ->execute();

    if ($ids === []) {
        return [];
    }

    $events = array_values($storage->loadMultiple($ids));

    // Preserve existing copyright filter
    return array_values(array_filter($events, function ($entity) {
        $mediaId = $entity->get('media_id');
        if ($mediaId === null || $mediaId === '') {
            return true;
        }
        $status = $entity->get('copyright_status');
        return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
    }));
}

private function loadPublicPeople(int $limit): array
{
    $storage = $this->entityTypeManager->getStorage('resource_person');
    $ids = $storage->getQuery()
        ->condition('consent_public', 1)
        ->condition('status', 1)
        ->range(0, $limit)
        ->execute();

    return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
}

private function loadGroups(int $limit): array
{
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->range(0, $limit)
        ->execute();

    return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
}

private function sortByProximity(array $entities, string $communityField, float $lat, float $lon, array $communityCoords): array
{
    usort($entities, function ($a, $b) use ($communityField, $lat, $lon, $communityCoords) {
        $distA = $this->entityDistance($a, $communityField, $lat, $lon, $communityCoords);
        $distB = $this->entityDistance($b, $communityField, $lat, $lon, $communityCoords);
        return $distA <=> $distB;
    });

    return $entities;
}

private function entityDistance(mixed $entity, string $communityField, float $lat, float $lon, array $communityCoords): float
{
    if ($communityField === 'community') {
        // People have a string community field — look up by name
        $name = $entity->get('community');
        $coords = $name !== null ? ($communityCoords['name:' . $name] ?? null) : null;
    } else {
        // Groups/events have community_id
        $cid = $entity->get($communityField);
        $coords = $cid !== null ? ($communityCoords[(int)$cid] ?? null) : null;
    }

    if ($coords === null) {
        return PHP_FLOAT_MAX; // Unknown community sorts last
    }

    return GeoDistance::haversine($lat, $lon, $coords['lat'], $coords['lon']);
}

/** @return array<string|int, array{lat: float, lon: float}> */
private function buildCommunityCoords(array $communities): array
{
    $coords = [];
    foreach ($communities as $c) {
        $cLat = $c->get('latitude');
        $cLon = $c->get('longitude');
        if ($cLat !== null && $cLon !== null) {
            $coords[(int)$c->id()] = ['lat' => (float)$cLat, 'lon' => (float)$cLon];
            $name = $c->get('name');
            if ($name !== null) {
                $coords['name:' . $name] = ['lat' => (float)$cLat, 'lon' => (float)$cLon];
            }
        }
    }
    return $coords;
}

private function buildNearbyMixed(float $lat, float $lon, array $communityCoords): array
{
    $groups = $this->sortByProximity($this->loadGroups(3), 'community_id', $lat, $lon, $communityCoords);
    $events = $this->sortByProximity($this->loadUpcomingEventsFiltered(3), 'community_id', $lat, $lon, $communityCoords);
    $people = $this->sortByProximity($this->loadPublicPeople(3), 'community', $lat, $lon, $communityCoords);

    // Tag each with type
    $taggedGroups = array_map(fn($e) => ['entity' => $e, 'type' => 'group'], $groups);
    $taggedEvents = array_map(fn($e) => ['entity' => $e, 'type' => 'event'], $events);
    $taggedPeople = array_map(fn($e) => ['entity' => $e, 'type' => 'person'], $people);

    // Round-robin interleave: 1 group, 1 event, 1 person, repeat until 6 or exhausted
    $result = [];
    $sources = [$taggedGroups, $taggedEvents, $taggedPeople];
    while (count($result) < 6) {
        $added = false;
        foreach ($sources as &$source) {
            if ($source !== []) {
                $result[] = array_shift($source);
                $added = true;
                if (count($result) >= 6) break 2;
            }
        }
        unset($source);
        if (!$added) break;
    }

    return $result;
}
```

- [ ] **Step 2: Rewrite the index method**

Replace the existing `index()` method:

```php
public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $config = $this->loadLocationConfig();
    $service = new LocationService($this->entityTypeManager, $config);
    $location = $service->fromRequest($request);

    $templateVars = [
        'path' => '/',
        'account' => $account,
        'location' => $location,
    ];

    if ($location->hasLocation()) {
        $service->storeInSession($location);
        $service->setCookie($location);

        $lat = $location->latitude ?? 0.0;
        $lon = $location->longitude ?? 0.0;

        $communities = $this->loadAllCommunities();
        $communityCoords = $this->buildCommunityCoords($communities);

        $finder = new CommunityFinder();
        $templateVars['nearby_communities'] = $finder->findNearby($lat, $lon, $communities, limit: 6);
        $templateVars['nearby_mixed'] = $this->buildNearbyMixed($lat, $lon, $communityCoords);
        $templateVars['tab_events'] = $this->sortByProximity(
            $this->loadUpcomingEventsFiltered(6), 'community_id', $lat, $lon, $communityCoords
        );
        $templateVars['tab_people'] = $this->sortByProximity(
            $this->loadPublicPeople(6), 'community', $lat, $lon, $communityCoords
        );
        $templateVars['tab_groups'] = $this->sortByProximity(
            $this->loadGroups(6), 'community_id', $lat, $lon, $communityCoords
        );
    } else {
        $templateVars['nearby_communities'] = [];
        $templateVars['nearby_mixed'] = [];
        $templateVars['tab_events'] = $this->loadUpcomingEventsFiltered(6);
        $templateVars['tab_people'] = $this->loadPublicPeople(6);
        $templateVars['tab_groups'] = $this->loadGroups(6);
    }

    $html = $this->twig->render('page.html.twig', $templateVars);
    return new SsrResponse(content: $html);
}
```

Note: `loadAllCommunities()` is only called inside the `hasLocation()` branch — no unnecessary DB query when location is unknown.

- [ ] **Step 3: Remove old loadUpcomingEvents method**

Delete the old `loadUpcomingEvents()` method (the one that sorts by `'date'`) — it's replaced by `loadUpcomingEventsFiltered()`.

- [ ] **Step 4: Run tests to confirm nothing broke**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Controller/HomeController.php
git commit -m "feat: expand HomeController with tab data assembly and proximity sorting"
```

---

## Chunk 2: Templates & CSS

### Task 3: Create homepage-card Twig component

**Files:**
- Create: `templates/components/homepage-card.html.twig`

- [ ] **Step 1: Create the unified card component**

```twig
<article class="homepage-card homepage-card--{{ type }}">
  <span class="homepage-badge">{{ type_label }}</span>
  <h3 class="homepage-title"><a href="{{ url }}">{{ title }}</a></h3>
  <p class="homepage-meta">{{ meta }}</p>
</article>
```

- [ ] **Step 2: Commit**

```bash
git add templates/components/homepage-card.html.twig
git commit -m "feat: add homepage-card unified Twig component"
```

---

### Task 4: Rewrite page.html.twig

**Files:**
- Modify: `templates/page.html.twig`

**Important:** All links must use `lang_url()` helper (not bare paths) to be consistent with the rest of the codebase. Example: `{{ lang_url('/groups/' ~ slug) }}` not `href="/groups/{{ slug }}"`.

- [ ] **Step 1: Replace the entire `{% block content %}` section**

Keep the `{% extends "base.html.twig" %}` and `{% block title %}` intact. Replace `{% block content %}` with:

```twig
{% block content %}
  {# === Compact Hero === #}
  <section class="homepage-hero">
    <div class="homepage-hero-inner">
      <div class="homepage-hero-text">
        <h1 class="homepage-hero-tagline">{{ trans('page.title') }}</h1>
        {% if location is defined and location.hasLocation() %}
          <p class="homepage-hero-location">{{ trans('page.nearby_heading', {community: location.communityName}) }}</p>
        {% else %}
          <p class="homepage-hero-location">{{ trans('page.explore_north_shore') }}</p>
        {% endif %}
      </div>
      <form class="homepage-hero-search" action="{{ lang_url('/explore') }}" method="get" role="search" aria-label="{{ trans('page.search_label') }}">
        <label for="hero-type" class="visually-hidden">{{ trans('page.search_type') }}</label>
        <select id="hero-type" name="type" class="homepage-hero-select">
          <option value="all">{{ trans('page.search_all') }}</option>
          <option value="businesses">{{ trans('page.search_businesses') }}</option>
          <option value="people">{{ trans('page.search_people') }}</option>
          <option value="events">{{ trans('page.search_events') }}</option>
        </select>
        <label for="hero-q" class="visually-hidden">{{ trans('page.search_query') }}</label>
        <input id="hero-q" name="q" type="search" class="homepage-hero-input" placeholder="{{ trans('page.search_placeholder') }}" />
        <button type="submit" class="homepage-hero-submit">{{ trans('page.search_button') }}</button>
      </form>
    </div>
  </section>

  {# === Sticky Tab Row === #}
  <nav class="homepage-tabs" role="tablist" aria-label="{{ trans('page.tabs_label') }}">
    <a href="{{ lang_url('/') }}" role="tab" data-tab="nearby" aria-selected="true" class="homepage-tab active">{{ trans('page.tab_nearby') }}</a>
    <a href="{{ lang_url('/events') }}" role="tab" data-tab="events" aria-selected="false" class="homepage-tab">{{ trans('page.tab_events') }}</a>
    <a href="{{ lang_url('/people') }}" role="tab" data-tab="people" aria-selected="false" class="homepage-tab">{{ trans('page.tab_people') }}</a>
    <a href="{{ lang_url('/groups') }}" role="tab" data-tab="groups" aria-selected="false" class="homepage-tab">{{ trans('page.tab_groups') }}</a>
  </nav>

  {# === Tab Panels === #}
  <div role="tabpanel" data-panel="nearby" id="panel-nearby">
    {% if nearby_mixed is defined and nearby_mixed|length > 0 %}
      <div class="homepage-grid">
        {% for item in nearby_mixed %}
          {% if item.type == 'group' %}
            {% include "components/homepage-card.html.twig" with {
              type: 'group',
              type_label: trans('page.type_business'),
              title: item.entity.get('name'),
              url: lang_url('/groups/' ~ item.entity.get('slug')),
              meta: (item.entity.get('description')|default('')|length > 60 ? item.entity.get('description')|slice(0, 60) ~ '…' : item.entity.get('description')|default(''))
            } only %}
          {% elseif item.type == 'event' %}
            {% include "components/homepage-card.html.twig" with {
              type: 'event',
              type_label: trans('page.type_event'),
              title: item.entity.get('title'),
              url: lang_url('/events/' ~ item.entity.get('slug')),
              meta: (item.entity.get('starts_at') ? item.entity.get('starts_at')|date('F j, Y') : '') ~ (item.entity.get('location') ? ' · ' ~ item.entity.get('location') : '')
            } only %}
          {% elseif item.type == 'person' %}
            {% include "components/homepage-card.html.twig" with {
              type: 'person',
              type_label: trans('page.type_person'),
              title: item.entity.get('name'),
              url: lang_url('/people/' ~ item.entity.get('slug')),
              meta: item.entity.get('community')|default('')
            } only %}
          {% endif %}
        {% endfor %}
      </div>
    {% else %}
      <p class="homepage-empty">{{ trans('page.empty_state') }}</p>
    {% endif %}
  </div>

  <div role="tabpanel" data-panel="events" id="panel-events" hidden>
    {% if tab_events is defined and tab_events|length > 0 %}
      <div class="homepage-grid">
        {% for e in tab_events %}
          {% include "components/homepage-card.html.twig" with {
            type: 'event',
            type_label: trans('page.type_event'),
            title: e.get('title'),
            url: lang_url('/events/' ~ e.get('slug')),
            meta: (e.get('starts_at') ? e.get('starts_at')|date('F j, Y') : '') ~ (e.get('location') ? ' · ' ~ e.get('location') : '')
          } only %}
        {% endfor %}
      </div>
    {% else %}
      <p class="homepage-empty">{{ trans('page.empty_state') }}</p>
    {% endif %}
  </div>

  <div role="tabpanel" data-panel="people" id="panel-people" hidden>
    {% if tab_people is defined and tab_people|length > 0 %}
      <div class="homepage-grid">
        {% for p in tab_people %}
          {% include "components/homepage-card.html.twig" with {
            type: 'person',
            type_label: trans('page.type_person'),
            title: p.get('name'),
            url: lang_url('/people/' ~ p.get('slug')),
            meta: p.get('community')|default('')
          } only %}
        {% endfor %}
      </div>
    {% else %}
      <p class="homepage-empty">{{ trans('page.empty_state') }}</p>
    {% endif %}
  </div>

  <div role="tabpanel" data-panel="groups" id="panel-groups" hidden>
    {% if tab_groups is defined and tab_groups|length > 0 %}
      <div class="homepage-grid">
        {% for g in tab_groups %}
          {% include "components/homepage-card.html.twig" with {
            type: 'group',
            type_label: trans('page.type_business'),
            title: g.get('name'),
            url: lang_url('/groups/' ~ g.get('slug')),
            meta: (g.get('description')|default('')|length > 60 ? g.get('description')|slice(0, 60) ~ '…' : g.get('description')|default(''))
          } only %}
        {% endfor %}
      </div>
    {% else %}
      <p class="homepage-empty">{{ trans('page.empty_state') }}</p>
    {% endif %}
  </div>

  {# === Communities Row === #}
  {% if nearby_communities is defined and nearby_communities|length > 0 %}
    <section class="homepage-communities">
      <h2>{{ trans('page.communities_heading') }}</h2>
      <div class="homepage-pills">
        {% for item in nearby_communities %}
          <a href="{{ lang_url('/communities/' ~ item.community.get('slug')) }}" class="homepage-pill">{{ item.community.get('name') }}</a>
        {% endfor %}
      </div>
    </section>
  {% else %}
    <section class="homepage-communities">
      <h2>{{ trans('page.communities_heading') }}</h2>
      <div class="homepage-pills">
        <a href="{{ lang_url('/communities/sagamok-anishnawbek') }}" class="homepage-pill">Sagamok Anishnawbek</a>
        <a href="{{ lang_url('/communities/espanola') }}" class="homepage-pill">Espanola</a>
        <a href="{{ lang_url('/communities/elliot-lake') }}" class="homepage-pill">Elliot Lake</a>
        <a href="{{ lang_url('/communities/spanish') }}" class="homepage-pill">Spanish</a>
        <a href="{{ lang_url('/communities/massey') }}" class="homepage-pill">Massey</a>
      </div>
    </section>
  {% endif %}

  {# === What is Minoo === #}
  {% if location is not defined or not location.hasLocation() or (nearby_mixed is defined and nearby_mixed|length == 0) %}
    <section class="homepage-about">
      <h2>{{ trans('page.about_heading') }}</h2>
      <p>{{ trans('page.about_body') }}</p>
      <a href="{{ lang_url('/how-it-works') }}" class="btn btn--secondary">{{ trans('page.about_cta') }}</a>
    </section>
  {% else %}
    <section class="homepage-about homepage-about--compact">
      <p>{{ trans('page.about_compact') }} <a href="{{ lang_url('/how-it-works') }}">{{ trans('page.about_cta') }}</a></p>
    </section>
  {% endif %}

  {# === Tab Switching (Progressive Enhancement) === #}
  <script>
  (function() {
    const tabs = document.querySelectorAll('.homepage-tab');
    const panels = document.querySelectorAll('[role="tabpanel"]');
    if (!tabs.length) return;

    function activate(tabId) {
      tabs.forEach(t => {
        const isActive = t.dataset.tab === tabId;
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        t.classList.toggle('active', isActive);
      });
      panels.forEach(p => {
        p.hidden = p.dataset.panel !== tabId;
      });
    }

    tabs.forEach(tab => {
      tab.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.tab;
        activate(id);
        history.replaceState(null, '', id === 'nearby' ? '/' : '#' + id);
      });
      tab.addEventListener('keydown', function(e) {
        const tabList = [...tabs];
        const idx = tabList.indexOf(this);
        let next = -1;
        if (e.key === 'ArrowRight') next = (idx + 1) % tabList.length;
        if (e.key === 'ArrowLeft') next = (idx - 1 + tabList.length) % tabList.length;
        if (next >= 0) {
          e.preventDefault();
          tabList[next].focus();
          tabList[next].click();
        }
      });
    });

    const hash = location.hash.slice(1);
    if (hash && ['events', 'people', 'groups'].includes(hash)) {
      activate(hash);
    }
  })();
  </script>
{% endblock %}
```

- [ ] **Step 2: Verify the template renders locally**

Run: `php -S localhost:8081 -t public` and visit `http://localhost:8081/` — should render without errors.

- [ ] **Step 3: Commit**

```bash
git add templates/page.html.twig
git commit -m "feat: rewrite homepage with hero, tabs, cards, communities, and about sections"
```

---

### Task 5: Add homepage CSS to minoo.css

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add homepage component styles inside `@layer components`**

Find the `@layer components` section in `minoo.css` and add the following block. Place it after existing component styles:

```css
/* ── Homepage ── */

.homepage-hero {
  background: linear-gradient(135deg, var(--color-forest-700), oklch(0.25 0.06 155));
  color: #fff;
  padding-block: var(--space-md);
  padding-inline: var(--gutter);
}

.homepage-hero-inner {
  max-inline-size: var(--content-max, 80rem);
  margin-inline: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
}

.homepage-hero-tagline {
  font-family: var(--font-heading);
  font-size: var(--text-xl);
  margin: 0;
  color: #fff;
}

.homepage-hero-location {
  color: oklch(0.80 0.06 155);
  font-size: var(--text-sm);
  margin: 0;
  margin-block-start: var(--space-3xs);
}

.homepage-hero-search {
  display: flex;
  gap: var(--space-3xs);
  align-items: center;
}

.homepage-hero-select {
  background: oklch(1 0 0 / 0.15);
  border: 1px solid oklch(1 0 0 / 0.2);
  color: #fff;
  padding: var(--space-3xs) var(--space-2xs);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
}

.homepage-hero-input {
  background: oklch(1 0 0 / 0.15);
  border: 1px solid oklch(1 0 0 / 0.2);
  color: #fff;
  padding: var(--space-3xs) var(--space-xs);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  min-inline-size: 16rem;

  &::placeholder {
    color: oklch(1 0 0 / 0.5);
  }
}

.homepage-hero-submit {
  background: var(--color-forest-500);
  color: #fff;
  border: none;
  padding: var(--space-3xs) var(--space-sm);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  cursor: pointer;

  &:hover {
    background: var(--color-forest-700);
  }
}

.homepage-tabs {
  display: flex;
  gap: 0;
  background: var(--color-earth-50);
  border-block-end: 1px solid var(--color-earth-200);
  position: sticky;
  inset-block-start: 0;
  z-index: 10;
  padding-inline: var(--gutter);
  overflow-x: auto;
}

.homepage-tab {
  padding: var(--space-xs) var(--space-sm);
  color: var(--color-earth-700);
  text-decoration: none;
  font-size: var(--text-sm);
  white-space: nowrap;
  border-block-end: 2px solid transparent;

  &.active,
  &[aria-selected="true"] {
    color: var(--color-earth-900);
    border-block-end-color: var(--color-forest-500);
    font-weight: 600;
  }

  &:hover {
    color: var(--color-earth-900);
  }
}

[role="tabpanel"] {
  padding-block: var(--space-md);
  padding-inline: var(--gutter);
  max-inline-size: var(--content-max, 80rem);
  margin-inline: auto;
}

.homepage-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
  gap: var(--space-sm);
}

.homepage-card {
  background: var(--surface);
  border: 1px solid var(--color-earth-200);
  border-inline-start: 3px solid var(--color-earth-200);
  border-radius: var(--radius-sm);
  padding: var(--space-sm);

  &.homepage-card--group {
    border-inline-start-color: var(--color-forest-500);
  }

  &.homepage-card--event {
    border-inline-start-color: var(--color-water-600);
  }

  &.homepage-card--person {
    border-inline-start-color: var(--domain-people);
  }
}

.homepage-badge {
  font-size: var(--text-xs, 0.75rem);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;

  .homepage-card--group & { color: var(--color-forest-500); }
  .homepage-card--event & { color: var(--color-water-600); }
  .homepage-card--person & { color: var(--domain-people); }
}

.homepage-title {
  font-size: var(--text-base);
  margin-block: var(--space-3xs) 0;

  & a {
    color: var(--color-earth-900);
    text-decoration: none;

    &:hover { text-decoration: underline; }
  }
}

.homepage-meta {
  font-size: var(--text-sm);
  color: var(--color-earth-700);
  margin-block: var(--space-3xs) 0;
}

.homepage-empty {
  color: var(--color-earth-700);
  font-style: italic;
  padding-block: var(--space-lg);
  text-align: center;
}

.homepage-communities {
  padding-block: var(--space-md);
  padding-inline: var(--gutter);
  max-inline-size: var(--content-max, 80rem);
  margin-inline: auto;

  & h2 {
    font-size: var(--text-base);
    margin-block-end: var(--space-xs);
  }
}

.homepage-pills {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2xs);
}

.homepage-pill {
  background: var(--color-earth-100);
  color: var(--color-earth-700);
  padding: var(--space-3xs) var(--space-sm);
  border-radius: var(--radius-lg);
  text-decoration: none;
  font-size: var(--text-sm);

  &:hover {
    background: var(--color-earth-200);
    color: var(--color-earth-900);
  }
}

.homepage-about {
  background: var(--color-earth-100);
  padding-block: var(--space-lg);
  padding-inline: var(--gutter);
  text-align: center;

  & h2 { margin-block-end: var(--space-xs); }
  & p { max-inline-size: 40rem; margin-inline: auto; }
  & .btn { margin-block-start: var(--space-sm); }
}

.homepage-about--compact {
  padding-block: var(--space-sm);
  font-size: var(--text-sm);
  color: var(--color-earth-700);
}

@media (max-width: 40em) {
  .homepage-hero-inner {
    flex-direction: column;
    text-align: center;
  }

  .homepage-hero-search {
    flex-direction: column;
    inline-size: 100%;

    & input { inline-size: 100%; min-inline-size: 0; }
  }
}
```

- [ ] **Step 2: Verify styles render correctly**

Run dev server and check homepage at desktop (1366x768) and mobile (375x812) widths.

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add homepage CSS components (hero, tabs, cards, pills, about)"
```

---

### Task 6: Add translation keys

**Files:**
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add new translation keys to `resources/lang/en.php`**

Add these keys to the existing array (in the `page.` section, after existing `page.*` keys):

```php
// Homepage redesign keys
'page.explore_north_shore' => 'Explore communities across the North Shore',
'page.search_label' => 'Search',
'page.search_type' => 'Search type',
'page.search_all' => 'All',
'page.search_businesses' => 'Businesses',
'page.search_people' => 'People',
'page.search_events' => 'Events',
'page.search_query' => 'Search',
'page.search_placeholder' => 'What are you looking for? e.g., beadwork, salon, powwow',
'page.search_button' => 'Explore',
'page.tabs_label' => 'Browse content',
'page.tab_nearby' => 'Nearby',
'page.tab_events' => 'Events',
'page.tab_people' => 'People',
'page.tab_groups' => 'Groups',
'page.type_business' => 'Business',
'page.type_event' => 'Event',
'page.type_person' => 'Person',
'page.empty_state' => "We're just getting started in this area. Know a business, event, or community leader we should include? Let us know.",
'page.communities_heading' => 'Communities',
'page.about_heading' => 'What is Minoo?',
'page.about_body' => "We're building a place where Indigenous communities, businesses, and Knowledge Keepers are visible and connected. Browse local businesses, find upcoming events, or connect with people in your area.",
'page.about_cta' => 'Learn more',
'page.about_compact' => 'Minoo connects Indigenous communities.',
```

Note: `page.nearby_heading` already exists as `'Near {community}'` — reuse it (the template already uses it).

- [ ] **Step 2: Remove unused translation keys**

Remove these keys that are no longer used by the new homepage:

- `page.communities_button`
- `page.people_button`
- `page.explore_heading`
- `page.communities_desc`
- `page.people_desc`
- `page.teachings_desc`
- `page.events_desc`
- `page.elder_support_desc`
- `page.who_for_heading`
- `page.who_for_intro`
- `page.audience.*` (all 10 audience keys)

Check first if any of these keys are used in other templates before removing.

- [ ] **Step 3: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add resources/lang/en.php
git commit -m "feat: add translation keys for homepage redesign"
```

---

## Chunk 3: Testing & Verification

### Task 7: Run full test suite and manual verification

- [ ] **Step 1: Clear manifest and run all tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 2: Run dev server and manually verify**

```bash
php -S localhost:8081 -t public
```

Check:
1. Homepage loads at `/` without errors
2. Hero shows tagline + search form
3. Tabs switch content (Nearby, Events, People, Groups)
4. Arrow keys navigate between tabs
5. Hash deep linking works (`/#events`)
6. Cards link to correct detail pages (`/groups/slug`, `/events/slug`, `/people/slug`)
7. `/explore?type=businesses&q=test` redirects to `/groups?q=test`
8. `/explore?type=events` redirects to `/events`
9. `/explore` (no params) redirects to `/groups`
10. Communities pills link correctly
11. Mobile layout stacks hero and collapses grid
12. "What is Minoo" section appears when no location set
13. No personal contact info shown for `consent_public: false` people

- [ ] **Step 3: Run Playwright if available**

```bash
npx playwright test
```

Expected: Existing tests still pass. New homepage structure may require updating existing homepage Playwright tests if any assert on the old structure.

- [ ] **Step 4: Final commit if any fixes needed**
