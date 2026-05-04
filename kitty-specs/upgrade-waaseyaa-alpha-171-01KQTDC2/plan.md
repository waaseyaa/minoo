# Implementation Plan: Upgrade Waaseyaa to alpha.171

**Branch**: `upgrade-waaseyaa-alpha-171-01KQTDC2` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)
**Input**: Spec at `kitty-specs/upgrade-waaseyaa-alpha-171-01KQTDC2/spec.md`

## Summary

Bump every `waaseyaa/*` constraint in `composer.json` to alpha.171, run the upgrade, and reconcile Minoo with framework drift introduced across alpha.144–171. Resolve test failures from the `MinimalTestKernel` drain (alpha.158), the FieldStorage `_data` symmetry tightening (alpha.165), and the new bundle/storage schema diagnostics (alpha.167–171). Verify the bimaaji MCP server still ships after the upstream spec-MCP removal (alpha.164). Close the loop by smoke-testing the public surface locally and updating `CLAUDE.md`'s last-sync line.

This is an infrastructure upgrade, not a feature delivery. The "build" produces no new artifacts in `src/`; the change surface is dependency manifests, possibly migration files, possibly test refactors, and documentation.

## Technical Context

**Language/Version**: PHP 8.4+ (`declare(strict_types=1)` in every file)
**Primary Dependencies**: `waaseyaa/*` packages (40 of them, currently at alpha.142–143, target alpha.171); Symfony HttpFoundation; Twig 3; PHPUnit 10.5; PHPStan
**Storage**: SQLite at `storage/waaseyaa.sqlite` (dev); in-memory SQLite for integration tests; migrations live in `migrations/`
**Testing**: PHPUnit 10.5 with `MinooUnit` and `MinooIntegration` test suites; current count 914 tests / 2568 assertions / 3 skipped
**Target Platform**: Linux (production: PHP-FPM behind Caddy; dev: PHP built-in server)
**Project Type**: Single-project PHP app (Minoo) layered on a sibling framework (Waaseyaa)
**Performance Goals**: No regression in page render times; smoke-test routes return body > 1KB in < 2s locally
**Constraints**: Auto-deploy on push to `main`; cannot push the upgrade branch to `main` until smoke tests pass locally; production verification must use body-size, not just HTTP status (zero-byte 200s are the known WSOD signature)
**Scale/Scope**: 40 framework packages bumped; ~17 entity types touched indirectly via FieldStorage changes; 5 smoke-test routes; expected change set < 30 files (composer.json/lock + a small handful of test/source fixes + CLAUDE.md)

## Charter Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

This repo has no project-level charter (`.kittify/charter/` is gitignored). The applicable governance is the Minoo CLAUDE.md "5 rules" workflow (issue → milestone → PR-references-issue → drift report) plus the V1 release governance (CI gates: lint, PHPUnit, Playwright, security audit, commercial-use check; CODEOWNERS approval).

**Gates:**
- [x] Mission corresponds to a tracked GitHub issue (TBD — needs issue created and linked under a milestone before merge; flagged in Phase 1).
- [x] No commercial-use code introduced (this is a dependency bump; should be inert on this gate).
- [x] No payment surfaces touched.
- [x] Test suite remains green (this is the primary acceptance signal).
- [x] No silent fallback behavior added; failures must be diagnosed and fixed.
- [x] Production verification protocol followed (body-size + title check, not just HTTP status).

## Project Structure

### Documentation (this mission)

```
kitty-specs/upgrade-waaseyaa-alpha-171-01KQTDC2/
├── spec.md                 # Mission spec (committed)
├── plan.md                 # This file
├── research.md             # Phase 0 — release-note triage and risk surface
├── quickstart.md           # Phase 1 — runbook for executing the upgrade
└── tasks/                  # Phase 2 — work packages from /spec-kitty.tasks
```

No `data-model.md` or `contracts/` directory — this mission introduces no new domain entities or API contracts.

### Source Code (repository root)

The change surface in this mission is bounded to the following paths. No new directories are created.

```
composer.json                 # Bump waaseyaa/* constraints to ^0.1.0-alpha.171
composer.lock                 # Resolved tree, committed
phpstan-baseline.neon         # Likely regenerated
migrations/                   # Add migration(s) only if schema:check reports drift
tests/App/Unit/               # Adjust if MinimalTestKernel removal cascades
tests/App/Integration/        # Adjust HttpKernel boot pattern if alpha.158 forbids it
src/                          # Touch only if FieldStorage _data symmetry surfaces real bugs
.claude/settings.json         # Verify bimaaji MCP still wires; no expected change
CLAUDE.md                     # Update "Last framework sync" line to alpha.171
```

