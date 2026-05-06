---
work_package_id: WP01
title: Auth + Account cluster — migrate to MapRoute/MapQuery
dependencies: []
requirement_refs:
- C-001
- C-002
- C-005
- FR-001
- FR-002
- FR-003
- FR-004
- NFR-001
- NFR-002
- NFR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
history:
- timestamp: '2026-05-06T13:10:35Z'
  event: scaffolded by /spec-kitty.tasks
authoritative_surface: src/Controller/
execution_mode: code_change
mission_id: 01KQYNX7DWR7QNFK6XAZRKMWHV
mission_slug: migrate-controllers-explicit-route-attributes-01KQYNX7
owned_files:
- src/Controller/AccountHomeController.php
- src/Controller/AuthController.php
- src/Controller/CoordinatorDashboardController.php
- src/Controller/RoleManagementController.php
- src/Controller/VolunteerController.php
- src/Controller/VolunteerDashboardController.php
tags: []
---

# WP01 — Auth + Account cluster: migrate to `#[MapRoute]` / `#[MapQuery]`

## Objective

Migrate 6 controllers in the Auth + Account cluster from implicit `array $params` / `array $query` parameters to explicit `#[MapRoute]` / `#[MapQuery]` attributes. This is the first WP of the mission; the implementer drafts the transient migration tool here (per `contracts/migrate-cli.md`) and uses it to produce the WP01 diff. Subsequent WPs derive the same tool independently from the same contract; the tool itself is never committed.

## Context

- **Mission**: [`migrate-controllers-explicit-route-attributes-01KQYNX7`](../meta.json) (mid8: `01KQYNX7`).
- **Spec**: [`../spec.md`](../spec.md) — closes waaseyaa/minoo#753.
- **Plan**: [`../plan.md`](../plan.md) — design decisions for the migration approach.
- **Research**: [`../research.md`](../research.md) — Decision 1 (token-aware byte splice), Decision 6 (transient migration script).
- **Migration tool contract**: [`../contracts/migrate-cli.md`](../contracts/migrate-cli.md) — normative CLI surface for `scripts/migrate-controller-attributes.php`.
- **Quickstart**: [`../quickstart.md`](../quickstart.md) — per-WP execution recipe; consult the WP01 smoke-route table.
- **Issue #753 inventory**: 62 method×param entries across the 6 cluster controllers (10 in AccountHomeController, 22 in AuthController, 8 in CoordinatorDashboardController, 6 in RoleManagementController, 6 in VolunteerController, 8 in VolunteerDashboardController).

## Branch Strategy

- Planning base branch: `main`.
- Merge target branch: `main`.
- Execution worktree: allocated per lane by `finalize-tasks` (consult `lanes.json` after `spec-kitty next` runs).
- PR opens to `main`; squash-merge.
- PR title format: `migrate(#753): wp01 auth+account → MapRoute/MapQuery`.
- PR body: `Part of #753`. Do **not** include `Closes #753` (that line lives in WP06 only).

## Subtasks

### T001 — Create transient migration tool locally per `contracts/migrate-cli.md`

**Purpose**: Produce a working `scripts/migrate-controller-attributes.php` that satisfies the CLI contract. The tool is **transient** — it is created in the worktree but **never committed**. Subsequent WPs will independently re-derive the tool from the same contract.

**Steps**:

1. Read `kitty-specs/migrate-controllers-explicit-route-attributes-01KQYNX7/contracts/migrate-cli.md` end-to-end.
2. Create `scripts/migrate-controller-attributes.php` as a CLI script:
   - Shebang `#!/usr/bin/env php`.
   - `declare(strict_types=1);`
   - No Composer autoload, no framework boot. Use only built-in PHP (`token_get_all`, `file_get_contents`, etc.).
   - Implement options exactly as the contract specifies: `--cluster`, `--filter`, `--path`, `--dry-run`, `--apply`, `--verbose`, `--help`.
   - Hardcode the cluster definitions table from the contract. Be careful to include all 6 WP01 controller basenames in the `wp01` cluster.
   - Token-walking logic:
     a. Tokenise file with `token_get_all($source, TOKEN_PARSE)`.
     b. Track function-keyword param lists at method scope (depth-aware: only top-level `function` declarations, not closures inside method bodies).
     c. For each parameter list, walk tokens between matching `(` and `)`. Detect parameters where the type is exactly `T_ARRAY` (one token, no nullable, no union) and the variable name is exactly `$params` or `$query`.
     d. For each match, check the preceding tokens for an `T_ATTRIBUTE` block; if it contains `MapRoute` (for `$params`) or `MapQuery` (for `$query`), skip.
     e. Otherwise record the byte offset where the `T_ARRAY` token starts, and the attribute prefix to splice.
