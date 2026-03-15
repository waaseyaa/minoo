# Content Seeding for Launch — Design Spec

**Date:** 2026-03-15
**Status:** Draft
**Goal:** Seed minoo.live with curated, launch-ready content — real people, businesses, and events visible to community members.

## Executive Summary

Minoo has entity types for people, groups, events, and teachings but zero production content. This spec defines:

1. Schema changes to support businesses as a first-class Group type with contact fields, plus person-to-business linking
2. A `content/` fixtures directory with JSON files for people, businesses, and events
3. A `bin/seed-content` CLI command that validates and upserts fixture data idempotently
4. A content research plan targeting 10-20 businesses, 10-15 people, and 10-15 events across the North Shore corridor

Content is real, verified, and published on minoo.live. This is not demo data.

## 1. Schema Changes

### 1.1 New Group Fields

Add to `GroupServiceProvider` field definitions:

| Field | Type | Label | Notes |
|-------|------|-------|-------|
| `phone` | string | Phone | E.164 format, nullable |
| `email` | string | Email | Nullable |
| `address` | string | Address | Single string, nullable |
| `booking_url` | uri | Booking URL | External booking link, nullable |
| `source` | string | Source | Provenance tag, nullable |
| `verified_at` | datetime | Verified At | ISO 8601 UTC, nullable |

### 1.2 New ResourcePerson Fields

Add to `PeopleServiceProvider` field definitions:

| Field | Type | Label | Notes |
|-------|------|-------|-------|
| `linked_group_id` | entity_reference | Linked Business | Target: group, nullable |
| `source` | string | Source | Provenance tag, nullable |
| `verified_at` | datetime | Verified At | ISO 8601 UTC, nullable |

### 1.3 New Event Fields

Add to `EventServiceProvider` field definitions:

| Field | Type | Label | Notes |
|-------|------|-------|-------|
| `source` | string | Source | Provenance tag, nullable |
| `verified_at` | datetime | Verified At | ISO 8601 UTC, nullable |

### 1.4 New Group Type

Add `business` to `ConfigSeeder::groupTypes()`:

```php
['type' => 'business', 'name' => 'Local Business'],
```

### 1.5 Migration Notes

- All new columns are nullable — no destructive changes
- Run `bin/waaseyaa schema:check` after deploying to detect drift
- Manually `ALTER TABLE ... ADD COLUMN` on production SQLite if tables already exist
- Deploy schema changes before running `bin/seed-content`

### 1.6 What Is NOT Changing

- No `external_id` / `external_source` fields — deferred to social ingestion pipeline (v0.15+)
- No admin UI changes — separate feature
- No structured address fields (lat/lon) — single `address` string is sufficient for launch

## 2. Fixtures Format

### 2.1 Directory Structure

```
content/
├── businesses.json    # Group (type: business) records
├── people.json        # ResourcePerson records
└── events.json        # Event records
```

### 2.2 Upsert Key

All records upsert by `slug` — unique per entity type. The CLI queries by slug to decide create vs. update.

### 2.3 Community Resolution

Fixtures reference communities by name string (e.g., `"community": "Sagamok Anishnawbek"`). The CLI resolves to `community_id` at import time:

1. Exact name match
2. Case-insensitive fallback
3. Unresolved → record skipped, logged as warning

### 2.4 Business Fixture Format

```json
[
  {
    "name": "Nginaajiiw Salon & Spa",
    "slug": "nginaajiiw-salon-spa",
    "type": "business",
    "description": "Full-service salon and spa offering hair, esthetics, massage, and nail services.",
    "phone": "+17058698163",
    "email": "nginaajiiwsalonandspa@hotmail.com",
    "address": "610-7 Sagamok Road, Sagamok Anishnawbek, ON P0P 2L0",
    "url": "https://www.instagram.com/nginaajiiw.salonandspa",
    "booking_url": "https://nginaajiiw-salon-spa.square.site",
    "community": "Sagamok Anishnawbek",
    "source": "manual:russell:2026-03-15",
    "verified_at": "2026-03-15T00:00:00Z"
  }
]
```

### 2.5 People Fixture Format

