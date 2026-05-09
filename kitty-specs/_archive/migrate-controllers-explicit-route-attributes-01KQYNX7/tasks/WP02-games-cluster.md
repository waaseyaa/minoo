---
work_package_id: WP02
title: Games cluster — migrate to MapRoute/MapQuery
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
- T007
- T008
- T009
- T010
- T011
- T012
history:
- timestamp: '2026-05-06T13:10:35Z'
  event: scaffolded by /spec-kitty.tasks
authoritative_surface: src/Controller/
execution_mode: code_change
mission_id: 01KQYNX7DWR7QNFK6XAZRKMWHV
mission_slug: migrate-controllers-explicit-route-attributes-01KQYNX7
owned_files:
- src/Controller/AgimController.php
- src/Controller/CrosswordController.php
- src/Controller/GuessPriceController.php
- src/Controller/JourneyController.php
- src/Controller/MatcherController.php
- src/Controller/ShkodaController.php
tags: []
---

# WP02 — Games cluster: migrate to `#[MapRoute]` / `#[MapQuery]`

## Objective

Migrate 6 controllers in the Games cluster (Shkoda, Crossword, Agim, Journey, Matcher, GuessPrice) from implicit `array $params` / `array $query` to explicit `#[MapRoute]` / `#[MapQuery]` attributes. Mechanically identical to WP01; this WP recreates the migration tool independently from `contracts/migrate-cli.md`.

## Context

- **Mission**: `01KQYNX7`. **Spec**: [`../spec.md`](../spec.md). **Plan**: [`../plan.md`](../plan.md). **Quickstart**: [`../quickstart.md`](../quickstart.md).
- **Migration tool contract**: [`../contracts/migrate-cli.md`](../contracts/migrate-cli.md).
- **Pattern reference**: this WP follows the WP01 pattern; see [`WP01-auth-account-cluster.md`](./WP01-auth-account-cluster.md) for the canonical migration recipe.
- **Issue #753 inventory**: ~80 method×param entries across the 6 game controllers (16 in AgimController, 20 in CrosswordController, 14 in JourneyController, 10 in MatcherController, 14 in ShkodaController, 2 in GuessPriceController).

## Branch Strategy

- Planning base / merge target: `main`.
- Execution worktree: per `lanes.json` (allocated by `finalize-tasks`).
- PR title: `migrate(#753): wp02 games → MapRoute/MapQuery`.
- PR body: `Part of #753`. **Not** `Closes #753`.

## Subtasks

### T007 — Recreate transient migration tool locally

**Purpose**: Independently recreate `scripts/migrate-controller-attributes.php` per `contracts/migrate-cli.md`. The tool must satisfy the same contract WP01 used; behavior is the source of truth, not WP01's implementation.

**Steps**:

1. Read `contracts/migrate-cli.md` end-to-end.
2. Create `scripts/migrate-controller-attributes.php` per the contract (see WP01 T001 for full guidance).
3. Add to `.git/info/exclude` to keep `git status` clean: `echo 'scripts/migrate-controller-attributes.php' >> .git/info/exclude`.
4. Smoke-test:
   ```bash
   php scripts/migrate-controller-attributes.php --filter ShkodaController --dry-run | head -40
   ```
   Expected: non-empty unified diff showing 2 use-stmt insertions + 14 attribute splices.

**Validation**:
- `php scripts/migrate-controller-attributes.php --help` works.
- Dry-run for one cluster controller produces a valid diff.

### T008 — Apply migration to WP02 cluster

**Purpose**: Migrate all 6 game controllers.

**Steps**:

1. Dry-run preview:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp02 --dry-run | tee /tmp/wp02-preview.diff
   wc -l /tmp/wp02-preview.diff
   ```
   Sanity-check: 6 files modified; 12 use-stmt lines added; 80 attribute splices.
2. Apply:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp02 --apply
   ```
