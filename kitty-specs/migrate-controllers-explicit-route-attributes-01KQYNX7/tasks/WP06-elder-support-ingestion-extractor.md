---
work_package_id: WP06
title: 'Elder Support + Ingestion + extractor + reconciliation; closes #753'
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
requirement_refs:
- C-001
- C-002
- C-004
- C-005
- C-006
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- NFR-001
- NFR-002
- NFR-003
- NFR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Per-lane worktree off main, taken AFTER WP01..WP05 have squash-merged so reconciliation reflects the final state.
subtasks:
- T031
- T032
- T033
- T034
- T035
- T036
- T037
- T038
history:
- timestamp: '2026-05-06T13:10:35Z'
  event: scaffolded by /spec-kitty.tasks
authoritative_surface: src/Controller/
execution_mode: code_change
mission_id: 01KQYNX7DWR7QNFK6XAZRKMWHV
mission_slug: migrate-controllers-explicit-route-attributes-01KQYNX7
owned_files:
- src/Controller/ElderSupportController.php
- src/Controller/ElderSupportWorkflowController.php
- src/Controller/IngestionApiController.php
- src/Controller/IngestionDashboardController.php
- scripts/check-implicit-array-params.php
tags: []
---

# WP06 — Elder Support + Ingestion + extractor + reconciliation; closes #753

## Objective

Three responsibilities, all required for the mission to close:

