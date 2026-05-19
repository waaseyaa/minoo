---
work_package_id: WP05
title: Console Bypasses + Security Audit Doc
dependencies:
- WP01
- WP02
- WP03
- WP04
requirement_refs:
- FR-004
- FR-008
- FR-009
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T027
- T028
- T029
- T030
agent: "claude:opus-4-7:implementer:implementer"
shell_pid: "545689"
history:
- at: '2026-05-19'
  by: specify
  note: WP created from spec.md §10 WP05
authoritative_surface: src/Console/
execution_mode: code_change
mission_id: 01KS0WZ7MX6P96NP0V95RBTPG2
mission_slug: adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7
owned_files:
- src/Console/**
- docs/security/sql-entity-query-access-check-bypass-audit.md
- tests/App/Unit/Console/**
tags: []
---

# WP05 — Console Bypasses + Security Audit Doc

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Branch contract**: planning base = `main`, final merge target = `main`.
**Run command**: `spec-kitty agent action implement WP05 --agent <name>` — depends on WP01..WP04 being approved.
**Requirement refs**: FR-004, FR-008, FR-009, FR-010

## Objective

Verify and tighten system-context `accessCheck(false)` bypasses in `src/Console/**`, and publish Minoo's `docs/security/sql-entity-query-access-check-bypass-audit.md` enumerating every `accessCheck(false)` call site in the repo with file/line/justification/last-reviewed-date.

## Context

CLI handlers run without a request-scoped account. The framework's `EntityListHandler` does this (`packages/cli/src/Handler/EntityListHandler.php:27`) and is documented in the framework's audit doc — we mirror the pattern.

**Why this WP runs late**: WPs 02–04 may add their own `accessCheck(false)` bypasses (especially WP04 for ingestion + infrastructure). WP05 enumerates the **final** state and writes the audit doc against the post-WP04 mission branch. Running WP05 earlier would produce an audit doc that drifts as later WPs add bypasses.

## Files Owned by This WP

- `src/Console/GenealogyDemoSeedHandler.php` (already adopts bypass — verify)
- `src/Console/MessageDigestCommand.php` (add bypass)
- `docs/security/sql-entity-query-access-check-bypass-audit.md` (new)
- Corresponding test files

## Subtasks

### T027 — Verify GenealogyDemoSeedHandler bypass

**Purpose**: `GenealogyDemoSeedHandler` already uses `->accessCheck(false)` at lines 29, 42, 132 (verified during specify). Confirm it has inline comments and the bypass is correct.

**Steps**:
1. Read `src/Console/GenealogyDemoSeedHandler.php`.
2. Confirm each of the 3 `accessCheck(false)` sites has an inline comment of the shape `// System context: <reason>. See docs/security/sql-entity-query-access-check-bypass-audit.md.`
3. If comments are missing, add them.

**Files**: 1 file.

**Validation**: `grep -nB1 'accessCheck(false)' src/Console/GenealogyDemoSeedHandler.php` shows each bypass preceded by the inline comment.

---

### T028 — Add bypass to MessageDigestCommand

**Purpose**: `src/Console/MessageDigestCommand.php` aggregates messages for the digest email job. System context (cron/queue). Add `->accessCheck(false)` with audit-doc comment.

**Steps**:
1. Read `src/Console/MessageDigestCommand.php`.
2. Identify every `getQuery()` site.
3. Append `->accessCheck(false)` with inline comment:
   ```php
   // System context: digest aggregator must see all messages to build per-user summaries.
   // Per-user filtering happens at digest-render time, not at query time.
   // See docs/security/sql-entity-query-access-check-bypass-audit.md.
   $messages = $storage->getQuery()
       ->accessCheck(false)
       ->condition(...)
       ->execute();
   ```

**Files**: 1 file + test.

**Validation**: `./vendor/bin/phpunit --filter 'Console\\\\MessageDigest'` exits 0.

---

### T029 — Write Minoo security audit doc

**Purpose**: Publish `docs/security/sql-entity-query-access-check-bypass-audit.md` mirroring the framework's doc structure.

**Steps**:

1. Run `grep -rn 'accessCheck(false)' src/ --include='*.php'` and collect every match.
2. Create `docs/security/sql-entity-query-access-check-bypass-audit.md` with this structure:

   ```markdown
   # Minoo SqlEntityQuery `accessCheck(false)` Bypass Audit

   **Status:** Living document. Updated whenever a new `accessCheck(false)` call site lands in Minoo.
   **Last full audit:** 2026-05-19 (mission `adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7`).

   ## Why this document exists

   The Waaseyaa framework (alpha.181+) made `SqlEntityQuery::accessCheck(true)` the default. Every query that does not bind an account via `->setAccount($account)` must explicitly opt out via `->accessCheck(false)`, and every such opt-out is a documented choice. This file is Minoo's per-call-site audit.

   See also: the framework's `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md`.

   If you add a new `accessCheck(false)` call in Minoo, you MUST:
   1. Add a row to the table below.
   2. Add an inline comment at the call site referencing this document.

   Prefer `->setAccount($account)` over `->accessCheck(false)` whenever the calling code has access to a user account (typically via the `_account` request attribute on `Request` or via an injected `AccountInterface`).

   ## Current call sites

   ### Unconditional bypass — pure system context

   | File | Line | Justification | Last reviewed |
   |---|---|---|---|
   | `src/Console/GenealogyDemoSeedHandler.php` | 29 | Demo-seeder; CLI-driven, no request account. | 2026-05-19 |
   | `src/Console/GenealogyDemoSeedHandler.php` | 42 | Same. | 2026-05-19 |
   | `src/Console/GenealogyDemoSeedHandler.php` | 132 | Same. | 2026-05-19 |
   | `src/Console/MessageDigestCommand.php` | XX | Digest job runs from cron/queue; aggregates all messages then per-user filters at render. | 2026-05-19 |
   | `src/Ingestion/IngestMaterializer.php` | XX | Materializer dedupes across all communities; no per-user view restriction makes sense. | 2026-05-19 |
   | `src/Infrastructure/Fixture/FixtureResolver.php` | XX | Test/seed fixture resolver; no user context. | 2026-05-19 |
   | `src/Infrastructure/OpenGraph/CrisisOgImageService.php` | XX | OG image generator runs from cron/queue. | 2026-05-19 |
   | `src/Domain/Geo/Service/LocationService.php` | XX | Background geocoding; no per-user context. | 2026-05-19 |

   ### Conditional fallback — set account when available, bypass otherwise

   | File | Line | Justification (bypass branch only) | Last reviewed |
   |---|---|---|---|
   | `src/Http/Controller/Auth/AuthController.php` | XX | Pre-session lookup; bypass only runs when no session is established (login attempt path). | 2026-05-19 |
   | `src/Infrastructure/OpenGraph/PublicOgEntityLoader.php` | XX | OG card lookup; falls back when invoked from cron without account. | 2026-05-19 |

   ## How to audit

   To regenerate the bypass list:

   ```bash
   grep -rn 'accessCheck(false)' src/ --include='*.php'
   ```

   For each result:
   - **Keep (unconditional bypass)**: system context — runs without a user. Add a comment, add a row to the table above.
   - **Keep (conditional fallback)**: may or may not have an account. Add to conditional-fallback table.
   - **Switch**: user-facing — replace with `->setAccount($account)`.

   ## Future automation

   A CI grep gate that fails on new `accessCheck(false)` without an audit-doc row is a candidate follow-up. Not implemented in v1.
   ```

3. Fill in the `XX` line numbers with actual values from the grep.
4. Adjust the categorization (unconditional vs conditional) based on the actual code patterns landed in WP02–WP04.

**Files**: `docs/security/sql-entity-query-access-check-bypass-audit.md` (new).

**Validation**:
- [ ] Doc exists and follows the framework structure.
- [ ] Every `accessCheck(false)` site in `src/` is listed.
- [ ] Every row has a justification and `2026-05-19` date.

---

### T030 — Verify audit-doc / call-site parity

**Purpose**: The number of `accessCheck(false)` sites in `src/` must equal the number of rows in the audit doc.

**Steps**:
1. Count call sites: `grep -rn 'accessCheck(false)' src/ --include='*.php' | wc -l`
2. Count audit-doc rows: `grep -cE '^\| `src/' docs/security/sql-entity-query-access-check-bypass-audit.md`
3. Numbers must match.
4. For each row in the audit doc, open the file at the listed line and confirm the inline comment exists.

**Files**: None directly — verification step.

**Validation**: Counts match; all inline comments present.

---

## Definition of Done

- [ ] All 4 subtasks (T027–T030) complete.
- [ ] `src/Console/MessageDigestCommand.php` and `src/Console/GenealogyDemoSeedHandler.php` have inline bypass comments.
- [ ] `docs/security/sql-entity-query-access-check-bypass-audit.md` exists with every Minoo `accessCheck(false)` site listed.
- [ ] Audit-doc / call-site parity verified.

## Risks

- **Audit drift if WPs reorder.** This WP must run after WP02–WP04 are approved, or the audit doc captures a stale call-site list. Dependency frontmatter enforces this; spec-kitty `next` will not dispatch WP05 until WP02..WP04 are approved.
- **Line numbers move when code changes.** The audit doc lists line numbers — if a subsequent micro-edit shifts lines, the doc is wrong. WP06's gate will not re-verify line numbers (only count parity). Accept that line numbers are advisory; the file+pattern is the authoritative identifier.

## Reviewer Guidance

- Confirm the audit doc has the same shape as the framework's doc.
- Confirm grep count = doc row count.
- Approve when both conditions hold.

## Activity Log

- 2026-05-19T21:33:09Z – claude:opus-4-7:implementer:implementer – shell_pid=545689 – Started implementation via action command
- 2026-05-19T21:35:42Z – claude:opus-4-7:implementer:implementer – shell_pid=545689 – Audit doc parity 53=53; MessageDigest bypassed; Genealogy seeder verified
- 2026-05-19T21:35:44Z – claude:opus-4-7:implementer:implementer – shell_pid=545689 – Approved: full audit-doc parity