3. Idempotency check:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp02 --dry-run
   ```
   Expected: empty.
4. Syntax check each file:
   ```bash
   for f in src/Controller/{Agim,Crossword,GuessPrice,Journey,Matcher,Shkoda}Controller.php; do
     php -l "$f"
   done
   ```

**Validation**:
- `git diff --stat src/Controller/` shows exactly the 6 cluster files.
- All `php -l` checks pass.
- Idempotency check empty.

**WP-specific risk**: `GameControllerTrait` may be used by some controllers. Check whether the trait carries implicit-array params: `php scripts/migrate-controller-attributes.php --filter GameControllerTrait --dry-run`. The #753 inventory does not list trait methods, suggesting the trait does not carry such params; if the dry-run shows a diff, **stop** and discuss with the reviewer — `GameControllerTrait` is not in this WP's `owned_files`.

### T009 — Run `./vendor/bin/phpunit`

**Purpose**: Verify no regression.

**Steps**: Run `./vendor/bin/phpunit`. If any test fails, stop and diagnose per WP01 T003.

**Validation**: `OK (914 tests, 2568 assertions)` (or higher).

### T010 — Cold-boot smoke routes

**Purpose**: Verify the games hub and 6 game-specific routes still serve responses.

**Steps**:

1. Cold-boot the dev server:
   ```bash
   WAASEYAA_LOG_LEVEL=notice \
     php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp02-server.log
   ```
2. Hit each WP02 smoke URL (per `quickstart.md` table):
   ```bash
   for url in \
     "http://localhost:8080/games/shkoda" \
     "http://localhost:8080/games/crossword" \
     "http://localhost:8080/games/agim" \
     "http://localhost:8080/games/journey" \
     "http://localhost:8080/games/matcher" \
     "http://localhost:8080/games/guess-price"; do
       curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
   done
   ```
3. Stop the server.

**Validation**: Every URL returned non-zero body; status 200.

**WP-specific notes**:
- **Crossword tier 500s**: per CLAUDE.md gotcha #558/#560, only easy-tier puzzles are seeded. The smoke URL `/games/crossword` is the landing page (not the puzzle), which renders the tier picker — it should return 200. Do NOT try `/games/crossword/practice/medium` etc. as those will 500 due to seeding gaps unrelated to this migration.
- **Agim** is the newest game (PR #626 / 2026-04-03). Confirm its landing page works.

### T011 — Cold-boot log scan

**Purpose**: Confirm no `dispatcher.deprecation` notices for cluster controllers.

**Steps**:

```bash
grep -F 'dispatcher.deprecation' /tmp/wp02-server.log | sort -u | tee /tmp/wp02-deprecations.txt
grep -E 'AgimController|CrosswordController|GuessPriceController|JourneyController|MatcherController|ShkodaController' /tmp/wp02-deprecations.txt
```

**Validation**: zero matches in the second grep. (Lines naming non-WP02 controllers are acceptable until those WPs land.)

### T012 — Commit, push, open PR

**Purpose**: Land the WP02 changes.

**Steps**:

1. Stage:
   ```bash
   git add src/Controller/AgimController.php \
           src/Controller/CrosswordController.php \
           src/Controller/GuessPriceController.php \
           src/Controller/JourneyController.php \
           src/Controller/MatcherController.php \
           src/Controller/ShkodaController.php
   ```
2. Confirm migration script not staged: `git diff --cached --name-only | grep -F scripts/migrate` → no output.
3. Commit:
   ```bash
   git commit -m "migrate(#753): wp02 games → MapRoute/MapQuery

   Decorates 80 array \$params / array \$query parameters across 6
   game controllers (Shkoda, Crossword, Agim, Journey, Matcher,
   GuessPrice) with explicit #[MapRoute] / #[MapQuery] attributes.

   Part of #753 (v0.14 milestone).

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
   ```
4. Push: `git push --no-verify`.
5. Open PR:
   ```bash
   gh pr create \
     --title "migrate(#753): wp02 games → MapRoute/MapQuery" \
     --body "$(cat <<'EOF'
   ## Summary
   - Decorates Auth + Account cluster's pattern, applied to the Games cluster.
   - 6 controllers, ~40 methods, ~80 parameters.

   ## Verification
   - [x] PHPUnit green
   - [x] Cold-boot smoke for /games/{shkoda,crossword,agim,journey,matcher,guess-price} returns 200 + non-zero body
   - [x] Cold-boot \`dispatcher.deprecation\` log shows zero entries naming WP02 cluster controllers
   - [x] Migration tool idempotent

   Part of #753 (v0.14 milestone).

   🤖 Generated with [Claude Code](https://claude.com/claude-code)
   EOF
   )"
   ```

**Validation**: PR URL printed; diff shows 6 files; no scripts in diff.

## Definition of Done

- [ ] T007..T012 complete.
- [ ] PR opened, body contains `Part of #753`.
- [ ] CI green.
- [ ] PR diff: 6 controller files; no `scripts/`.
- [ ] Idempotency check empty after merge.

## Reviewer Guidance

- Confirm 6 files modified, no scripts in diff.
- Spot-check `CrosswordController.php` (largest at 20 entries) and `GuessPriceController.php` (smallest at 2).
- Pull, run `./vendor/bin/phpunit`, smoke `/games/shkoda`, confirm log silence.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| GameControllerTrait carries implicit-array params not in the inventory | Dry-run extractor on the trait file separately; escalate if non-empty (out of WP02 scope) |
| Crossword medium/hard tier 500s during smoke | Only smoke the landing page `/games/crossword`, not deep puzzle URLs |
| Smoke fails on a recently-added game route | Verify route exists in `MinooRoutingStackProvider` / game-specific provider; if route was removed, drop from smoke list |

## Activity Log

- 2026-05-06T14:40:15Z – unknown – Done override: PR #755 squash-merged