```json
[
  {
    "name": "Charlotte Southwind",
    "slug": "charlotte-southwind",
    "bio": "Beadwork artist from Sagamok Anishnawbek, creating handmade beaded earrings and accessories.",
    "roles": ["artist"],
    "offerings": ["beadwork"],
    "community": "Sagamok Anishnawbek",
    "consent_public": false,
    "source": "manual:russell:2026-03-15",
    "verified_at": "2026-03-15T00:00:00Z"
  },
  {
    "name": "Larissa",
    "slug": "larissa-nginaajiiw",
    "bio": "Owner and operator of Nginaajiiw Salon & Spa in Sagamok Anishnawbek.",
    "roles": ["business_owner"],
    "offerings": ["hair_services", "esthetics"],
    "linked_group": "nginaajiiw-salon-spa",
    "community": "Sagamok Anishnawbek",
    "consent_public": true,
    "source": "manual:russell:2026-03-15",
    "verified_at": "2026-03-15T00:00:00Z"
  }
]
```

### 2.6 Event Fixture Format

```json
[
  {
    "title": "Example Community Gathering",
    "slug": "example-community-gathering-2026",
    "type": "gathering",
    "description": "A community gathering for all ages.",
    "location": "Sagamok Community Centre",
    "starts_at": "2026-04-15T10:00:00Z",
    "ends_at": "2026-04-15T16:00:00Z",
    "community": "Sagamok Anishnawbek",
    "source": "manual:russell:2026-03-15",
    "verified_at": "2026-03-15T00:00:00Z"
  }
]
```

### 2.7 Linking Convention

People reference their business by slug in the `linked_group` field. The CLI resolves this to `linked_group_id` during import. Businesses must be seeded before people.

### 2.8 Partial Updates

On upsert, only fields present in the fixture are written. Unspecified fields are never nulled. This allows manual edits via admin UI to coexist with fixture-based seeding.

## 3. CLI Command: `bin/seed-content`

### 3.1 Usage

```bash
bin/seed-content                    # dry-run (default) — validate and report
bin/seed-content --apply            # write to database
bin/seed-content --apply --verbose  # write + per-record detail
bin/seed-content --file businesses  # seed one fixture file only
```

### 3.2 Execution Flow

```
1. Boot kernel
2. Load fixture files from content/*.json
3. VALIDATE — required fields, phone/email format, URL validity, slug uniqueness within file
4. RESOLVE — match community names to community entities
5. RESOLVE LINKS — map linked_group slugs to group IDs
6. UPSERT — query by slug → create or update (fields present in fixture only)
7. REPORT — print summary table
```

### 3.3 Seed Order

Config types → Businesses → People → Events

This order is enforced because people reference businesses via `linked_group`. When `--file` is used and referenced groups are missing, those people are skipped with a warning.

### 3.4 Dry-Run Output

```
Seed Summary (dry-run)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Businesses:  3 create  0 update  0 skip  0 error
People:      4 create  1 update  0 skip  0 error
Events:      5 create  0 update  0 skip  0 error

Warnings:
  - Community "Unknown Town" not found (2 records skipped)

Run with --apply to persist changes.
```

### 3.5 Validation Rules

**Required fields per entity type:**

| Entity | Required |
|--------|----------|
| Business | name, slug, type, community |
| Person | name, slug, community |
| Event | title, slug, type, community, starts_at |

**Format validation:**
- Phone: must match E.164 pattern (`+1XXXXXXXXXX`) or be omitted
- Email: basic format check
- URLs (url, booking_url): valid URI or omitted
- Dates (starts_at, ends_at, verified_at): ISO 8601 UTC

**Severity:**
- Missing required fields → fatal (record blocked)
- Invalid format → warning (record skipped)

### 3.6 Error Handling

- Validation errors are collected across all records, reported together
- Community resolution failures are warnings — record skipped, not fatal to the run
- Database write failures are per-record — one failure doesn't abort others
- Exit codes: `0` success, `1` validation fatal, `2` partial write failures

### 3.7 What the CLI Does NOT Do

- No `--force` mode that nulls missing fields
- No retry with backoff — unnecessary at this scale
- No lock file for concurrent runs — unnecessary at this scale
- No machine-readable JSON output — human table is sufficient
- No environment guards beyond dry-run default — server permissions handle authorization

## 4. Content Research Plan

### 4.1 Target Counts

