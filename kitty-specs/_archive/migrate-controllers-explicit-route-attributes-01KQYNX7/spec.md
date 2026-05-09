# Specification: Migrate Controllers to Explicit Route Attributes

**Mission ID**: `01KQYNX7DWR7QNFK6XAZRKMWHV` (mid8: `01KQYNX7`)
**Mission Type**: software-dev
**Target Branch**: `main`
**Closes**: waaseyaa/minoo#753 (milestone: v0.14 — Framework Upgrade alpha.173)
**Created**: 2026-05-06

## Summary

The Waaseyaa framework alpha.173 controller dispatcher (framework#1390) introduced a compatibility shim that lets unannotated `array $params` parameters be treated as `#[MapRoute]` and unannotated `array $query` parameters as `#[MapQuery]`. The shim emits a `notice`-level deprecation in the `dispatcher.deprecation` channel for every `(controller_class::method, parameter)` triple it observes, and it is scheduled for removal upstream once Minoo (and other consumers) finish migrating.

Issue #753 inventoried **346 unannotated array params across 173 methods in 37 controllers** under `src/Controller/`. Every method is fully unannotated (zero partial migrations). This mission migrates all of them to explicit attribute form, ahead of the framework removing the shim.

## User Scenarios & Testing

### Primary User Story

**As a** Minoo framework maintainer
**I want** every controller method that accepts `array $params` / `array $query` to carry explicit `#[MapRoute]` / `#[MapQuery]` attributes
**So that** the alpha.173 dispatcher compatibility shim can be removed upstream without breaking Minoo, the cold-boot log is free of `dispatcher.deprecation` noise, and CI catches any regression that re-introduces an implicit-array parameter.

### Acceptance Scenarios

1. **Given** a controller method with `public function name(array $params, array $query, ...)`,
   **When** the migration runs,
   **Then** the method signature becomes `public function name(#[MapRoute] array $params, #[MapQuery] array $query, ...)` and the file imports `Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery`.

2. **Given** the full mission has merged,
   **When** `php scripts/check-implicit-array-params.php` runs,
   **Then** it exits 0 with a count of 0 (no remaining implicit-array params in `src/Controller/`).

3. **Given** the full mission has merged,
   **When** the application cold-boots and the log is filtered with `grep -F 'dispatcher.deprecation'`,
   **Then** zero `event: implicit_array_shim` notices for migrated controllers appear (`AppControllerMethodInvoker::$specCache` will not record a single triple for a migrated controller).

4. **Given** any work package merges,
   **When** `./vendor/bin/phpunit` runs,
   **Then** the suite stays green at the current `main` baseline (1091 tests / 3375 assertions / 3 skipped as of 2026-05-06; verify against `main` before each WP).

5. **Given** smoke routes for each migrated controller cluster,
   **When** they are exercised against the local dev server,
   **Then** the rendered response is byte-equivalent to the pre-migration response (200 status, identical content-length and `<title>`).

### Edge Cases

- **Variadic / additional non-array parameters** following `$params` / `$query` (e.g. `string $slug`, `Request $request`) — must be left untouched. Only the two array params are decorated.
- **Methods that already have one (but not both) attribute** — the inventory says zero exist today, but the extractor must skip rather than re-decorate when it encounters a parameter that already has the attribute.
- **Methods with `array $params` but no `array $query`** (or vice versa) — the inventory shows every affected method has both, but the extractor must handle each parameter independently.
- **Controllers that do not use `array $params` / `array $query` at all** (e.g. controllers that bind specific scalars / DTOs) — must be skipped silently.
- **Trait methods (`GameControllerTrait`)** — if traits define controller methods, they must follow the same rule. (Inventory does not list traits; verify during WP02.)

## Requirements

### Functional Requirements

| ID      | Requirement                                                                                                                                                        | Status   |
|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------|
| FR-001  | All 346 inventoried `array $params` / `array $query` parameters in `src/Controller/*.php` SHALL be decorated with `#[MapRoute]` / `#[MapQuery]` respectively.       | Proposed |
| FR-002  | Every modified controller file SHALL import `Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery` via `use` statements (alphabetical).    | Proposed |
| FR-003  | The migration SHALL NOT change parameter names, types, defaults, or order; it SHALL NOT rename methods or alter return types.                                     | Proposed |
| FR-004  | The migration SHALL be split into 6 work packages grouped by controller cluster (per `tasks.md`), each independently mergeable.                                   | Proposed |
| FR-005  | A standalone PHP script SHALL be committed at `scripts/check-implicit-array-params.php` that uses `token_get_all` to detect any remaining unannotated `array $params` / `array $query` parameters in `src/Controller/*.php`. | Proposed |
| FR-006  | The extractor script SHALL exit non-zero with a non-empty count when any implicit-array parameter is found and exit 0 with count 0 when the migration is complete. | Proposed |
| FR-007  | The extractor script SHALL be runnable as `php scripts/check-implicit-array-params.php` (no Composer scripts or framework boot required).                          | Proposed |
| FR-008  | After all 6 WPs land, a final reconciliation step SHALL verify the cold-boot log emits zero `event: implicit_array_shim` notices for any controller in `src/Controller/`. | Proposed |

### Non-Functional Requirements

| ID       | Requirement                                                                                                                       | Threshold                                          | Status   |
|----------|-----------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------|----------|
| NFR-001  | Each work package merges with `./vendor/bin/phpunit` green.                                                                       | Current `main` baseline (1091/3375/3 skipped as of 2026-05-06; rebaseline per WP). | Proposed |
| NFR-002  | Smoke check of migrated routes returns the same HTTP status and non-zero `<title>` content-length as before the WP.               | Status code identical; body size > 0 (no WSOD)     | Proposed |
| NFR-003  | The extractor script is fast enough to run on every push (lefthook / pre-push) without becoming a bottleneck.                     | < 2s on the full `src/Controller/` tree            | Proposed |
| NFR-004  | The migration introduces no behavioral change to request dispatch — same routes, same parameter binding, same controller output. | Production parity                                  | Proposed |

### Constraints

| ID    | Constraint                                                                                                                          | Status   |
|-------|-------------------------------------------------------------------------------------------------------------------------------------|----------|
| C-001 | Must not edit the framework (`vendor/waaseyaa/*`). The migration is application-side only.                                          | Active   |
| C-002 | Must not refactor controller methods (no method renames, no parameter reorders, no DTO swaps).                                      | Active   |
| C-003 | Must not modify routes, templates, CSS, or any non-controller code (out of scope per #753).                                         | Active   |
| C-004 | CI workflow integration is out of scope for this mission (script committed only — wiring tracked as a follow-up issue).             | Active   |
| C-005 | Each WP must be a standalone PR (or stacked branch) that is independently green; no WP may depend on another WP's intermediate state. | Active   |
| C-006 | Final WP (WP06) must verify the cold-boot log is clean before closing #753.                                                         | Active   |

## Success Criteria

| ID     | Criterion                                                                                                                                                | Verification                                                                 |
|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------|
| SC-001 | All 346 inventoried implicit-array params are explicitly attributed.                                                                                     | `php scripts/check-implicit-array-params.php` exits 0 with count 0           |
| SC-002 | All 914 PHPUnit tests still pass after every WP.                                                                                                          | `./vendor/bin/phpunit` returns 0 with `OK (914 tests, 2568 assertions)`      |
| SC-003 | Cold-boot log emits zero `dispatcher.deprecation` / `implicit_array_shim` notices for `src/Controller/*` after the final WP.                              | `grep -F 'dispatcher.deprecation' <log>` finds zero matches for `App\\Controller\\` |
| SC-004 | Issue #753 closes automatically when the final WP merges.                                                                                                | `Closes #753` in WP06 PR description                                         |
| SC-005 | All controller smoke routes return identical HTTP status and non-empty body before/after each WP merges.                                                  | `curl -sS -o /tmp/page -w "%{http_code}/%{size_download}"` parity check     |

## Key Entities

| Entity                                              | Type             | Notes                                                                                                                              |
|-----------------------------------------------------|------------------|------------------------------------------------------------------------------------------------------------------------------------|
| `Waaseyaa\SSR\Attribute\MapRoute`               | Framework class  | Existing PHP attribute applied to `array $params`.                                                                                  |
| `Waaseyaa\SSR\Attribute\MapQuery`               | Framework class  | Existing PHP attribute applied to `array $query`.                                                                                   |
| `App\Controller\*`                                  | Application code | 37 controllers under `src/Controller/`. Inventory in #753 lists all 173 affected methods.                                          |
| `scripts/check-implicit-array-params.php`           | Tool             | New file. Walks `src/Controller/*.php` with `token_get_all`, prints offending entries, exits non-zero on count > 0.                |
| `Waaseyaa\Foundation\Routing\AppControllerMethodInvoker` | Framework class | Owns the `$specCache` that emits the dispatcher.deprecation notice. Mission consumes its observations as the migration ground truth. |
| `dispatcher.deprecation` log channel                | Telemetry        | Channel that emits `event: implicit_array_shim` notices. Used as a verification source.                                            |

## Work Package Plan (informational — finalized in `tasks.md`)

Six work packages grouped by controller cluster. Order is the suggested sequence; lanes may execute in parallel where worktrees do not collide.

| WP    | Cluster                              | Controllers                                                                                                                                                                                                                                                                                  | Method×param count |
|-------|--------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------:|
| WP01  | Auth + Account                       | AuthController, AccountHomeController, RoleManagementController, CoordinatorDashboardController, VolunteerController, VolunteerDashboardController                                                                                                                                            | ~62                |
| WP02  | Games                                | ShkodaController, CrosswordController, AgimController, JourneyController, MatcherController, GuessPriceController                                                                                                                                                                              | ~80                |
| WP03  | Newsletter                           | NewsletterAdminApiController, NewsletterController, NewsletterEditorController                                                                                                                                                                                                                 | ~56                |
| WP04  | Engagement + Messaging               | EngagementController, MessagingController, ChatController, BlockController, FeedController                                                                                                                                                                                                     | ~68                |
| WP05  | Static + Communities + Misc          | StaticPageController, CommunityController, BusinessController, ContributorController, EventController, GroupController, TeachingController, LanguageController, LocationController, OpenGraphController, OralHistoryController, PeopleController, HomeController                              | ~64                |
| WP06  | Elder Support + Ingestion + extractor | ElderSupportController, ElderSupportWorkflowController, IngestionApiController, IngestionDashboardController + `scripts/check-implicit-array-params.php` + final reconciliation                                                                                                                | ~16 + 1 script     |

(Counts approximate; exact distribution computed from the inventory in #753.)

## Out of Scope

- Removal of the framework dispatcher implicit-array compatibility shim (upstream framework work, gated on consumers — Minoo + others — finishing migration first).
- New routing logic, DTO binding, or parameter-resolver work.
- Renaming or refactoring controller methods, parameters, or return types.
- Template, CSS, JS, or asset changes.
- Wiring the extractor into `.github/workflows/*` (intentionally deferred to a follow-up issue per branch context Q1 → option B).
- Migrating any `array $params` / `array $query` parameters outside `src/Controller/*.php` (scope is application controllers only; if framework or test fixtures contain similar patterns they are not part of this mission).

## Assumptions

- The `dispatcher.deprecation` channel emits exactly one notice per `(controller_class::method, parameter_name)` triple per FPM worker lifetime / `php -S` process, dedup'd inside `AppControllerMethodInvoker::$specCache`. Verifying "no notice on cold boot" is sufficient evidence of full migration.
- The `Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery` attribute classes already exist in the framework (alpha.173). The mission only consumes them — it does not need to add anything to `vendor/`.
- The 346-entry inventory in #753 is current as of the alpha.173 sync (commit `1293350` / merge `afbfc84`). Any controller added between #753 filing and WP execution will be picked up by the extractor and added to the closing WP if applicable.
- Branch protection allows squash-merge of stacked WP PRs; the team can review and land six PRs in sequence within the v0.14 milestone window.
- `dispatcher.deprecation` log analysis uses the existing `WAASEYAA_LOG_LEVEL=notice` configuration (per `config/waaseyaa.php` and CLAUDE.md gotcha) — no log-level changes required for this mission.

## Dependencies

- Waaseyaa framework alpha.173 (already merged via #750/#751 — `composer.json` `waaseyaa/*: ^0.1.0-alpha.173`).
- Issue #753 inventory (the 346-line table in the issue body is the migration ground truth).
- Framework attribute classes `Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery` (shipped pre-alpha.173).
- `AppControllerMethodInvoker::$specCache` static cache (framework-side) — used as the verification source for "no notice on cold boot."

## Glossary

| Term                        | Meaning                                                                                                                                                |
|-----------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|
| Implicit-array param        | An untyped/unattributed `array $params` or `array $query` controller parameter that the dispatcher binds via the alpha.173 compatibility shim.         |
| Explicit attribute          | A PHP 8 attribute (`#[MapRoute]` / `#[MapQuery]`) applied to a parameter to declare how the dispatcher should bind it.                                  |
| Dispatcher deprecation      | A `notice`-level log entry on the `dispatcher.deprecation` channel emitted by `AppControllerMethodInvoker` when the shim is invoked.                    |
| Cold-boot log               | The application log captured from a fresh PHP process (no warm `$specCache`) — the only place where the shim's first observation of each triple is logged. |
| Migration ground truth      | The inventory in issue #753 (346 entries) and the live `dispatcher.deprecation` log; either source MUST agree with the other after each WP.            |
