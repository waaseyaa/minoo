---
work_package_id: WP06
title: Docs, Issue Tracking, Final Gate
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
requirement_refs:
- FR-011
- FR-012
- FR-013
- NFR-001
- NFR-002
- NFR-003
- NFR-004
- NFR-005
- NFR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T031
- T032
- T033
- T034
- T035
- T036
history:
- at: '2026-05-19'
  by: specify
  note: WP created from spec.md §10 WP06
authoritative_surface: CLAUDE.md
execution_mode: code_change
mission_id: 01KS0WZ7MX6P96NP0V95RBTPG2
mission_slug: adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7
owned_files:
- CLAUDE.md
tags: []
---

# WP06 — Docs, Issue Tracking, Final Gate

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Branch contract**: planning base = `main`, final merge target = `main`.
**Run command**: `spec-kitty agent action implement WP06 --agent <name>` — depends on WP01..WP05 being approved.
**Requirement refs**: FR-011, FR-012, FR-013, NFR-001, NFR-002, NFR-003, NFR-004, NFR-005, NFR-006

## Objective

Update `CLAUDE.md` and `MEMORY.md` to reflect the alpha.182 sync, file three GitHub issues for out-of-scope alpha.181 surfaces, and run the full mission-acceptance quality gate (phpunit + phpstan + cs-fixer + boundary-check + curl smokes). When this WP is approved, the mission is ready for mission-review and merge to `main`.

## Context

This is the last WP. Every prior WP has landed binding/bypass fixes; the mission branch is now green. This WP makes the upgrade visible in operator documentation, files trackers for the alpha.181 surfaces we're deliberately deferring, and runs the full gate to prove mission acceptance.

## Files Owned by This WP

- `CLAUDE.md` (in repo)
- `~/.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md` (outside repo — not in `owned_files`, but updated by T032)
- GitHub issues (filed via `gh issue create`)

## Subtasks

### T031 — Update CLAUDE.md sync line + highlights

**Purpose**: Update the framework-sync section at the top of `CLAUDE.md` to point at alpha.182 and explain what changed in alpha.181 + alpha.182.

**Steps**:
1. Edit `CLAUDE.md` to change `Last framework sync: Waaseyaa alpha.180 (2026-05-17)` to `Last framework sync: Waaseyaa alpha.182 (2026-05-19)`.
2. Add new bullets to the "Highlights since alpha.175" block:
   ```markdown
   - **alpha.181** — `SqlEntityQuery::accessCheck(true)` is now the default and fail-closed. Every `getQuery()` call must `->setAccount($account)` or explicitly `->accessCheck(false)` (with a row in `docs/security/sql-entity-query-access-check-bypass-audit.md`); otherwise the query throws `Waaseyaa\EntityStorage\Exception\MissingQueryAccountException`. Adopted in Minoo via mission `adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`.
   - **alpha.182** — follow-up fixes for two framework-internal bypass sites missed by alpha.181 (`PathAliasResolver` and `Groups\Tests\Integration\TwoBundleCoexistenceTest`). No Minoo action required beyond pinning the constraint.
   ```
3. Confirm no other CLAUDE.md sections need updates (Architecture, Code Style, Workflow — none changed).

**Files**: `CLAUDE.md`.

**Validation**: `grep 'alpha.182' CLAUDE.md` returns at least one match in the sync line.

---

### T032 — Update auto-memory MEMORY.md project state line

**Purpose**: Update the project-memory file at `~/.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md` to reflect the bump.

**Steps**:
1. Open `~/.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md`.
2. Locate the "Project State" section. Find the framework-version line referencing alpha.173 (or whatever the current value is).
3. Update to read: `- **Framework version**: see `CLAUDE.md` (sync line). Notable bumps: alpha.180 (2026-05-17, M-004 two-axis storage) → alpha.182 (2026-05-19, mission `adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`, fail-closed SqlEntityQuery access checking).`
4. **This file is outside the repo** — it is not committed, so `owned_files` does not include it. The edit is operator-side only.

**Files**: `~/.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md` (outside repo).

**Validation**: `grep 'alpha.182' ~/.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md` returns the new line.

---

### T033 — File 3 GitHub issues for out-of-scope alpha.181 surfaces

**Purpose**: Per FR-013, capture the deferred work so it doesn't fall off the radar.

**Steps**: Use `gh issue create` from repo root. Three issues:

