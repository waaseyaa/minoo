# Unified Business & People Content Model + Map Integration — Design Spec

**Date:** 2026-03-21
**Scope:** Content quality invariants, geocoding, map integration, consent cascade, template defaults
**Reference pattern:** Larissa Toulouse / Nginaajiiw Salon & Spa
**Phase:** 1 of 2 (Phase 2: NC content enrichment pipeline — separate milestone)

## Problem

Business and people pages have inconsistent content quality. Nginaajiiw Salon & Spa and Larissa Toulouse were manually polished to a high standard — narrative bios, full contact info, taxonomy terms, mutual linking, community grounding. The rest of the content ranges from partial to placeholder. There is no map on business pages despite addresses being available. No formal definition of "complete" exists for these entity types.

## Goals

1. Define content quality invariants for businesses and people
2. Add geocoded map to business detail pages (community fallback)
3. Implement consent cascade — owner cards respect person consent
4. Establish template-driven default bios as baseline
5. Polish all current consented entries to the reference pattern
6. Add fixture validation to surface incomplete entries

## Non-Goals

- NC pipeline integration for content enrichment (Phase 2)
- Admin UI for editing content (fixture files remain source of truth)
- Map on listing pages (detail pages only)
- Draggable pin / coordinate editor
- Changes to placeholder/hidden entries (consent_public: false)
- Geocoding on page render (stored at save time only)

## 1. Schema Changes

### Group Entity

Add two nullable float fields to `GroupServiceProvider`:

| Field | Type | Label | Description |
|-------|------|-------|-------------|
| `latitude` | float, nullable | Latitude | Geocoded from address, or community fallback |
| `longitude` | float, nullable | Longitude | Geocoded from address, or community fallback |

**Migration:** `bin/waaseyaa make:migration add_coordinates_to_group`

No changes to ResourcePerson entity — existing fields cover the model.

## 2. Content Quality Invariants

### Business (complete)

A business entry is considered complete when it has:

- **Narrative description** — 2+ sentences; mentions founder/owner if linked; cultural context where applicable
- **Address OR community** — at least one location anchor
- **At least one contact method** — phone, email, or url
- **booking_url** — if the business accepts appointments
- **latitude/longitude** — geocoded from address, community fallback when unavailable

### Person (complete, consent_public: true only)

A consented person entry is considered complete when it has:

- **Narrative bio** — 2+ sentences; tells their story, not just "Owner of X"
- **Community** — community affiliation
- **At least one role** — resolved taxonomy term from person_roles vocabulary
- **At least one offering** — resolved taxonomy term from person_offerings vocabulary
- **linked_group** — if they own/operate a business

### Placeholder entries (consent_public: false)

Remain minimal and functional. Do not participate in content quality validation. Serve as scaffolding for roles the platform supports until real community members opt in.

### Fixture validation

`bin/seed-content` reports warnings (non-blocking) for incomplete public entries:

```
⚠ larissa-toulouse: missing phone (person completeness)
⚠ zgamok-enterprises: description under 2 sentences (business completeness)
```

Warnings surface drift without blocking seeding.

## 3. Geocoding Service

### `src/Support/GeocodingService.php`

```
GeocodingService
  + geocode(string $address): ?array{lat: float, lng: float}
```

- Calls Nominatim API: `https://nominatim.openstreetmap.org/search`
- Query params: `q={address}&format=json&limit=1`
- User-Agent header: `Minoo/1.0 (https://minoo.live)`
- Returns `['lat' => float, 'lng' => float]` or `null` on failure
- Respects 1 request/second rate limit (Nominatim usage policy)
- No API key required

### `bin/geocode-businesses`

Standalone backfill script:

1. Iterates all Group entities with `type = business`
2. Skips entities that already have lat/lng
3. For entities with an `address` field: call `GeocodingService::geocode()`
4. If geocoding returns null and entity has `community_id`: copy community lat/lng as fallback
5. Save coordinates to entity
6. Rate-limited with `usleep(1_000_000)` between requests
7. Reports progress: `[GEOCODED] nginaajiiw-salon-spa: 46.4234, -81.9876`

Idempotent — safe to run multiple times.

### Integration with `bin/seed-content`

After upserting businesses, geocode any entry that has an address but no lat/lng. Uses the same `GeocodingService`. Skips geocoding in dry-run mode.

## 4. Map on Business Detail Page

### Template (`businesses.html.twig`)

Add map container in the Location section, above the address text:

```twig
{% if business.latitude and business.longitude %}
  <div id="business-detail-map"
       class="business-detail-map"
       data-lat="{{ business.latitude }}"
       data-lng="{{ business.longitude }}"
       data-name="{{ business.name }}"
       data-address="{{ business.address ?? '' }}"
       data-precision="{{ business.latitude != community.latitude ? 'address' : 'community' }}">
  </div>
{% endif %}
```

Only rendered when coordinates are available.

### JavaScript (`public/js/business-map.js`)

Follows the existing `atlas-detail.js` pattern:

