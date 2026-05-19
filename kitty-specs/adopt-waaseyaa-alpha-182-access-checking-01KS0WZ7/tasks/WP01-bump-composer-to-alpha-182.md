---
work_package_id: WP01
title: Bump Composer to waaseyaa alpha.182
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Execution worktree for this WP is allocated per the lane computed by `finalize-tasks` (see `lanes.json`). The lane branch derives from the mission branch and squash-merges back via the implement-review loop. `main` is never directly touched by this WP.
subtasks:
- T001
- T002
- T003
- T004
history:
- at: '2026-05-19'
  by: specify
  note: WP created from spec.md §10 WP01
authoritative_surface: composer.
execution_mode: code_change
mission_id: 01KS0WZ7MX6P96NP0V95RBTPG2
mission_slug: adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7
owned_files:
- composer.json
- composer.lock
- scripts/smoke/alpha-182-boot.php
tags: []
---

# WP01 — Bump Composer to waaseyaa alpha.182

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Branch contract**: planning base = `main`, final merge target = `main`. Execution happens on the lane branch computed by `finalize-tasks`.
**Run command**: `spec-kitty agent action implement WP01 --agent <name>`
**Requirement refs**: FR-001, FR-002, FR-003 (composer constraints + lockfile + install succeeds)

## Objective

Bump all 40 `waaseyaa/*` package constraints in `composer.json` from `^0.1.0-alpha.180` to `^0.1.0-alpha.182`, regenerate `composer.lock`, and prove the kernel still boots after the bump. **The test suite is expected to be red after this WP** — that is intentional and is the entry condition for WP02..WP05.

## Context

This is the first WP in the `adopt-waaseyaa-alpha-182-access-checking` mission. Per `research.md` §"Decision 3", the composer bump must come first because `EntityQueryInterface::setAccount()` is new in alpha.181 — adding bind calls to controllers before the bump produces a fatal class error. By landing the bump in WP01, we put the mission branch into a known-red state that subsequent WPs bring back to green.

After this WP, every `getQuery()` call site in `src/` that does not bind an account will throw `Waaseyaa\EntityStorage\Exception\MissingQueryAccountException` on first request. That is the symptom WPs 02–05 fix.

## Files Owned by This WP

- `composer.json` — bump constraints
- `composer.lock` — regenerated
- `scripts/smoke/alpha-182-boot.php` — new (10-line kernel boot smoke; mission-internal)

No `src/`, `docs/`, or `tests/` files are owned by WP01.

## Subtasks

### T001 — Bump `composer.json` constraints to alpha.182

**Purpose**: Update all 40 `waaseyaa/*` package constraints in `composer.json` from `^0.1.0-alpha.180` to `^0.1.0-alpha.182` in a single mechanical edit.

**Steps**:

1. Open `composer.json`. Locate the `require` block.
2. Use a single `sed` (via the Bash tool) or Edit/replace_all to substitute every `^0.1.0-alpha.180` with `^0.1.0-alpha.182`. Example sed command (run from repo root):
   ```bash
   sed -i 's/\^0\.1\.0-alpha\.180/^0.1.0-alpha.182/g' composer.json
   ```
3. Inspect the diff: exactly 40 lines should change (all under `require` for `waaseyaa/*` packages). If `require-dev` contains a `waaseyaa/testing` entry on the same version, that becomes 41. **Verify the diff before staging.**
4. Run `composer validate` — it must exit 0.

**Files**: `composer.json` (modified, ~40 lines changed).

**Validation**:
- [ ] Diff shows exactly the 40-or-41 expected constraint bumps and nothing else.
- [ ] `composer validate` exits 0.

**Edge cases**:
- If `composer.json` already pins one or two `waaseyaa/*` packages to a different version (e.g. a `dev-main` override during framework work), leave those alone — the sed only touches `^0.1.0-alpha.180` patterns. Confirm no overrides exist before bumping.

---

### T002 — `composer update 'waaseyaa/*' --with-all-dependencies` and commit `composer.lock`

**Purpose**: Refresh `composer.lock` to resolve every `waaseyaa/*` package to its `v0.1.0-alpha.182` tagged release.

**Steps**:

1. Run from repo root:
   ```bash
   composer update 'waaseyaa/*' --with-all-dependencies --no-progress 2>&1 | tail -60
   ```
2. Inspect the output. Every `waaseyaa/*` package should show an upgrade line `Upgrading waaseyaa/<name> (v0.1.0-alpha.180 => v0.1.0-alpha.182)`. Any package that stays at alpha.180 indicates Packagist hasn't propagated the split tag — investigate before proceeding (see Risks).
3. Verify with: `composer show 'waaseyaa/*' | head -10`. Every line should read `... v0.1.0-alpha.182`.
4. Stage and commit `composer.lock` alongside `composer.json`.

**Files**: `composer.lock` (regenerated, ~50–80 lines diff per package, ~3000 lines total).

**Validation**:
- [ ] `composer show 'waaseyaa/*' | grep -v 'v0.1.0-alpha.182' | wc -l` returns 0.
- [ ] `composer install` from a clean `vendor/` (`rm -rf vendor/ && composer install`) succeeds.

