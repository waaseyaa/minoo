# Group → `community_group` Entity Type Rename (#923)

**Date:** 2026-07-16 · **Anchor:** #923 · **Status:** SPEC — accepted, pre-implementation (design + adversarial review incorporated)
**Repo state evidenced against:** minoo `main @ f49923a` (post-#921); `waaseyaa/entity-storage` pinned `^0.1.0-alpha.249` (real Packagist install, verified not a symlink)
**DB evidence:** PROD snapshot `orchestration-reports/minoo-waaseyaa/db-snapshots/minoo-prod-snapshot-20260716.sqlite` + pre-#921 local-dev copy, both opened `mode=ro`. Canonical `storage/waaseyaa.sqlite` never opened.

---

## 1. Decision summary

Rename the entity type id `group` → `community_group` **and** rename the SQL table with it, via one shape-aware app migration that also converges PROD and LOCAL onto a single canonical table shape (core columns + `_data` blob). Ship type-id rename, migration, and code sweep in a single PR so schema and code change within one deploy run — the deploy's `db:init` one-shot applies the migration, then containers are recreated onto the new code (see §4.6 for the brief window between the two; it is not strictly atomic).

**Why the table must move with the id (no alternative exists):** the framework derives the table name from the type id unconditionally in three places — `SqlSchemaHandler.php:63`, `SqlEntityStorage.php:113`, `SqlEntityQuery.php:127` all do `$this->tableName = $this->entityType->id();` — and `EntityTypeInterface` exposes **no table-name accessor or override** (only `id()`). Subtable names likewise derive from the base table (`resolveSubtableName()` = `$baseTable . '__' . $bundle`, `SqlSchemaHandler.php:190-201`). Renaming the id without the table fails **silently**: `ensureTable()` (`SqlSchemaHandler.php:79-99`) creates any missing table on sight during the deploy's `db:init` schema-sync step (runtime never syncs schema — the only `syncAll()` callers are CLI handlers), so the next deploy would mint an empty `community_group`, the app would serve zero groups with no error, and the 15 real rows would be orphaned.

**Alternatives (rejected, one line each):**
- *Type-id rename with table-name override keeping SQL table `group`* — no override mechanism exists in the pinned framework; would require a framework feature first. Rejected.
- *Rename table only via schema-sync* — `EntitySchemaSync::syncAll()`/`ensureTable()` is additive-only ("re-runs never drop and never fail on existing tables", `SqlSchemaHandler.php:76-77`); it cannot rename, copy, or drop. A migration is mandatory. Rejected as insufficient.
- *Full rebuild-and-copy into byte-canonical DDL (fresh `community_group_uuid` constraint name)* — works, but more moving parts than `ALTER TABLE RENAME`; the frozen `group_uuid` UNIQUE constraint name is harmless cosmetic drift. Rejected for complexity.

---

## 2. Evidence base (what the rename actually touches)

- **Data:** 15 rows in `group`, all `type='business'`, identical logical entities in both environments (`sqlite_sequence` = `group|15` both). Businesses are cut/dormant (provider comment `EntityCommunityProvider.php:49-51`) — the public `/groups` list and feed group lane are empty today.
- **Two physical layouts for the same 15 rows:**
  - **PROD (pre-extract):** 6 legacy real columns on the base table (`social_posts`, `consent_public`, `consent_ai_training`, `latitude`, `longitude`, `coordinate_source`) + 13 business keys in `_data`; `group__business` exists but is **empty** and DBAL-shaped (created by framework schema-sync in the #739/#740 era — its DDL matches `SqlSchemaHandler::buildBundleSubtableSpec()` incl. the `group__business_fk` constraint name, `SqlSchemaHandler.php:886`).
  - **LOCAL (post-extract):** base `_data` is exactly `created_at,updated_at,status`; the 15 business rows live in `group__business` (hand-written DDL from `migrations/20260419_160000_extract_group_business_subtable.php:72-96`).
- **Ledger corruption (critical constraint):** PROD's `waaseyaa_migrations` (1002 rows / 47 packages, known bloat) **falsely records** `extract_group_business_subtable` as ran (batch 2026-06-12) — the schema proves it never executed. The rename migration therefore **cannot assume any prior shape from the ledger**; it must be shape-aware via `hasTable`/`hasColumn` guards, and the extract migration will never (re-)run on PROD.
- **References to group rows — zero today, everywhere swept:** the only SQL FK in either DB is `group__business.gid → group.gid` (full `pragma_foreign_key_list` sweep). Reference-bearing sites split into two storage shapes:
  - **JSON `_data` refs:** `reaction` (2 rows, both `target_type='post'` inside `_data`), `comment` (0 rows), `featured_item` (`entity_type` in `_data`, no group values), `path_alias` (0 group paths) — **0 group refs**.
  - **Real-column refs:** `follow.target_type` is a **real column**, not `_data` — the vendor declares it as the entity's label key (`engagement/src/Follow.php:13` `ContentEntityKeys(…, label: 'target_type')`), which `SqlSchemaHandler::buildTableSpec()` materializes as a base column and `SqlEntityStorage::splitForStorage()` always routes there (`columnExists()`-driven). `follow` has 0 rows. `search_metadata.entity_type` is a real column (the table has **no `_data` column at all**) — 0 `'group'` rows. `audit_event.entity_type_id` is a real column (1019 rows: `tm_backlog` 564, `example_sentence` 195, `game_session` 69, … — **zero `'group'`**). `embeddings.entity_type` is a real column (0 rows). `relationship`, `attachment`, `menu_link`, `workflow`, `node` also swept — 0 group refs.
  - `search_index` (FTS5) is empty in PROD. No feed table exists (pull-based, #814).
- **Current registration** (`EntityCommunityProvider.php:54-77`): entity-level `_fieldDefinitions`, sql-blob backend, **no bundle-scoped fields** — post-rename schema-sync would create `community_group` with core columns + `_data` only, and the framework never reads a bundle subtable under this registration. This makes "fold everything into `_data`" the shape the code already serves.
- **Bonus fork:** `group_type` (de-registered in #920, 0 rows both) has divergent shapes — PROD legacy, LOCAL reshaped. Dropped in the same migration.

---

## 3. Target end-state (both environments, byte-identical logical shape)

```sql
CREATE TABLE "community_group" (
  "gid" INTEGER PRIMARY KEY AUTOINCREMENT,
  "uuid" TEXT NOT NULL DEFAULT '',
  "type" TEXT NOT NULL DEFAULT '',
  "name" TEXT NOT NULL DEFAULT '',
  "langcode" TEXT NOT NULL DEFAULT 'en',
  "_data" TEXT NOT NULL DEFAULT '{}',
  CONSTRAINT "group_uuid" UNIQUE ("uuid")   -- frozen legacy constraint name; harmless
);
CREATE INDEX "community_group_bundle" ON "community_group" ("type");
```

15 rows, all business fields (incl. consent, geo, social_posts where present) folded into `_data`. No `group`, no `group__business`, no `community_group__business`, no `group_type`. `sqlite_sequence.name` auto-follows the rename. `cultural_group` untouched (separate de-registered type, out of scope).

---

## 4. The migration

**File:** `migrations/20260716_XXXXXX_rename_group_to_community_group.php` (new file — historical migrations are ledger; never edited). Runs inside a transaction where SQLite allows; every step guarded so re-runs and every known shape are no-ops or safe.

### 4.1 Guards (version check + idempotency)

```
if (sqlite_version() < 3.35)       → throw    // DROP COLUMN requires ≥3.35; same runtime guard
                                              // the 20260419 extract migration opens with
if (!hasTable('group'))            → return   // already migrated (community_group exists) or a DB
                                              // that never had the table — no-op either way
if (hasTable('community_group') && hasTable('group')) → throw   // half-state; refuse, investigate
```

**Fresh-DB reality (verified, not the happy path previously assumed):** a truly fresh `db:init` on an empty database never reaches this migration — it crashes earlier, inside `migrations/20260419_160000_extract_group_business_subtable.php`, whose `up()` has no `hasTable('group')` guard and executes `INSERT INTO group__business … SELECT … FROM "group"` against a table that nothing in the migration chain creates (`group` was historically schema-sync-minted; no earlier app or vendor-package migration creates it) → *no such table: group* → transaction rollback → `db:init` exits non-zero before the rename migration ever runs. Fresh environments are in practice provisioned from a DB copy (prod snapshot or dev copy), not from an empty file through the migration chain. Per the historical-ledger rule the 20260419 file is **not** edited in this PR; repairing the fresh-install path is filed as a follow-up (§10). The `!hasTable('group')` guard above stays — it is still correct for already-migrated DBs and for any future repaired fresh path.

### 4.2 PROD shape branch — `hasColumn('group', 'consent_public')` true

1. **Fold the 6 legacy columns into `_data`** (PHP row loop, 15 rows): decode `_data`, merge `consent_public`, `consent_ai_training` (always, they're NOT NULL with defaults) and `social_posts`/`latitude`/`longitude`/`coordinate_source` **only when non-NULL**; write back. No key collisions: PROD `_data` key sets (5 variants observed) contain none of these six. Fold-before-drop is required because registered fields with real columns (`consent_public`/`consent_ai_training`, provider lines 69-70) are served from the columns while they exist; once absent, sql-blob serves `_data`.
2. **Drop the 6 columns** — `ALTER TABLE "group" DROP COLUMN …` ×6 (SQLite ≥3.35, guarded in §4.1; none participates in an index/constraint). Guard each with `hasColumn`.
3. **Drop `group__business`** — assert `COUNT(*) = 0` first (it is 0 on PROD; if >0, abort loudly — that would mean an unknown shape).

### 4.3 LOCAL shape branch — `hasTable('group__business')` with rows

1. **Fold the subtable back into `_data`** (reverse of the 20260419 extract): per `gid`, merge all non-NULL business columns (the 20 minus `gid`) into the base row's `_data` (base `_data` is only `created_at,updated_at,status` — no collisions).
2. **Drop `group__business`.**

### 4.4 Common tail (both shapes)

1. `ALTER TABLE "group" RENAME TO community_group` — SQLite auto-updates `sqlite_sequence.name`; with default `legacy_alter_table=OFF` any FK references would be rewritten (moot — subtable already dropped).
2. `DROP INDEX IF EXISTS group_bundle; CREATE INDEX IF NOT EXISTS community_group_bundle ON community_group ("type")` — converges index naming with what a fresh sync would produce. `IF NOT EXISTS` is load-bearing: indexes survive `RENAME TO` under their old names, so a `down()`→`up()` cycle would otherwise crash on *index already exists*. The `group_uuid` UNIQUE constraint name is baked into the DDL and stays (documented cosmetic drift; renaming it would require a full table rebuild — not worth it).
3. `DROP TABLE IF EXISTS group_type` — 0 rows both environments, de-registered since #920, and it's the second shape fork owned by the never-ran-on-PROD extract migration. Same pass, done.
4. **Engagement/reference retargeting (defensive — every count is 0 in both environments today, see §2; the statements are cheap insurance against rows created between the 2026-07-16 snapshot and the actual cutover, and they make the migration correct on any future DB).** Two storage shapes, two statement forms — do not mix them:
   - **JSON `_data` refs** (`target_type`/`entity_type` live inside the `_data` blob — verified for these tables only):
     ```sql
     UPDATE reaction      SET _data = json_set(_data,'$.target_type','community_group') WHERE json_extract(_data,'$.target_type')='group';
     UPDATE comment       SET _data = json_set(_data,'$.target_type','community_group') WHERE json_extract(_data,'$.target_type')='group';
     UPDATE featured_item SET _data = json_set(_data,'$.entity_type','community_group') WHERE json_extract(_data,'$.entity_type')='group';
     ```
   - **Real-column refs** (plain UPDATEs — a `json_set(_data, …)` here is wrong: on `follow` it is a guaranteed silent no-op because `target_type` is the materialized label-key column the storage layer always writes (§2), and on `search_metadata` it **crashes the whole migration** — the table has no `_data` column, and `Migrator::applyLegacy()` wraps `up()` + the ledger row in one `transactional()`, so *no such column: _data* rolls everything back and fails `db:init` and the deploy):
     ```sql
     UPDATE follow          SET target_type    = 'community_group' WHERE target_type    = 'group';
     UPDATE search_metadata SET entity_type    = 'community_group' WHERE entity_type    = 'group';
     UPDATE audit_event     SET entity_type_id = 'community_group' WHERE entity_type_id = 'group';
     UPDATE embeddings      SET entity_type    = 'community_group' WHERE entity_type    = 'group';
     ```

### 4.5 `down()`

Rename-back only, mirroring every `up()` tail step:

1. `DROP INDEX IF EXISTS community_group_bundle` — indexes survive `RENAME TO` under their old names; without this drop, a later `up()` re-run would collide (and the renamed-back `group` table would carry a misnamed index).
2. `ALTER TABLE community_group RENAME TO "group"`; `CREATE INDEX IF NOT EXISTS group_bundle ON "group" ("type")`.
3. Reverse **all** §4.4 retargeting statements — the three `json_set` updates on `reaction`/`comment`/`featured_item` **and** the four plain-column updates on `follow`/`search_metadata`/`audit_event`/`embeddings`, each mapping `'community_group'` back to `'group'`.

It deliberately does **not** resurrect the legacy columns or subtable — after the fold, sql-blob serves everything from `_data`, so pre-rename code runs correctly against the folded shape. Primary rollback is the file-level DB restore (§8), not `down()`.

### 4.6 Deploy-path safety

`db:init` is **not** container startup: `minoo-app` runs bare php-fpm (`compose/docker-compose.yml:300-333` — no command override, no entrypoint). The deploy workflow (`waaseyaa-infra/.github/workflows/deploy-minoo.yml:69-70`) runs `docker compose run … bin/waaseyaa db:init --sync-schema` as a **one-shot**, then `up -d --force-recreate`.

Within that one-shot, ordering is safe: `DbInitHandler` runs pending migrations **first**, then schema-sync (`vendor/waaseyaa/cli/src/Handler/DbInitHandler.php:85-113`). So our migration renames/folds, then sync sees `community_group` already present with core columns + `_data` (matching the entity-level registration) and adds nothing. Sync never drops (`SqlSchemaHandler.php:76-77`; sole drop path is empty `_translations` siblings — inapplicable), so ordering is safe even on re-runs.

**Known window (accepted):** between the `db:init` one-shot (schema renamed) and `--force-recreate` completing (new code live), the old code serves against the renamed DB. Runtime never syncs schema, so `getStorage('group')` queries fail loudly with *no such table: group* — a brief 500 window on `/groups` and feed-group-lane paths. Both surfaces are dormant/empty today and the window is seconds long; this is a loud, bounded failure, not silent data loss.

---

## 5. Code sweep (RENAME items only — from the #923 occurrence map)

New type id string everywhere below: `community_group`.

| File | Lines | Change |
|---|---|---|
| `src/Provider/Entity/EntityCommunityProvider.php` | 55 | `id: 'community_group'` — THE registration; table name derives from it. Line 56 `label:` → `'Community Group'` (copy, same PR). The `group: 'groups'` nav arg on :60 is **not** the type id — leave it. |
| `src/Entity/Groups/Group.php` | 14 | `$entityTypeId = 'community_group'` |
| `src/Access/Groups/GroupAccessPolicy.php` | 13, 18 | `#[PolicyAttribute(entityType: ['community_group'])]`; `$entityTypeId === 'community_group'` |
| `src/Http/Controller/Groups/GroupController.php` | 82, 86, 109, 113 | `hasDefinition`/`getStorage` ×4. The other ~42 `group` matches in this file (namespace, `/groups/` URLs, display fallback, template paths) are KEEP. |
| `src/Domain/Feed/EntityLoaderService.php` | 63, 67 (+58 docblock) | `hasDefinition`/`getStorage` + docblock |
| `src/Domain/Feed/FeedItemFactory.php` | 58, 143, 148 | match arm, id prefix `'community_group:' . id`, `type: 'community_group'`. **Conflation hazard:** `url: '/groups/'.slug` and `badge: 'Group'` on :150-151 are KEEP — a naive sed corrupts the URL. |
| `src/Domain/Feed/FeedAssembler.php` | 70 | `'type' => 'community_group'` |
| `src/Http/Controller/Social/EngagementController.php` | 22-24 | `ALLOWED_TARGET_TYPES`: `'group'` → `'community_group'`. Lockstep with templates' `data-type="{{ item.type }}"` → POST `target_type` chain. |
| `src/Http/Controller/Feed/FeedController.php` | 406 | **Edit RHS only:** `'group' => 'community_group'`. LHS is the `?filter=group` URL param (product surface, KEEP — decided §6). |
| `templates/components/domain/search/result-card.html.twig` | 7 | **Edit LHS only:** `'community_group': 'group'`. RHS is the badge/CSS token (`card--group`, `search.badge_group`) — KEEP (decided §6). |
| `resources/lang/en.php` | 158, 167 | `feed.action_group` → `feed.action_community_group`, `feed.posted_group` → `feed.posted_community_group`. Dynamic keys built from `item.type` (`engagement.html.twig:20`, `card.html.twig:65`) — silent i18n breakage if missed. `oj.php` lacks these keys: no change there. |
| `public/css/utilities.css` | 2056 | `.feed-card--group` → `.feed-card--community_group`. Selector is generated at runtime (`feed-card--{{ item.type }}`, `card.html.twig:59,61`) — styling silently breaks if missed. |
| `tests/App/Unit/I18n/TranslationParityTest.php` | 36 | Update comment citing `feed.posted_group` |
| `CLAUDE.md` | 108, 126 | Entity-table row + provider list |
| `README.md` | 17 | Entities table |
| `docs/specs/entity-model.md` | 9, 46, 109, 316, 334, 368, 376 | Minimal type-id edits only in this PR (see §6 disposition on its independent staleness) |
| `docs/specs/events-ingestion.md` | 25, 36, 70-71 | `scope: ['event','community_group']` etc. — living W-0 spec (#919) |

**Verified nothing-to-do:** `bin/` (incl. `bin/smoke-test`) has **zero** `group` matches; post-deploy smoke is the `/groups` body-size curl + playwright specs. Everything in the occurrence map's KEEP class (product URLs `/groups`, route names, `groups.*` i18n, CSS layout classes, dialect/lesson "group" words, historical migrations and docs, minified vendor JS) is untouched by design.

**Also in this PR (one-line fixes):** `tests/App/Unit/Provider/ConsentFieldsTest.php:20-22` stale comment (claims `group` was extracted to `waaseyaa/groups` — false at f49923a; the package is not in composer.json and the type is registered locally).

---

## 6. Decisions on the occurrence map's DECIDE items

| # | Item | Decision |
|---|---|---|
| 2 | `?filter=group` URL param (FILTER_MAP key, `feed/index.html.twig:13`, `feed.filter_groups`) | **KEEP** — product URL surface, consistent with `/groups` staying. Edit FILTER_MAP RHS only. |
| 3 | `label: 'Group'` | Change to `'Community Group'` — copy, trivial, same PR. |
| 4 | `group__business` subtable | **Fold + drop** (both shapes), per §4 — with the current entity-level registration the framework never reads a subtable, so renaming it would leave 15 business rows permanently invisible; folding converges shape. Naming rule verified against pinned entity-storage (`resolveSubtableName()`). |
| 5 | `group_type` table / `ConfigSeeder::groupTypes()` | Drop the table in the migration (0 rows, de-registered, shape-forked). **Keep the method name** — it names the bundle vocabulary (`online/offline/advocacy/business`), which is unaffected by the type-id rename. |
| 6 | `entity-model.md` staleness | Minimal type-id edits in this PR; full refresh (deleted `GroupType.php`/`CulturalGroup*` references) filed as a separate issue. |
| 7 | Route names `groups.list`/`groups.show` | **KEEP** — product surface, tracks URLs not type ids. |
| 8 | Class/namespace/dir names (`GroupController`, `App\Entity\Groups\Group`, `templates/pages/groups/`, …) | **Out of scope** — separate optional refactor (non-goal §9). |
| 9 | Search badge token | **KEEP** the `'group'` badge value (preserves `card--group` CSS + `search.badge_group`); rename only the badge_map key. `search_index` is empty in PROD (no reindex debt), but run an index rebuild post-deploy as prudence. |

---

## 7. Test plan (TDD — write these failing first)

Coverage gap is total: **no PHP test anywhere references the type id `'group'`** (exact-literal sweep) — the rename has zero existing regression coverage. New tests, in implementation order:

1. **Migration round-trip, PROD shape** — in-memory SQLite fixture built with PROD's exact DDL (6 legacy columns + DBAL-shaped empty `group__business` + legacy `group_type`), 3-4 synthetic rows covering the observed `_data` key variants and NULL/non-NULL geo. Run migration → assert: `community_group` exists with core-only columns; consent/geo values folded into `_data`; `group`/`group__business`/`group_type` gone; row count preserved; `community_group_bundle` index present.
2. **Migration round-trip, LOCAL shape** — fixture with post-extract shape (populated subtable). Assert subtable values folded per `gid`, same end state as (1).
3. **Idempotency + down()→up() cycle** — run migration on the already-migrated shape and on an empty DB (neither table): both no-op cleanly. Run the half-state guard case: throws. Run the full `down()` → `up()` cycle on a migrated fixture: no crash (locks the index-recreation path — `down()` drops `community_group_bundle`, `up()` uses `CREATE INDEX IF NOT EXISTS`; a bare `CREATE INDEX` would fail on *index already exists*).
4. **Retargeting, both storage shapes** — seed one synthetic `'group'` reference per §4.4 site: `reaction`/`comment`/`featured_item` (inside `_data`) and `follow`/`search_metadata`/`audit_event`/`embeddings` (real columns). Assert all become `'community_group'` after `up()` and revert to `'group'` after `down()` — this locks the json-vs-plain-column split (F1/F2/F6).
5. **Boot with renamed type** — integration kernel boot (`WAASEYAA_DB=:memory:`): `hasDefinition('community_group')` true, `hasDefinition('group')` false, `getStorage('community_group')->getQuery()` executes (proves table-name derivation end to end).
6. **Engagement targeting** — `EngagementController` accepts `target_type='community_group'` and rejects `'group'` (422), locking `ALLOWED_TARGET_TYPES`.
7. **Feed item shape** — `FeedItemFactory` unit test: group entity produces `type='community_group'`, id `community_group:N`, and `url` still `/groups/{slug}` (locks the conflation site).
8. **i18n lockstep** — assert `feed.action_community_group` / `feed.posted_community_group` exist in `en.php` (extends `TranslationParityTest` territory).
9. **Unchanged:** playwright `/groups` URL specs (`accessibility`, `content-pages`, `empty-states` — 8 references) must pass with **no edits**; they are the product-surface invariant.

Gates: full `./vendor/bin/phpunit`, `composer phpstan`, `composer cs-fixer` — same as CI.

---

## 8. Production cutover & rollback

**Deploy path (per repo memory / runbook `06-minoo-deploy.md`):** minoo.live deploys from `waaseyaa-infra` via the `MINOO_REF` pin → Raspberry Pi containers; the legacy Deployer is disabled. `db:init` is a **deploy-workflow one-shot**, not container startup: the workflow runs `docker compose run … bin/waaseyaa db:init --sync-schema` and then `up -d --force-recreate` (`deploy-minoo.yml:69-70`); `minoo-app` itself is bare php-fpm with no entrypoint db:init. **NEVER run bare `bin/waaseyaa migrate` on the Pi** — the migration executes only via the pinned-ref `db:init` flow so code and schema change in the same deploy run.

**Cutover steps:**
1. **Pre-flight backup on the Pi:** `sqlite3 /app/storage/waaseyaa.sqlite ".backup /app/storage/waaseyaa-pre-923.sqlite"` (live-safe) on the `minoo_storage` volume. Record the current `MINOO_REF`.
2. Merge the single #923 PR to `main`, tag.
3. Bump `MINOO_REF` in `waaseyaa-infra` `compose/docker-compose.yml`; deploy per runbook (honouring the alpha.250 secret requirement).
4. The workflow's `db:init` one-shot runs the rename migration, then sync (no-op adds); containers are then recreated onto the new code. Expect the brief old-code 500 window on `/groups`/feed-lane paths between the two steps (§4.6) — loud, bounded, dormant surface.
5. **Verify** (status codes lie — check body size per CLAUDE.md gotcha): `curl -sS -o /dev/null -w "%{http_code}/%{size_download}" https://minoo.live/groups` non-zero body; in-container `bin/waaseyaa migrate:status` shows the rename migration ran; `sqlite3` spot-check: `community_group` 15 rows, no `group`/`group__business`/`group_type`; playwright smoke against `/groups`.
6. Post-deploy: trigger a search index rebuild (prudence; index currently empty).

**PROD ledger caveat:** the new migration's ledger row lands in the known-bloated `waaseyaa_migrations` (1002 rows) — harmless; the migration's correctness never consults the ledger (shape guards only), precisely because PROD's ledger already lies about the 20260419 extract.

**Rollback (primary):** repin the previous `MINOO_REF` and restore the `.backup` file over `/app/storage/waaseyaa.sqlite`. Data-loss window is effectively nil: the group tables are dormant (businesses cut, `/groups` and the feed group lane empty), zero engagement rows reference groups, and no other table writes are affected by the rename — but the restore rolls back the *whole* DB, so if unrelated writes occurred in the window, use the secondary path instead.

**Rollback (secondary, no data loss) — order is critical: `down()` first, under the NEW ref; repin only after.**
1. While the new ref is still deployed, run the migration's `down()` via a one-off in-container invocation (`docker compose run … bin/waaseyaa migrate:rollback`) — rename-back + index swap + full retargeting reversal (§4.5). Old code serves the folded `_data` shape correctly under sql-blob.
2. Only then repin the old `MINOO_REF` and redeploy.

**Never repin first.** The deploy flow runs `db:init` before recreate, so the old ref's schema-sync — old registration `id: 'group'` + no `group` table — would `ensureTable()`-mint an empty `group` table (the exact silent zero-groups failure §1 warns about), after which `down()`'s `ALTER TABLE community_group RENAME TO "group"` fails with *table group already exists*. Worse, running `migrate:rollback` under the old ref (which lacks the migration file) hits `Migrator::rollback()`'s missing-file behavior: it **removes the ledger row without executing `down()`** — ledger says never-ran while the DB is still renamed; a later roll-forward re-runs `up()` against the sync-minted half-state and the §4.1 guard throws, bricking deploys until manual surgery.

**Framework rollback caveats (both verified in vendor `foundation/src/Migration/Migrator.php`):**
- `migrate:rollback` (`MigrateRollbackHandler`) rolls back the **entire last batch** (`getByBatch`), not a single migration — if anything else shipped in the same deploy's batch, its `down()` runs too. Check `migrate:status` and confirm batch contents before invoking; prefer shipping this migration as the only one in its deploy.
- The missing-file behavior above (`if (isset($flat[$name])) { … down() … } $this->repository->remove($name)`) silently drops ledger rows for migrations whose files are absent from the running code — the second reason `down()` must run under the new ref.

---

## 9. Non-goals (explicit)

- **`/groups` URL surface stays** — paths, `groups.list`/`groups.show` route names, `?filter=group` param, `'businesses' => '/groups'` redirect, `scripts/generate-og.js` URLs, playwright specs.
- **No class/namespace/directory renames** — `GroupController`, `App\Entity\Groups\Group`, `Access/Groups/`, `templates/pages/groups/`, `components/domain/groups/` keep their names.
- **Framework groups package:** `waaseyaa/groups` is **not** in composer.json at f49923a (verified); it stays uninstalled — no adoption or removal as part of this rename.
- **No API exposure change** — no new endpoints; the only contract delta is the engagement `target_type` vocabulary (`'group'` → `'community_group'`), which has zero stored rows and zero live clients (group lane empty).
- **No consent-semantics, geo, or business-bundle revival** — businesses stay dormant/cut; geo stays Phase 5; folded values are preserved verbatim in `_data`.
- **`cultural_group`** (separate de-registered type, present in PROD only) untouched.
- **i18n copy beyond the two dynamic keys** — `groups.*`, `nav.groups`, `feed.filter_groups`, `search.badge_group` untouched.
- **Historical ledger untouched** — migrations (including the unguarded 20260419 extract, see §4.1), `docs/plans|reviews|milestones|…`, CHANGELOG history, `kitty-specs/`; the rename ships as a new migration and a CHANGELOG `[Unreleased]` entry only.

## 10. Follow-ups to file separately

1. Full refresh of `docs/specs/entity-model.md` + `docs/architecture/entity-layer.md` (stale independently of this rename: deleted `GroupType.php`/`CulturalGroup*` classes, de-registered types).
2. PROD `waaseyaa_migrations` ledger-bloat cleanup (1002 rows / 47 packages; mark-without-execute batch of 2026-06-12) — this design works around it but does not fix it.
3. **Fresh-install path repair:** a fresh `db:init` on an empty DB crashes inside the unguarded `20260419_160000_extract_group_business_subtable.php` `up()` (§4.1) — decide whether to add a guarded superseding migration, a seeded baseline DB, or a documented provision-from-snapshot rule. This rename neither causes nor fixes it.
4. Optional cosmetic: `ConfigSeeder::groupTypes()` → `communityGroupTypes()` rename.
