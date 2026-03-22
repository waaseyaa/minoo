# Map Entity Type Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add entity type filter toggles to the interactive community map so users can show/hide communities and businesses as separate Leaflet layers with domain-colored markers.

**Architecture:** Extend `CommunityController::list()` to also load businesses with address-level coordinates. Refactor `atlas-discovery.js` from a single `L.markerClusterGroup` to per-type cluster layers with an Alpine.js `filters` object. Add pill-style toggle buttons above the map.

**Tech Stack:** PHP 8.4, Leaflet.js 1.9.4, leaflet.markercluster, Alpine.js, Twig 3, vanilla CSS

**Spec:** `docs/superpowers/specs/2026-03-21-map-entity-filter-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `src/Controller/CommunityController.php` | Modify | Load businesses, serialize to JSON, pass to template |
| `public/js/atlas-discovery.js` | Modify | Per-type layers, filter state, toggleFilter, split updateMapMarkers |
| `templates/communities/list.html.twig` | Modify | Filter bar, business data script tag |
| `public/css/minoo.css` | Modify | Atlas filter pill CSS in `@layer components` |

---

### Task 1: Load businesses in CommunityController

**Files:**
- Modify: `src/Controller/CommunityController.php:27-88`

- [ ] **Step 1: Add business loading after community serialization**

In `CommunityController::list()`, after the `$communitiesJson` loop (after line 74), add:

```php
        // Serialize businesses with address-level coordinates for map overlay
        $businessStorage = $this->entityTypeManager->getStorage('group');
        $businessIds = $businessStorage->getQuery()
            ->condition('status', 1)
            ->condition('group_type', 'business')
            ->execute();
        $businesses = $businessIds !== [] ? array_values($businessStorage->loadMultiple($businessIds)) : [];

        $businessesJson = [];
        foreach ($businesses as $business) {
            $lat = $business->get('latitude');
            $lng = $business->get('longitude');
            $source = $business->get('coordinate_source');
            if ($lat === null || $lng === null || $source !== 'address') {
                continue;
            }
            $businessesJson[] = [
                'id' => $business->id(),
                'name' => $business->get('name'),
                'slug' => $business->get('slug'),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'community_name' => $business->get('community_name') ?? '',
                'type' => 'business',
            ];
        }
