# People + Band Office Integration — Design Spec

**Date:** 2026-03-10
**Issues:** #178 (display), #179 (outreach workflow)
**Milestone:** People + Band Office Integration

## Overview

Add leadership (chief + council) and band office contact sections to the community detail page, fetched from NorthCloud's people and band office API.

## Architecture Decision: Cross-System Identity

**Decision:** Store NorthCloud's canonical UUID as `nc_id` on Minoo's community entity.

**Rationale:**
- NorthCloud is the authoritative registry; Minoo is a consumer
- INAC IDs are not present on municipalities, not guaranteed stable
- UUIDs provide a single, unambiguous lookup path
- Eliminates extra API calls for ID resolution
- Compatible with future community types (municipalities, mixed)

**Backfill:** One-time `bin/backfill-nc-ids` script matches existing communities by `inac_id` and writes NC UUIDs. Issue #176 (sync rewrite) will inherit this field.

## Data Flow

1. `CommunityController::show()` reads `nc_id` from the community entity
2. If `nc_id` is present, calls `NorthCloudClient`:
   - `GET {NC_BASE}/api/v1/communities/{nc_id}/people?current_only=true`
   - `GET {NC_BASE}/api/v1/communities/{nc_id}/band-office`
3. On failure: log warning via `error_log()`, pass `null` to template — sections don't render
4. On success: pass `people` array and `band_office` object to template
5. If `nc_id` is null: skip API calls entirely, sections don't render

## NC API Response Structures

### People Response

```json
{
  "people": [
    {
      "id": "UUID",
      "name": "string",
      "role": "string",
      "role_title": "string|null",
      "email": "string|null",
      "phone": "string|null",
      "is_current": true,
      "verified": false,
      "term_start": "RFC3339|null",
      "term_end": "RFC3339|null",
      "source_url": "string|null",
      "updated_at": "RFC3339"
    }
  ],
  "total": 7
}
```

### Band Office Response

```json
{
  "band_office": {
    "id": "UUID",
    "address_line1": "string|null",
    "address_line2": "string|null",
    "city": "string|null",
    "province": "string|null",
    "postal_code": "string|null",
    "phone": "string|null",
    "fax": "string|null",
    "email": "string|null",
    "toll_free": "string|null",
    "office_hours": "string|null",
    "verified": false,
    "source_url": "string|null",
    "updated_at": "RFC3339"
  }
}
```

## New Component: NorthCloudClient

**File:** `src/Support/NorthCloudClient.php`

- Constructor takes base URL from config (`waaseyaa.northcloud.base_url`)
- `getPeople(string $ncId): ?array` — returns decoded people array or null on failure
- `getBandOffice(string $ncId): ?array` — returns decoded band office object or null on failure
- Uses `file_get_contents` with stream context (consistent with `CommunityAutocompleteClient`)
- Logs errors via `error_log()`
- No caching for now (see future issue for SQLite cache layer)

## Entity Change

Add `nc_id` to community entity `fieldDefinitions()`:
- Type: string, nullable
- Production: `ALTER TABLE community ADD COLUMN nc_id TEXT`
- Schema drift: run `bin/waaseyaa schema:check` after deploy

## Backfill Script

**File:** `bin/backfill-nc-ids`

1. Fetch all communities from NC: `GET /api/v1/communities?limit=1000`
2. Match to local communities by `inac_id`
3. Update `nc_id` on matched records
4. Report: matched count, unmatched count, skipped (no inac_id)

## Template Changes

### Leadership Section

Placement: after stats grid, before location section.

- Chief shown prominently (larger card with role badge)
- Councillors in responsive grid below chief
- Each person card: name, role_title (or role), optional email, optional phone
- Unverified disclaimer: "This information was sourced from community websites and may not be current." (shown when `verified === false`)
- Section hidden entirely when no people data or NC unreachable

### Band Office Section

Placement: after leadership, before nearby communities.

- Address block: line1, line2, city, province, postal code
- Contact grid: phone, fax, toll-free, email
- Office hours (if present)
- Google Maps link constructed from address
- Unverified disclaimer (same as leadership)
- Section hidden entirely when no band office data or NC unreachable

## CSS Classes

All in `@layer components` in `minoo.css`:

- `.community__leadership` — section container (flex column, gap)
- `.community__chief` — prominent chief card (larger, accent border)
- `.community__councillors` — responsive grid (`auto-fill, minmax(14rem, 1fr)`)
- `.community__person` — person card (name, role, contact info)
- `.community__person-name` — name styling
- `.community__person-role` — role badge
- `.community__person-contact` — contact details (email, phone)
- `.community__band-office` — section container
- `.community__address` — address block
- `.community__contact-grid` — responsive grid for contact fields
- `.community__office-hours` — office hours display
- `.community__unverified` — disclaimer badge (muted, italic, small)
- `.community__maps-link` — Google Maps link

Design tokens: uses existing `--surface-raised`, `--text-secondary`, `--border`, `--accent-*` variables. Logical properties only. Native nesting. Container queries for card responsiveness.

## Error Handling

- NC unreachable: log warning, omit sections entirely (silent omission)
- NC returns 404: treat as "no data" — omit sections
- NC returns malformed JSON: log error, omit sections
- `nc_id` is null: skip API calls, omit sections
- No chief in people list: show councillors only (no chief card)
- Empty people list: omit leadership section
- Null band office: omit band office section

## Caching

**Current:** No caching. Two HTTP calls per community detail page load.

**Future:** SQLite cache table with TTL (separate issue). Store NC responses with 1-hour TTL, reducing NC dependency and improving page load times.

## Testing

### Unit Tests
- `NorthCloudClientTest` — mock HTTP responses for people, band office, error states
- Community entity `nc_id` field test

### Playwright Tests
- Community detail page renders leadership when NC data available
- Community detail page renders band office when NC data available
- Community detail page works without leadership/band office (graceful fallback)

## Configuration

Add to `config/waaseyaa.php`:

```php
'northcloud' => [
    'base_url' => getenv('NORTHCLOUD_BASE_URL') ?: 'https://northcloud.one',
],
```

## Dependencies

- NorthCloud #272 (people + band office API) — **CLOSED** ✓
- Minoo community entity must have `nc_id` populated (backfill script)
- Production: `ALTER TABLE` for `nc_id` column + backfill run
