# Tasks: Upgrade Waaseyaa to alpha.171

**Mission**: `upgrade-waaseyaa-alpha-171-01KQTDC2`
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Generated**: 2026-05-04T21:23:06Z
**Planning base / merge target**: `main` (no feature branch — direct work on main per project workflow)

## Overview

10 work packages, 47 subtasks, sized 3-7 each. Sequence is mostly serial because every downstream WP depends on a green test suite from the prior WPs. Two parallel branches exist mid-mission (WP05 PHPStan + WP06 Bimaaji can run alongside WP07 smoke tests once WP02–WP04 are done).

## Subtask Index

| ID | Description | WP | Parallel |
|----|-------------|----|----------|
| T001 | Bump waaseyaa/* constraints in composer.json to ^0.1.0-alpha.171 | WP01 | — |
| T002 | Decide path-repository question (entity/field/genealogy overrides) | WP01 | — |
| T003 | Run `composer update 'waaseyaa/*' --with-dependencies` | WP01 | — |
| T004 | Run `composer validate --strict` and `composer install --dry-run` | WP01 | — |
| T005 | Verify all 40 packages at alpha.171 via `composer show 'waaseyaa/*'` | WP01 | — |
| T006 | Commit composer.json + composer.lock | WP01 | — |
| T007 | Inventory named kernel subclasses in tests/** (alpha.158 ban surface) | WP02 | — |
| T008 | Refactor integration tests to sanctioned boot path | WP02 | — |
| T009 | Run `--testsuite MinooIntegration`; capture failures, fix, repeat | WP02 | — |
| T010 | Commit integration test refactors | WP02 | — |
| T011 | Run `--testsuite MinooUnit`; capture failures verbatim | WP03 | — |
| T012 | Diagnose FieldStorage `_data` symmetry failures (alpha.165) | WP03 | — |
| T013 | Fix Minoo entity classes / access tests for symmetry | WP03 | — |
| T014 | Re-run `--testsuite MinooUnit`; verify green | WP03 | — |
| T015 | Commit unit test + entity fixes | WP03 | — |
| T016 | Run `bin/waaseyaa schema:check`; capture output | WP04 | — |
| T017 | Diagnose column-vs-`_data` drift findings | WP04 | — |
| T018 | Diagnose BUNDLE_SUBTABLE_MISSING / ORPHAN_BUNDLE_SUBTABLE findings | WP04 | — |
| T019 | Generate migrations via `bin/waaseyaa make:migration <name>` | WP04 | — |
| T020 | Run migrations; re-verify schema:check is clean | WP04 | — |
| T021 | Commit migrations | WP04 | — |
| T022 | Run `./vendor/bin/phpstan analyse`; capture drift | WP05 | [P] |
| T023 | Regenerate baseline if needed | WP05 | [P] |
| T024 | Commit phpstan-baseline.neon | WP05 | [P] |
| T025 | Run `composer bimaaji-mcp-install`; verify exit 0 | WP06 | [P] |
| T026 | Verify .claude/settings.json still registers minoo + bimaaji | WP06 | [P] |
| T027 | Document MCP verification result | WP06 | [P] |
| T028 | Boot local server `php -S 0.0.0.0:8080 -t public public/index.php` | WP07 | — |
| T029 | Smoke `/` (anonymous public homepage) | WP07 | — |
| T030 | Smoke `/feed` (authenticated) | WP07 | — |
| T031 | Smoke `/games`, `/games/shkoda`, `/games/crossword`, `/games/agim` | WP07 | — |
| T032 | Smoke a community detail page (NC client adapter exercise) | WP07 | — |
| T033 | Smoke `/elder-support` request form | WP07 | — |
| T034 | Document smoke results (any non-fatal issues for follow-up) | WP07 | — |
| T035 | Update CLAUDE.md "Last framework sync" line to alpha.171 | WP08 | — |
| T036 | Update Minoo MEMORY.md framework version pointer | WP08 | — |
| T037 | Verify ComposerProviderParityTest still passes | WP08 | — |
| T038 | Commit docs / memory updates | WP08 | — |
| T039 | Final full PHPUnit run on clean repo | WP09 | — |
| T040 | Push to main (triggers GitHub Actions auto-deploy) | WP09 | — |
| T041 | Watch GitHub Actions deploy run to completion | WP09 | — |
| T042 | Verify production with curl body-size + title checks on 5 routes | WP09 | — |
| T043 | Document deploy outcome / rollback decisions | WP09 | — |
| T044 | Add `bin/waaseyaa schema:check` invocation to CI workflow | WP10 | — |
| T045 | Add CI step asserting BUNDLE_SUBTABLE_MISSING / ORPHAN_BUNDLE_SUBTABLE fail the build | WP10 | — |
| T046 | Verify CI fails on a deliberate drift; revert the drift | WP10 | — |
| T047 | Commit CI workflow changes | WP10 | — |

`[P]` denotes subtasks safely parallelizable across WPs (different file domains, no shared state).

---

## Phase 1 — Foundation

### WP01 — Composer bump and resolution

**Goal**: Bring every `waaseyaa/*` package to alpha.171 with a clean lockfile and validation.
**Priority**: P1 (gating)
**Independent test**: `composer show 'waaseyaa/*'` reports `0.1.0-alpha.171` everywhere; `composer validate --strict` exits 0.
**Acceptance**: SC-001, FR-001, FR-002
**Estimated prompt**: ~250 lines
**Prompt**: [WP01-composer-bump.md](./tasks/WP01-composer-bump.md)

**Subtasks**:
- [ ] T001 Bump waaseyaa/* constraints in composer.json to ^0.1.0-alpha.171 (WP01)
- [ ] T002 Decide path-repository question (entity/field/genealogy overrides) (WP01)
- [ ] T003 Run `composer update 'waaseyaa/*' --with-dependencies` (WP01)
- [ ] T004 Run `composer validate --strict` and `composer install --dry-run` (WP01)
- [ ] T005 Verify all 40 packages at alpha.171 via `composer show 'waaseyaa/*'` (WP01)
- [ ] T006 Commit composer.json + composer.lock (WP01)

**Dependencies**: none.
**Risks**: Path-repository entries for entity/field/genealogy may pin to sibling worktrees that aren't at alpha.171; unresolved constraint conflicts; bimaaji/api/ssr packages have alpha.142 mismatches indicating a coordinated split lag — verify all 40 land on alpha.171.

---

## Phase 2 — Test reconciliation

### WP02 — Integration tests: alpha.158 kernel-subclass ban

**Goal**: Reconcile Minoo's integration tests with the alpha.158 ban on named kernel subclasses in `tests/**`.
**Priority**: P1
**Independent test**: `./vendor/bin/phpunit --testsuite MinooIntegration` exits 0.
**Acceptance**: US2/AS2, FR-003
**Estimated prompt**: ~300 lines
**Prompt**: [WP02-integration-kernel-ban.md](./tasks/WP02-integration-kernel-ban.md)

**Subtasks**:
- [ ] T007 Inventory named kernel subclasses in tests/** (alpha.158 ban surface) (WP02)
- [ ] T008 Refactor integration tests to sanctioned boot path (WP02)
- [ ] T009 Run `--testsuite MinooIntegration`; capture failures, fix, repeat (WP02)
- [ ] T010 Commit integration test refactors (WP02)

**Dependencies**: WP01.
**Risks**: Sanctioned boot path may not yet be obvious from release notes — may require reading framework testing surface to find the new helper; reflection-based boot pattern may be the *thing* being banned.

---

### WP03 — Unit tests + entities: alpha.165 `_data` symmetry

**Goal**: Reconcile Minoo entity classes and unit tests with FieldStorage `_data` read/write symmetry tightening.
**Priority**: P1
**Independent test**: `./vendor/bin/phpunit --testsuite MinooUnit` exits 0 with assertion count >= 2568.
**Acceptance**: US2/AS1, FR-003
**Estimated prompt**: ~400 lines
**Prompt**: [WP03-unit-data-symmetry.md](./tasks/WP03-unit-data-symmetry.md)

**Subtasks**:
- [ ] T011 Run `--testsuite MinooUnit`; capture failures verbatim (WP03)
- [ ] T012 Diagnose FieldStorage `_data` symmetry failures (alpha.165) (WP03)
- [ ] T013 Fix Minoo entity classes / access tests for symmetry (WP03)
- [ ] T014 Re-run `--testsuite MinooUnit`; verify green (WP03)
- [ ] T015 Commit unit test + entity fixes (WP03)

**Dependencies**: WP01.
**Risks**: Minoo's longstanding "fields live in _data JSON blob" pattern may require touching most of the 17 entity classes; if the change is broad and invasive, escalate per Complexity Tracking section in plan.md.

---

### WP04 — Schema diagnostics + migrations

**Goal**: Address any drift surfaced by alpha.171's new diagnostics: column-vs-`_data` drift, `BUNDLE_SUBTABLE_MISSING`, `ORPHAN_BUNDLE_SUBTABLE`.
**Priority**: P1
**Independent test**: `bin/waaseyaa schema:check` exits 0.
**Acceptance**: SC-003, FR-004
**Estimated prompt**: ~350 lines
**Prompt**: [WP04-schema-diagnostics.md](./tasks/WP04-schema-diagnostics.md)

**Subtasks**:
- [ ] T016 Run `bin/waaseyaa schema:check`; capture output (WP04)
- [ ] T017 Diagnose column-vs-`_data` drift findings (WP04)
- [ ] T018 Diagnose BUNDLE_SUBTABLE_MISSING / ORPHAN_BUNDLE_SUBTABLE findings (WP04)
- [ ] T019 Generate migrations via `bin/waaseyaa make:migration <name>` (WP04)
- [ ] T020 Run migrations; re-verify schema:check is clean (WP04)
- [ ] T021 Commit migrations (WP04)

**Dependencies**: WP01.
**Risks**: Migration bodies must use the `_data` CLOB schema (not individual columns) — verify against existing migrations in `migrations/`.

---

## Phase 3 — Verification surface (parallelizable)

### WP05 — PHPStan baseline regen

**Goal**: Regenerate `phpstan-baseline.neon` if framework upgrade introduced new analysis drift.
**Priority**: P2
**Independent test**: `./vendor/bin/phpstan analyse` exits 0 (with regenerated baseline if needed).
**Acceptance**: FR-008
**Estimated prompt**: ~180 lines
**Prompt**: [WP05-phpstan-baseline.md](./tasks/WP05-phpstan-baseline.md)

**Subtasks**:
- [ ] T022 [P] Run `./vendor/bin/phpstan analyse`; capture drift (WP05)
- [ ] T023 [P] Regenerate baseline if needed (WP05)
- [ ] T024 [P] Commit phpstan-baseline.neon (WP05)

**Dependencies**: WP02, WP03, WP04 (must be after all code/test fixes land).
**Risks**: Baseline regeneration could mask real new errors — review the diff manually before committing.

---

### WP06 — Bimaaji MCP verification

**Goal**: Confirm the bimaaji MCP server still ships and registers after upstream spec-MCP removal in alpha.164.
**Priority**: P2
**Independent test**: `composer bimaaji-mcp-install` exits 0; `mcp__bimaaji__*` tools resolve in Claude Code after restart.
**Acceptance**: US4/AS1–AS2, FR-005
**Estimated prompt**: ~200 lines
**Prompt**: [WP06-bimaaji-mcp-verification.md](./tasks/WP06-bimaaji-mcp-verification.md)

**Subtasks**:
- [ ] T025 [P] Run `composer bimaaji-mcp-install`; verify exit 0 (WP06)
- [ ] T026 [P] Verify .claude/settings.json still registers minoo + bimaaji (WP06)
- [ ] T027 [P] Document MCP verification result (WP06)

**Dependencies**: WP01.
**Risks**: alpha.164 explicitly removed *spec* MCP servers but `waaseyaa/bimaaji` is a separate package — should be unaffected, but verify the install script's Node entry point still resolves.

---

### WP07 — Local smoke tests

**Goal**: Verify the public surface renders end-to-end on the upgraded framework before merge.
**Priority**: P1
**Independent test**: All 5 canonical routes return HTTP 200 with body > 1KB and `<title>` present.
**Acceptance**: SC-004, US3/AS1–AS5, FR-007
**Estimated prompt**: ~350 lines
**Prompt**: [WP07-local-smoke-tests.md](./tasks/WP07-local-smoke-tests.md)

**Subtasks**:
- [ ] T028 Boot local server `php -S 0.0.0.0:8080 -t public public/index.php` (WP07)
- [ ] T029 Smoke `/` (anonymous public homepage) (WP07)
- [ ] T030 Smoke `/feed` (authenticated) (WP07)
- [ ] T031 Smoke `/games`, `/games/shkoda`, `/games/crossword`, `/games/agim` (WP07)
- [ ] T032 Smoke a community detail page (NC client adapter exercise) (WP07)
- [ ] T033 Smoke `/elder-support` request form (WP07)
- [ ] T034 Document smoke results (any non-fatal issues for follow-up) (WP07)

**Dependencies**: WP02, WP03, WP04.
**Risks**: Past framework jumps produced zero-byte 200s — verify by body size, not status code; NC client adapter on community detail pages depends on production source-manager URL `localhost:8050` which won't be reachable locally — accept network failure on that route as expected dev behavior or stub.

---

## Phase 4 — Documentation and cutover

### WP08 — Docs and memory pointers

**Goal**: Update CLAUDE.md framework-sync line, MEMORY.md framework version pointer, and confirm composer provider parity.
**Priority**: P2
**Independent test**: ComposerProviderParityTest passes; CLAUDE.md "Last framework sync" line shows alpha.171.
**Acceptance**: FR-006, FR-009
**Estimated prompt**: ~200 lines
**Prompt**: [WP08-docs-memory-update.md](./tasks/WP08-docs-memory-update.md)

**Subtasks**:
- [ ] T035 Update CLAUDE.md "Last framework sync" line to alpha.171 (WP08)
- [ ] T036 Update Minoo MEMORY.md framework version pointer (WP08)
- [ ] T037 Verify ComposerProviderParityTest still passes (WP08)
- [ ] T038 Commit docs / memory updates (WP08)

**Dependencies**: WP07.
**Risks**: MEMORY.md should never carry volatile facts — keep the framework-version line factual and short, use a pointer to composer.lock for canonical truth.

---

### WP09 — Push, deploy, verify production

**Goal**: Land the upgrade on `main` and verify production health with body-size checks.
**Priority**: P1
**Independent test**: All 5 production routes return 200 with body > 1KB.
**Acceptance**: SC-005
**Estimated prompt**: ~250 lines
**Prompt**: [WP09-deploy-verify-production.md](./tasks/WP09-deploy-verify-production.md)

**Subtasks**:
- [ ] T039 Final full PHPUnit run on clean repo (WP09)
- [ ] T040 Push to main (triggers GitHub Actions auto-deploy) (WP09)
- [ ] T041 Watch GitHub Actions deploy run to completion (WP09)
- [ ] T042 Verify production with curl body-size + title checks on 5 routes (WP09)
- [ ] T043 Document deploy outcome / rollback decisions (WP09)

**Dependencies**: WP05, WP06, WP07, WP08.
**Risks**: Auto-deploys on push to main; if deploy fails, follow incident-layering principle (assume multiple stacked bugs; budget 2-4 fix-deploy-verify rounds, not one).

---

## Phase 5 — Optional follow-up

### WP10 — Wire schema diagnostics into CI

**Goal**: Add `BUNDLE_SUBTABLE_MISSING` / `ORPHAN_BUNDLE_SUBTABLE` / column-vs-`_data` checks to CI to prevent silent drift on future upgrades.
**Priority**: P3 (optional spinoff)
**Independent test**: A deliberate test drift fails the CI build with the appropriate diagnostic.
**Acceptance**: US5/AS1–AS2
**Estimated prompt**: ~250 lines
**Prompt**: [WP10-ci-wire-diagnostics.md](./tasks/WP10-ci-wire-diagnostics.md)

**Subtasks**:
- [ ] T044 Add `bin/waaseyaa schema:check` invocation to CI workflow (WP10)
- [ ] T045 Add CI step asserting BUNDLE_SUBTABLE_MISSING / ORPHAN_BUNDLE_SUBTABLE fail the build (WP10)
- [ ] T046 Verify CI fails on a deliberate drift; revert the drift (WP10)
- [ ] T047 Commit CI workflow changes (WP10)

**Dependencies**: WP04, WP09.
**Risks**: If WP01–WP09 take longer than budgeted, spin WP10 out as a separate follow-up issue rather than expanding the mission.

---

## Parallelization Map

```
WP01 (composer bump)
   ├── WP02 (integration tests, alpha.158)         ─┐
   ├── WP03 (unit tests + entities, alpha.165)      ├─→ WP05 (phpstan baseline) [P with WP06, WP07]
   ├── WP04 (schema diagnostics + migrations)      ─┘
   └── WP06 (bimaaji MCP) [P with WP02–WP04]

WP02, WP03, WP04
   └── WP07 (local smoke tests)
       └── WP08 (docs + memory)
           └── WP09 (push, deploy, verify)
               └── WP10 (CI wiring) [optional spinoff]
```

## MVP Scope Recommendation

WP01 + WP02 + WP03 + WP04 + WP07 + WP09 = the minimum that ships a working alpha.171 upgrade to production. WP05 (phpstan baseline) is highly recommended (low cost, prevents future drift). WP06 (bimaaji MCP), WP08 (docs), WP10 (CI wiring) can spin off as follow-ups if scope creeps.

## Notes

- All work happens directly on `main` (per project workflow — no feature branches for infra upgrades).
- Each WP commits incrementally rather than batching; this keeps the deploy-on-push window tight and forces early signal on regressions.
- If WP02 or WP03 reveals invasive refactoring (touching most of the 17 entity classes), pause and escalate per the Complexity Tracking section of plan.md before continuing.
