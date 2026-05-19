# Adopt Waaseyaa alpha.182 Access-Checking Contract

**Mission ID:** `01KS0WZ7MX6P96NP0V95RBTPG2` (`mid8: 01KS0WZ7`)
**Mission slug:** `adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`
**Mission type:** `software-dev`
**Target branch:** `main`
**Created:** 2026-05-19

## 1. Problem & Motivation

Minoo runs against the Waaseyaa framework via versioned `composer` constraints. The framework shipped two new alpha releases since our last sync:

- **alpha.181** (2026-05-19, framework mission `sql-entity-query-access-checking-01KRYP15` / #1495) made `SqlEntityQuery::accessCheck(true)` the default. The previously no-op stub now applies a real per-row filter through `EntityAccessHandler::check($entity, 'view', $account)`. Every entity query that does not bind an account via `EntityQueryInterface::setAccount(?AccountInterface)` — and does not explicitly opt out via `accessCheck(false)` — throws `Waaseyaa\EntityStorage\Exception\MissingQueryAccountException`.
- **alpha.182** (2026-05-19) is an additive follow-up that patches two framework-internal bypass sites missed by alpha.181 (`PathAliasResolver` and `Groups\Tests\Integration\TwoBundleCoexistenceTest`). Pinning past alpha.181 without also picking up alpha.182 leaves SSR routing broken under fail-closed checking.

Minoo currently has **135 `getQuery()` call sites** in `src/`. Only 4 already opt in to the new contract (3 in seed/demo handlers using `accessCheck(false)`, 1 in `EventController`). The remaining **131 sites will throw `MissingQueryAccountException` on the first request** after the bump — affecting feed loading, every public-SSR controller (Community, Language, Groups, Teachings, OralHistory, Events), authenticated API controllers (engagement, newsletter, games, admin), domain services (`Feed/EntityLoaderService`, search providers, ingestion materializer), and console handlers.

This mission upgrades the framework dependency and audits every Minoo `getQuery()` site, converting each to one of three legal shapes per the framework's authoritative pattern in `docs/security/sql-entity-query-access-check-bypass-audit.md`.

## 2. User Scenarios & Testing

### Primary scenarios

**Scenario A — Anonymous visitor browses public content (regression).**
A visitor lands on `https://minoo.live/` without a session. The framework's `SessionMiddleware` resolves an anonymous `AccountInterface` and binds it to `_account`. Every controller that builds a public list (events, groups, teachings, language, communities, dictionary entries) returns the published rows the anonymous account is allowed to see. No `MissingQueryAccountException` is thrown.

**Scenario B — Authenticated user views the feed.**
A signed-in user requests `/feed`. The controller pulls posts, reactions, comments, follows via `EntityLoaderService`. Every query inside `EntityLoaderService` binds the request's authenticated account; the feed renders the rows visible to that user under the existing access policies (`PostAccessPolicy`, `EngagementAccessPolicy`, etc.).

**Scenario C — Coordinator views Elder Support workflow.**
A coordinator opens the Elder Support dashboard. The controller queries `elder_support_request` entities. The query binds the coordinator's account, and the coordinator's elevated policy decisions are honoured. Rows that other accounts could not see are still filtered if the coordinator does not have the policy.

**Scenario D — Ingestion job materializes North Cloud content.**
The `bin/waaseyaa ingest:nc-sync` CLI runs `IngestMaterializer`, which queries existing teachings/events to deduplicate. There is no request account in a CLI context. The query opts out via `accessCheck(false)` with a documented justification in `docs/security/sql-entity-query-access-check-bypass-audit.md` (system context: materializer must see every existing row to dedupe).

**Scenario E — Save-time validator runs inside a transaction.**
A user saves a `Teaching` entity. A domain validator inside the save transaction queries related entities to enforce integrity constraints. The query opts out via `accessCheck(false)` because the validator runs without a request-scoped account and integrity checks must span access boundaries by design (mirrors framework pattern at `packages/relationship/src/RelationshipValidator.php`).

### Edge cases

- A controller that previously rendered "no results" for an account that lacked view access now still renders "no results" (semantically identical from the user's perspective). No 500 surface regression.
- A misconfigured controller that forgets to bind an account causes `MissingQueryAccountException` (fail-closed) — caught in tests, never reaches production.
- A bypass site without a corresponding audit-doc row fails the audit-doc completeness check (manual review in mission-review WP).

## 3. Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `composer.json` pins every `waaseyaa/*` package constraint to `^0.1.0-alpha.182`. | Open |
| FR-002 | `composer.lock` resolves every `waaseyaa/*` package to the `v0.1.0-alpha.182` tagged release. | Open |
| FR-003 | `composer install` from a clean state succeeds against the bumped lockfile. | Open |
| FR-004 | Every `getQuery()` call site in `src/` is converted to one of three legal shapes: (a) `->setAccount($account)` for user-facing reads with an in-scope account; (b) conditional fallback (`setAccount($account)` when present, `accessCheck(false)` otherwise) for surfaces that may run with or without an account; (c) unconditional `->accessCheck(false)` for system contexts (seeders, save-time validators, integrity checks, CLI tooling, system lookups). | Open |
| FR-005 | Public SSR controllers (`src/Http/Controller/Community/*`, `Language/*`, `Groups/*`, `Teachings/*`, `OralHistory/*`, `Events/*`) bind the request's `_account` attribute on every `getQuery()` they emit. | Open |
| FR-006 | Authenticated API controllers (engagement, newsletter, games, admin) bind the request's authenticated account on every `getQuery()` they emit. | Open |
| FR-007 | Domain services (`src/Domain/Feed/EntityLoaderService.php`, search providers under `src/Search/`, ingestion components under `src/Ingestion/`) either accept the account as a method parameter and bind it, or document their system-context bypass. | Open |
| FR-008 | Console handlers under `src/Console/` (`GenealogyDemoSeedHandler`, ingestion sync handlers, future handlers) declare their system-context bypass via `accessCheck(false)` with inline reference to the audit doc. | Open |
| FR-009 | `docs/security/sql-entity-query-access-check-bypass-audit.md` exists in the Minoo repo, mirrors the structure of the framework doc, and lists every Minoo `accessCheck(false)` call site under one of the two categorized sections (unconditional bypass or conditional fallback) with file path, line number, justification, and last-reviewed date. | Open |
| FR-010 | Every `accessCheck(false)` call site in `src/` carries an inline comment referencing `docs/security/sql-entity-query-access-check-bypass-audit.md`. | Open |
| FR-011 | `CLAUDE.md` "Last framework sync" line is updated to `Waaseyaa alpha.182` with a short highlights block summarizing the alpha.181 access-checking change (one paragraph) and the alpha.182 follow-up (one sentence). | Open |
| FR-012 | The MEMORY.md "Project State" framework-version line reflects the bump. | Open |
| FR-013 | A follow-up GitHub issue is filed against `waaseyaa/minoo` capturing each out-of-scope alpha.181 surface that Minoo will eventually adopt: AI agent executor (#1496), 2FA endpoint enablement (#1499), dead-code Phase 4 gate (#1500). | Open |

## 4. Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|---|---|---|---|
| NFR-001 | Test suite green after bump. | `./vendor/bin/phpunit` exits 0 with zero `MissingQueryAccountException` raised across the full suite (currently 914 tests / 2568 assertions). | Open |
| NFR-002 | Anonymous SSR works under fail-closed access checking. | `curl -sS -o /tmp/home.html -w "%{http_code}/%{size_download}\n" http://localhost:8080/` returns `200/` with `size_download > 1000` and `/tmp/home.html` contains a `<title>` tag. | Open |
| NFR-003 | Authenticated feed works under fail-closed access checking. | Authenticated `curl` (with session cookie) to `/feed` returns `200/` with `size_download > 1000` and the rendered HTML contains the feed container element. | Open |
| NFR-004 | Repository boundary check stays green. | `bin/check-milestones` exits 0 with the same `OK: No boundary violations detected.` line. | Open |
| NFR-005 | No regression in PHP static analysis. | `composer phpstan` exits 0 (PHPStan level 5 on `src/` with current baseline). | Open |
| NFR-006 | CS-Fixer remains green. | `composer cs-fixer` exits 0 (dry-run clean). | Open |
| NFR-007 | Mission lands on a single mission branch and merges to `main` only when every WP is approved and the full quality gate is green. | Mission branch state at merge: all 6 WPs approved, `phpunit` green, `phpstan` green, `cs-fixer` green, scenarios A–E manually verified. | Open |

## 5. Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | The composer bump must come first in WP01. `EntityQueryInterface::setAccount()` is new in alpha.181; calling it under the alpha.180 lock is a fatal error. WPs 2–5 therefore land on top of a red mission branch and bring it back to green. | Active |
| C-002 | `main` must not be polluted by the in-progress red state. All WPs land on the mission branch (`kitty/mission-adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`); mission only squash-merges to `main` once green. | Active |
| C-003 | The three legal shapes are exhaustive. A `getQuery()` call site that does not bind an account and does not opt out is a defect. No fourth shape (e.g. swallowing `MissingQueryAccountException`) is acceptable. | Active |
| C-004 | Every new `accessCheck(false)` call must be added to `docs/security/sql-entity-query-access-check-bypass-audit.md` in the same WP that introduces it. A bypass without an audit-doc row is a defect. | Active |
| C-005 | This mission does not adopt the AI agent executor (#1496), the 2FA endpoint enablement (#1499), or the dead-code Phase 4 gate (#1500). Those are out of scope and tracked separately. | Active |
| C-006 | The Waaseyaa MCP server's `minoo-specs` and `bimaaji` configurations must continue to work after the bump (no node-side dependency changes expected from .181/.182). | Active |

## 6. Success Criteria

1. **Framework dependency current.** Minoo runs against `waaseyaa/* v0.1.0-alpha.182` end-to-end (composer + lockfile + installed `vendor/`).
2. **No fail-closed regressions.** Every test in the suite passes; anonymous SSR (`/`) and authenticated feed (`/feed`) return non-empty bodies; production-grade smoke (curl with body-size check) confirms no zero-byte 200s.
3. **Auditable security posture.** Every `accessCheck(false)` call in `src/` is justified in `docs/security/sql-entity-query-access-check-bypass-audit.md`. The doc enumerates ≤ 20 bypass sites in alpha.182 baseline (loose upper bound; mission may discover fewer or more); each row has a date.
4. **Documentation current.** `CLAUDE.md` sync line points at alpha.182 and explains the access-check change in one paragraph. MEMORY.md project state reflects the bump.
5. **Out-of-scope surfaces tracked.** GitHub issues exist for AI agent executor, 2FA endpoints, and dead-code Phase 4 — one issue per surface, each linking back to this mission.

## 7. Key Entities & Files Touched

- `composer.json`, `composer.lock` (root)
- `src/Http/Controller/Community/{ContributorController,…}.php`
- `src/Http/Controller/Language/LanguageController.php`
- `src/Http/Controller/Groups/GroupController.php`
- `src/Http/Controller/Teachings/TeachingController.php`
- `src/Http/Controller/OralHistory/OralHistoryController.php`
- `src/Http/Controller/Events/EventController.php` (already partially adopted; tighten remaining sites)
- `src/Http/Controller/**/*.php` (full audit, especially API + admin surfaces)
- `src/Domain/Feed/EntityLoaderService.php`
- `src/Search/*.php`
- `src/Ingestion/IngestMaterializer.php`, `src/Ingestion/EntityMapper/*.php`
- `src/Console/*.php`
- `src/Seed/*.php` (if any seed paths emit queries beyond the demo handler)
- `docs/security/sql-entity-query-access-check-bypass-audit.md` (new)
- `CLAUDE.md` (sync line + highlights)
- `.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md` (project state line)
- `tests/App/Unit/**/*.php`, `tests/App/Integration/**/*.php` (account-binding regression coverage where behavior changes)

## 8. Assumptions

- **Anonymous account is an `AccountInterface`.** Framework's `SessionMiddleware` resolves an anonymous-shaped `AccountInterface` for un-authenticated requests, so `setAccount($account)` is always a valid call (no null-account branch needed in the bind path itself).
- **Controller DI auto-injects `AccountInterface`.** Per `CLAUDE.md` gotcha "Controller DI", `SsrPageHandler::resolveControllerInstance()` already injects `AccountInterface` from the `$serviceMap` when a controller declares it as a constructor parameter. Controllers that do not already accept it gain the parameter in the same WP that binds their queries.
- **Domain services accept account as a method parameter.** Services like `EntityLoaderService` that do not currently take an account gain an account parameter on the methods that load entities, threading it through from the controller layer. Internal helpers used purely from console contexts continue to bypass.
- **Composer Packagist propagation has completed.** `waaseyaa/* v0.1.0-alpha.182` tags exist on the per-package mirrors and Packagist has resolved them. Verify in WP01 via `composer show 'waaseyaa/*' -a` before locking.

## 9. Out of Scope

- AI agent executor (#1496) wiring — separate issue.
- Two-factor authentication endpoint enablement (#1499) — separate issue.
- Dead-code Phase 4 fail-on-new gate (#1500) — separate issue.
- Adoption of any other alpha.181 surface not covered by FR-004..FR-010.
- Migrations or schema changes (none required for this upgrade).
- V1 release governance — this is a framework dependency upgrade, not a V1 item; no signoffs from #202 needed.

## 10. Suggested WP Breakdown (planning refines)

- **WP01 — Bump framework.** Edit `composer.json`, run `composer update 'waaseyaa/*' --with-all-dependencies`, commit lockfile. Boot the kernel via a minimal smoke script to confirm `composer install` and class loading succeed; do not run the full test suite yet (it is expected to be red). Land on the mission branch.
- **WP02 — Public SSR controllers.** Bind `_account` on every query in `Community/`, `Language/`, `Groups/`, `Teachings/`, `OralHistory/`, `Events/`. Expected ~60 of the 131 sites. Tests for these controllers go green.
- **WP03 — Authenticated API controllers.** Bind on engagement, newsletter, games, admin controllers. Expected ~30 sites.
- **WP04 — Domain services.** Add account-threading to `EntityLoaderService`, search providers, ingestion materializer's user-context call paths. Expected ~30 sites.
- **WP05 — System-context bypasses + audit doc.** Add `accessCheck(false)` with inline doc comment to console handlers, save-time validators, and any remaining system-context sites. Write `docs/security/sql-entity-query-access-check-bypass-audit.md` enumerating every bypass.
- **WP06 — Mission close-out.** Update `CLAUDE.md` sync line and highlights. Update `MEMORY.md`. File the three out-of-scope follow-up issues (#1496/#1499/#1500 trackers in `waaseyaa/minoo`). Run the full quality gate (`phpunit`, `phpstan`, `cs-fixer`, `bin/check-milestones`) and the curl-based smoke for scenarios A + B. Mission branch is now green; ready for mission-review and merge.

## 11. References

- Framework changelog: `../waaseyaa/CHANGELOG.md` (alpha.181 + alpha.182 entries).
- Authoritative pattern: `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md`.
- Framework mission planning: `../waaseyaa/kitty-specs/sql-entity-query-access-checking-01KRYP15/`.
- Minoo CLAUDE.md (sync line, gotchas).
- Minoo workflow spec: `docs/specs/workflow.md` (Spec Kitty governance).
