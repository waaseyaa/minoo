# v0.8 — Location Awareness & Local Relevance

## Summary

Add location detection to Minoo so the platform feels local to each visitor. IP geolocation identifies the user's approximate location on first visit, browser geolocation refines it with permission, and manual selection provides a fallback. Location context silently personalizes forms, filters, and highlights across the site.

## Architecture

### LocationService (`src/Geo/LocationService.php`)

Three-layer resolution with fallback chain:

1. **Session/cookie** — reuse previously resolved location
2. **IP geolocation** — MaxMind GeoLite2-City local DB lookup on `REMOTE_ADDR`
3. **Browser geolocation** — JS Geolocation API (async, user permission required)
4. **Manual fallback** — inline autocomplete dropdown ("Select your community")

Once coordinates are resolved, `GeoDistance::haversine()` finds the nearest `Community` entity. Result stored in `$_SESSION['minoo_location']` + `minoo_location` cookie for cross-session persistence.

### LocationContext (value object, `src/Geo/LocationContext.php`)

```php
final class LocationContext
{
    public function __construct(
        public readonly ?int $communityId,
        public readonly ?string $communityName,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly string $source, // 'ip', 'browser', 'manual', 'none'
    ) {}

    public function hasLocation(): bool;
    public function nearbyCommunities(EntityTypeManager $etm, int $limit = 5): array;
}
```

### LocationMiddleware (`src/Middleware/LocationMiddleware.php`)

Runs after `SessionMiddleware`. Resolves location on every request and injects `LocationContext` into the request attributes (accessible in controllers via `$request->attributes->get('location')`).

```
Request → SessionMiddleware → LocationMiddleware
  ├─ session has location? → hydrate LocationContext from session
  ├─ cookie has location? → hydrate from cookie, store in session
  ├─ IP lookup → MaxMind → nearest community → store in session+cookie
  └─ no result → LocationContext with source='none'
```

### Browser Geolocation (async refinement)

Small inline `<script>` in `base.html.twig`:

1. Check if `navigator.geolocation` is available and location source is `'ip'` or `'none'`
2. Request position with `getCurrentPosition()`
3. POST coordinates to `/api/location/update`
4. Server resolves nearest community, updates session+cookie
5. Reload context bar via page refresh or fetch

Only triggers once per session. Does not prompt if user already has manual or browser-sourced location.

## Data Flow

### IP Geolocation

- **Library:** `geoip2/geoip2` (Composer)
- **Database:** GeoLite2-City.mmdb in `storage/geoip/` (gitignored)
- **Lookup:** `$reader->city($ip)` → lat/lon → `GeoDistance::haversine()` against all communities → nearest match
- **Caching:** Community coordinates cached in memory per request (already done in `VolunteerRanker`)
- **Dev mode:** Falls back to configurable default coordinates when IP is `127.0.0.1` or private range

### Nearest Community Resolution

Reuses existing `GeoDistance::haversine()`. Loads all communities once, calculates distance to each, returns the closest. For `nearbyCommunities()`, sorts by distance and returns top N.

## New Routes

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/location/set` | Manual community selection (from autocomplete) |
| POST | `/api/location/update` | Browser geolocation refinement (lat/lon) |
| GET | `/api/location/current` | Current location context (JSON, for JS) |

### POST `/api/location/set`

```
Body: { community_id: int }
Response: { success: true, community: { id, name, latitude, longitude } }
```

Stores selected community in session + cookie. Source becomes `'manual'`.

### POST `/api/location/update`

```
Body: { latitude: float, longitude: float }
Response: { success: true, community: { id, name } }
```

Resolves nearest community from coordinates. Source becomes `'browser'`.

## UI Components

### Context Bar (`templates/components/location-bar.html.twig`)

Included in `base.html.twig` below the nav. Two states:

**With location:**
```
📍 Near Sagamok · Change
```

**Without location:**
```
📍 Set your location
```

"Change" / "Set your location" opens an inline dropdown with community autocomplete input. Selection POSTs to `/api/location/set`, page reloads.

### CSS

Added to `@layer components` in `minoo.css`:

- `.location-bar` — full-width bar below nav, subtle background
- `.location-bar__toggle` — clickable text trigger
- `.location-bar__dropdown` — autocomplete dropdown panel
- `.location-bar__input` — search input within dropdown

### Homepage Location-Aware Sections

When `LocationContext::hasLocation()` is true, the homepage shows:

- **"Events near you"** — events filtered by community proximity
- **"Your community"** — link to the matched community detail page
- **"Nearby resources"** — resource people in the area (if any)

These sections are optional — they only render when location is available. They link to the existing listing pages with location pre-applied, not duplicate content.

### Form Pre-Fill

**Elder request form** (`templates/elders/request.html.twig`):
- Community field pre-selected from `LocationContext.communityName`
- User can still change it

**Volunteer signup form** (`templates/elders/volunteer.html.twig`):
- Add community field to form (currently missing from template)
- Pre-select from `LocationContext.communityName`

### Listing Enhancements

**Resource directory** (`/people`):
- When location is known, auto-filter to nearby resource people
- Show "Showing results near {community}" with "Show all" link

**Communities page** (`/communities`):
- Highlight nearest community with visual indicator
- Sort by proximity when location is known (nearest first)

## Dependencies

### New Composer Package

```json
"require": {
    "geoip2/geoip2": "^3.0"
}
```

### GeoLite2 Database

- Free registration at MaxMind for GeoLite2-City download
- File: `storage/geoip/GeoLite2-City.mmdb` (gitignored)
- Download script: `bin/download-geoip-db` (uses MaxMind license key from env)
- Deploy task: download DB on deploy if missing or stale

### No Schema Changes

All location data lives in session/cookie. No entity field changes. No migrations.

## Testing

### Unit Tests

- `LocationContextTest` — value object construction, `hasLocation()`, serialization
- `LocationServiceTest` — IP resolution, nearest community matching, fallback chain
- `LocationMiddlewareTest` — session hydration, cookie reading, context injection

### Integration Tests

- Middleware chain boots with LocationMiddleware
- Session persistence across simulated requests

### Playwright Smoke Tests

- Context bar appears on homepage
- "Set your location" opens dropdown
- Selecting a community updates the bar
- Elder request form pre-fills community
- Volunteer signup form pre-fills community
- Resource directory filters by location
- Communities page highlights nearest

## Configuration

```php
// config/waaseyaa.php
'location' => [
    'geoip_db' => getenv('GEOIP_DB_PATH') ?: __DIR__ . '/../storage/geoip/GeoLite2-City.mmdb',
    'default_coordinates' => [46.49, -81.00], // Sudbury fallback for dev/missing IP
    'cookie_name' => 'minoo_location',
    'cookie_ttl' => 86400 * 30, // 30 days
],
```

## Out of Scope

- GIS maps or travel-time calculations
- User profile location storage (persisted to DB)
- Location-based notifications or alerts
- Multi-language location names