| Entity | Target | Phase |
|--------|--------|-------|
| Businesses (Group type: business) | 10-20 | Phase 1 (this session) |
| People (ResourcePerson) | 10-15 | Phase 1 (this session) |
| Events | 10-15 | Phase 1 (this session) |
| Teachings | Deferred | Phase 2 (when real content available) |

### 4.2 Geographic Scope

Primary: Sagamok Anishnawbek, Massey, Espanola, Elliot Lake, Spanish
Secondary: Manitoulin Island, Sudbury corridor (if needed to reach target counts)

### 4.3 Research Sources

- YellowPages.ca — local business listings
- Municipal business directories (Espanola, Central Manitoulin, West Manitoulin)
- Google Maps — business details, hours, contact info
- Community Facebook pages — events, local businesses
- Band office websites — community events, leadership
- NorthCloud — existing community and leadership data

### 4.4 Content Priorities

1. Indigenous-owned businesses first
2. Community-serving businesses (health, food, services)
3. Small/side businesses mentioned by Russell (beadwork, crafts)
4. General local businesses in the corridor

### 4.5 Consent Policy

**Businesses:**
- Public directory listings → `consent_public: true`
- Source recorded (e.g., `"directory:yellowpages"`, `"directory:municipal"`)
- Contact info from public sources is publishable

**People:**
- Default `consent_public: false` — contact info hidden until consent verified
- People known personally to Russell → he confirms consent
- Business owners linked to a public business → business contact is public, personal contact remains private

### 4.6 Provenance Tags

Format: `{method}:{who}:{date}`

Examples:
- `manual:russell:2026-03-15` — Russell provided the info directly
- `directory:yellowpages:2026-03-15` — sourced from YellowPages
- `directory:espanola:2026-03-15` — sourced from municipal directory
- `web:google:2026-03-15` — sourced from Google Maps/web search

### 4.7 Verification

All seeded records include `verified_at` timestamps. Records older than 12 months should be flagged for re-verification (future admin feature, not part of this spec).

### 4.8 Opt-Out Process

Any person or business can request removal or edits by contacting the site admin. This is a manual process for launch — no self-service UI yet. Document the contact method on the site's privacy/legal pages.

## 5. Rollout Plan

### 5.1 Implementation Order

1. Add new fields to service providers (GroupServiceProvider, PeopleServiceProvider, EventServiceProvider)
2. Add `business` group type to ConfigSeeder
3. Create `content/` directory and fixture files
4. Implement `bin/seed-content` CLI
5. Write unit tests for CLI (validation, resolution, upsert logic)
6. Write integration test (full dry-run + apply against in-memory SQLite)
7. Run on staging / dev environment
8. Deploy schema changes to production (`ALTER TABLE` for new columns)
9. Run `bin/seed-content --apply` on production

### 5.2 Testing

**Unit tests:**
- Fixture parser: valid JSON, invalid JSON, duplicate slugs
- Validation: missing required fields, invalid phone/email/URL
- Community resolution: exact match, case-insensitive, unresolved
- Linked group resolution: present vs. missing
- Upsert logic: create, update (partial), no-op on unchanged

**Integration test:**
- Boot kernel with in-memory SQLite
- Seed communities first
- Run CLI in apply mode with test fixtures
- Assert correct entity counts and field values
- Re-run and assert idempotency (zero creates on second run)

### 5.3 QA Checklist

- [ ] Dry-run shows correct counts for all fixture files
- [ ] Apply creates all records with correct field values
- [ ] Re-run apply is idempotent (0 creates, 0 updates)
- [ ] Community resolution works for all seeded communities
- [ ] Linked group references resolve correctly
- [ ] Phone numbers stored in E.164 format
- [ ] consent_public=false records don't expose contact info on site
- [ ] Source and verified_at populated on all records
- [ ] Events display with correct dates on the site
- [ ] Businesses appear on the groups listing page

## 6. Future Considerations (Not In Scope)

- Admin UI for editing seeded content
- Community submission flow with moderation queue
- Meta/Facebook ingestion pipeline (v0.15+)
- Geocoding and map display
- Structured address fields (street, city, province, postal)
- Teaching content seeding (Phase 2)
- Machine-readable audit logs
- Automated re-verification reminders