**Risks**:
- **Packagist propagation lag.** If any package shows "could not find a matching version" or stays at alpha.180, check `git ls-remote --tags https://github.com/waaseyaa/<package>.git | grep alpha.182`. If the per-package mirror lacks the tag, the framework's release pipeline hasn't completed — report and wait.
- **Sibling repo path override.** If `composer.json` contains a `repositories` block pointing at `../waaseyaa/packages/*`, the bump uses the sibling tree state rather than Packagist. Confirm the sibling tree is at `v0.1.0-alpha.182` (`cd ../waaseyaa && git describe --tags HEAD` should report `v0.1.0-alpha.182` or later).

---

### T003 — Kernel boot smoke

**Purpose**: Prove the application can still autoload and boot the kernel after the bump, even if subsequent request handling will be red.

**Steps**:

1. Create `scripts/smoke/alpha-182-boot.php` with this content:
   ```php
   <?php
   declare(strict_types=1);

   require __DIR__ . '/../../vendor/autoload.php';

   $reflector = new \ReflectionClass(\Waaseyaa\Foundation\Http\HttpKernel::class);
   $kernel = $reflector->newInstanceWithoutConstructor();
   $boot = $reflector->getMethod('boot');
   $boot->setAccessible(true);
   $boot->invoke($kernel);

   echo "OK: kernel booted under alpha.182\n";

   $expected = \Waaseyaa\EntityStorage\Exception\MissingQueryAccountException::class;
   echo class_exists($expected) ? "OK: $expected loaded\n" : "FAIL: $expected missing\n";
   ```
2. Run: `php scripts/smoke/alpha-182-boot.php`
3. Output must contain both `OK:` lines.

**Files**: `scripts/smoke/alpha-182-boot.php` (new, 16 lines).

**Validation**:
- [ ] Smoke script exits 0 with both `OK:` lines.
- [ ] Class `Waaseyaa\EntityStorage\Exception\MissingQueryAccountException` exists.

**Edge cases**:
- If `HttpKernel` constructor depends on bootstrap parameters that `newInstanceWithoutConstructor()` can't satisfy, fall back to invoking the kernel through `public/index.php`-style instantiation but stop short of `handle()`. The goal is to prove autoload + class loading; full request handling is out of scope for this smoke.

---

### T004 — Verify anonymous `AccountInterface` auto-injection

**Purpose**: Confirm that `SessionMiddleware` resolves an `AccountInterface` (not `null`) for unauthenticated requests, so the WP02+ pattern `->setAccount($this->account)` is safe.

**Steps**:

1. Boot a dev server: `php -S 0.0.0.0:8080 -t public public/index.php` (run in another shell or in the background).
2. From the framework sibling repo or a one-liner, inspect `SessionMiddleware`:
   ```bash
   grep -A5 'function handle' ../waaseyaa/packages/auth/src/Middleware/SessionMiddleware.php | head -20
   ```
   Confirm the middleware sets `_account` to an `AccountInterface` instance (anonymous-shaped) even when no session cookie is present.
3. Hit the homepage anonymously: `curl -sS -o /tmp/wp01-home.html -w "%{http_code}\n" http://localhost:8080/` — expect a 5xx because controllers haven't been bound yet. **This is expected.** What you're checking is that the failure mode is `MissingQueryAccountException` thrown from a controller's `getQuery()`, not a null-account dereference from middleware.
4. Stop the dev server.

**Files**: None (verification step only).

**Validation**:
- [ ] Framework `SessionMiddleware` confirmed to set `_account` to an `AccountInterface` instance for anonymous requests.
- [ ] Homepage 5xx traces to `MissingQueryAccountException` (not a null-dereference earlier in middleware).

**Edge cases**:
- If `SessionMiddleware` returns `null` for anonymous, the controller bind pattern needs a `$this->account ?? null` null-coalesce — flag this for WP02 in the WP01 review note.

---

## Definition of Done

- [ ] `composer.json` pins all `waaseyaa/*` to `^0.1.0-alpha.182`.
- [ ] `composer.lock` resolves all `waaseyaa/*` to `v0.1.0-alpha.182`.
- [ ] `composer install` from clean state succeeds.
- [ ] `scripts/smoke/alpha-182-boot.php` exists and exits 0 with both `OK:` lines.
- [ ] Anonymous account resolution confirmed.
- [ ] All 4 subtasks marked done in `tasks.md`.

## Risks

- **Packagist propagation lag** — see T002 mitigation.
- **Mission branch red** — expected. Document in the WP commit message that test suite is intentionally red and will be brought back by WPs 02–05.

## Reviewer Guidance

- The composer diff should be 40–41 single-character constraint bumps and nothing else.
- The composer.lock diff is large but mechanical (one `version` field bump per `waaseyaa/*` package + matching `reference` SHA updates).
- The smoke script is the only new src-tree file; it must be unit-test-free and limited to the boot probe.
- **Do not** attempt to fix any `MissingQueryAccountException` failures in this WP — those belong to WPs 02–05.
- Approve when the smoke script passes and the lockfile is clean. The full test suite is **not** part of WP01's gate.
