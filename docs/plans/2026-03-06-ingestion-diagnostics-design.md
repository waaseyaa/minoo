# Ingestion Diagnostics & Admin Review Queue — Design

**Issue:** #4
**Milestone:** v0.2 – First Entities + NorthCloud Ingestion
**Approach:** C — Entity type + dashboard summary widget

## Overview

Add an `ingest_log` entity type to track ingestion attempts and a dashboard widget for at-a-glance diagnostics. The entity type gives us free CRUD, list/detail views, filtering, SSE, and access control. The widget provides operational visibility without committing to a full custom page.

## Entity: `ingest_log`

### Fields

| Field | Type | Key | Purpose |
|-------|------|-----|---------|
| `ilid` | int | id | Primary key |
| `uuid` | string | uuid | UUID |
| `title` | string | label | Auto-generated: `{source} — {timestamp}` |
| `status` | string | — | `pending_review`, `approved`, `rejected`, `failed` |
| `source` | string | — | Origin identifier (e.g. `northcloud`, `ojibwe_lib`) |
| `entity_type_target` | string | — | Target entity type machine name |
| `entity_id` | int (nullable) | — | Created entity ID after approval |
| `payload_raw` | text | — | Original payload JSON |
| `payload_parsed` | text | — | Mapped/transformed fields JSON |
| `error_message` | text (nullable) | — | Error details if failed |
| `reviewed_by` | entity_reference (nullable) | — | User who reviewed |
| `reviewed_at` | timestamp (nullable) | — | When reviewed |
| `created_at` | timestamp | — | When ingested |
| `updated_at` | timestamp | — | Last modified |

### Registration

- **Entity class:** `Minoo\Entity\IngestLog` extending `ContentEntityBase`
- **Provider:** `Minoo\Provider\IngestServiceProvider`
- **Access policy:** `Minoo\Access\IngestAccessPolicy`
- **Sidebar group:** `ingestion`
- **Entity key:** `ilid`

### Status values

- `pending_review` — ingested, awaiting human review
- `approved` — reviewed and accepted, entity created
- `rejected` — reviewed and rejected
- `failed` — ingestion error, see `error_message`

### Design decisions

- **No reprocessing from UI** — retries originate from CLI/queue. The log records what happened; it doesn't drive re-execution.
- **No assignment workflows** — single-admin for v0.2. `reviewed_by` and `reviewed_at` are nullable, ready for multi-editor later.
- **`payload_raw` + `payload_parsed` both stored** — enables diffing, debugging, reprocessing, and audit trails.
- **`entity_type_target` uses machine names** — exactly as registered in providers (e.g. `dictionary_entry`).

## Dashboard Widget

### Placement

Above the entity type card grid on the admin home page (`/`).

### Contents

4 status counters, each linking to the filtered `ingest_log` list:

```
[ Pending Review: 3 ] [ Approved: 12 ] [ Rejected: 1 ] [ Failed: 0 ]
```

### Empty state

When zero logs exist: all counters show `0` with a "No ingestion activity yet" message.

### Data source

Standard entity list API: `GET /api/ingest_log?fields=status`. Client-side aggregation. No new backend endpoint.

### Real-time updates

SSE via existing admin SPA broadcast channel. Widget refreshes automatically on entity changes.

### i18n

New translation keys in `en.json` and `fr.json`:
- `nav_group_ingestion` — sidebar group label
- `ingest_widget_title` — widget heading
- `ingest_widget_empty` — empty state message
- `ingest_status_pending_review`, `ingest_status_approved`, `ingest_status_rejected`, `ingest_status_failed`

## Testing

### PHPUnit (Minoo)

- `IngestLogTest` — entity class fields, type ID, keys
- `IngestAccessPolicyTest` — policy attribute, access results
- `IngestServiceProviderTest` — entity type registration
- Integration smoke test — kernel discovers new provider/policy

### Playwright

- Sidebar shows `ingest_log` under "Ingestion" group
- Dashboard widget renders with zero-state
- `ingest_log` list page loads
- Create form works (manual entry for testing review flow)

## Future evolution

When ingestion volume grows or richer UX is needed:
- Dedicated `/api/ingestion/stats` endpoint for server-side aggregation
- Custom admin page with payload diffing, batch actions, retry controls (Approach B)
- Multi-editor assignment workflows (`assigned_to` field)
- Source-level analytics and ingestion timelines
