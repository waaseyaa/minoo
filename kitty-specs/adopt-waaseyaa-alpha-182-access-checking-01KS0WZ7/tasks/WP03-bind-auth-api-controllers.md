---
work_package_id: WP03
title: Bind Authenticated API & Admin Controllers
dependencies:
- WP01
requirement_refs:
- FR-004
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T015
- T016
- T017
- T018
- T019
- T020
- T021
history:
- at: '2026-05-19'
  by: specify
  note: WP created from spec.md §10 WP03
authoritative_surface: src/Http/Controller/
execution_mode: code_change
mission_id: 01KS0WZ7MX6P96NP0V95RBTPG2
mission_slug: adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7
owned_files:
- src/Http/Controller/Social/**
- src/Http/Controller/Newsletter/**
- src/Http/Controller/Games/**
- src/Http/Controller/Dashboard/**
- src/Http/Controller/Feed/**
- src/Http/Controller/Ingestion/**
- tests/App/Unit/Http/Controller/Social/**
- tests/App/Unit/Http/Controller/Newsletter/**
- tests/App/Unit/Http/Controller/Games/**
- tests/App/Unit/Http/Controller/Dashboard/**
- tests/App/Unit/Http/Controller/Feed/**
- tests/App/Unit/Http/Controller/Ingestion/**
tags: []
---

# WP03 — Bind Authenticated API & Admin Controllers

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Branch contract**: planning base = `main`, final merge target = `main`.
**Run command**: `spec-kitty agent action implement WP03 --agent <name>`
**Requirement refs**: FR-004, FR-006

## Objective

Convert every `getQuery()` call site in the 11 authenticated/admin controller files (Social, Newsletter, Games, Dashboard, Feed, Ingestion) to bind the request's account.

## Context

These controllers run behind auth middleware (`requires_auth: true` in route definitions), so the bound account is always a real user — no anonymous-fallback branch needed. Pattern is identical to WP02 shape 1: `->setAccount($this->account)`.

**Game controllers gotcha** (per CLAUDE.md): All game API endpoints that mutate session state (`check`, `complete`, `hint`, `abandon`, `guess`) must continue calling `$this->gate->denies('update', $session, $account)` for session-ownership validation. **This WP does not change that** — the gate call uses the same `$account` we bind to the query.

## Files Owned by This WP

- `src/Http/Controller/Social/{BlockController,EngagementController,MessagingController}.php` (3 files)
- `src/Http/Controller/Newsletter/NewsletterAdminApiController.php` (1 file)
- `src/Http/Controller/Games/{CrosswordController,MatcherController,ShkodaController}.php` (3 files)
- `src/Http/Controller/Dashboard/{CoordinatorDashboardController,RoleManagementController,VolunteerDashboardController}.php` (3 files)
- `src/Http/Controller/Feed/FeedController.php` (1 file)
- `src/Http/Controller/Ingestion/IngestionDashboardController.php` (1 file)
- Corresponding test files

**11 files** affecting **~30 of the 131 unaudited `getQuery()` call sites**.

## Subtasks

### T015 — Social/EngagementController

**Purpose**: Bind account on the reaction/comment/follow API endpoints. These are the highest-traffic endpoints in the engagement flow.

**Steps**:
1. Read `src/Http/Controller/Social/EngagementController.php`.
2. Add `AccountInterface $account` to constructor (`private readonly`).
3. Append `->setAccount($this->account)` to every `getQuery()` call.
4. Verify the existing `try/catch InvalidArgumentException` (per CLAUDE.md gotcha for EngagementController create()+save()) remains untouched — it's defense-in-depth for entity constructor validation, separate from access checking.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Social\\EngagementController'` exits 0; reactions/comments/follows POST endpoints return 200/201 for authenticated users.

---

### T016 — Social/Block + Messaging controllers

**Purpose**: Bind account in `BlockController` (block/unblock users) and `MessagingController` (DM API).

**Steps**: Same shape as T015 for each. Note that `MessagingController` likely queries `message` and `conversation` entities — bind each storage's query.

**Files**: 2 controllers + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Social\\(Block|Messaging)Controller'` exits 0.

---

### T017 — Games controllers (Crossword, Matcher, Shkoda)

**Purpose**: Bind account in the 3 game controllers. Game sessions are owned by the user; the bound account drives session ownership filtering.

**Steps**:
1. Read each of `CrosswordController.php`, `MatcherController.php`, `ShkodaController.php`.
2. Add `AccountInterface $account` to each constructor.
3. Append `->setAccount($this->account)` to every `getQuery()` site.
4. Verify the existing `$this->gate->denies('update', $session, $account)` calls (per CLAUDE.md gotcha) still receive `$account` — typically the same `$this->account` field.
5. Verify the `'game_type' => 'shkoda'`/`'crossword'`/`'matcher'` setter on session creation (per CLAUDE.md gotcha) is unchanged.

**Files**: 3 controllers + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Games\\(Crossword|Matcher|Shkoda)Controller'` exits 0; `GameStatsCalculator::build($account)` returns non-zero for an account with finished sessions.

---

### T018 — Dashboard controllers (Coordinator, RoleManagement, VolunteerDashboard)

**Purpose**: Bind account in 3 dashboard controllers. The bound account is the actor (coordinator or volunteer viewing their own dashboard), not the target user.

**Steps**:
1. Read each of `CoordinatorDashboardController.php`, `RoleManagementController.php`, `VolunteerDashboardController.php`.
2. Add `AccountInterface $account` to each constructor.
3. Append `->setAccount($this->account)` to every `getQuery()` site.
4. For `CoordinatorDashboardController`, the queries fetch other users' records — but the access policy already routes "coordinator can see all" vs "regular user sees own" correctly off the bound account.
5. For `RoleManagementController`, the safe-referrer guard (per CLAUDE.md gotcha) is unrelated and remains.

**Files**: 3 controllers + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Dashboard\\(Coordinator|RoleManagement|VolunteerDashboard)Controller'` exits 0.

---

### T019 — Newsletter admin API controller

**Purpose**: Bind account in `NewsletterAdminApiController` (CRUD endpoints for newsletter editions, items, submissions).

**Steps**: Same shape as T015. The controller is mounted behind admin role; account is always a real admin.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Newsletter\\NewsletterAdminApiController'` exits 0.

---

### T020 — Feed controller

**Purpose**: Bind account in `FeedController` (the `/feed` page). This controller delegates heavy lifting to `EntityLoaderService` (WP04 owns that).

**Steps**:
1. Read `src/Http/Controller/Feed/FeedController.php`.
2. Add `AccountInterface $account` to constructor.
3. Append `->setAccount($this->account)` to any `getQuery()` site directly in the controller (sparse — most logic delegates).
4. Pass `$this->account` to every `EntityLoaderService` method call (WP04 will have added the parameter; coordinate with WP04 if running parallel).

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Feed\\FeedController'` exits 0; authenticated `curl /feed` returns 200 with feed content.

---

### T021 — Ingestion dashboard controller

**Purpose**: Bind account in `IngestionDashboardController` (admin-only ingest log view).

**Steps**: Same shape as T015. Bound behind admin role.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Ingestion\\IngestionDashboardController'` exits 0.

---

## Definition of Done

- [ ] All 7 subtasks (T015–T021) complete.
- [ ] All 11 controller files modified to bind account.
- [ ] No `getQuery()` site in this WP's owned files left without a bind.
- [ ] `./vendor/bin/phpunit --filter 'Http\\\\Controller\\\\(Social|Newsletter|Games|Dashboard|Feed|Ingestion)'` exits 0.
- [ ] Game gate calls (`$this->gate->denies('update', ...)`) remain in place.
- [ ] EngagementController defense-in-depth try/catch remains in place.

## Risks

- **Game `game_type` field still required at create.** Do not accidentally drop the `'game_type' => …` setter while editing.
- **Dashboard test fixtures may need coordinator role.** Some tests instantiate a regular-user fixture and expect to see coordinator-only rows — those tests should already be using a coordinator fixture, but verify.
- **FeedController couples to WP04.** If running WP03 in parallel with WP04, coordinate the `EntityLoaderService` method-signature change. Either WP can drive the change; the other adopts.

## Reviewer Guidance

- Run `grep -nE 'getQuery\(\)' src/Http/Controller/{Social,Newsletter,Games,Dashboard,Feed,Ingestion}/*.php` and verify every line has a follow-on bind.
- Verify game gate calls and engagement defense-in-depth try/catch are untouched.
- Approve when the test slice is green and the grep shows full adoption.
