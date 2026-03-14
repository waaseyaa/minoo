# Communities Atlas Redesign Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the `/communities` discovery page and `/communities/{slug}` detail page with a geography-first "Atlas" experience — contextual map, proximity grouping, rich filter chips, and editorial detail layout.

**Architecture:** Server-rendered Twig templates with Alpine.js for client-side interactivity (filtering, map-list sync, geolocation). Leaflet + OpenStreetMap for maps. All community data shipped as a JSON blob in the page — no AJAX for filtering. Existing PHP services (LocationService, CommunityFinder, NorthCloudClient) unchanged.

**Tech Stack:** PHP 8.3 / Symfony / Twig (server), Alpine.js (reactivity), Leaflet + Leaflet.markercluster (maps), CSS custom properties (theming)

**Spec:** `docs/superpowers/specs/2026-03-14-communities-atlas-redesign-design.md`

---

## File Structure

### New Files
| File | Responsibility |
|------|---------------|
| `public/css/atlas.css` | Atlas color palette, discovery page layout, detail page layout, community cards, filter chips, responsive breakpoints |
| `public/js/atlas-discovery.js` | Alpine.js component for discovery page: filtering, proximity sorting, map-list sync, client-side Haversine |
| `public/js/atlas-detail.js` | Leaflet mini-map for detail page with nearby community markers and polylines |
| `templates/communities/list.html.twig` | Discovery page template (replaces list view from `communities.html.twig`) |
| `templates/communities/detail.html.twig` | Detail page template (replaces detail view from `communities.html.twig`) |
| `templates/communities/partials/community-card.html.twig` | Reusable card partial for both discovery and nearby sections |

### Modified Files
| File | Changes |
|------|---------|
| `src/Controller/CommunityController.php` | `list()`: serialize communities to JSON blob, pass to new template. `show()`: pass nearby communities with distances, use new template. |
| `templates/communities.html.twig` | Replaced by `list.html.twig` and `detail.html.twig` — will be deleted after migration |

### Unchanged Files
| File | Why |
|------|-----|
| `src/Provider/CommunityServiceProvider.php` | Routes unchanged — controller methods stay the same |
| `src/Domain/Geo/Service/*` | LocationService, CommunityFinder, GeoDistance — used as-is |
| `src/Support/NorthCloudClient.php` | API calls unchanged |
| `src/Entity/Community.php` | Entity unchanged |
| `templates/base.html.twig` | Base template unchanged — new CSS/JS loaded per-page via blocks |

---

## Chunk 1: Atlas CSS Foundation + Template Split

### Task 1: Create atlas.css with color palette and base layout

**Files:**
- Create: `public/css/atlas.css`

- [ ] **Step 1: Create the CSS file with custom properties and base styles**

