# Communities Atlas Redesign

**Date:** 2026-03-14
**Status:** Draft
**Scope:** Discovery page + Detail page for `/communities` and `/communities/{slug}`

---

## Problem

The current communities page is a flat alphabetical list with a single filter (First Nations vs. Municipalities). It underutilizes rich geographic, cultural, and administrative data. Users exploring geographically — the primary use pattern — have no spatial context. The detail page is functional but bland, treating communities as data records rather than places.

## Audience

- **Residents / community members** — looking up their own or nearby communities for contact info, leadership, band office details
- **General public / curious visitors** — exploring and learning about First Nations and northern communities

## Design Direction: Atlas

A geographic storytelling approach — warm, editorial, place-centered. The map provides spatial orientation, the list provides depth. Communities are presented as places and peoples, not data points.

---

## Discovery Page (`/communities`)

### Layout (Top to Bottom)

1. **Contextual header** — Dark green gradient banner. Shows detected region name, province, and community count within radius. Falls back to "All Communities" with total count if no geolocation.

2. **Map** — Leaflet + OpenStreetMap terrain tiles. Clustered markers. User location pin if available. ~40% viewport height on desktop, ~100px collapsed strip on mobile with "Tap to expand" affordance.

3. **Search + Filter chips** — Prominent search bar with autocomplete. Below it, floating filter chips:
   - **Community type**: Toggle chips (All / First Nations / Municipalities)
   - **Province**: Dropdown multi-select
   - **Nation**: Dropdown multi-select (dynamically filtered to nations present in current results)
   - **Population**: Range presets (< 500, 500–2,000, 2,000–5,000, 5,000+)
   - All filters AND-combined. Update both list and map markers.

4. **Community list** — Grouped by proximity bands when geolocation is available:
   - Within 50 km
   - 50–100 km
   - 100–200 km
   - 200+ km

   Without geolocation: alphabetical, no proximity groups.

### Community Card

Each card shows:
- **Community name** (prominent, left-aligned)
- **Type badge** (pill: "First Nation" or "Municipality")
- **Nation + Province** (secondary text)
- **Population** (right-aligned)
- **Distance** (right-aligned, only when geolocation available)

Click → navigates to detail page.

### Map ↔ List Sync

- Pan/zoom the map → list filters to visible communities
- Hover a card → highlights corresponding map marker
- Click a map marker → scrolls list to that card
- Markers use Leaflet.markercluster for performance at 634+ communities

### Mobile Adaptations

- Map collapses to ~100px strip, expandable on tap
- Filter chips horizontally scroll
- Cards stack single-column with compact layout (abbreviated province, inline population + distance)

---

## Detail Page (`/communities/{slug}`)

### Layout (Top to Bottom)

1. **Hero** — Full-width dark green gradient. Contains:
   - Back link ("← Back to communities")
   - Type badge
   - Community name (large, bold)
   - Nation · Treaty · Province
   - Population (with year) · Distance from user

2. **About** — 2-column grid of labeled data points:
   - Nation, Language Group, Treaty, Reserve Name, INAC Band No., Website (linked)

3. **Leadership** — Fetched from NorthCloud API via existing `NorthCloudClient`:
   - Chief displayed in a prominent card (name + role + "Current" badge)
   - Councillors in a 2-column grid of smaller cards
   - Mobile: Chief card + councillors as comma-separated compact list

4. **Band Office** — Styled contact card with:
   - Address, Office Hours, Phone (tappable), Email (tappable), Fax, Toll-free
   - 2-column grid layout on desktop, stacked on mobile

5. **The Land** — Interactive Leaflet mini-map showing:
   - Community pin (primary)
   - Nearby community markers (secondary, with connecting polylines)
   - Coordinates displayed below (lat/long to 4 decimals)
   - Links to OpenStreetMap and Google Maps

6. **Nearby Communities** — 3-column grid of clickable cards:
   - Community name, nation/type, distance
   - Click → navigates to that community's detail page
   - Creates a browsing flow between communities

### Mobile Adaptations

- Hero compact (smaller text, no treaty in subtitle)
- About grid: 2 columns maintained
- Leadership: chief card + councillors inline
- Band office: stacked single column, phone/email tappable
- Nearby: single column cards

---

## Technical Architecture

### Client-Side Stack

- **Leaflet + OpenStreetMap** — Maps (free, no API key, lightweight)
- **Alpine.js** — Filter reactivity, search, map-list sync (~15KB gzipped)
- **Twig** — Server-rendered shell, unchanged

### Data Strategy

- Server renders all communities as a JSON blob in a `<script>` tag on the discovery page
- ~634 communities ≈ 50–80KB JSON — acceptable payload
- All filtering, sorting, and proximity grouping happens client-side via Alpine.js
- No AJAX needed for filtering — instant responsiveness

### Geolocation Flow

1. Check session/cookie for saved location (existing `LocationService`)
2. Try browser `navigator.geolocation` API (user permission prompt)
3. Fall back to IP geolocation (existing `GeoIP2`)
4. If all fail → "All Communities" alphabetical, no proximity grouping

### Search

- Client-side fuzzy match on the JSON blob for instant results
- Existing `/api/communities/autocomplete` endpoint as fallback

### Detail Page Data

- Community data: server-rendered from SQLite (existing)
- Leadership + Band Office: fetched from NorthCloud API via `NorthCloudClient` (existing, cached in SQLite with configurable TTL)
- Nearby communities: calculated server-side via existing `CommunityFinder` (Haversine, 6 results, 200km radius)

### Dependencies

- `leaflet` (~40KB gzipped) + `leaflet.markercluster` (~10KB)
- `alpinejs` (~15KB gzipped)
- No build step required — load via CDN or vendor locally

### Performance

- Marker clustering prevents rendering 634+ individual markers
- JSON blob is cacheable (communities change infrequently)
- Alpine.js filtering is O(n) on 634 items — imperceptible

---

## Color Palette

| Token | Value | Usage |
|-------|-------|-------|
| `--atlas-deep` | `#2d4a3e` | Hero backgrounds, active chips |
| `--atlas-forest` | `#3d6b5a` | Links, accents, interactive elements |
| `--atlas-sage` | `#6b8f71` | Section labels, secondary text, badges |
| `--atlas-mist` | `#e8f0ec` | Hero text, light foregrounds |
| `--atlas-cloud` | `#f7faf8` | Card backgrounds, subtle fills |
| `--atlas-border` | `#e2ebe5` | Card borders, section dividers |
| `--atlas-chip-bg` | `#c8d6c4` | Inactive chip borders, map controls |

---

## Out of Scope

- Community editing/admin UI (existing admin flow is sufficient)
- User accounts or saved favorites
- Community-contributed content or comments
- Historical data or timeline views
- Advanced analytics or data export
