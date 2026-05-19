---
work_package_id: WP04
title: Thread Account Through Domain Services & Infrastructure
dependencies:
- WP01
requirement_refs:
- FR-004
- FR-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Execution worktree allocated per `finalize-tasks` lane. Squash-merges back via implement-review.
subtasks:
- T022
- T023
- T024
- T025
- T026
history:
- at: '2026-05-19'
  by: specify
  note: WP created from spec.md §10 WP04
authoritative_surface: src/Domain/
execution_mode: code_change
mission_id: 01KS0WZ7MX6P96NP0V95RBTPG2
mission_slug: adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7
owned_files:
- src/Domain/**
- src/Infrastructure/**
- src/Ingestion/**
- tests/App/Unit/Domain/**
- tests/App/Unit/Infrastructure/**
- tests/App/Unit/Ingestion/**
- tests/App/Integration/Domain/**
- tests/App/Integration/Ingestion/**
tags: []
---

# WP04 — Thread Account Through Domain Services & Infrastructure

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Branch contract**: planning base = `main`, final merge target = `main`.
**Run command**: `spec-kitty agent action implement WP04 --agent <name>`
**Requirement refs**: FR-004, FR-007

## Objective

Convert every `getQuery()` call site in `src/Domain/**`, `src/Infrastructure/**`, and `src/Ingestion/**` to either bind the threaded account (for user-facing services) or use an audited `accessCheck(false)` bypass (for system-context services like the ingestion materializer).

## Context

Services are called from controllers (WP02/WP03) or from CLI handlers (WP05). The pattern depends on the caller:

- **Service called from controller** → service method gains an `AccountInterface $account` parameter; controller passes `$this->account` at the call site. Bind inside the service: `$storage->getQuery()->setAccount($account)`.
- **Service called from CLI/console** → service method's account parameter accepts `null`; null → `accessCheck(false)` branch (conditional fallback), or the service is split into a user-context and system-context variant.
- **Service called from save-time validators / integrity checks** → unconditional `accessCheck(false)` with audit-doc comment (system context).

**Coordination note**: If WP03 runs in parallel, FeedController's `EntityLoaderService` calls need a coordinated method-signature change. WP04 is authoritative for the signature; WP03 adopts.

## Files Owned by This WP

- `src/Domain/Feed/{EntityLoaderService,EngagementCounter}.php` (2 files)
- `src/Domain/Feed/Scoring/{AffinityCalculator,EngagementCalculator}.php` (2 files)
- `src/Domain/Events/Service/EventFeedBuilder.php` (1 file)
- `src/Domain/Games/GameStatsCalculator.php` (1 file)
- `src/Domain/Geo/Service/LocationService.php` (1 file)
- `src/Domain/Newsletter/Service/NewsletterAssembler.php` (1 file)
- `src/Infrastructure/Fixture/FixtureResolver.php` (1 file)
- `src/Infrastructure/OpenGraph/{CrisisOgImageService,PublicOgEntityLoader}.php` (2 files)
- `src/Ingestion/IngestMaterializer.php` (1 file)
- Corresponding test files

**12 files** affecting **~35 of the 131 unaudited `getQuery()` call sites**.

## Subtasks

### T022 — Feed services (EntityLoaderService, EngagementCounter, Scoring)

**Purpose**: Thread account through the 4 feed service files. `FeedController` (WP03) and `EngagementController` (WP03) call these.

**Steps**:
1. Read each of the 4 files.
2. For each method that calls `getQuery()`, add `AccountInterface $account` as a parameter. Place it positionally where it makes sense — typically first or last in the public methods.
3. Inside the method, append `->setAccount($account)` to each query chain.
4. Update every caller of the modified methods (mostly in `src/Http/Controller/Feed/` and `src/Http/Controller/Social/` — but those are WP03's owned files, coordinate at lane time).
5. For `Scoring/AffinityCalculator` and `Scoring/EngagementCalculator`, the account is needed to filter "this user's signals" — these probably already receive a user reference (perhaps as ID, not `AccountInterface`); converge on `AccountInterface`.

**Files**: 4 service files + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Domain\\\\Feed'` exits 0; `GameStatsCalculator::build($account)` returns expected counts.

---

### T023 — Domain services (EventFeedBuilder, GameStatsCalculator, LocationService, NewsletterAssembler)

**Purpose**: Thread account through 4 domain services.

**Steps**:
1. For each, add `AccountInterface $account` parameter to the public methods that call `getQuery()`.
2. Append `->setAccount($account)` to each query.
3. Update callers (controllers from WP02/WP03; CLI handlers in WP05).
4. **`LocationService` special case**: per `docs/specs/geo-domain.md`, this service may run from background jobs (geocoding). Methods that have no account scope (system context — building the band-office cache) use `->accessCheck(false)` with audit-doc inline comment.
5. **`GameStatsCalculator::build()`** already receives an `$account` parameter per CLAUDE.md gotcha — just append `->setAccount($account)` inside.

**Files**: 4 service files + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Domain\\\\(Events|Games|Geo|Newsletter)'` exits 0.

---

### T024 — Infrastructure adapters (FixtureResolver, OpenGraph services)

**Purpose**: Thread account or bypass in 3 infrastructure files.

**Steps**:
1. **`FixtureResolver`** — used by test fixtures and the `php scripts/populate_*.php` scripts. System context. Use `->accessCheck(false)` with audit-doc inline comment.
2. **`OpenGraph/CrisisOgImageService`** — generates social-share images. Likely system context (called from cron/queue worker rendering OG images for public content). Use `->accessCheck(false)` with audit-doc inline comment. If called from any user-facing path, switch that call path to thread an account.
3. **`OpenGraph/PublicOgEntityLoader`** — fetches public entities for OG card rendering. Anonymous-friendly. Use the **conditional fallback** shape: thread `?AccountInterface $account = null` and bypass when null.

**Files**: 3 files + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Infrastructure\\\\(Fixture|OpenGraph)'` exits 0.

---

### T025 — Ingestion materializer (system-context bypass)

**Purpose**: `IngestMaterializer` runs from `bin/waaseyaa ingest:nc-sync` (CLI) and dedupe-queries existing entities. Pure system context.

**Steps**:
1. Read `src/Ingestion/IngestMaterializer.php`.
2. Identify the `getQuery()` site(s).
3. Use unconditional `->accessCheck(false)` with inline comment:
   ```php
   // System context: materializer must see every existing entity to dedupe across all communities.
   // See docs/security/sql-entity-query-access-check-bypass-audit.md.
   $existing = $storage->getQuery()
       ->accessCheck(false)
       ->condition('uuid', $uuid)
       ->execute();
   ```

**Files**: 1 file + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Ingestion'` exits 0; `bin/waaseyaa ingest:nc-sync --dry-run --limit=5` runs without `MissingQueryAccountException`.

---

### T026 — Audit caller sites

**Purpose**: After T022–T025 land the new method signatures, walk every caller of the changed methods and confirm they pass an account. This catches missed updates.

**Steps**:
1. For each method that now takes `AccountInterface $account`, grep the codebase:
   ```bash
   grep -rn "->methodName(" src/ tests/
   ```
2. Confirm every call passes an account argument. Fix missed callers.
3. Run the WP04 test slice. Any `TypeError: too few arguments` indicates a missed caller.

**Files**: None directly — verification step.

**Validation**: `./vendor/bin/phpunit --filter '(Domain|Infrastructure|Ingestion)'` exits 0 with zero `TypeError` traces.

---

## Definition of Done

- [ ] All 5 subtasks (T022–T026) complete.
- [ ] All 12 service files modified.
- [ ] Every method that calls `getQuery()` either accepts an account parameter (and binds) or has an audit-doc-commented `->accessCheck(false)` bypass.
- [ ] Every caller of changed signatures passes an account.
- [ ] `./vendor/bin/phpunit --filter '(Domain|Infrastructure|Ingestion)'` exits 0.

## Risks

- **Cross-WP coordination on FeedController.** If WP03 changes FeedController to call new EntityLoaderService methods before WP04 lands the signature change, WP03 will fail. Coordinate at lane time — if running parallel, one WP signals the other when its signature change is committed.
- **Anonymous-context paths in OG services.** PublicOgEntityLoader serves OG cards for anonymous shares; bypass is wrong (cards should respect view access). The conditional fallback shape is the right choice.
- **GameStatsCalculator silently returning zero.** Per CLAUDE.md gotcha, missing `game_type` causes this. Not changed by this WP — just be aware.

## Reviewer Guidance

- Grep `grep -rnE 'getQuery\(\)' src/Domain/ src/Infrastructure/ src/Ingestion/` — every match should be within 2 lines of a `->setAccount(` or `->accessCheck(false)`.
- Each `->accessCheck(false)` must have an inline comment referencing the audit doc; WP05 will enumerate them.
- Approve when the slice test runs green and the audit step T026 finds no missed callers.