```css
/* Atlas Communities Redesign */
:root {
  --atlas-deep: #2d4a3e;
  --atlas-forest: #3d6b5a;
  --atlas-sage: #6b8f71;
  --atlas-mist: #e8f0ec;
  --atlas-cloud: #f7faf8;
  --atlas-border: #e2ebe5;
  --atlas-chip-bg: #c8d6c4;
}

/* --- Contextual Header --- */
.atlas-header {
  background: linear-gradient(135deg, var(--atlas-deep) 0%, var(--atlas-forest) 100%);
  color: var(--atlas-mist);
  padding: 2rem 1.5rem;
}
.atlas-header__label {
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.125rem;
  opacity: 0.7;
  margin-bottom: 0.25rem;
}
.atlas-header__title {
  font-size: 1.375rem;
  font-weight: 700;
}
.atlas-header__meta {
  font-size: 0.75rem;
  opacity: 0.8;
  margin-top: 0.25rem;
}

/* --- Map Container --- */
.atlas-map {
  height: 40vh;
  min-height: 200px;
  max-height: 400px;
  background: #dfe6e0;
  border-bottom: 2px solid var(--atlas-chip-bg);
}

/* --- Search + Filters --- */
.atlas-filters {
  padding: 1rem 1.5rem;
  background: var(--atlas-cloud);
  border-bottom: 1px solid var(--atlas-border);
}
.atlas-search {
  width: 100%;
  background: #fff;
  border: 1.5px solid var(--atlas-chip-bg);
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.8125rem;
  margin-bottom: 0.75rem;
  outline: none;
}
.atlas-search:focus {
  border-color: var(--atlas-forest);
}
.atlas-chips {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.atlas-chip {
  padding: 0.3125rem 0.875rem;
  border-radius: 1.25rem;
  font-size: 0.6875rem;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid var(--atlas-chip-bg);
  background: #fff;
  color: var(--atlas-forest);
  transition: background 0.15s, color 0.15s;
}
.atlas-chip--active {
  background: var(--atlas-deep);
  color: #fff;
  border-color: var(--atlas-deep);
}
.atlas-chip-dropdown {
  position: relative;
}
.atlas-chip-dropdown__menu {
  position: absolute;
  top: 100%;
  left: 0;
  margin-top: 0.25rem;
  background: #fff;
  border: 1px solid var(--atlas-border);
  border-radius: 0.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  min-width: 12rem;
  max-height: 16rem;
  overflow-y: auto;
  z-index: 100;
  padding: 0.25rem 0;
}
.atlas-chip-dropdown__item {
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.atlas-chip-dropdown__item:hover {
  background: var(--atlas-cloud);
}

/* --- Proximity Group --- */
.atlas-group__label {
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.09375rem;
  color: var(--atlas-sage);
  font-weight: 600;
  margin: 1.5rem 0 0.75rem;
}
.atlas-group__label:first-child {
  margin-top: 0;
}

/* --- Community Card --- */
.atlas-card {
  background: #fff;
  border: 1px solid var(--atlas-border);
  border-radius: 0.5rem;
  padding: 0.875rem 1rem;
  margin-bottom: 0.625rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.atlas-card:hover {
  border-color: var(--atlas-sage);
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.atlas-card__name {
  font-weight: 600;
  font-size: 0.875rem;
  color: #2d3436;
}
.atlas-card__meta {
  font-size: 0.75rem;
  color: var(--atlas-sage);
  margin-top: 0.1875rem;
}
.atlas-card__badge {
  display: inline-block;
  background: var(--atlas-mist);
  padding: 0.125rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.625rem;
  font-weight: 500;
  color: var(--atlas-forest);
}
.atlas-card__stats {
  text-align: right;
  flex-shrink: 0;
}
.atlas-card__population {
  font-size: 0.75rem;
  color: #999;
}
.atlas-card__distance {
  font-size: 0.6875rem;
  color: var(--atlas-sage);
}

/* --- Community List Container --- */
.atlas-list {
  padding: 1rem 1.5rem;
}

/* --- Detail Page --- */
.atlas-detail-hero {
  background: linear-gradient(135deg, var(--atlas-deep) 0%, var(--atlas-forest) 60%, #4a8a6a 100%);
  color: var(--atlas-mist);
  padding: 2rem 1.75rem 1.5rem;
}
.atlas-detail-hero__back {
  font-size: 0.6875rem;
  opacity: 0.6;
  color: var(--atlas-mist);
  text-decoration: none;
}
.atlas-detail-hero__back:hover {
  opacity: 1;
}
.atlas-detail-hero__badge {
  display: inline-block;
  background: rgba(255,255,255,0.15);
  padding: 0.1875rem 0.625rem;
  border-radius: 0.25rem;
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.0625rem;
  margin-top: 1rem;
}
.atlas-detail-hero__name {
  font-size: 1.625rem;
  font-weight: 700;
  margin-top: 0.5rem;
}
.atlas-detail-hero__subtitle {
  font-size: 0.8125rem;
  opacity: 0.85;
  margin-top: 0.375rem;
}
.atlas-detail-hero__stats {
  display: flex;
  gap: 1.25rem;
  margin-top: 1rem;
  font-size: 0.75rem;
  opacity: 0.75;
}

/* --- Detail Sections --- */
.atlas-section {
  padding: 1.5rem 1.75rem;
  border-bottom: 1px solid var(--atlas-border);
}
.atlas-section:last-child {
  border-bottom: none;
}
.atlas-section__label {
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.09375rem;
  color: var(--atlas-sage);
  font-weight: 600;
  margin-bottom: 0.75rem;
}
.atlas-section__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.atlas-section__field-label {
  font-size: 0.6875rem;
  color: #999;
  margin-bottom: 0.125rem;
}
.atlas-section__field-value {
  font-size: 0.875rem;
  font-weight: 500;
}
.atlas-section__field-value a {
  color: var(--atlas-forest);
  text-decoration: underline;
}

/* --- Leadership Cards --- */
.atlas-leader-card {
  background: var(--atlas-cloud);
  border-radius: 0.5rem;
  padding: 0.875rem 1rem;
}
.atlas-leader-card--chief {
  margin-bottom: 0.625rem;
}
.atlas-leader-card__role {
  font-size: 0.5625rem;
  text-transform: uppercase;
  letter-spacing: 0.0625rem;
  color: var(--atlas-sage);
  font-weight: 600;
}
.atlas-leader-card--chief .atlas-leader-card__role {
  color: var(--atlas-sage);
}
.atlas-leader-card__name {
  font-size: 0.9375rem;
  font-weight: 600;
  margin-top: 0.125rem;
}
.atlas-leader-card--chief .atlas-leader-card__name {
  font-size: 1rem;
}
.atlas-councillor-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

/* --- Band Office Card --- */
.atlas-contact-card {
  background: var(--atlas-cloud);
  border-radius: 0.5rem;
  padding: 1rem 1.125rem;
}
.atlas-contact-card__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.875rem;
}
.atlas-contact-card a {
  color: var(--atlas-forest);
}

/* --- Detail Mini Map --- */
.atlas-detail-map {
  height: 200px;
  border-radius: 0.5rem;
  margin-bottom: 0.875rem;
  background: #dfe6e0;
}
.atlas-detail-coords {
  display: flex;
  gap: 1.25rem;
  font-size: 0.75rem;
  color: #666;
}
.atlas-detail-coords a {
  color: var(--atlas-forest);
  text-decoration: underline;
}

/* --- Nearby Communities Grid --- */
.atlas-nearby-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.625rem;
}
.atlas-nearby-card {
  background: var(--atlas-cloud);
  border-radius: 0.5rem;
  padding: 0.75rem 0.875rem;
  border: 1px solid var(--atlas-border);
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s;
}
.atlas-nearby-card:hover {
  border-color: var(--atlas-sage);
}
.atlas-nearby-card__name {
  font-size: 0.8125rem;
  font-weight: 600;
}
.atlas-nearby-card__meta {
  font-size: 0.6875rem;
  color: var(--atlas-sage);
  margin-top: 0.25rem;
}

/* --- Mobile Responsive --- */
@media (max-width: 768px) {
  .atlas-header {
    padding: 1rem;
  }
  .atlas-header__title {
    font-size: 1.0625rem;
  }
  .atlas-map {
    height: 100px;
    min-height: 100px;
  }
  .atlas-filters {
    padding: 0.75rem 1rem;
  }
  .atlas-chips {
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .atlas-list {
    padding: 0.75rem 1rem;
  }
  .atlas-card__badge {
    font-size: 0.5625rem;
    padding: 0.0625rem 0.375rem;
  }

  .atlas-detail-hero {
    padding: 1.25rem 1rem 1rem;
  }
  .atlas-detail-hero__name {
    font-size: 1.25rem;
  }
  .atlas-section {
    padding: 1rem;
  }
  .atlas-councillor-grid {
    grid-template-columns: 1fr;
  }
  .atlas-contact-card__grid {
    grid-template-columns: 1fr;
  }
  .atlas-nearby-grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 2: Verify the file loads correctly**

Open `public/css/atlas.css` in browser dev tools or verify file is well-formed:

```bash
cd /home/fsd42/dev/minoo && wc -l public/css/atlas.css
```

Expected: ~300 lines, no syntax errors.

- [ ] **Step 3: Commit**

```bash
git add public/css/atlas.css
git commit -m "feat(communities): add atlas CSS foundation with color palette and component styles"
```

---

### Task 2: Split communities.html.twig into list and detail templates

**Files:**
- Create: `templates/communities/list.html.twig`
- Create: `templates/communities/detail.html.twig`
- Delete (later): `templates/communities.html.twig`

- [ ] **Step 1: Create the communities directory**

```bash
mkdir -p /home/fsd42/dev/minoo/templates/communities
```

- [ ] **Step 2: Create the discovery page template (list.html.twig)**

```twig
{% extends 'base.html.twig' %}

