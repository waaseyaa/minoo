# Implementation Plan: Migrate Controllers to Explicit Route Attributes

**Branch**: `main` (planning base) → `main` (merge target) | **Date**: 2026-05-06 | **Spec**: [spec.md](./spec.md)
**Mission ID**: `01KQYNX7DWR7QNFK6XAZRKMWHV` (mid8: `01KQYNX7`)
**Input**: [spec.md](./spec.md) — closes waaseyaa/minoo#753

## Summary

Migrate 346 unannotated `array $params` / `array $query` parameters across 173 methods in 37 `src/Controller/*.php` files to explicit `#[MapRoute]` / `#[MapQuery]` attributes. The migration is a six-WP, file-disjoint refactor: each WP owns a controller cluster, decorates parameters via a token-aware PHP migration script, verifies green PHPUnit + clean cold-boot log + smoke routes, and squash-merges to `main`. The final WP commits a `scripts/check-implicit-array-params.php` extractor as a long-lived regression guard and reconciles any drift between the #753 inventory and the live tree.

The technical approach is intentionally mechanical: no new behavior, no method renames, no parameter reorders. Risk is concentrated in the migration tool — a blind regex would mangle defaults / variadics / type unions / trailing commas, so a `token_get_all` based PHP script does the rewriting and an identical (but simpler) sibling script polices regressions.

## Technical Context

**Language/Version**: PHP 8.4+, `declare(strict_types=1)` mandatory
**Primary Dependencies**: Waaseyaa framework alpha.173 (`waaseyaa/foundation`, `waaseyaa/ssr`); attribute classes `Waaseyaa\SSR\Attribute\MapRoute` and `Waaseyaa\SSR\Attribute\MapQuery`
**Storage**: N/A (no schema or persistence change)
**Testing**: PHPUnit 10.5 (current `main` baseline 1091 tests / 3375 assertions / 3 skipped, as of 2026-05-06; re-baseline before each WP), `./vendor/bin/phpunit`
**Target Platform**: Linux + WSL2; PHP-FPM in production, `php -S` in dev
**Project Type**: Single PHP application (Minoo on Waaseyaa CMF)
**Performance Goals**: Extractor < 2s on full `src/Controller/` tree (NFR-003); migration script < 10s end-to-end per cluster
**Constraints**:
- No framework edits (`vendor/` is read-only) — application-side only (C-001)
- No method/parameter refactors (C-002)
- No template/CSS/JS edits (C-003)
- CI workflow wiring deferred (C-004)
- Each WP independently green and reversible (C-005)
**Scale/Scope**: 37 controllers, 173 methods, 346 parameters; 6 work packages

## Charter Check

Charter file `.kittify/charter/charter.md` not present — Charter Check **SKIPPED** for this mission. No project governance gates apply.

## Project Structure

### Documentation (this feature)

```
kitty-specs/migrate-controllers-explicit-route-attributes-01KQYNX7/
├── plan.md              # This file
├── spec.md              # Feature specification (committed in 717aa9a)
├── meta.json            # Mission metadata (id, slug, friendly_name, target_branch)
├── research.md          # Phase 0 output (this command)
├── quickstart.md        # Phase 1 output (this command)
├── contracts/
│   ├── migrate-cli.md       # CLI surface for scripts/migrate-controller-attributes.php
│   └── check-cli.md         # CLI surface for scripts/check-implicit-array-params.php
├── checklists/
│   └── requirements.md  # Spec quality checklist (committed in 717aa9a)
└── tasks/               # WP files (created by /spec-kitty.tasks)
```

`data-model.md` is not generated — this mission introduces no entities, schema, or persistence changes.

### Source Code (repository root)

The migration touches three areas of the existing repo:

```
src/
└── Controller/                         # 37 files modified across 6 WPs
    ├── AccountHomeController.php       # WP01
    ├── AgimController.php              # WP02
    ├── AuthController.php              # WP01
    ├── BlockController.php             # WP04
    ├── BusinessController.php          # WP05
    ├── ChatController.php              # WP04
    ├── CommunityController.php         # WP05
    ├── ContributorController.php       # WP05
    ├── CoordinatorDashboardController.php  # WP01
    ├── CrosswordController.php         # WP02
    ├── ElderSupportController.php      # WP06
    ├── ElderSupportWorkflowController.php  # WP06
    ├── EngagementController.php        # WP04
    ├── EventController.php             # WP05
    ├── FeedController.php              # WP04
    ├── GroupController.php             # WP05
    ├── GuessPriceController.php        # WP02
    ├── HomeController.php              # WP05
    ├── IngestionApiController.php      # WP06
    ├── IngestionDashboardController.php # WP06
    ├── JourneyController.php           # WP02
    ├── LanguageController.php          # WP05
    ├── LocationController.php          # WP05
    ├── MatcherController.php           # WP02
    ├── MessagingController.php         # WP04
    ├── NewsletterAdminApiController.php # WP03
    ├── NewsletterController.php        # WP03
    ├── NewsletterEditorController.php  # WP03
    ├── OpenGraphController.php         # WP05
    ├── OralHistoryController.php       # WP05
    ├── PeopleController.php            # WP05
    ├── RoleManagementController.php    # WP01
    ├── ShkodaController.php            # WP02
    ├── StaticPageController.php        # WP05
    ├── TeachingController.php          # WP05
    ├── VolunteerController.php         # WP01
    └── VolunteerDashboardController.php # WP01

scripts/                                # New per-mission tooling (WP06)
├── migrate-controller-attributes.php   # Tool, NOT committed (used per-WP, then deleted in WP06)
└── check-implicit-array-params.php     # Long-lived regression guard, committed in WP06

tests/App/                              # No new tests; existing PHPUnit suite is the verification
```