1. **Migrate** the Elder Support + Ingestion controller cluster (4 controllers) to explicit `#[MapRoute]` / `#[MapQuery]` attributes — the last cluster.
2. **Commit** `scripts/check-implicit-array-params.php`, the long-lived regression-guard extractor (per `contracts/check-cli.md`).
3. **Reconcile**: run the extractor against the full repo and confirm count = 0; cold-boot the server and confirm zero `dispatcher.deprecation` notices for any `App\Controller\*`. If drift is detected (a controller added between #753 filing and now), include the fix-up in this WP.

The mission auto-closes when this WP's PR merges (`Closes #753` in body).

## Context

- **Mission**: `01KQYNX7`. **Spec**: [`../spec.md`](../spec.md). **Plan**: [`../plan.md`](../plan.md). **Quickstart**: [`../quickstart.md`](../quickstart.md).
- **Migration tool contract**: [`../contracts/migrate-cli.md`](../contracts/migrate-cli.md) (transient — same as previous WPs).
- **Extractor contract**: [`../contracts/check-cli.md`](../contracts/check-cli.md) (long-lived — committed by this WP).
- **Pattern reference**: [`WP01-auth-account-cluster.md`](./WP01-auth-account-cluster.md) for the canonical migration recipe.
- **Issue #753 inventory**: 16 method×param entries across 4 cluster controllers (6 ElderSupport + 8 ElderSupportWorkflow + 10 IngestionApi + 2 IngestionDashboard = 26 — this differs slightly from the rough 16 estimate; consult the inventory directly).
- **Dependencies**: WP01..WP05 must have squash-merged to `main` before this WP can run. The reconciliation step (T037) verifies the **whole repo** is clean, which is only true once every other cluster is migrated.

## Branch Strategy

- Planning base / merge target: `main`.
- Execution worktree: per `lanes.json`. **Take the worktree only after WP01..WP05 have merged**, so the worktree's base is the post-WP05 `main`.
- PR title: `migrate(#753): wp06 elder support + ingestion + extractor; closes #753`.
- PR body: `Closes #753` (NOT `Part of #753` — this is the closing WP).

## Subtasks

### T031 — Recreate transient migration tool locally

**Purpose**: Recreate `scripts/migrate-controller-attributes.php` per `contracts/migrate-cli.md`. **Not committed.**

**Steps**: Same as WP01 T001 / WP02 T007. Implement contract; add to `.git/info/exclude`; smoke-test.

**Validation**: `php scripts/migrate-controller-attributes.php --filter ElderSupportController --dry-run` produces a non-empty diff.

### T032 — Apply migration to WP06 cluster

**Purpose**: Migrate the 4 Elder Support + Ingestion controllers.

**Steps**:

1. Dry-run preview:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp06 --dry-run | tee /tmp/wp06-preview.diff
   ```
   Sanity-check: 4 files; 8 use-stmt insertions; ~26 attribute splices.
2. Apply: `php scripts/migrate-controller-attributes.php --cluster wp06 --apply`.
3. Idempotency: dry-run again → empty.
4. Syntax check:
   ```bash
   for f in src/Controller/{ElderSupport,ElderSupportWorkflow,IngestionApi,IngestionDashboard}Controller.php; do
     php -l "$f"
   done
   ```

**WP-specific note**: ElderSupportWorkflow has 8 method×param entries covering the 6-state workflow (assign, confirm, decline, start, complete, cancel, reassign). Diff reading should confirm every method gets both attributes.

### T033 — Create `scripts/check-implicit-array-params.php`

**Purpose**: Author the long-lived regression-guard extractor per `contracts/check-cli.md`. This file IS committed to `main` and stays there indefinitely.

**Steps**:

1. Read `contracts/check-cli.md` end-to-end.
2. Create `scripts/check-implicit-array-params.php`:
   - Shebang `#!/usr/bin/env php`, `declare(strict_types=1);`.
   - No Composer autoload, no framework boot.
   - Implement options: `--path` (default `src/Controller`), `--format` (`text` | `json`, default `text`), `--quiet`, `--help`.
   - Reuse the same token-walking logic as the migration tool's read-only inspection pass (do not import; this is a separate file with its own copy of the walking logic — the extractor is the long-lived contract).
   - For each offender: print `<FQCN>::<method> $<param> -> #[<RecommendedAttribute>]` to stdout (text format) or assemble a JSON record (json format).
   - After scanning: print `TOTAL: <N> unannotated array params across <M> controllers` to **stderr**.
   - Exit 0 if N = 0; exit 1 if N > 0; exit 2 on argument error; exit 3 on parse error.
3. Make executable: `chmod +x scripts/check-implicit-array-params.php`.
4. Performance check: `time php scripts/check-implicit-array-params.php --path src/Controller --quiet` — confirm wall time < 2s per NFR-003.
5. Hand-spot-check the extractor against a known offender: in a separate worktree or using `git stash`, revert one of WP01's decorations, run the extractor, confirm it flags the reverted parameter. Restore with `git stash pop` or worktree-discard.

**Validation**:
- `php scripts/check-implicit-array-params.php --help` prints usage matching `contracts/check-cli.md`.
- Extractor performance < 2s.
- After the migration tool has been applied to all clusters (T032 + already-merged WP01..WP05), `php scripts/check-implicit-array-params.php` exits 0 with empty stdout and stderr summary `TOTAL: 0 unannotated array params across 0 controllers`.
- Hand-spot-check confirms the extractor catches a deliberately-reverted decoration.

### T034 — Run `./vendor/bin/phpunit`

**Purpose**: Verify the WP06 cluster migration introduced no regression.

**Steps**: Run `./vendor/bin/phpunit`; expect green.

**Validation**: `OK (914 tests, 2568 assertions)`.

### T035 — Cold-boot smoke routes

**Purpose**: Smoke the WP06 cluster.

**Steps**:

1. Cold-boot:
   ```bash
   WAASEYAA_LOG_LEVEL=notice \
     php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp06-server.log
   ```
2. Smoke per `quickstart.md` WP06 table:
   ```bash
   for url in \
     "http://localhost:8080/elder-support/request" \
     "http://localhost:8080/admin/elder-support" \
     "http://localhost:8080/api/ingestion/status?id=1" \
     "http://localhost:8080/admin/ingestion"; do
       curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
   done
   ```

**Validation**: All URLs non-zero body; statuses in {200, 302, 401, 404}.

**Note**: `ConsoleKernel` is broken on production per CLAUDE.md gotcha #493 — but that's a CLI concern; this is a HTTP smoke and unaffected.

### T036 — Cold-boot log scan for cluster controllers

**Steps**:

```bash
grep -F 'dispatcher.deprecation' /tmp/wp06-server.log | \
  grep -E 'ElderSupportController|ElderSupportWorkflowController|IngestionApiController|IngestionDashboardController'
```

**Validation**: zero matches.

### T037 — Final reconciliation

**Purpose**: Verify the **entire mission** is complete. This is the gate that decides whether #753 can close.

**Steps**:

1. **Extractor against full repo**:
   ```bash
   php scripts/check-implicit-array-params.php --path src/Controller
   echo "exit code: $?"
   ```
   Expected: exit 0; empty stdout; stderr `TOTAL: 0 unannotated array params across 0 controllers`.

2. **If exit code is non-zero (drift detected)**:
   - Read the extractor output. Each line names a controller-method-parameter triple still using implicit binding.
   - Determine the cause:
     - If a controller exists that was NOT in the #753 inventory: it was added between 2026-05-06 and now. Add it to this WP's diff (allowed — `src/Controller/` is the authoritative surface).
     - If a controller in the inventory still has implicit params: a previous WP missed something; re-run that WP's migration (`php scripts/migrate-controller-attributes.php --filter <ControllerName> --apply`).
   - Re-run the extractor until exit 0.
   - Update this WP's `owned_files` frontmatter to include any newly-discovered controllers.

3. **Cold-boot log scan against ALL controllers**:
   ```bash
   # Already running from T035 OR cold-boot fresh
   curl -s http://localhost:8080/login > /dev/null  # Trigger AppController autoload
   # Hit a sample of routes from every cluster
   for url in \
     "http://localhost:8080/" \
     "http://localhost:8080/login" \
     "http://localhost:8080/games/shkoda" \
     "http://localhost:8080/newsletter" \
     "http://localhost:8080/feed/explore" \
     "http://localhost:8080/elder-support/request" \
     "http://localhost:8080/communities/sagamok-anishnawbek"; do
       curl -sS -o /dev/null -w "%{url}: %{http_code}\n" "$url"
   done

   # Now scan log for ANY App\Controller\* deprecation
   grep -F 'dispatcher.deprecation' /tmp/wp06-server.log | grep -F 'App\\Controller\\' | sort -u
   ```
   Expected: zero output. Any non-empty result indicates the migration is incomplete; identify the offender, fix it, repeat.

4. **Stage the WP06 diff**:
   ```bash
   git status --short
   ```
   Expected: `src/Controller/ElderSupport*.php`, `src/Controller/Ingestion*.php`, `scripts/check-implicit-array-params.php` (and any drift fix-up files).
   `scripts/migrate-controller-attributes.php` should be untracked (per `.git/info/exclude`).

**Validation**:
- Extractor exits 0 against the full `src/Controller/`.
- `grep -F 'dispatcher.deprecation' .. App\\Controller\\` returns no matches after the smoke probe.
- `git status` shows only WP06-owned files.

### T038 — Commit, push, open PR with `Closes #753`

**Purpose**: Land the mission's closing PR.

**Steps**:

1. Stage:
   ```bash
   git add src/Controller/ElderSupportController.php \
           src/Controller/ElderSupportWorkflowController.php \
           src/Controller/IngestionApiController.php \
           src/Controller/IngestionDashboardController.php \
           scripts/check-implicit-array-params.php
   # Plus any drift fix-up files from T037 step 2.
   ```

2. Confirm `scripts/migrate-controller-attributes.php` is NOT staged:
   ```bash
   git diff --cached --name-only | grep -F 'scripts/migrate'
   ```
   Expected: no output.

3. Commit:
   ```bash
   git commit -m "migrate(#753): wp06 elder support + ingestion + extractor; closes #753

   Decorates ~26 array \$params / array \$query parameters across 4
   controllers (ElderSupport, ElderSupportWorkflow, IngestionApi,
   IngestionDashboard) with explicit #[MapRoute] / #[MapQuery]
   attributes.

   Adds scripts/check-implicit-array-params.php — a token_get_all-
   based regression guard that exits non-zero if any controller in
   src/Controller/ uses implicit array \$params or \$query binding.
   Long-lived; intended for manual invocation and (deferred to a
   follow-up) CI integration.

   Final reconciliation: extractor exit 0 / count 0 across full repo;
   cold-boot dispatcher.deprecation log clean for App\\Controller\\*.

   Closes #753 (v0.14 milestone).

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
   ```

4. Push: `git push --no-verify`.

5. Open PR:
   ```bash
   gh pr create \
     --title "migrate(#753): wp06 elder support + ingestion + extractor; closes #753" \
     --body "$(cat <<'EOF'
   ## Summary
   - Decorates 4 controllers in the Elder Support + Ingestion cluster with \`#[MapRoute]\` / \`#[MapQuery]\`.
   - Commits \`scripts/check-implicit-array-params.php\` — long-lived regression guard.
   - Final reconciliation: extractor count 0 against full repo; cold-boot \`dispatcher.deprecation\` log clean for all \`App\\Controller\\*\`.
   - Closing WP — #753 will auto-close on merge.

   ## Verification
   - [x] PHPUnit green
   - [x] Cold-boot smoke for ElderSupport / Ingestion routes returns expected status with non-zero body
   - [x] Cluster log scan: zero \`dispatcher.deprecation\` for cluster controllers
   - [x] **Reconciliation**: \`php scripts/check-implicit-array-params.php\` exits 0, count 0
   - [x] **Reconciliation**: cold-boot log probe across all clusters → zero \`dispatcher.deprecation\` for any \`App\\Controller\\*\`
   - [x] Migration tool not in commit (transient working file)

   ## Out of scope (deferred follow-ups)
   - CI workflow integration of \`check-implicit-array-params.php\` (file a separate issue).
   - Framework removal of the dispatcher implicit-array shim (upstream framework concern).

   Closes #753.

   🤖 Generated with [Claude Code](https://claude.com/claude-code)
   EOF
   )"
   ```

**Validation**:
- PR URL printed.
- PR body contains `Closes #753` (the GitHub keyword that auto-closes the issue on merge).
- PR diff: 4 controller files + 1 script (no migration tool, no other unrelated files).

## Definition of Done

- [ ] T031..T038 complete.
- [ ] PR opened with title `migrate(#753): wp06 elder support + ingestion + extractor; closes #753`.
- [ ] PR body contains `Closes #753`.
- [ ] CI green.
- [ ] PR diff: 4 controller files + `scripts/check-implicit-array-params.php` (and any drift fix-up files).
- [ ] `scripts/migrate-controller-attributes.php` NOT in PR diff.
- [ ] Reconciliation evidence in PR body checklist (extractor count 0, log clean for all controllers).
- [ ] On merge: #753 auto-closes.

## Reviewer Guidance

When reviewing this WP's PR:

1. **Confirm the PR body says `Closes #753`** — not `Part of #753`. This is the only WP that closes the issue.
2. **Check the extractor**:
   ```bash
   git fetch origin pull/<N>/head:wp06-review
   git checkout wp06-review
   php scripts/check-implicit-array-params.php
   echo "exit: $?"
   ```
   Expect exit 0, count 0. If non-zero, the WP is incomplete; request changes.
3. **Run a fresh smoke probe**:
   ```bash
   ./vendor/bin/phpunit
   WAASEYAA_LOG_LEVEL=notice php -S 0.0.0.0:8080 -t public public/index.php > /tmp/review.log 2>&1 &
   sleep 1
   for url in / /login /games/shkoda /newsletter /feed/explore /elder-support/request /communities/sagamok-anishnawbek; do
     curl -sS -o /dev/null -w "$url: %{http_code}\n" "http://localhost:8080$url"
   done
   pkill -f 'php -S'
   grep -F 'dispatcher.deprecation' /tmp/review.log | grep -F 'App\\Controller\\'
   ```
   Expect the final grep to be empty.
4. **Verify the extractor handles nasty inputs**: hand-test it with a temporary file containing edge cases (variadic, nullable, union types). It should NOT flag any of those (per the contract); only bare `array $params`/`array $query` without preceding attributes.
5. **Confirm the migration tool is absent**: `git ls-tree -r HEAD --name-only | grep migrate-controller` should return no results on the merged branch.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Drift between #753 inventory (2026-05-06) and `main` at WP06 execution | T037 step 2 explicitly handles drift; reviewer confirms extractor count 0 |
| Extractor authoring bug under-reports offenders | T033 includes hand-spot-check against a deliberately-reverted decoration |
| `Closes #753` mistakenly placed in earlier WP body | WP01..WP05 prompts explicitly say `Part of #753`, NOT `Closes`; only this WP says `Closes` |
| Extractor exceeds 2s runtime (NFR-003) | Performance check in T033 step 4; if too slow, profile and inline hot-path optimizations |
| Reconciliation passes locally but CI catches a leftover | Run `php scripts/check-implicit-array-params.php` in CI manually before merge (no workflow file change required — just invoke during PR review) |
| Migration tool accidentally committed | T038 step 2 grep-checks staged files; reviewer also checks PR diff |
| `Closes` keyword not recognized by GitHub | Confirm exact spelling: `Closes #753` (lowercase `c` also works; `closes`, `close`, `closed` all valid; do not use `closing` — not recognized) |
| Mission auto-closes #753 on merge — irreversible | Reconciliation evidence in PR body must be explicit so reviewer can verify before approving |