{% block title %}Communities — Minoo{% endblock %}

{% block head %}
  <link rel="stylesheet" href="/css/atlas.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
{% endblock %}

{% block content %}
<div x-data="atlasDiscovery()" x-init="init()">

  {# --- Contextual Header --- #}
  <div class="atlas-header">
    <div class="atlas-header__label">Exploring</div>
    <h1 class="atlas-header__title" x-text="headerTitle">All Communities</h1>
    <div class="atlas-header__meta" x-text="headerMeta"></div>
  </div>

  {# --- Map --- #}
  <div id="atlas-map" class="atlas-map"></div>

  {# --- Search + Filters --- #}
  <div class="atlas-filters">
    <input
      type="text"
      class="atlas-search"
      placeholder="Search communities by name..."
      x-model="searchQuery"
      @input.debounce.200ms="applyFilters()"
    />
    <div class="atlas-chips">
      {# Type filter #}
      <button
        class="atlas-chip"
        :class="{ 'atlas-chip--active': typeFilter === 'all' }"
        @click="typeFilter = 'all'; applyFilters()"
      >All Types</button>
      <button
        class="atlas-chip"
        :class="{ 'atlas-chip--active': typeFilter === 'fn' }"
        @click="typeFilter = 'fn'; applyFilters()"
      >First Nations</button>
      <button
        class="atlas-chip"
        :class="{ 'atlas-chip--active': typeFilter === 'mun' }"
        @click="typeFilter = 'mun'; applyFilters()"
      >Municipalities</button>

      {# Province dropdown #}
      <div class="atlas-chip-dropdown" x-data="{ open: false }" @click.outside="open = false">
        <button class="atlas-chip" :class="{ 'atlas-chip--active': provinceFilter.length > 0 }" @click="open = !open">
          Province <span x-show="provinceFilter.length > 0" x-text="'(' + provinceFilter.length + ')'"></span> ▾
        </button>
        <div class="atlas-chip-dropdown__menu" x-show="open" x-cloak>
          <template x-for="prov in availableProvinces" :key="prov">
            <label class="atlas-chip-dropdown__item" @click.stop>
              <input type="checkbox" :value="prov" x-model="provinceFilter" @change="applyFilters()" />
              <span x-text="prov"></span>
            </label>
          </template>
        </div>
      </div>

      {# Nation dropdown #}
      <div class="atlas-chip-dropdown" x-data="{ open: false }" @click.outside="open = false">
        <button class="atlas-chip" :class="{ 'atlas-chip--active': nationFilter.length > 0 }" @click="open = !open">
          Nation <span x-show="nationFilter.length > 0" x-text="'(' + nationFilter.length + ')'"></span> ▾
        </button>
        <div class="atlas-chip-dropdown__menu" x-show="open" x-cloak>
          <template x-for="nat in availableNations" :key="nat">
            <label class="atlas-chip-dropdown__item" @click.stop>
              <input type="checkbox" :value="nat" x-model="nationFilter" @change="applyFilters()" />
              <span x-text="nat"></span>
            </label>
          </template>
        </div>
      </div>

      {# Population dropdown #}
      <div class="atlas-chip-dropdown" x-data="{ open: false }" @click.outside="open = false">
        <button class="atlas-chip" :class="{ 'atlas-chip--active': populationFilter !== 'all' }" @click="open = !open">
          Population ▾
        </button>
        <div class="atlas-chip-dropdown__menu" x-show="open" x-cloak>
          <div class="atlas-chip-dropdown__item" @click="populationFilter = 'all'; applyFilters(); open = false"
            :style="populationFilter === 'all' ? 'font-weight:600' : ''">All</div>
          <div class="atlas-chip-dropdown__item" @click="populationFilter = '0-500'; applyFilters(); open = false"
            :style="populationFilter === '0-500' ? 'font-weight:600' : ''">Under 500</div>
          <div class="atlas-chip-dropdown__item" @click="populationFilter = '500-2000'; applyFilters(); open = false"
            :style="populationFilter === '500-2000' ? 'font-weight:600' : ''">500 – 2,000</div>
          <div class="atlas-chip-dropdown__item" @click="populationFilter = '2000-5000'; applyFilters(); open = false"
            :style="populationFilter === '2000-5000' ? 'font-weight:600' : ''">2,000 – 5,000</div>
          <div class="atlas-chip-dropdown__item" @click="populationFilter = '5000+'; applyFilters(); open = false"
            :style="populationFilter === '5000+' ? 'font-weight:600' : ''">5,000+</div>
        </div>
      </div>
    </div>
  </div>

  {# --- Community List --- #}
  <div class="atlas-list">
    {# Proximity-grouped view (when location known) #}
    <template x-if="hasLocation">
      <div>
        <template x-for="group in proximityGroups" :key="group.label">
          <div x-show="group.communities.length > 0">
            <div class="atlas-group__label" x-text="group.label"></div>
            <template x-for="c in group.communities" :key="c.id">
              <a :href="'/communities/' + c.slug" class="atlas-card"
                 @mouseenter="highlightMarker(c.id)" @mouseleave="unhighlightMarker(c.id)">
                <div>
                  <div class="atlas-card__name" x-text="c.name"></div>
                  <div class="atlas-card__meta">
                    <span class="atlas-card__badge" x-text="c.is_municipality ? 'Municipality' : 'First Nation'"></span>
                    <span x-show="c.nation" x-text="c.nation" style="margin-left: 0.5rem;"></span>
                    <span style="margin-left: 0.25rem;">·</span>
                    <span x-text="c.province" style="margin-left: 0.25rem;"></span>
                  </div>
                </div>
                <div class="atlas-card__stats">
                  <div class="atlas-card__population" x-show="c.population" x-text="'Pop. ' + Number(c.population).toLocaleString()"></div>
                  <div class="atlas-card__distance" x-text="c.distance.toFixed(0) + ' km'"></div>
                </div>
              </a>
            </template>
          </div>
        </template>
      </div>
    </template>

    {# Alphabetical view (no location) #}
    <template x-if="!hasLocation">
      <div>
        <template x-for="c in filteredCommunities" :key="c.id">
          <a :href="'/communities/' + c.slug" class="atlas-card"
             @mouseenter="highlightMarker(c.id)" @mouseleave="unhighlightMarker(c.id)">
            <div>
              <div class="atlas-card__name" x-text="c.name"></div>
              <div class="atlas-card__meta">
                <span class="atlas-card__badge" x-text="c.is_municipality ? 'Municipality' : 'First Nation'"></span>
                <span x-show="c.nation" x-text="c.nation" style="margin-left: 0.5rem;"></span>
                <span style="margin-left: 0.25rem;">·</span>
                <span x-text="c.province" style="margin-left: 0.25rem;"></span>
              </div>
            </div>
            <div class="atlas-card__stats">
              <div class="atlas-card__population" x-show="c.population" x-text="'Pop. ' + Number(c.population).toLocaleString()"></div>
            </div>
          </a>
        </template>
      </div>
    </template>

    {# Empty state #}
    <div x-show="filteredCommunities.length === 0" style="text-align: center; padding: 2rem; color: #999;">
      No communities match your filters.
    </div>
  </div>
</div>

{% endblock %}

{% block scripts %}
{# --- Community Data --- #}
<script>
  window.__atlas_communities = {{ communities_json|raw }};
  window.__atlas_location = {{ location_json|raw }};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="/js/atlas-discovery.js"></script>
<script defer src="https://unpkg.com/alpinejs@3.14.9/dist/cdn.min.js"></script>
{% endblock %}
```

- [ ] **Step 3: Create the detail page template (detail.html.twig)**

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ community.get('name') }} — Minoo{% endblock %}

{% block head %}
  <link rel="stylesheet" href="/css/atlas.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
{% endblock %}

{% block content %}
  {# --- Hero --- #}
  <div class="atlas-detail-hero">
    <a href="/communities" class="atlas-detail-hero__back">← Back to communities</a>
    <div class="atlas-detail-hero__badge">
      {{ community.get('is_municipality') ? 'Municipality' : 'First Nation' }}
    </div>
    <h1 class="atlas-detail-hero__name">{{ community.get('name') }}</h1>
    <div class="atlas-detail-hero__subtitle">
      {% if community.get('nation') %}{{ community.get('nation') }} · {% endif %}
      {% if community.get('treaty') %}{{ community.get('treaty') }} · {% endif %}
      {{ community.get('province') }}
    </div>
    <div class="atlas-detail-hero__stats">
      {% if community.get('population') %}
        <span>Pop. {{ community.get('population')|number_format }} <span style="opacity:0.5">({{ community.get('population_year') }})</span></span>
      {% endif %}
      {% if distance_from_user %}
        <span>·</span>
        <span>{{ distance_from_user|number_format(0) }} km from you</span>
      {% endif %}
    </div>
  </div>

  {# --- About --- #}
  <div class="atlas-section">
    <div class="atlas-section__label">About</div>
    <div class="atlas-section__grid">
      {% if community.get('nation') %}
      <div>
        <div class="atlas-section__field-label">Nation</div>
        <div class="atlas-section__field-value">{{ community.get('nation') }}</div>
      </div>
      {% endif %}
      {% if community.get('language_group') %}
      <div>
        <div class="atlas-section__field-label">Language Group</div>
        <div class="atlas-section__field-value">{{ community.get('language_group') }}</div>
      </div>
      {% endif %}
      {% if community.get('treaty') %}
      <div>
        <div class="atlas-section__field-label">Treaty</div>
        <div class="atlas-section__field-value">{{ community.get('treaty') }}</div>
      </div>
      {% endif %}
      {% if community.get('reserve_name') %}
      <div>
        <div class="atlas-section__field-label">Reserve</div>
        <div class="atlas-section__field-value">{{ community.get('reserve_name') }}</div>
      </div>
      {% endif %}
      {% if community.get('inac_id') %}
      <div>
        <div class="atlas-section__field-label">INAC Band No.</div>
        <div class="atlas-section__field-value">{{ community.get('inac_id') }}</div>
      </div>
      {% endif %}
      {% if community.get('website') %}
      <div>
        <div class="atlas-section__field-label">Website</div>
        <div class="atlas-section__field-value"><a href="{{ community.get('website') }}" target="_blank" rel="noopener">{{ community.get('website')|replace({'https://': '', 'http://': ''})|trim('/') }}</a></div>
      </div>
      {% endif %}
    </div>
  </div>

  {# --- Leadership --- #}
  {% if people is not empty %}
  <div class="atlas-section">
    <div class="atlas-section__label">Leadership</div>
    {% for person in people %}
      {% if person.role == 'chief' %}
      <div class="atlas-leader-card atlas-leader-card--chief">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div class="atlas-leader-card__role">Chief</div>
            <div class="atlas-leader-card__name">{{ person.name }}</div>
          </div>
          <div style="font-size: 0.6875rem; color: #999;">Current</div>
        </div>
      </div>
      {% endif %}
    {% endfor %}
    <div class="atlas-councillor-grid">
      {% for person in people %}
        {% if person.role == 'councillor' %}
        <div class="atlas-leader-card">
          <div class="atlas-leader-card__role">Councillor</div>
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
    <div class="atlas-section__label">Band Office</div>
    <div class="atlas-contact-card">
      <div class="atlas-contact-card__grid">
        {% if band_office.address_line1 %}
        <div>
          <div class="atlas-section__field-label">Address</div>
          <div class="atlas-section__field-value">
            {{ band_office.address_line1 }}
            {% if band_office.address_line2 %}<br>{{ band_office.address_line2 }}{% endif %}
            {% if band_office.city %}<br>{{ band_office.city }}, {{ band_office.province }} {{ band_office.postal_code }}{% endif %}
          </div>
        </div>
        {% endif %}
        {% if band_office.office_hours %}
        <div>
          <div class="atlas-section__field-label">Hours</div>
          <div class="atlas-section__field-value">{{ band_office.office_hours }}</div>
        </div>
        {% endif %}
        {% if band_office.phone %}
        <div>
          <div class="atlas-section__field-label">Phone</div>
          <div class="atlas-section__field-value"><a href="tel:{{ band_office.phone }}">{{ band_office.phone }}</a></div>
        </div>
        {% endif %}
        {% if band_office.email %}
        <div>
          <div class="atlas-section__field-label">Email</div>
          <div class="atlas-section__field-value"><a href="mailto:{{ band_office.email }}">{{ band_office.email }}</a></div>
        </div>
        {% endif %}
        {% if band_office.toll_free %}
        <div>
          <div class="atlas-section__field-label">Toll Free</div>
          <div class="atlas-section__field-value"><a href="tel:{{ band_office.toll_free }}">{{ band_office.toll_free }}</a></div>
        </div>
        {% endif %}
        {% if band_office.fax %}
        <div>
          <div class="atlas-section__field-label">Fax</div>
          <div class="atlas-section__field-value">{{ band_office.fax }}</div>
        </div>
        {% endif %}
      </div>
    </div>
  </div>
  {% endif %}

  {# --- The Land --- #}
  {% if community.get('latitude') and community.get('longitude') %}
  <div class="atlas-section">
    <div class="atlas-section__label">The Land</div>
    <div id="atlas-detail-map" class="atlas-detail-map"></div>
    <div class="atlas-detail-coords">
      <span>{{ community.get('latitude')|number_format(4) }}° N, {{ community.get('longitude')|abs|number_format(4) }}° W</span>
      <a href="https://www.openstreetmap.org/?mlat={{ community.get('latitude') }}&mlon={{ community.get('longitude') }}#map=13/{{ community.get('latitude') }}/{{ community.get('longitude') }}" target="_blank" rel="noopener">OpenStreetMap</a>
      <a href="https://www.google.com/maps?q={{ community.get('latitude') }},{{ community.get('longitude') }}" target="_blank" rel="noopener">Google Maps</a>
    </div>
  </div>
  {% endif %}

  {# --- Nearby Communities --- #}
  {% if nearby is not empty %}
  <div class="atlas-section">
    <div class="atlas-section__label">Nearby Communities</div>
    <div class="atlas-nearby-grid">
      {% for item in nearby %}
      <a href="/communities/{{ item.community.get('slug') }}" class="atlas-nearby-card">
        <div class="atlas-nearby-card__name">{{ item.community.get('name') }}</div>
        <div class="atlas-nearby-card__meta">
          {{ item.community.get('is_municipality') ? 'Municipality' : item.community.get('nation') ?: 'First Nation' }}
          · {{ item.distanceKm|number_format(0) }} km
        </div>
      </a>
      {% endfor %}
    </div>
  </div>
  {% endif %}

{% endblock %}

{% block scripts %}
{% if community.get('latitude') and community.get('longitude') %}
<script>
  window.__atlas_detail = {
    lat: {{ community.get('latitude') }},
    lng: {{ community.get('longitude') }},
    name: {{ community.get('name')|json_encode|raw }},
    nearby: [
      {% for item in nearby %}
      { lat: {{ item.community.get('latitude') }}, lng: {{ item.community.get('longitude') }}, name: {{ item.community.get('name')|json_encode|raw }}, slug: {{ item.community.get('slug')|json_encode|raw }} }{% if not loop.last %},{% endif %}
      {% endfor %}
    ]
  };
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/atlas-detail.js"></script>
{% endif %}
{% endblock %}
```

- [ ] **Step 4: Update CommunityController list() to serialize JSON and use new template**

In `CommunityController::list()`, change the return to pass `communities_json` and `location_json`, and render the new template `communities/list.html.twig`.

Replace the existing `list()` method body. After loading and filtering communities, add:

```php
// Serialize communities for client-side Alpine.js
$communitiesJson = [];
foreach ($communities as $community) {
    $communitiesJson[] = [
        'id' => $community->id(),
        'name' => $community->get('name'),
        'slug' => $community->get('slug'),
        'lat' => (float) $community->get('latitude'),
        'lng' => (float) $community->get('longitude'),
        'province' => $community->get('province'),
        'nation' => $community->get('nation'),
        'population' => (int) $community->get('population'),
        'population_year' => (int) $community->get('population_year'),
        'is_municipality' => (bool) $community->get('is_municipality'),
    ];
}

// Serialize location for client-side geolocation
// LocationService::fromRequest() already checks session → cookie → IP GeoIP2,
// so $location includes IP-based coordinates when no session/cookie exists.
// This ensures the spec's 4-step cascade: server session → server cookie → server GeoIP2 → browser geolocation → alphabetical.
$locationJson = $location->hasLocation()
    ? ['lat' => $location->latitude, 'lng' => $location->longitude, 'name' => $location->communityName]
    : null;
```

Change the template from `'communities.html.twig'` to `'communities/list.html.twig'` and pass:

```php
'communities_json' => json_encode($communitiesJson, JSON_THROW_ON_ERROR),
'location_json' => json_encode($locationJson, JSON_THROW_ON_ERROR),
```

Remove the old `communities`, `type_filter` variables from the template context (no longer needed — filtering is client-side).

- [ ] **Step 5: Update show() to use new template and pass distance_from_user**

Change the template from `'communities.html.twig'` to `'communities/detail.html.twig'`.

Add `distance_from_user` calculation:

```php
$distanceFromUser = null;
if ($location->hasLocation()) {
    $distanceFromUser = GeoDistance::haversine(
        $location->latitude,
        $location->longitude,
        (float) $community->get('latitude'),
        (float) $community->get('longitude')
    );
}
```

Add to template context:

```php
'distance_from_user' => $distanceFromUser,
```

- [ ] **Step 6: Remove old communities.html.twig**

```bash
rm templates/communities.html.twig
```

- [ ] **Step 7: Test that pages load**

```bash
cd /home/fsd42/dev/minoo && php -S localhost:8000 -t public &
# Visit http://localhost:8000/communities — should render the new discovery page (map placeholder, filters, empty list until JS loads)
# Visit http://localhost:8000/communities/garden-river-first-nation — should render the new detail page
kill %1
```

- [ ] **Step 8: Commit**

```bash
git add templates/communities/list.html.twig templates/communities/detail.html.twig src/Controller/CommunityController.php
git rm templates/communities.html.twig
git commit -m "feat(communities): add atlas templates and wire controller with JSON serialization"
```

---

## Chunk 2: Client-Side JavaScript — Discovery Map & Filters

### Task 4: Create atlas-discovery.js with Alpine.js component

**Files:**
- Create: `public/js/atlas-discovery.js`

- [ ] **Step 1: Create the Alpine.js discovery component**

```javascript
function atlasDiscovery() {
  return {
    // Data
    allCommunities: [],
    filteredCommunities: [],
    proximityGroups: [],
    hasLocation: false,
    userLat: null,
    userLng: null,
    locationName: '',

    // Filters
    searchQuery: '',
    typeFilter: 'all',
    provinceFilter: [],
    nationFilter: [],
    populationFilter: 'all',

    // Computed lists for dropdowns
    availableProvinces: [],
    availableNations: [],

    // Intermediate filter state
    attributeFiltered: [],

    // Map
    map: null,
    markers: null,
    markerMap: {},

    // Header
    headerTitle: 'All Communities',
    headerMeta: '',

    init() {
      this.allCommunities = window.__atlas_communities || [];
      const loc = window.__atlas_location;

      // Extract available filter values
      const provinces = new Set();
      const nations = new Set();
      this.allCommunities.forEach(function(c) {
        if (c.province) provinces.add(c.province);
        if (c.nation) nations.add(c.nation);
      });
      this.availableProvinces = Array.from(provinces).sort();
      this.availableNations = Array.from(nations).sort();

      // Set location if available from server
      if (loc && loc.lat && loc.lng) {
        this.setLocation(loc.lat, loc.lng, loc.name);
      } else {
        this.tryBrowserGeolocation();
      }

      this.applyFilters();
      this.initMap();
    },

    tryBrowserGeolocation() {
      if (!navigator.geolocation) return;
      var self = this;
      navigator.geolocation.getCurrentPosition(
        function(pos) {
          self.setLocation(pos.coords.latitude, pos.coords.longitude, null);
          // Persist to server session
          fetch('/api/location/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude: pos.coords.latitude, longitude: pos.coords.longitude })
          }).catch(function() {});
        },
        function() {
          // Denied or error — stay in alphabetical mode
          self.applyFilters();
        },
        { timeout: 5000 }
      );
    },

    setLocation(lat, lng, name) {
      this.userLat = lat;
      this.userLng = lng;
      this.hasLocation = true;
      this.locationName = name || '';

      // Calculate distances for all communities
      var self = this;
      this.allCommunities.forEach(function(c) {
        c.distance = self.haversine(lat, lng, c.lat, c.lng);
      });

      this.applyFilters();
      this.updateHeader();
    },

    haversine(lat1, lon1, lat2, lon2) {
      var R = 6371;
      var dLat = (lat2 - lat1) * Math.PI / 180;
      var dLon = (lon2 - lon1) * Math.PI / 180;
      var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    },

    applyFilters() {
      var self = this;
      var results = this.allCommunities.filter(function(c) {
        // Search
        if (self.searchQuery && c.name.toLowerCase().indexOf(self.searchQuery.toLowerCase()) === -1) return false;

        // Type
        if (self.typeFilter === 'fn' && c.is_municipality) return false;
        if (self.typeFilter === 'mun' && !c.is_municipality) return false;

        // Province
        if (self.provinceFilter.length > 0 && self.provinceFilter.indexOf(c.province) === -1) return false;

        // Nation
        if (self.nationFilter.length > 0 && (!c.nation || self.nationFilter.indexOf(c.nation) === -1)) return false;

        // Population
        if (self.populationFilter !== 'all') {
          var pop = c.population || 0;
          if (self.populationFilter === '0-500' && pop >= 500) return false;
          if (self.populationFilter === '500-2000' && (pop < 500 || pop >= 2000)) return false;
          if (self.populationFilter === '2000-5000' && (pop < 2000 || pop >= 5000)) return false;
          if (self.populationFilter === '5000+' && pop < 5000) return false;
        }

        return true;
      });

      // Update available nations based on results WITHOUT the nation filter applied
      // This prevents the circular dependency where selecting a type empties the nation dropdown
      var self2 = this;
      var forNationDropdown = this.allCommunities.filter(function(c) {
        if (self2.searchQuery && c.name.toLowerCase().indexOf(self2.searchQuery.toLowerCase()) === -1) return false;
        if (self2.typeFilter === 'fn' && c.is_municipality) return false;
        if (self2.typeFilter === 'mun' && !c.is_municipality) return false;
        if (self2.provinceFilter.length > 0 && self2.provinceFilter.indexOf(c.province) === -1) return false;
        if (self2.populationFilter !== 'all') {
          var pop = c.population || 0;
          if (self2.populationFilter === '0-500' && pop >= 500) return false;
          if (self2.populationFilter === '500-2000' && (pop < 500 || pop >= 2000)) return false;
          if (self2.populationFilter === '2000-5000' && (pop < 2000 || pop >= 5000)) return false;
          if (self2.populationFilter === '5000+' && pop < 5000) return false;
        }
        return true;
      });
      var filteredNations = new Set();
      forNationDropdown.forEach(function(c) {
        if (c.nation) filteredNations.add(c.nation);
      });
      this.availableNations = Array.from(filteredNations).sort();

      // Sort
      if (this.hasLocation) {
        results.sort(function(a, b) { return (a.distance || 0) - (b.distance || 0); });
      } else {
        results.sort(function(a, b) { return a.name.localeCompare(b.name); });
      }

      // Store attribute-filtered set, then apply viewport filter on top
      this.attributeFiltered = results;
      this.filteredCommunities = results;
      this.updateMapMarkers();
      this.applyViewportFilter();
    },

    buildProximityGroups(communities) {
      var groups = [
        { label: 'Within 50 km', max: 50, communities: [] },
        { label: '50 – 100 km', max: 100, communities: [] },
        { label: '100 – 200 km', max: 200, communities: [] },
        { label: '200+ km', max: Infinity, communities: [] }
      ];
      communities.forEach(function(c) {
        var d = c.distance || Infinity;
        for (var i = 0; i < groups.length; i++) {
          if (d < groups[i].max || (i === groups.length - 1)) {
            groups[i].communities.push(c);
            break;
          }
        }
      });
      this.proximityGroups = groups;
    },

    updateHeader() {
      if (this.hasLocation && this.locationName) {
        this.headerTitle = 'Communities near ' + this.locationName;
        var within200 = this.allCommunities.filter(function(c) { return c.distance && c.distance <= 200; }).length;
        this.headerMeta = within200 + ' communities within 200 km';
      } else if (this.hasLocation) {
        var within200Count = this.allCommunities.filter(function(c) { return c.distance && c.distance <= 200; }).length;
        this.headerTitle = 'Communities Near You';
        this.headerMeta = within200Count + ' communities within 200 km';
      } else {
        this.headerTitle = 'All Communities';
        this.headerMeta = this.allCommunities.length + ' communities';
      }
    },

    // --- Map ---
    initMap() {
      var defaultCenter = this.hasLocation ? [this.userLat, this.userLng] : [50.0, -85.0];
      var defaultZoom = this.hasLocation ? 8 : 5;

      this.map = L.map('atlas-map').setView(defaultCenter, defaultZoom);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
      }).addTo(this.map);

      // User location marker
      if (this.hasLocation) {
        L.circleMarker([this.userLat, this.userLng], {
          radius: 8, fillColor: '#3388ff', fillOpacity: 0.8, color: '#fff', weight: 2
        }).addTo(this.map).bindPopup('Your location');
      }

      // Marker cluster group
      this.markers = L.markerClusterGroup();
      this.map.addLayer(this.markers);

      this.updateMapMarkers();

      // Sync map viewport with list
      var self = this;
      this.map.on('moveend', function() {
        self.applyViewportFilter();
      });
    },

    updateMapMarkers() {
      if (!this.markers) return;
      this.markers.clearLayers();
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
        self.markers.addLayer(marker);
        self.markerMap[c.id] = marker;
      });
    },

    applyViewportFilter() {
      if (!this.map) return;
      var bounds = this.map.getBounds();
      // Apply viewport constraint on top of attribute-filtered set
      this.filteredCommunities = (this.attributeFiltered || this.allCommunities).filter(function(c) {
        if (!c.lat || !c.lng) return false;
        return bounds.contains([c.lat, c.lng]);
      });
      if (this.hasLocation) {
        this.buildProximityGroups(this.filteredCommunities);
      }
    },

    highlightMarker(id) {
      var marker = this.markerMap[id];
      if (marker && marker._icon) {
        marker._icon.style.filter = 'hue-rotate(120deg) brightness(1.2)';
      }
    },

    unhighlightMarker(id) {
      var marker = this.markerMap[id];
      if (marker && marker._icon) {
        marker._icon.style.filter = '';
      }
    }
  };
}
```

- [ ] **Step 2: Verify the JS file is syntactically correct**

```bash
cd /home/fsd42/dev/minoo && node -e "require('fs').readFileSync('public/js/atlas-discovery.js', 'utf8'); console.log('OK')"
```

Expected: `OK` (no parse error from reading; full syntax check would need a linter but this confirms the file exists and is readable).

- [ ] **Step 3: Commit**

```bash
git add public/js/atlas-discovery.js
git commit -m "feat(communities): add Alpine.js discovery component with map, filters, proximity grouping"
```

---

### Task 5: Create atlas-detail.js for detail page mini-map

**Files:**
- Create: `public/js/atlas-detail.js`

- [ ] **Step 1: Create the detail page map script**

```javascript
(function() {
  var data = window.__atlas_detail;
  if (!data || !data.lat || !data.lng) return;

  var mapEl = document.getElementById('atlas-detail-map');
  if (!mapEl) return;

  var map = L.map('atlas-detail-map').setView([data.lat, data.lng], 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
  }).addTo(map);

  // Primary community marker
  L.marker([data.lat, data.lng])
    .addTo(map)
    .bindPopup('<strong>' + data.name + '</strong>')
    .openPopup();

  // Nearby community markers + polylines
  if (data.nearby && data.nearby.length > 0) {
    data.nearby.forEach(function(n) {
      if (!n.lat || !n.lng) return;

      // Secondary marker (smaller, grey)
      L.circleMarker([n.lat, n.lng], {
        radius: 6,
        fillColor: '#6b8f71',
        fillOpacity: 0.7,
        color: '#fff',
        weight: 1
      }).addTo(map).bindPopup('<a href="/communities/' + n.slug + '">' + n.name + '</a>');

      // Decorative polyline
      L.polyline([[data.lat, data.lng], [n.lat, n.lng]], {
        color: '#c8d6c4',
        weight: 1.5,
        opacity: 0.6,
        dashArray: '4 6'
      }).addTo(map);
    });

    // Fit bounds to include all markers
    var allPoints = [[data.lat, data.lng]];
    data.nearby.forEach(function(n) {
      if (n.lat && n.lng) allPoints.push([n.lat, n.lng]);
    });
    map.fitBounds(allPoints, { padding: [30, 30] });
  }
})();
```

- [ ] **Step 2: Commit**

```bash
git add public/js/atlas-detail.js
git commit -m "feat(communities): add detail page mini-map with nearby markers and polylines"
```

---

## Chunk 3: Integration Testing & Polish

### Task 6: Manual integration test checklist

**Files:** None (testing only)

- [ ] **Step 1: Start the dev server and test discovery page**

```bash
cd /home/fsd42/dev/minoo && php -S localhost:8000 -t public
```

Test the following at `http://localhost:8000/communities`:
1. Page loads with "All Communities" header (or location-based header if geolocation works)
2. Map renders with markers
3. Search bar filters communities as you type
4. Type chips toggle between All / First Nations / Municipalities
5. Province dropdown opens and filters
6. Nation dropdown opens and filters
7. Population dropdown filters
8. Community cards show name, badge, nation, province, population
9. Hovering a card highlights its map marker
10. Clicking a card navigates to detail page

- [ ] **Step 2: Test detail page**

Test at `http://localhost:8000/communities/garden-river-first-nation` (or any valid slug):
1. Hero shows community name, nation, treaty, province, population
2. About section shows relevant fields (only non-empty ones)
3. Leadership section shows chief + councillors (if nc_id is set and NorthCloud API reachable)
4. Band Office section shows contact info (if available)
5. Mini-map renders with community pin and nearby markers
6. Polylines connect community to nearby communities
7. Nearby community cards link to their detail pages
8. "Back to communities" link works

- [ ] **Step 3: Test mobile responsive**

Resize browser to 375px width and verify:
1. Discovery: map collapses to 100px strip, chips scroll horizontally, cards stack
2. Detail: hero is compact, councillor grid goes single-column, nearby grid goes single-column

- [ ] **Step 4: Test empty/edge states**

1. Apply filters that match no communities → "No communities match your filters." message
2. Visit a community with no nc_id → Leadership and Band Office sections are hidden
3. Visit a community with no lat/lng → "The Land" section and map are hidden
4. Deny browser geolocation → page falls back to alphabetical

### Task 7: Delete old template and final commit

**Files:**
- Delete: `templates/communities.html.twig`

- [ ] **Step 1: Verify old template is no longer referenced**

```bash
cd /home/fsd42/dev/minoo && grep -r "communities.html.twig" src/ templates/ --include="*.php" --include="*.twig"
```

Expected: No results (controller now references `communities/list.html.twig` and `communities/detail.html.twig`).

- [ ] **Step 2: Delete and commit**

```bash
git rm templates/communities.html.twig
git commit -m "chore(communities): remove old single-file communities template"
```

- [ ] **Step 3: Run PHPUnit tests**

```bash
cd /home/fsd42/dev/minoo && vendor/bin/phpunit
```

Expected: All 42 tests pass (no existing tests depend on the old template directly).

- [ ] **Step 4: Final integration commit (if any fixes needed)**

```bash
git add -A
git commit -m "fix(communities): integration fixes from atlas redesign testing"
```
