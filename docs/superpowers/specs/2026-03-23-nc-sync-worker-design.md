# NC Content Sync Worker (#495)

## Context

Minoo's `NcContentSyncService` pulls indigenous content from NorthCloud's Search API and creates teachings/events. Currently manual — needs a long-running worker to keep content flowing automatically.

**Dependencies:**
- `NcContentSyncService` (merged, deployed, working)
- NC Search API with `topics[]=indigenous` (live, 8,009 articles tagged)
- Caddy route for NC Search (live, needs stable IP fix #494)
- ConsoleKernel broken (#493) — worker uses HttpKernel workaround

## Architecture

```
systemd (minoo-nc-sync.service)
  └── php scripts/nc-sync-worker.php
        ├── Boot HttpKernel (reflection workaround)
        ├── Create NcContentSyncService (15s timeout)
        └── Loop:
              ├── sync(limit: 20) → teachings/events
              ├── Write status to storage/nc-sync-status.json
              ├── Log result to stdout (journalctl)
              ├── Check SIGTERM/SIGINT flag
              └── sleep(1800) — 30 minutes
```

## Components

### 1. Worker Script (`scripts/nc-sync-worker.php`)

- Boots `HttpKernel` via reflection (same pattern as `populate_featured.php`)
- Registers `pcntl_signal()` handlers for `SIGTERM` and `SIGINT` — sets `$running = false`
- Validates config at startup: exits with error if `northcloud.base_url` is empty
- Calls `pcntl_async_signals(true)` for reliable signal delivery
- Main loop: while `$running`, call `sync()`, write status, sleep
- Exits after 48 cycles (~24 hours) to prevent memory leaks; systemd restarts fresh
- Sleep uses a 1-second tick loop checking `$running` so shutdown is responsive (doesn't block for 30 min)
- Logs each cycle: `[2026-03-23 05:30:00] Sync: created=3 skipped=17 failed=0`

### 2. Systemd Service (`deploy/minoo-nc-sync.service`)

```ini
[Unit]
Description=Minoo NC Content Sync
After=network.target

[Service]
Type=simple
User=deployer
WorkingDirectory=/home/deployer/minoo/current
ExecStart=/usr/bin/php scripts/nc-sync-worker.php
Restart=on-failure
RestartSec=30
TimeoutStopSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=default.target
```

Installed as system service (not user service — avoids `XDG_RUNTIME_DIR` issues with Deployer SSH). Requires `loginctl enable-linger deployer` or install as system-level with `User=deployer`.

### 3. Status File (`storage/nc-sync-status.json`)

Written after each sync cycle:

```json
{
  "last_sync": "2026-03-23T05:30:00+00:00",
  "created": 3,
  "skipped": 17,
  "failed": 0,
  "fetch_failed": false,
  "cycles": 12
}
```

Written atomically (temp file + `rename()`). Monitoring: if `last_sync` is older than 2x the interval, the worker is stale.

### 4. Deployer Hook

Add to `deploy.php` post-deploy:
```php
task('nc-sync:restart', function () {
    run('systemctl --user restart minoo-nc-sync || true');
});
after('deploy:symlink', 'nc-sync:restart');
```

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Process manager | systemd | Already used for PHP-FPM and Caddy on this server |
| Poll interval | 30 minutes | Indigenous news isn't time-critical; keeps ES load low |
| State management | Stateless + dedup | `source_url` dedup already works; no state file to corrupt. Future: add `since` param if content volume grows |
| Batch size | 20 per cycle | Matches NC Search API default page size |
| Shutdown | SIGTERM + tick loop | Responsive shutdown without blocking on 30-min sleep |
| Kernel | HttpKernel | ConsoleKernel broken (#493); HttpKernel workaround is proven |

## Error Handling

- **NC unreachable:** `fetchFailed=true` logged, status file updated, worker continues next cycle
- **Entity creation failure:** Counted as `failed`, logged with source URL, worker continues
- **PHP fatal:** systemd `Restart=on-failure` restarts after 30s
- **Deploy:** Deployer restarts service after symlink swap

## Testing

- Extract loop logic into testable `NcSyncWorkerLoop` class with injectable sleep callable and running flag
- Unit test: mocked sync service, verify cycle counting, status file JSON, max-cycle exit
- Unit test: config validation exits on missing base_url
- `NcContentSyncService`: already covered (6 integration tests)
- Manual: `journalctl -u minoo-nc-sync -f` to watch live
- Note: `pcntl_signal` is process-level and not unit-testable; loop flag is the testable boundary

## Files

| File | Purpose |
|------|---------|
| `scripts/nc-sync-worker.php` | Worker script |
| `deploy/minoo-nc-sync.service` | Systemd unit file |
| `deploy.php` | Add restart hook (if Deployer config exists) |
| `tests/Minoo/Unit/NcSyncWorkerTest.php` | Loop + signal + status tests |