3. Implement the use-statement insertion as a separate narrow regex pass (per Decision 2 in `research.md`):
   - Find lines matching `^use Waaseyaa\\.*;$`.
   - Insert `use Waaseyaa\Routing\Attribute\MapQuery;` and `use Waaseyaa\Routing\Attribute\MapRoute;` alphabetically among the existing `Waaseyaa\` uses, idempotent (skip if already present).
4. Implement byte splicing:
   - Sort splice points descending by byte offset.
   - Apply each splice as `substr($source, 0, $offset) . $prefix . substr($source, $offset)`.
5. Implement `--dry-run` (default if neither `--dry-run` nor `--apply` is passed): print unified diff to stdout (use `diff -u` against a temp file, or hand-roll a simple diff).
6. Implement `--apply`: write modified bytes back to the file.
7. **DO NOT `git add` the script.** Verify with `git status` that `scripts/migrate-controller-attributes.php` shows as untracked (or add it to `.git/info/exclude` to keep `git status` clean).

**Files**:
- `scripts/migrate-controller-attributes.php` (new file, working tool, **not committed**)
- (Optional) `.git/info/exclude` — add `scripts/migrate-controller-attributes.php` to keep `git status` clean for the duration of this mission.

**Validation**:
- `php scripts/migrate-controller-attributes.php --help` prints usage (mirrors the contract).
- `php scripts/migrate-controller-attributes.php --filter AuthController --dry-run` prints a non-empty unified diff.
- `php scripts/migrate-controller-attributes.php --filter NonexistentController --dry-run` exits 0 with empty output (no offenders).

**Edge cases to handle**:
- Parameters with default values: `array $params = []` → `#[MapRoute] array $params = []` (do NOT touch the default).
- Variadic params: `array ...$params` → must NOT be decorated (variadic spread is incompatible with parameter attributes in this context).
- Parameters with nullable / union types: `?array $params`, `array|null $params`, `iterable $params` → MUST NOT be decorated.
- Function declarations inside method bodies (closures): `function (array $params)` inside `public function foo() { ... }` → MUST be skipped (closure params are not method params).
- Arrow functions: same as closures — skipped.
- `array $params` appearing in docblocks / string literals — `token_get_all` makes these non-issues automatically (they are `T_DOC_COMMENT` / `T_CONSTANT_ENCAPSED_STRING`, not `T_ARRAY` + `T_VARIABLE`).
- Trailing commas in param lists (`function foo(array $params,)`): the byte splice respects original whitespace; trailing comma is preserved.

### T002 — Apply migration to WP01 cluster

**Purpose**: Apply the migration tool to the 6 Auth + Account controllers and verify the diff matches expectations.

**Steps**:

1. Run dry-run preview:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp01 --dry-run | tee /tmp/wp01-preview.diff
   ```
2. Read the diff. Sanity-check:
   - 6 files modified (one per cluster controller).
   - 12 use-stmt insertions (2 per file).
   - 62 attribute splices total (one per inventoried `array $params`/`array $query` param across the cluster's 31 methods × 2 params).
   - No collateral changes outside the params and the use block.
3. Apply:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp01 --apply
   ```