1. **Adopt Waaseyaa AI Agent Executor (framework #1496)**:
   ```bash
   gh issue create --title "Adopt Waaseyaa AI Agent Executor (framework #1496)" --body "$(cat <<'EOF'
   Framework alpha.181 shipped a full AI agent runtime: `bin/waaseyaa ai:run`, `POST /api/ai/agent/run`, persisted `AgentRun` + `AgentAuditLog`, HITL state machine, provider retry, Messenger transport. See ../waaseyaa/CHANGELOG.md (alpha.181 Added).

   Minoo deferred adoption during the alpha.182 upgrade mission (`adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`). To adopt:
   1. Decide on intended Minoo agent surfaces (which entity tools, which UI affordance).
   2. Wire `AgentRouteServiceProvider` into the routing stack.
   3. Configure `AgentRunAccessPolicy` for Minoo's role model.
   4. Decide HITL policy (`none` / `all` / `interactive`).
   5. Stand up the Messenger worker.

   No timeline. File against the AI subsystem milestone when one exists.
   EOF
   )" --label "framework-upgrade"
   ```

2. **Wire Waaseyaa 2FA endpoints to SSR UI (framework #1499)**:
   ```bash
   gh issue create --title "Wire Waaseyaa 2FA endpoints to SSR UI (framework #1499)" --body "$(cat <<'EOF'
   Framework alpha.181 shipped end-to-end 2FA at the API layer: `POST /api/auth/2fa/{setup,enable,verify,disable}`, `User` entity gained two `#[Field]` properties (`two_factor_secret`, `two_factor_recovery_codes_hash`), `LoginController` emits `state: 2fa_required`. See ../waaseyaa/CHANGELOG.md (alpha.181 Added).

   Minoo deferred SSR wiring during the alpha.182 upgrade mission. To adopt:
   1. Add 2FA setup screen to user settings (QR code render + recovery codes download).
   2. Update `AuthController` flow to handle `state: 2fa_required` response and render verify-code form.
   3. Decide enforcement policy (opt-in vs required for coordinators/elders).
   4. Update Playwright auth specs.

   No timeline.
   EOF
   )" --label "framework-upgrade"
   ```

3. **Adopt Waaseyaa dead-code Phase 4 fail-on-new gate (framework #1500)**:
   ```bash
   gh issue create --title "Adopt Waaseyaa dead-code Phase 4 fail-on-new gate (framework #1500)" --body "$(cat <<'EOF'
   Framework alpha.181 renamed `bin/audit-dead-code` to `bin/check-dead-code` and flipped the CI job from warn-only to fail-on-new. See ../waaseyaa/CHANGELOG.md (alpha.181 Changed).

   Minoo deferred adoption during the alpha.182 upgrade mission. To adopt:
   1. Re-attest Minoo's `phpstan-dead-code-baseline.neon`.
   2. Add `bin/check-dead-code` (or its Minoo equivalent) to `composer verify`.
   3. Add a CI job that blocks merge on new dead code.
   4. Document policy in CLAUDE.md (`@api` PHPDoc vs deliberate baseline).

   No timeline.
   EOF
   )" --label "framework-upgrade"
   ```

**Files**: None in repo — GitHub-side.

**Validation**: 3 issues exist; URLs captured in the WP completion note.

---

### T034 — Run full quality gate

**Purpose**: Run every CI-equivalent check locally and confirm they all pass on the post-mission state.

**Steps**:
```bash
./vendor/bin/phpunit 2>&1 | tail -20
composer phpstan 2>&1 | tail -10
composer cs-fixer 2>&1 | tail -10
bin/check-milestones 2>&1 | tail -10
```

Each must exit 0. Capture the last 5 lines of each output in the WP completion note.

**Files**: None.

**Validation**: All 4 commands exit 0; test count at or above 914 baseline.

---

### T035 — Run curl smokes (NFR-002, NFR-003)

**Purpose**: Prove anonymous + authenticated requests succeed under the new fail-closed default.

**Steps**:
1. Boot dev server: `php -S 0.0.0.0:8080 -t public public/index.php &`.
2. Anonymous smoke (NFR-002):
   ```bash
   curl -sS -o /tmp/wp06-home.html -w "%{http_code}/%{size_download}\n" http://localhost:8080/
   grep -c '<title>' /tmp/wp06-home.html
   ```
   Expect `200/<N>` with `N > 1000` and at least 1 `<title>` match.
3. Authenticated smoke (NFR-003): create a test account or use an existing one, capture session cookie, then:
   ```bash
   COOKIE='waaseyaa_session=...'
   curl -sS -b "$COOKIE" -o /tmp/wp06-feed.html -w "%{http_code}/%{size_download}\n" http://localhost:8080/feed
   ```
   Expect `200/<N>` with `N > 1000`.
4. Kill dev server.

**Files**: None.

**Validation**: Both smokes return 200 with body > 1000.

---

### T036 — Mark mission ready for review

**Purpose**: Confirm WP01..WP05 are all approved and submit WP06 for review.

**Steps**:
1. `spec-kitty agent action implement WP06 --agent <name>` should already have been used to start; on completion the agent submits the WP via `spec-kitty agent action review WP06`.
2. Confirm `spec-kitty status --mission 01KS0WZ7` shows all 6 WPs at `approved` after the implementer reviewer round.
3. Move the mission to `mission-review` per the spec-kitty workflow.

**Files**: None (state-machine action).

**Validation**: Mission state shows all 6 WPs approved; ready for mission-review skill.

---

## Definition of Done

- [ ] CLAUDE.md sync line updated to alpha.182.
- [ ] CLAUDE.md highlights block has alpha.181 + alpha.182 entries.
- [ ] MEMORY.md project-state framework-version line updated.
- [ ] 3 GitHub issues filed for out-of-scope surfaces.
- [ ] `./vendor/bin/phpunit` exits 0.
- [ ] `composer phpstan` exits 0.
- [ ] `composer cs-fixer` exits 0.
- [ ] `bin/check-milestones` exits 0.
- [ ] Anonymous SSR curl smoke returns 200 with body > 1000.
- [ ] Authenticated `/feed` curl smoke returns 200 with body > 1000.
- [ ] All 5 prior WPs are at `approved` state.

## Risks

- **A gate fails late.** If `phpunit` exits non-zero on something unrelated to the binding work (e.g. flaky integration test), the WP must triage: is this a real regression from the bump, or a pre-existing flake? If real, return to the offending WP for fix. If pre-existing flake, document and proceed.
- **Curl smoke requires a running server.** Don't forget to `kill` the dev server after; CI doesn't need this step (it runs the PHPUnit-only path).
- **MEMORY.md is outside repo.** Operator-side change only; cannot be reviewed as part of the WP. Document the change in the WP completion note.

## Reviewer Guidance

- Verify each `Definition of Done` checkbox is satisfied by running the commands listed.
- Spot-check the 3 GitHub issue URLs (open each, confirm body and labels).
- Approve when all gates pass.
