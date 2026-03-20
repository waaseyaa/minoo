# Dictionary Sync Verification Results

## Environment

- Verification timestamp: `2026-03-20T19:44:55-04:00`
- PHP: `PHP 8.4.18 (cli) (built: Feb 13 2026 16:00:19) (NTS)`
- Git commit: `c012e02`
- Isolated SQLite database: `/tmp/minoo-dictionary-sync-GI354B.sqlite`

## Live NC Endpoint Checks

Observed against the current Minoo default base URL:

- `curl -i -sS "https://northcloud.one/api/v1/dictionary/entries?limit=1&offset=0" | sed -n '1,40p'`
  - `HTTP/2 200`
  - `content-type: text/html`
  - body is the North Cloud search SPA shell, not JSON
- `curl -i -sS "https://northcloud.one/api/v1/dictionary/search?q=makwa" | sed -n '1,40p'`
  - `HTTP/2 200`
  - `content-type: text/html`
  - body is the same SPA shell, not JSON

Implications:

- The current Minoo default `NORTHCLOUD_BASE_URL=https://northcloud.one` does not expose the dictionary API contract needed by `NorthCloudClient`.
- `jq` parsing of both endpoints fails immediately because the response is HTML instead of JSON.

Additional repo-level verification:

- North Cloud issue `#483` is closed, and merged PR `jonesrussell/north-cloud#486` added dictionary routes in `source-manager`.
- The merged `source-manager` router exposes:
  - `GET /api/v1/dictionary/entries`
  - `GET /api/v1/dictionary/entries/:id`
  - `GET /api/v1/dictionary/words/:id`
  - `GET /api/v1/dictionary/search`
- The merged handler returns JSON with `entries`, `total`, `attribution`, `limit`, `size`, and pagination metadata.

Conclusion:

- The code contract exists in North Cloud mainline.
- The live host/path Minoo is configured to call is not currently serving that contract.
- This is a deployment/routing/base-URL integration mismatch, not evidence of a missing Minoo mapper or template feature.

## Dry-Run Sync

Command:

- `php bin/sync-dictionary --dry-run`

Observed output:

```text
DRY RUN — no changes will be written.

Fetching page 1 (limit 200)...

DRY RUN — no changes written.
Done: 0 fetched, 0 created, 0 updated, 1 errors.
NorthCloud dictionary entries response malformed
ERROR: Failed to fetch dictionary entries from NorthCloud (page 1).
```

Interpretation:

- `bin/sync-dictionary --dry-run` does not crash under the current default config.
- It does not produce a usable preview because the upstream response is HTML instead of the expected dictionary JSON payload.
- The immediate failure is inside `NorthCloudClient::getDictionaryEntries()` response parsing, not in the mapper or storage layer.

## Isolated Real Sync

Not attempted beyond setup because the live API contract was not reachable at the configured base URL.

Verification constraint discovered:

- An empty temp SQLite file plus `WAASEYAA_DB=<temp> php bin/waaseyaa migrate` is not sufficient to produce a usable `dictionary_entry` table in this repo.
- The existing local app DB contains `dictionary_entry` and currently reports `21721` rows, so this repo relies on a prebuilt application DB shape rather than migrations alone for entity-table availability.

## Post-Sync Entity Inspection

Blocked by the live API/base-URL mismatch and the inability to bootstrap a fresh isolated DB to the full app schema from migrations alone.

## SSR Verification

Blocked in this pass because no verified live dictionary sync could be performed against an isolated dataset.

## Acceptance Criteria Status

- [ ] `bin/sync-dictionary` successfully pulls entries from NC and stores locally
  - Failed in this pass because the configured live NC base URL returned HTML instead of JSON.
- [ ] `/language` page displays dictionary entries
  - Not re-verified in this pass because live sync did not succeed.
- [ ] Entry detail pages show full data (definition, POS, inflections)
  - Not re-verified in this pass because live sync did not succeed.
- [ ] Attribution displayed on every entry
  - Implementation exists from merged PR `#333`, but live synced rendering was not re-verified in this pass.
- [ ] Consent governance respected (only public entries visible)
  - Could not be verified end to end because the live sync did not reach entity storage.
- [ ] `bin/sync-dictionary --dry-run` previews without writing
  - Partial: it exits without writing or crashing, but it does not produce a real preview under the current live NC base URL.

## Follow-Up Recommendations

1. Confirm the actual deployed base URL for the North Cloud `source-manager` dictionary API and set `NORTHCLOUD_BASE_URL` in Minoo to that value.
2. Re-run the verification pass once the live base URL returns JSON for:
   - `GET /api/v1/dictionary/entries`
   - `GET /api/v1/dictionary/search`
3. If isolated verification is still required, define the supported way to bootstrap a disposable app DB with entity tables in addition to app migrations.
4. After the base URL is corrected, re-run:
   - `php bin/sync-dictionary --dry-run`
   - `WAASEYAA_DB=<isolated-db> php bin/sync-dictionary`
   - `/language` and `/language/{slug}` SSR checks against the synced isolated dataset