```javascript
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('business-detail-map');
  if (!el) return;

  var lat = parseFloat(el.dataset.lat);
  var lng = parseFloat(el.dataset.lng);
  var name = el.dataset.name;
  var address = el.dataset.address;
  var precision = el.dataset.precision;
  var zoom = precision === 'address' ? 15 : 12;

  var map = L.map('business-detail-map').setView([lat, lng], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 18
  }).addTo(map);

  var popupContent = '<strong>' + name + '</strong>';
  if (address) popupContent += '<br>' + address;

  L.marker([lat, lng])
    .addTo(map)
    .bindPopup(popupContent)
    .openPopup();
});
```

### CSS (`minoo.css`, `@layer components`)

```css
.business-detail-map {
  block-size: 200px;
  border-radius: var(--radius-md);
  margin-block-end: var(--space-xs);
  background: var(--surface-raised);
}
```

Reuses existing design tokens. Consistent with `.community-detail-map` dimensions.

### Leaflet loading

Leaflet JS/CSS already loaded in `base.html.twig`. Add `business-map.js` via a `<script>` tag in the business detail template's scripts block, loaded only on detail pages.

## 5. Consent Cascade

### Rule

Businesses are public entities — always visible regardless of owner consent status. The **owner card** on the business detail page only renders when the linked person has `consent_public: true`.

### Template change (`businesses.html.twig`)

```twig
{# Owner section — only show consented people #}
{% if owner and owner.consent_public %}
  <section class="business-owner">
    <h2>{{ t('businesses.owner') }}</h2>
    {% include 'components/person-card.html.twig' with { person: owner } %}
  </section>
{% endif %}
```

### Controller change

`BusinessController` (or the Group controller rendering businesses) passes the linked person entity to the template. The template checks `consent_public` before rendering.

### Edge cases

- **No linked person:** Owner section not rendered (current behavior)
- **Linked person with consent_public: false:** Owner section hidden, business stays visible
- **Person withdraws consent later:** Owner card disappears on next page render, no data deletion needed

## 6. Template-Driven Default Bios

For entries without hand-written narrative content, generate baseline descriptions automatically in `bin/seed-content`.

### Business default template

```
{name} is a {type} in {community}.
```

Applied only when `description` is empty or null. Never overwrites existing content.

### Person default template

```
{name} is a {roles joined with ' and '} from {community}.
```

Applied only when `bio` is empty or null. Never overwrites existing content.

### Implementation

In `bin/seed-content`, after upserting an entity, check if description/bio is empty. If so, generate from template and save. Log as `[DEFAULT BIO]` in verbose mode.

## 7. Assisted Content Drafts (Current Entries)

For Phase 1, draft polished narrative content for all current entries that meet these criteria:

- **People:** `consent_public: true` (currently: Russell Jones, Larissa Toulouse)
- **Businesses:** All 15 entries in `content/businesses.json`

Larissa Toulouse and Nginaajiiw Salon & Spa are already at the reference standard. Remaining entries get narrative drafts following the same pattern:

- Mention the person/founder by name where applicable
- Cultural context (Anishinaabemowin name origins, community connection)
- Specific services/offerings
- Call to action (book, visit, connect)

All drafts committed to `content/*.json` only after human review and approval.

## 8. File Changes Summary

### New files

| File | Purpose |
|------|---------|
| `src/Support/GeocodingService.php` | Nominatim geocoding client |
| `bin/geocode-businesses` | Standalone coordinate backfill script |
| `public/js/business-map.js` | Leaflet map initialization for business detail |
| `migrations/NNNN_add_coordinates_to_group.php` | Schema migration |
| `tests/Minoo/Unit/Support/GeocodingServiceTest.php` | Unit test for geocoder |

### Modified files

| File | Change |
|------|--------|
| `src/Provider/GroupServiceProvider.php` | Add latitude/longitude field definitions |
| `templates/businesses.html.twig` | Add map container, consent cascade on owner card |
| `public/css/minoo.css` | Add `.business-detail-map` styles |
| `bin/seed-content` | Add completeness validation, geocoding integration, default bio generation |
| `content/businesses.json` | Polished narrative descriptions for all entries |
| `content/people.json` | Polished narrative bios for consented entries |

## 9. Testing

- **GeocodingService unit test** — mock HTTP response, verify lat/lng parsing, verify null on failure
- **Completeness validation test** — verify warnings for incomplete entries, no warnings for complete entries
- **Consent cascade test** — verify owner card hidden when consent_public: false
- **Default bio test** — verify template generation, verify no overwrite of existing content
- **Integration test** — seed with geocoding disabled (in-memory DB), verify map container renders with coordinates

## 10. Deployment Sequence

1. Deploy code (migration runs automatically via `minoo:migrate`)
2. SSH: `bin/geocode-businesses` (backfill coordinates for existing businesses)
3. SSH: `bin/seed-content --apply` (update descriptions, bios, validate completeness)
4. Verify map renders on business detail pages
5. Verify consent cascade on owner cards

## 11. Phase 2 — NC Content Enrichment Pipeline (Separate Milestone)

Filed as a GitHub milestone. Content enrichment becomes a pipeline stage:

- Detect entries missing narrative content
- Generate assisted drafts from structured data
- Surface for human review
- Commit approved content back to fixture files
- Seed into environments via existing content pipeline

This transforms the manual assisted-draft workflow from Phase 1 into a repeatable, auditable, pipeline-driven process.