```

- [ ] **Step 2: Pass businesses_json to template**

In the `$this->twig->render()` call (line 81), add `businesses_json`:

```php
        $html = $this->twig->render('communities/list.html.twig', [
            'path' => '/communities',
            'communities_json' => json_encode($communitiesJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            'location_json' => json_encode($locationJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            'businesses_json' => json_encode($businessesJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        ]);
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: 569 pass, 3 skipped (no regressions).

- [ ] **Step 4: Write integration test for business data in controller response**

Check existing integration tests for the controller pattern. Add a test that boots the kernel, calls the `/communities` route, and asserts the rendered HTML contains `window.__atlas_businesses`. If the existing integration test infrastructure doesn't support this easily, add a note in the commit and move on — the Playwright test in Task 5 covers this end-to-end.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/CommunityController.php
git commit -m "feat(#340): load businesses with address coordinates in CommunityController"
```

---

### Task 2: Refactor atlas-discovery.js for per-type layers

**Files:**
- Modify: `public/js/atlas-discovery.js`

- [ ] **Step 1: Add business data and filter state to Alpine data**

After `markerMap: {},` (line 29), add:

```js
    // Businesses
    allBusinesses: [],
    businessCount: 0,

    // Entity type filters
    filters: {
      communities: true,
      businesses: true
    },
```

- [ ] **Step 2: Load business data in init()**

In `init()`, after `this.allCommunities = window.__atlas_communities || [];` (line 36), add:

```js
      this.allBusinesses = window.__atlas_businesses || [];
      this.businessCount = this.allBusinesses.length;
```

- [ ] **Step 3: Refactor initMap() to use per-type layers**

Replace the marker cluster group setup in `initMap()` (lines 227-229):

```js
      // OLD:
      // this.markers = L.markerClusterGroup();
      // this.map.addLayer(this.markers);

      // NEW: Per-type cluster layers
      this.communityLayer = L.markerClusterGroup();
      this.businessLayer = L.markerClusterGroup({ maxClusterRadius: 40 });
      this.map.addLayer(this.communityLayer);
      this.map.addLayer(this.businessLayer);

      // Keep backward compat for markerMap highlight
      this.markers = this.communityLayer;
```

- [ ] **Step 4: Replace updateMapMarkers() with split functions**

Replace `updateMapMarkers()` (lines 240-257) with:

```js
    updateMapMarkers() {
      this.updateCommunityMarkers();
      this.updateBusinessMarkers();
    },

    updateCommunityMarkers() {
      if (!this.communityLayer) return;
      this.communityLayer.clearLayers();
      this.markerMap = {};
      var self = this;
      this.filteredCommunities.forEach(function(c) {
        if (!c.lat || !c.lng) return;
        var marker = L.marker([c.lat, c.lng])
          .bindPopup('<strong>' + c.name + '</strong><br>' +
            (c.is_municipality ? 'Municipality' : 'First Nation') +
            (c.nation ? ' · ' + c.nation : ''));
        marker.on('click', function() {
          window.location.href = '/communities/' + c.slug;
        });
        self.communityLayer.addLayer(marker);
        self.markerMap[c.id] = marker;
      });
    },

    updateBusinessMarkers() {
      if (!this.businessLayer) return;
      this.businessLayer.clearLayers();
      var self = this;
      this.allBusinesses.forEach(function(b) {
        if (!b.lat || !b.lng) return;
        var marker = L.circleMarker([b.lat, b.lng], {
          radius: 6,
          fillColor: '#b0643c',
          color: '#b0643c',
          fillOpacity: 0.8,
          weight: 1
        }).bindPopup('<strong>' + b.name + '</strong>' +
          (b.community_name ? '<br>' + b.community_name : ''));
        marker.on('click', function() {
          window.location.href = '/businesses/' + b.slug;
        });
        self.businessLayer.addLayer(marker);
      });
    },
```

- [ ] **Step 5: Add toggleFilter() method**

Add before the `highlightMarker()` method (before line 272):

```js
    toggleFilter(type) {
      this.filters[type] = !this.filters[type];
      if (type === 'communities') {
        if (this.filters.communities) {
          this.map.addLayer(this.communityLayer);
        } else {
          this.map.removeLayer(this.communityLayer);
        }
      } else if (type === 'businesses') {
        if (this.filters.businesses) {
          this.map.addLayer(this.businessLayer);
        } else {
          this.map.removeLayer(this.businessLayer);
        }
      }
    },
```

- [ ] **Step 6: Verify manually**

Open `http://localhost:8081/communities`. Verify:
- Community markers render as before (icon-based `L.marker`)
- Business markers render as colored circles (`L.circleMarker`)
- Both layers cluster independently

- [ ] **Step 7: Commit**

```bash
git add public/js/atlas-discovery.js
git commit -m "feat(#340): refactor atlas map to per-type cluster layers with filter toggle"
```

---

### Task 3: Add filter controls to template

**Files:**
- Modify: `templates/communities/list.html.twig`

- [ ] **Step 1: Add filter bar above the map**

After the community header `</div>` (after line 19) and before the map div (line 22), add:

```twig
  {# --- Entity type filters --- #}
  <div class="atlas-filters" role="group" aria-label="Map entity type filters">
    <button class="atlas-filter atlas-filter--communities"
            :class="{ 'is-active': filters.communities }"
            :aria-pressed="String(filters.communities)"
            @click="toggleFilter('communities')"
            type="button">
      {{ trans('communities.filter_communities') }}
    </button>
    <button class="atlas-filter atlas-filter--businesses"
            :class="{ 'is-active': filters.businesses }"
            :aria-pressed="String(filters.businesses)"
            @click="toggleFilter('businesses')"
            x-show="businessCount > 0"
            type="button">
      {{ trans('communities.filter_businesses') }}
    </button>
  </div>
```

- [ ] **Step 2: Add business data script tag**

In the `{% block scripts %}` section, after `window.__atlas_location` (line 170), add:

```html
  window.__atlas_businesses = {{ businesses_json|raw }};
```

- [ ] **Step 3: Verify manually**

Open `http://localhost:8081/communities`. Verify:
- Two filter pill buttons appear above the map
- Both show as active (filled with domain color) by default
- Clicking toggles markers on/off without page reload
- Business pill hidden if no businesses have coordinates

- [ ] **Step 4: Commit**

```bash
git add templates/communities/list.html.twig
git commit -m "feat(#340): add entity type filter controls to atlas template"
```

---

### Task 4: Add filter pill CSS

**Files:**
- Modify: `public/css/minoo.css`

**Note:** The CSS tokens `--color-communities` (#6a9caf, line 96) and `--color-businesses` (oklch(0.65 0.18 25), line 99) already exist in `@layer tokens`. Verify they're present before proceeding. The JS marker hex `#b0643c` is the approximate hex equivalent of the oklch business token — these must be kept in sync manually.

- [ ] **Step 1: Add atlas filter styles**

Add in `@layer components`, near the existing `.community-map` styles (around line 3192):

```css
  /* ── Atlas entity filters ── */
  .atlas-filters {
    display: flex;
    gap: var(--space-2xs);
    padding-block: var(--space-xs);
    flex-wrap: wrap;
  }

  .atlas-filter {
    padding: var(--space-3xs) var(--space-sm);
    border: 1px solid var(--border-subtle);
    border-radius: 999px;
    background: none;
    color: var(--text-secondary);
    font-size: var(--text-sm);
    cursor: pointer;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
  }

  .atlas-filter:hover {
    border-color: var(--text-muted);
    color: var(--text-primary);
  }

  .atlas-filter.is-active {
    color: #fff;
    border-color: transparent;
  }

  .atlas-filter--communities.is-active {
    background: var(--color-communities);
  }

  .atlas-filter--businesses.is-active {
    background: var(--color-businesses);
  }
```

- [ ] **Step 2: Add light mode overrides**

Add inside `@layer components` near the other light mode overrides:

```css
  [data-theme="light"] .atlas-filter {
    border-color: var(--border-light);
  }

  [data-theme="light"] .atlas-filter:hover {
    border-color: var(--text-muted);
  }

  @media (prefers-color-scheme: light) {
    :root:not([data-theme="dark"]) .atlas-filter {
      border-color: var(--border-light);
    }
    :root:not([data-theme="dark"]) .atlas-filter:hover {
      border-color: var(--text-muted);
    }
  }
```

- [ ] **Step 3: Bump CSS version**

In `templates/base.html.twig`, bump `?v=11` to `?v=12`.

- [ ] **Step 4: Verify both themes**

Check filter pills render correctly in both dark and light mode.

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat(#340): atlas filter pill CSS with light mode support"
```

---

### Task 5: Playwright test

**Files:**
- Create: `tests/playwright/map-entity-filter.spec.ts`

- [ ] **Step 1: Write Playwright test**

```typescript
import { test, expect } from '@playwright/test';

test.describe('Map entity type filters', () => {
  test('filter buttons are visible on communities page', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('.atlas-filter--communities')).toBeVisible();
  });

  test('community filter toggles markers', async ({ page }) => {
    await page.goto('/communities');
    // Both active by default
    await expect(page.locator('.atlas-filter--communities.is-active')).toBeVisible();

    // Toggle off
    await page.click('.atlas-filter--communities');
    await expect(page.locator('.atlas-filter--communities.is-active')).not.toBeVisible();

    // Toggle back on
    await page.click('.atlas-filter--communities');
    await expect(page.locator('.atlas-filter--communities.is-active')).toBeVisible();
  });

  test('filter buttons have correct aria-pressed', async ({ page }) => {
    await page.goto('/communities');
    const btn = page.locator('.atlas-filter--communities');
    await expect(btn).toHaveAttribute('aria-pressed', 'true');

    await btn.click();
    await expect(btn).toHaveAttribute('aria-pressed', 'false');
  });
});
```

- [ ] **Step 2: Commit**

```bash
git add tests/playwright/map-entity-filter.spec.ts
git commit -m "test(#340): Playwright tests for map entity type filters"
```

---

## Verification

After all tasks:

```bash
./vendor/bin/phpunit                    # All PHP tests pass
php -S localhost:8081 -t public &       # Start dev server
# Open http://localhost:8081/communities
# Verify: filter pills visible, toggles work, business markers distinct color
# Verify: existing community filters (search, province, nation) still work
# Verify: light mode filter pills render correctly
```
