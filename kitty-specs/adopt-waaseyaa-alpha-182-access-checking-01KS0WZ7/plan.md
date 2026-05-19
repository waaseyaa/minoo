# Implementation Plan: Adopt Waaseyaa alpha.182 Access-Checking Contract

**Branch**: `main` (planning) → mission branch `kitty/mission-adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7-lane-a` (execution) → squash-merges back to `main`
**Date**: 2026-05-19
**Spec**: [kitty-specs/adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7/spec.md](spec.md)
**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2` (mid8 `01KS0WZ7`)

## Summary

Upgrade Minoo from `waaseyaa/* ^0.1.0-alpha.180` to `^0.1.0-alpha.182`. The bump itself is one line of `composer.json` + a lockfile refresh, but it pulls in the fail-closed `SqlEntityQuery::accessCheck(true)` default introduced in alpha.181 (#1495). That contract change requires auditing all 135 `getQuery()` call sites in `src/` and converting each to one of three legal shapes (bind account, conditional fallback, audited bypass). The mission lands on its own branch in 6 sequenced WPs; WP01 bumps the lock (mission branch goes red), WPs 02–05 land per-domain binding/bypass fixes that bring it back to green, WP06 updates docs and runs the final smoke. Merge to `main` only when green.

Technical approach is constrained by C-001 (composer bump first — `setAccount()` doesn't exist on alpha.180 `EntityQueryInterface`) and by the framework's authoritative pattern at `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md` (three shapes, audit-doc per bypass). No new entities, no new HTTP contracts, no schema migrations.

## Technical Context

**Language/Version**: PHP 8.5 (baseline since waaseyaa alpha.176).
**Primary Dependencies**: `waaseyaa/*` framework packages (40 of them) — being bumped from `^0.1.0-alpha.180` to `^0.1.0-alpha.182`. Symfony HTTP foundation (transitive). Twig 3. SQLite (dev) / MySQL (prod, currently unused).
**Storage**: SQLite at `storage/waaseyaa.sqlite` in dev/CI; entity payloads in `_data` JSON blobs. No schema changes in this mission.
**Testing**: PHPUnit 10.5 (`./vendor/bin/phpunit`), 914 tests / 2568 assertions baseline. Test suites: `MinooUnit` and `MinooIntegration`. Integration tests boot `HttpKernel` via reflection with `WAASEYAA_DB=:memory:`. Curl-based body-size smoke for production-style verification (NFR-002, NFR-003).
**Target Platform**: Linux (production: deployer to `minoo.live` via GitHub Actions; dev: WSL2 + PHP 8.5 + Caddy or `php -S`). No platform-specific behavior added by this mission.
**Project Type**: Single-app PHP project (Minoo) consuming a versioned framework (Waaseyaa).
**Performance Goals**: Per-row access checking adds an `EntityAccessHandler::check()` call per candidate row. For large query result sets this is non-zero overhead — but the framework's mission #1495 already measured and accepted this cost for v1. Minoo inherits that posture. No new performance budget.
**Constraints**:
- **C-001** (sequencing): composer bump first; subsequent WPs land on top of red mission branch.
- **C-002** (branch hygiene): `main` stays green throughout.
- **C-003** (no fourth shape): every `getQuery()` site must use bind, conditional fallback, or audited bypass — no swallow-the-exception escape hatch.
- **C-004** (audit doc parity): every `accessCheck(false)` site mirrored in `docs/security/sql-entity-query-access-check-bypass-audit.md`.
- **C-005** (out of scope): no AI agent, no 2FA endpoints, no dead-code Phase 4 gate.
- **C-006** (MCP servers): Bimaaji and `minoo-specs` Node MCP servers must continue working.

**Scale/Scope**: 135 `getQuery()` call sites across `src/` (135 raw matches; 4 already adopt the new contract; 131 require triage). ~9 controller domains, ~3 domain-services, ~5 console handlers. Single composer bump.

## Charter Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Charter status:** No `.kittify/charter/charter.md` file in Minoo. Charter check is **skipped** per the skill's "missing charter is not a blocker" rule.

Cross-cutting governance applied in lieu of a formal charter:
- **Minoo CLAUDE.md** "Working agreements" (5 rules — Spec Kitty mission coverage, control loop usage, codified context, PR descriptions, advisory drift). All five satisfied by mission shape.
- **Architectural Boundaries** (CLAUDE.md §"Architectural Boundaries"): Minoo imports from Waaseyaa, not the reverse — this mission upgrades the import side only. No NC Go imports, no NC-specific entity types added. Compliant.
- **Code Style** (CLAUDE.md §"Code Style"): PHP 8.5+, `declare(strict_types=1)`, `final class` by default, `App\` namespace. Compliant — the mission only edits existing files.

**Re-check after Phase 1 design:** Still compliant. Design adds no new abstractions, no new packages, no boundary crossings.

## Project Structure

### Documentation (this feature)

```
kitty-specs/adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7/
├── spec.md                        # /spec-kitty.specify output (committed)
├── plan.md                        # this file
├── research.md                    # Phase 0 — three-shape decision rationale + framework pattern lift
├── quickstart.md                  # Phase 1 — operator quickstart (verify the upgrade locally)
├── meta.json                      # mission identity
├── checklists/
│   └── requirements.md            # spec quality gate (committed, all green)
├── tasks/
│   └── README.md                  # scaffolded by /spec-kitty.tasks (WP01..WP06 land here)
└── status.events.jsonl            # mission state machine event log (managed by spec-kitty)
```

**Phase 1 omits** `data-model.md` and `contracts/` — this mission introduces no new entities and no new HTTP contracts. Those artifact files would be empty; omitting them is consistent with the planning template's "include only when relevant" guidance.

### Source Code (repository root)

The mission edits files in three Minoo subtrees, plus three documentation files. No new files except the audit doc.

```
minoo/
├── composer.json                                                # WP01: bump 40 waaseyaa/* constraints
├── composer.lock                                                # WP01: regenerate
├── src/
│   ├── Http/Controller/
│   │   ├── Community/{ContributorController,…}.php              # WP02
│   │   ├── Language/LanguageController.php                      # WP02
│   │   ├── Groups/GroupController.php                           # WP02
│   │   ├── Teachings/TeachingController.php                     # WP02
│   │   ├── OralHistory/OralHistoryController.php                # WP02
│   │   ├── Events/EventController.php                           # WP02 (partial — tighten remaining sites)
│   │   ├── Social/{EngagementController,…}.php                  # WP03
│   │   ├── Newsletter/*.php                                     # WP03
│   │   ├── Games/{ShkodaController,CrosswordController,…}.php   # WP03
│   │   └── Admin/*.php                                          # WP03
│   ├── Domain/
│   │   ├── Feed/EntityLoaderService.php                         # WP04
│   │   └── Feed/Scoring/*.php                                   # WP04 (if any emit queries)
│   ├── Search/*.php                                             # WP04
│   ├── Ingestion/
│   │   ├── IngestMaterializer.php                               # WP04 (user-context paths) / WP05 (system-context paths)
│   │   └── EntityMapper/*.php                                   # WP04
│   └── Console/
│       ├── GenealogyDemoSeedHandler.php                         # WP05 (already adopted — verify)
│       ├── NcSyncHandler.php (or equivalent)                    # WP05
│       └── *.php                                                # WP05
├── docs/security/
│   └── sql-entity-query-access-check-bypass-audit.md            # WP05: NEW
├── CLAUDE.md                                                    # WP06: sync line + highlights
├── .claude/projects/-home-jones-dev-minoo/memory/MEMORY.md      # WP06: framework-version line
└── tests/App/{Unit,Integration}/                                # added/updated where binding changes behavior
```

**Structure Decision**: Single-project layout (Option 1 from the template). No new top-level directories; the only new file is `docs/security/sql-entity-query-access-check-bypass-audit.md`.

## Phase 0 Output: Research Decisions

See [research.md](research.md). Summary of the three core decisions:

1. **Three-shape classification adopted verbatim from the framework's audit doc.** No Minoo-specific fourth shape.
2. **Account threading: prefer DI injection of `AccountInterface` into controller constructors over per-request lookup.** Per CLAUDE.md gotcha "Controller DI", `SsrPageHandler::resolveControllerInstance()` already auto-injects `AccountInterface` from `$serviceMap`. Pattern is "add `AccountInterface $account` to constructor, store on `$this->account`, call `setAccount($this->account)` on queries."
3. **Sequencing: WP01 bumps the lock; WPs 02–05 land binding/bypass fixes on the mission branch; WP06 closes out.** Parallel lanes for WPs 02–05 are possible (they touch disjoint files) but starting sequential simplifies merge-conflict management and lets each WP independently verify it brings tests further toward green. Parallelization is a tactical decision for the implementer to make at lane time, not a planning constraint.

## Phase 1 Output: Design & Contracts

**Data model:** No new entities. No field-definition changes. No schema changes. The mission edits behavior of existing queries, not their underlying storage shape. `data-model.md` is intentionally omitted.

**HTTP contracts:** No new endpoints, no changed request/response shapes. The user-visible behavior is "previously-leaked rows are now filtered" — but since Minoo's existing access policies have been correctly classifying "view" intent all along, no published endpoint changes its documented response. `contracts/` directory is intentionally omitted.

**Quickstart:** See [quickstart.md](quickstart.md) — local verification recipe an operator runs after pulling the merged mission.

## Complexity Tracking

No charter violations to justify; charter is absent. No template-flagged complexity (single project, single dependency upgrade). Empty.

## Phase 2 Hand-off

Run `/spec-kitty.tasks --mission 01KS0WZ7` to materialize WP01..WP06 from the spec + this plan. The WP shape is documented in spec §10; the tasks command produces machine-readable task files plus the lane manifest.

**Branch contract restated for /spec-kitty.tasks consumers:**

- Current branch at plan completion: `main`
- Planning/base branch for this feature: `main`
- Final merge target for completed changes: `main`
- `branch_matches_target`: true
