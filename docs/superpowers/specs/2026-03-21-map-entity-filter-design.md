# Map Entity Type Filter — Design Spec

**Date:** 2026-03-21
**Issue:** #340 (Interactive Community Map milestone)
**Status:** Approved

---

## Context

The interactive community map at `/communities` shows 637 First Nations as Leaflet markers with clustering. Issue #340 adds entity type filter controls so users can toggle visibility of different entity types on the map.

### Scope Decision

**v1 shows communities + businesses only.** Events, teachings, groups, and people are excluded because they lack their own coordinates — they only have `community_id`, which would stack markers at the same point as the parent community, creating noise. Businesses have address-level geocoded lat/lng, making them meaningful distinct map points. Other types will be added when they gain their own coordinates.

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Entity types in v1 | Communities + businesses | Only types with real lat/lng coordinates |
| Layer architecture | Separate `L.markerClusterGroup` per type | Clean separation, domain-colored clusters, scalable pattern |
| Data delivery | Page-load JSON (`window.__atlas_*`) | Matches existing community pattern, no new API endpoints |
| Community markers | Keep existing `L.marker` (icon-based) | Preserve current map appearance, no visual regression |
| Business markers | `L.circleMarker` with domain accent `fillColor` | Visually distinct from community icons, canvas-rendered |
| Color sync | Hex values hardcoded in JS must match CSS tokens | Leaflet canvas only accepts hex/rgb, not oklch — keep in sync manually |
| Filter UI | Pill toggle buttons above map | Accessible, touch-friendly, Alpine.js reactive |
| Existing filters | Province/nation/search apply to communities only | Businesses don't have those fields |

---

## Data Layer

### Controller: `CommunityController::index()`

Currently serializes communities into `window.__atlas_communities`. Add business loading:

1. Load all business entities from storage
2. Filter for non-null `latitude` and `longitude` AND `coordinate_source = 'address'` (exclude businesses that only have community-fallback coordinates, which would stack on top of the parent community marker)
3. Serialize into `window.__atlas_businesses`:

```js
window.__atlas_businesses = [
  { id: 1, name: "...", slug: "...", lat: 46.5, lng: -81.0, community_name: "...", type: "business" }
]
```

No new API endpoints. Both datasets are page-load JSON.

---

## JavaScript Layer

### `public/js/atlas-discovery.js`

**Current:** Single `L.markerClusterGroup` for communities.

**New:** Per-type cluster layers in Alpine.js component:

```js
this.layers = {
  communities: L.markerClusterGroup({ maxClusterRadius: 50 }),
  businesses: L.markerClusterGroup({ maxClusterRadius: 50 })
};
```

**Filter state:**
```js
filters: {
  communities: true,
  businesses: true
}
```

**`toggleFilter(type)` method:**
- Flip `this.filters[type]`
- If off: `this.map.removeLayer(this.layers[type])`
- If on: `this.map.addLayer(this.layers[type])`

**Marker creation:**
- Communities: Keep existing `L.marker` with current icon — no visual change. Click navigates to `/communities/{slug}`.
- Businesses: `L.circleMarker([lat, lng], { fillColor: '#b0643c', color: '#b0643c', radius: 6, fillOpacity: 0.8 })` — click navigates to `/businesses/{slug}` (oklch(0.65 0.18 25) ≈ #b0643c in hex for Leaflet canvas)

**Refactoring `updateMapMarkers()`:** The existing function clears and rebuilds the single cluster group. Split into:
- `updateCommunityMarkers()` — clears and rebuilds `this.layers.communities` from `filteredCommunities`. Called by existing `applyFilters()` pipeline (search, province, nation).
- `updateBusinessMarkers()` — clears and rebuilds `this.layers.businesses` from `window.__atlas_businesses`. Called once on init (businesses have no attribute filters in v1).

**Initialization:** On load, both layers populated and added to the map. If `window.__atlas_businesses` is empty or undefined, the businesses filter pill is hidden.

**Viewport sync:** `applyViewportFilter()` remains unchanged — continues filtering the sidebar community list only. Businesses are map-only (no sidebar list entry).

**Empty state:** If no businesses have address-level coordinates, the filter pill is hidden via `x-show="businessCount > 0"` on the button.

---

## Template Layer

### `templates/communities/list.html.twig`

Add filter bar above the map container:

```html
<div class="atlas-filters" role="group" aria-label="Map entity type filters">
  <button class="atlas-filter atlas-filter--communities"
          :class="{ 'is-active': filters.communities }"
          :aria-pressed="filters.communities.toString()"
          @click="toggleFilter('communities')">
    Communities
  </button>
  <button class="atlas-filter atlas-filter--businesses"
          :class="{ 'is-active': filters.businesses }"
          :aria-pressed="filters.businesses.toString()"
          @click="toggleFilter('businesses')">
    Businesses
  </button>
</div>
```

### Business data script tag

Add alongside existing community data injection:
```html
<script>window.__atlas_businesses = {{ businesses_json|raw }};</script>
```

---

## CSS Layer

### `public/css/minoo.css` — `@layer components`

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

**Light mode:** Add `[data-theme="light"]` variants — inactive pills use lighter borders, active pills keep domain colors with white text (sufficient contrast on both `--color-communities` and `--color-businesses`).

---

## Accessibility

- `role="group"` + `aria-label="Map entity type filters"` on container
- `aria-pressed` on each toggle button (via Alpine binding)
- Native `<button>` elements — keyboard navigable by default
- Visible `:focus-visible` indicators (inherited from reset layer)
- Touch-friendly sizing via `padding` + `flex-wrap` for mobile

---

## Files Changed

| File | Action |
|------|--------|
| `src/Controller/CommunityController.php` | Modify — load + serialize businesses |
| `public/js/atlas-discovery.js` | Modify — per-type layers, filter state, toggleFilter method |
| `templates/communities/list.html.twig` | Modify — filter bar, business data script |
| `public/css/minoo.css` | Modify — atlas filter pill CSS |

---

## Testing

- **PHPUnit:** Test that `CommunityController::index()` includes business data in template context
- **Playwright:** Navigate to `/communities`, verify filter buttons visible, toggle businesses off/on, verify marker count changes, verify community markers still work after toggle