**Structure Decision**: Single-project PHP app, no new modules. The mission's footprint is composer manifests + reactive fixes to whatever the framework upgrade surfaces. Branch off `main`, work the upgrade in-place (no separate worktree needed unless test-fix volume gets large), commit incrementally per acceptance scenario.

## Phase Plan

### Phase 0 — Research & Risk Surface (output: `research.md`)

Goal: enumerate every meaningful change between alpha.143 and alpha.171 against Minoo's known surface area.

1. Pull all release notes for alpha.144 → alpha.171 from `gh api repos/waaseyaa/framework/releases`.
2. Produce a per-alpha summary in `research.md` highlighting:
   - PRs touching `waaseyaa/entity`, `waaseyaa/foundation`, `waaseyaa/api`, `waaseyaa/routing`, `waaseyaa/ssr` — these are Minoo's hot-path packages.
   - Any test-infrastructure changes (kernel base classes, ban rules, deprecations).
   - Any composer/manifest changes (provider list shape, package split).
   - Any new schema diagnostics or migration requirements.
3. For each significant change, note the **Minoo touch point** (file/test/migration) and a **mitigation hypothesis** (what we expect to do, before running tests).
4. Decide and document the path-repository question (see Edge Cases in spec): keep, remove, or update sibling tree for `entity`/`field`/`genealogy`.

**Exit gate**: `research.md` exists; the path-repository decision is recorded; the predicted-touch-point list is concrete enough to drive task scaffolding.

### Phase 1 — Quickstart Runbook (output: `quickstart.md`)

Goal: a copy-pasteable runbook for executing the upgrade on a fresh checkout. Not the actual fixes — those happen in Phase 2 — but the procedural scaffolding so anyone (or any future agent) can re-run this upgrade end-to-end.

Sections:
1. **Pre-flight** — confirm clean working tree; create GitHub issue under appropriate milestone; create branch off `main`.
2. **Composer bump** — exact `composer.json` edits + `composer update` invocation.
3. **First test run** — record the failures verbatim before any fix; this is the diagnostic baseline.
4. **Schema diagnostics** — run `bin/waaseyaa schema:check`; record output.
5. **MCP verification** — run `composer bimaaji-mcp-install`; verify `.claude/settings.json` registration.
6. **Smoke tests** — boot local server; curl each of the 5 routes; assert body size + title presence.
7. **CLAUDE.md update** — bump "Last framework sync" line.
8. **Merge & deploy verification** — push to `main`; watch auto-deploy; verify production with body-size checks.

**Exit gate**: `quickstart.md` exists and is internally consistent (commands match the spec's success criteria).

### Phase 2 — Task Decomposition (`/spec-kitty.tasks`)

Hand off to `spec-kitty tasks` to break the runbook into concrete work packages. Expected work packages, sequenced:

| WP | Title | Depends on | Acceptance |
|----|-------|------------|------------|
| WP01 | Composer bump + lock + validate | — | SC-001 |
| WP02 | Resolve test failures from alpha.158 kernel ban | WP01 | US2/AS2 |
| WP03 | Resolve test failures from alpha.165 `_data` symmetry | WP01 | US2/AS1 + US2/AS2 |
| WP04 | Schema diagnostics: investigate + migrate | WP01 | SC-003 |
| WP05 | PHPStan baseline regen if needed | WP02–WP04 | FR-008 |
| WP06 | Bimaaji MCP verification | WP01 | US4/AS1–AS2 |
| WP07 | Local smoke tests on 5 routes | WP02–WP04 | SC-004 |
| WP08 | CLAUDE.md "Last framework sync" + memory update | WP07 | FR-009 |
| WP09 | Push to `main`, deploy, verify production | WP07–WP08 | SC-005 |
| WP10 (P3) | Wire new schema diagnostics into CI | WP04 | US5/AS1–AS2 |

**Note**: WP10 is the P3 user story. If WP01–WP09 take longer than estimated, WP10 spins out as a follow-up issue rather than blocking the upgrade.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (none anticipated) | — | — |

If WP02 or WP03 requires invasive refactoring (e.g., the FieldStorage `_data` change forces every Minoo entity to be rewritten), pause and re-scope rather than expanding the mission silently. The simpler alternative — staying on alpha.143 — is on the table if the cost crosses a documented threshold.