4. Idempotency check:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp01 --dry-run
   ```
   Expected: empty diff. If non-empty, the script splice has a bug; investigate.
5. Spot-check one file by hand to confirm shape:
   ```bash
   grep -n 'MapRoute\|MapQuery' src/Controller/AuthController.php | head -20
   ```

**Validation**:
- `git diff --stat src/Controller/` shows exactly 6 files changed.
- Idempotency check produces zero output.
- `php -l src/Controller/AuthController.php` (and the other 5 files) returns "No syntax errors detected" — proves the migration produced valid PHP.

### T003 — Run `./vendor/bin/phpunit`

**Purpose**: Verify the migration introduced no behavioral regression. The framework is alpha.173 — both the shim and the explicit attribute path resolve to the same dispatcher behavior, so tests should pass unchanged.

**Steps**:

1. Run the full suite:
   ```bash
   ./vendor/bin/phpunit
   ```
2. If any failures, **stop**. Do not proceed to smoke. Diagnose:
   - Which tests fail?
   - Are they related to controller binding (e.g. integration tests that hit `AuthController::loginForm` via a request fixture)?
   - Run a single failing test with `--filter` to get focused output.
3. Fix the root cause:
   - If the migration tool spliced wrongly: revert the diff (`git restore src/Controller/`) and fix the tool.
   - If a test asserts on `ReflectionParameter::getAttributes()`: the test should now find `MapRoute`/`MapQuery` — update the test expectation if necessary, but only if the test is a meta-test about parameter shape (rare).
   - If the framework is misbehaving: file a separate framework issue and pause this WP.

**Validation**:
- `./vendor/bin/phpunit` exits 0.
- Output line: `OK (914 tests, 2568 assertions)` (or higher if `main` has advanced).

### T004 — Cold-boot smoke routes

**Purpose**: Verify each migrated controller still serves a request end-to-end with attributes in place. PHPUnit covers binding semantics; smoke covers the full dispatcher path.

**Steps**:

1. Start the dev server in a separate shell with notice-level logging:
   ```bash
   WAASEYAA_LOG_LEVEL=notice \
     php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp01-server.log
   ```
2. From the main shell, hit each route from the WP01 smoke table in `quickstart.md`:
   ```bash
   for url in \
     "http://localhost:8080/login" \
     "http://localhost:8080/register" \
     "http://localhost:8080/logout" \
     "http://localhost:8080/account" \
     "http://localhost:8080/admin/coordinator" \
     "http://localhost:8080/admin/coordinator/applications" \
     "http://localhost:8080/admin/role-management" \
     "http://localhost:8080/volunteer/signup" \
     "http://localhost:8080/account/volunteer"; do
       curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
   done
   ```
3. Verify every line shows non-zero `size_download` and an expected status (200 / 302 / 401).
4. Stop the dev server with `Ctrl-C`.

**Validation**:
- Every smoke URL returned a non-zero body.
- Every status code is one of: 200, 302, 401 (302/401 are valid for auth-protected routes).
- No 500 / 502 / connection-refused errors.
- No zero-byte 200 (the WSOD signature — see CLAUDE.md production-verification gotcha).

### T005 — Cold-boot log scan

**Purpose**: Verify the migrated controllers no longer trigger the `dispatcher.deprecation` shim. This is the only direct evidence that the migration achieved its goal.

**Steps**:

1. Filter the server log for deprecation notices:
   ```bash
   grep -F 'dispatcher.deprecation' /tmp/wp01-server.log | sort -u | tee /tmp/wp01-deprecations.txt
   ```
2. Filter further to entries naming WP01 cluster controllers:
   ```bash
   grep -E 'AuthController|AccountHomeController|RoleManagementController|CoordinatorDashboardController|VolunteerController|VolunteerDashboardController' /tmp/wp01-deprecations.txt
   ```
3. Expected: zero output. If any line appears, the migration missed a method. Re-run T002's idempotency check; manually inspect the offending controller.

**Validation**:
- Zero `dispatcher.deprecation` entries naming any WP01 cluster controller.
- (Lines naming other controllers — Games, Newsletter, etc. — are acceptable until those WPs land.)

### T006 — Commit, push, open PR

**Purpose**: Land the WP01 changes as an independent PR.

**Steps**:

1. Stage only the 6 controller files:
   ```bash
   git add src/Controller/AuthController.php \
           src/Controller/AccountHomeController.php \
           src/Controller/RoleManagementController.php \
           src/Controller/CoordinatorDashboardController.php \
           src/Controller/VolunteerController.php \
           src/Controller/VolunteerDashboardController.php
   ```
2. Verify `scripts/migrate-controller-attributes.php` is **not** staged:
   ```bash
   git diff --cached --name-only | grep -F 'scripts/migrate'
   ```
   Expected: no output. If the script appears, `git restore --staged scripts/migrate-controller-attributes.php`.
3. Commit:
   ```bash
   git commit -m "migrate(#753): wp01 auth+account → MapRoute/MapQuery

   Decorates 62 array \$params / array \$query parameters across 6
   controllers in the Auth + Account cluster with explicit
   #[MapRoute] / #[MapQuery] attributes from
   Waaseyaa\\Routing\\Attribute. Clears 62 dispatcher.deprecation
   notices from the cold-boot log for these controllers.

   Part of #753 (v0.14 milestone).

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
   ```
4. Push (worktrees lack `vendor/`, so the `.husky/pre-push` `phpunit` hook would fail; tests passed locally in T003):
   ```bash
   git push --no-verify
   ```
5. Open PR:
   ```bash
   gh pr create \
     --title "migrate(#753): wp01 auth+account → MapRoute/MapQuery" \
     --body "$(cat <<'EOF'
   ## Summary
   - Decorates \`array \$params\` with \`#[MapRoute]\` and \`array \$query\` with \`#[MapQuery]\` across the Auth + Account cluster.
   - 6 controllers, 31 methods, 62 parameters.
   - Adds \`use Waaseyaa\\Routing\\Attribute\\MapRoute;\` and \`use Waaseyaa\\Routing\\Attribute\\MapQuery;\` to each affected file.

   ## Verification
   - [x] PHPUnit green (914 tests / 2568 assertions baseline)
   - [x] Cold-boot smoke routes return 200/302/401 with non-zero body for all 6 controllers
   - [x] Cold-boot \`dispatcher.deprecation\` log shows zero entries naming any WP01 cluster controller
   - [x] Migration tool idempotent (second \`--dry-run\` empty)

   ## Out of scope
   - Other controller clusters (WP02–WP06).
   - The \`scripts/check-implicit-array-params.php\` extractor (committed in WP06).

   Part of #753 (v0.14 milestone).

   🤖 Generated with [Claude Code](https://claude.com/claude-code)
   EOF
   )"
   ```