**Structure Decision**: Single Minoo application. Controllers live exclusively under `src/Controller/`. Scripts live under `scripts/` (already populated with `populate_engagement.php`, `populate_featured.php`, etc. per CLAUDE.md). The migration tool (`migrate-controller-attributes.php`) is a working artifact and is intentionally **not** committed — it lives on the WP01 branch for the duration of the mission and is removed before WP06 lands. The check script (`check-implicit-array-params.php`) is a long-lived regression guard and **is** committed by WP06.

## Complexity Tracking

No charter, no gate violations to justify. Migration follows existing project conventions (PHP 8.4 strict types, namespace `App\`, framework attribute classes from `waaseyaa/ssr`).

## Phase 0: Research

See [research.md](./research.md) for detailed decisions. Summary:

| Decision | Choice |
|---|---|
| Migration mechanism | Single PHP script (`scripts/migrate-controller-attributes.php`) using `token_get_all` + targeted byte-level rewrite — NOT regex, NOT AST library |
| Use-statement insertion | Targeted regex (alphabetical placement after the last `Waaseyaa\` use stmt or before the first non-`use` statement after `namespace`) |
| Parameter decoration | Token-aware: walk param list between matching `(` / `)`, detect `T_ARRAY` followed by `T_VARIABLE` matching `$params`/`$query`, check preceding `T_ATTRIBUTE` block, splice attribute prefix at exact byte offset |
| WP isolation | Spec-Kitty default: each WP gets its own worktree under `.worktrees/migrate-controllers-explicit-route-attributes-01KQYNX7-lane-X/`; squash-merge to `main`; no stacked PRs |
| Verification | Three-pronged per WP: (1) `./vendor/bin/phpunit` green, (2) cold-boot smoke routes return 200 + non-zero body for each migrated controller, (3) `dispatcher.deprecation` log emits zero `implicit_array_shim` notices for migrated controllers |
| Rollback | Standard git revert per WP; worktree-disjoint clusters mean any one WP can be reverted without affecting others |
| Drift handling | WP06 final reconciliation: run extractor → list any new offenders not in #753 inventory → include them in WP06 fix-up commit |

## Phase 1: Design & Contracts

See [contracts/migrate-cli.md](./contracts/migrate-cli.md) and [contracts/check-cli.md](./contracts/check-cli.md) for the CLI surface of the two scripts. See [quickstart.md](./quickstart.md) for the per-WP execution recipe.

### Design highlights

**Migration script (`scripts/migrate-controller-attributes.php`, transient — used during WPs, removed before WP06 lands)**:
- Pure PHP CLI (`#!/usr/bin/env php`), no Composer autoload, no framework boot.
- Args: `--filter <ControllerName>` (repeatable), `--cluster <wp01..wp06>` (resolves to a fixed list of controllers per WP), `--dry-run` (prints unified diff to stdout, makes no changes), `--apply` (writes files in place).
- Token-walks each target file, splices `#[MapRoute] ` before `array $params` and `#[MapQuery] ` before `array $query` only when:
  1. the param is part of a `function`-keyword param list (not a closure inside the body, not a method docblock),
  2. the param is not already preceded by an `#[MapRoute]` / `#[MapQuery]` attribute block,
  3. the param's type is exactly `array` (not `?array`, not `array|null`, not `iterable`),
  4. the param's name is exactly `$params` or `$query`.
- Adds the two `use` statements if missing (alphabetical insertion among `Waaseyaa\` uses).
- Idempotent: running twice produces no diff.

**Extractor script (`scripts/check-implicit-array-params.php`, committed in WP06)**:
- Pure PHP CLI, no Composer autoload, no framework boot.
- No args (or `--path <dir>` defaulting to `src/Controller/`).
- Same token-walking logic as the migration script's read-only inspection pass.
- For each offender, prints `<FQCN>::<method> $<param> -> #[<RecommendedAttribute>]` to stdout.
- Exit 0 if zero offenders; exit 1 with non-zero count printed to stderr otherwise.
- Wall time < 2s on full `src/Controller/` tree (NFR-003).

**Smoke route table (per controller)** — documented in `quickstart.md`. One representative HTTP method per controller, chosen to exercise the migrated `array $params`/`array $query` binding (e.g. for `AuthController::loginForm` use `GET /login`; for `EngagementController::react` use `POST /api/engagement/react/<post-id>`).

**Worktree topology**:
- 6 worktrees, file-disjoint per cluster.
- No worktree shares files with another, so they may be developed in parallel; in practice the user will execute serially via `spec-kitty next` to keep PR review traffic manageable.
- Each worktree branch: `.worktrees/migrate-controllers-explicit-route-attributes-01KQYNX7-lane-{a..f}/`.

## Phase 2: Tasks (handled by `/spec-kitty.tasks`)

Six work packages, in suggested execution order:

1. **WP01** — Auth + Account cluster (6 controllers)
2. **WP02** — Games cluster (6 controllers)
3. **WP03** — Newsletter cluster (3 controllers)
4. **WP04** — Engagement + Messaging cluster (5 controllers)
5. **WP05** — Static + Communities + Misc cluster (13 controllers)
6. **WP06** — Elder Support + Ingestion cluster (4 controllers) + extractor commit + final reconciliation + close #753

WP file authorship is deferred to `/spec-kitty.tasks` per the workflow's mandatory stop point.

## Branch contract (restated)

- Current branch at plan start: `main`
- Planning base branch: `main`
- Merge target branch: `main`
- `branch_matches_target`: `true` ✓

Each WP claims a fresh worktree off `main`. PRs target `main`. The mission auto-closes when WP06 merges (`Closes #753` in WP06 PR description).
