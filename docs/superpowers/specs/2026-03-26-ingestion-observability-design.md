# Ingestion Observability Dashboard

**Date:** 2026-03-26
**Status:** Draft
**Scope:** Admin/operator visibility into NorthCloud content sync pipeline

## Problem

The ingestion pipeline (NorthCloud → Minoo teachings/events) has no web-based observability. IngestLog entities are stored but invisible. Sync results go to STDOUT/STDERR and a JSON status file that nobody checks. Operators have no way to see what's been ingested, what failed, or when the last sync ran without SSH access.

## Solution

A server-rendered admin page at `/admin/ingestion` that surfaces sync status and IngestLog entries.

## Route & Access

- **URL:** `GET /admin/ingestion`
- **Controller:** `IngestionDashboardController::index()`
- **Permission:** `administer content`
- **Sidebar:** Hidden (`hide_sidebar: true`)
- **Template:** `templates/admin/ingestion.html.twig` extending `base.html.twig`

## Page Sections

### 1. Sync Summary (top bar)

Three summary cards showing:
- **Last Sync** — timestamp from the NcSyncWorkerLoop JSON status file (`storage/nc-sync-status.json`). "No sync data" if file doesn't exist.
- **Created / Skipped / Failed** — counts from the same status file.
- **Total Ingest Logs** — count of IngestLog entities in the database.

### 2. Recent Ingest Logs (main table)

- Most recent 50 IngestLog entries, ordered by `created_at` DESC.
- Columns: status badge, source, entity type target, title, created_at, error_message (truncated to ~80 chars).
- **Filterable by status** via query param `?status=failed` (values: `pending_review`, `approved`, `rejected`, `failed`).

### 3. Error Details (expandable rows)

- Each table row wraps content in a `<details>` element.
- Expanding shows: full `error_message`, `payload_raw` (formatted JSON), `payload_parsed` (formatted JSON).
- Pure HTML/CSS, no JavaScript required.

## Styling

- Reuse existing CSS patterns — no new CSS layer or major additions.
- Summary cards: `.sidebar-widget` pattern adapted for horizontal layout.
- Status badges: `.feed-badge` pattern with status-specific colors:
  - `approved` → green (`--color-language`)
  - `pending_review` → yellow (`--color-teachings`)
  - `failed` → red (`--color-events`)
  - `rejected` → gray (`--text-muted`)
- Table: minimal styling consistent with existing pages.
- Expandable details: native `<details>/<summary>` with subtle styling.

## Files to Create/Modify

| File | Action |
|------|--------|
| `src/Controller/IngestionDashboardController.php` | Create — loads sync status + IngestLog data |
| `src/Provider/IngestionDashboardServiceProvider.php` | Create — registers route |
| `templates/admin/ingestion.html.twig` | Create — page template |
| `public/css/minoo.css` | Modify — add ingestion dashboard component styles |
| `templates/base.html.twig` | Modify — cache bust CSS version |
| `composer.json` | Modify — register new provider |

## Data Loading

```php
// Sync status from JSON file
$statusFile = $projectRoot . '/storage/nc-sync-status.json';
$syncStatus = file_exists($statusFile)
    ? json_decode(file_get_contents($statusFile), true)
    : null;

// IngestLog entries
$storage = $etm->getStorage('ingest_log');
$query = $storage->getQuery()->sort('created_at', 'DESC');
if ($statusFilter) {
    $query->condition('status', $statusFilter);
}
$ids = $query->range(0, 50)->execute();
$logs = $storage->loadMultiple($ids);
```

## Not In Scope

- Approval/rejection workflow UI (future work)
- Real-time sync monitoring or WebSocket updates
- Structured logging migration (separate concern)
- CLI `ingest:status` command (nice-to-have, not this iteration)