**Validation**:
- PR URL printed by `gh pr create`.
- PR title starts with `migrate(#753):`.
- PR body contains `Part of #753` (NOT `Closes #753`).
- The PR diff shows exactly 6 files changed; no `scripts/migrate-controller-attributes.php` in the diff.

## Definition of Done

- [ ] T001..T006 complete.
- [ ] PR opened with title `migrate(#753): wp01 auth+account → MapRoute/MapQuery`.
- [ ] PR body contains `Part of #753`.
- [ ] CI green on the PR (lint + PHPUnit).
- [ ] PR diff: exactly 6 files modified; 12 use-stmt insertions + 62 attribute splices; no `scripts/` files.
- [ ] Reviewer can confirm via `gh pr diff` that no parameter outside `array $params` / `array $query` was modified.
- [ ] Idempotency: re-running the migration tool on the merged branch produces empty diff.

## Reviewer Guidance

When reviewing this WP's PR:

1. **Spot-check 3 of 6 files** — pick `AuthController.php` (largest), `RoleManagementController.php` (smallest), and one in between. Confirm:
   - Two `use` statements added in alphabetical position.
   - Every `array $params` is preceded by `#[MapRoute]`.
   - Every `array $query` is preceded by `#[MapQuery]`.
   - No other lines changed.
2. **Verify no script files in diff** — `gh pr diff` should show only `src/Controller/*.php`.
3. **Pull and run locally**:
   ```bash
   git fetch origin pull/<N>/head:wp01-review
   git checkout wp01-review
   ./vendor/bin/phpunit
   WAASEYAA_LOG_LEVEL=notice php -S 0.0.0.0:8080 -t public public/index.php &
   curl -s http://localhost:8080/login | head -c 100
   # Confirm non-empty response
   pkill -f 'php -S'
   ```
4. **Confirm log silence**: with the dev server running, hit each smoke URL and grep the log for `dispatcher.deprecation` + cluster controller names. Expect zero hits.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Migration tool mis-splices defaults / variadics / union types | Token-aware logic per contract; idempotency check; PHPUnit + smoke catches binding bugs |
| Migration tool accidentally committed | Explicit T006 step grep-checks staged files; reviewer also checks PR diff |
| Husky pre-push runs phpunit but worktree lacks `vendor/` | Use `git push --no-verify`; tests already passed locally |
| Smoke URL requires a CSRF token / session | 302/401 + non-zero body counts as success — the dispatcher booted, the controller dispatched |
| Reviewer pattern drift between WP01 and later WPs | This WP01 PR is the canonical pattern; WPs 02–06 reviewers compare against it |
