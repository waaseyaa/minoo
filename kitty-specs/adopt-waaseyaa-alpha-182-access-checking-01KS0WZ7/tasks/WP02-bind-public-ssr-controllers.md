---
work_package_id: WP02
title: Bind Public SSR Controllers
dependencies:
- WP01
requirement_refs:
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Execution worktree for this WP is allocated per the lane computed by `finalize-tasks` (see `lanes.json`). The lane branch derives from the mission branch (post-WP01) and squash-merges back via the implement-review loop.
subtasks:
- T005
- T006
- T007
- T008
- T009
- T010
- T011
- T012
- T013
- T014
history:
- at: '2026-05-19'
  by: specify
  note: WP created from spec.md §10 WP02
authoritative_surface: src/Http/Controller/
execution_mode: code_change
mission_id: 01KS0WZ7MX6P96NP0V95RBTPG2
mission_slug: adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7
owned_files:
- src/Http/Controller/Auth/**
- src/Http/Controller/Community/**
- src/Http/Controller/Groups/**
- src/Http/Controller/Home/**
- src/Http/Controller/Language/**
- src/Http/Controller/OralHistory/**
- src/Http/Controller/People/**
- src/Http/Controller/Teachings/**
- src/Http/Controller/Events/**
- src/Http/Controller/ElderSupport/**
- tests/App/Unit/Http/Controller/Auth/**
- tests/App/Unit/Http/Controller/Community/**
- tests/App/Unit/Http/Controller/Groups/**
- tests/App/Unit/Http/Controller/Home/**
- tests/App/Unit/Http/Controller/Language/**
- tests/App/Unit/Http/Controller/OralHistory/**
- tests/App/Unit/Http/Controller/People/**
- tests/App/Unit/Http/Controller/Teachings/**
- tests/App/Unit/Http/Controller/Events/**
- tests/App/Unit/Http/Controller/ElderSupport/**
tags: []
---

# WP02 — Bind Public SSR Controllers

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Branch contract**: planning base = `main`, final merge target = `main`. Execution on the lane branch computed by `finalize-tasks`.
**Run command**: `spec-kitty agent action implement WP02 --agent <name>`
**Requirement refs**: FR-004 (three legal shapes), FR-005 (public SSR controllers bound)

## Objective

Convert every `getQuery()` call site in the 10 public-SSR controller files to bind the request's account via `->setAccount($this->account)`. After this WP, anonymous and authenticated requests to public pages succeed without `MissingQueryAccountException`.

## Context

WP01 bumped the framework to alpha.182. The mission branch is currently red; this WP brings the public-SSR slice of test runs back to green. The pattern comes from `research.md` §"Decision 2": add `AccountInterface $account` to the constructor, store on `$this->account`, append `->setAccount($this->account)` to every query chain.

**Reference implementations:**
- Framework: `../waaseyaa/packages/api/src/JsonApiController.php` lines 50–80 — canonical bind pattern.
- Minoo: `src/Http/Controller/Events/EventController.php` line 201 — existing partial adoption.

**`AccountInterface` auto-injection** — Per CLAUDE.md gotcha "Controller DI", `SsrPageHandler::resolveControllerInstance()` already wires `AccountInterface` from its hardcoded `$serviceMap`. Just add the constructor parameter; no provider edits needed. For anonymous visitors, the framework's `SessionMiddleware` resolves an anonymous-shaped `AccountInterface` (verified in WP01.T004), so `setAccount($this->account)` is always valid — no null branch needed.

**Three legal shapes recap** (see `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md` for full pattern):
1. `->setAccount($this->account)` — user-facing reads with account in scope (THIS WP's primary pattern).
2. Conditional fallback — for controllers that may run pre-session (Auth is the main candidate).
3. `->accessCheck(false)` — system context (not used in this WP; that's WP05).

## Files Owned by This WP

- `src/Http/Controller/Auth/AuthController.php` (1 file)
- `src/Http/Controller/Community/{CommunityController,ContributorController}.php` (2 files)
- `src/Http/Controller/Groups/{GroupController,BusinessController}.php` (2 files)
- `src/Http/Controller/Home/HomeController.php` (1 file)
- `src/Http/Controller/Language/LanguageController.php` (1 file)
- `src/Http/Controller/OralHistory/OralHistoryController.php` (1 file)
- `src/Http/Controller/People/{PeopleController,VolunteerController}.php` (2 files)
- `src/Http/Controller/Teachings/TeachingController.php` (1 file)
- `src/Http/Controller/Events/EventController.php` (1 file — tighten remaining sites)
- `src/Http/Controller/ElderSupport/ElderSupportController.php` (1 file)
- Corresponding test files under `tests/App/Unit/Http/Controller/**`

**13 files** affecting **~60 of the 131 unaudited `getQuery()` call sites**.

## Subtasks

### T005 — Auth controller

**Purpose**: Bind account in `AuthController`. This is the trickiest because some endpoints (e.g. `POST /login`) may run pre-session.

**Steps**:
1. Read `src/Http/Controller/Auth/AuthController.php` in full.
2. Identify every `getQuery()` call site. Determine per site whether it runs pre-session (login attempt lookups) or post-session (logout, account fetch).
3. For pre-session sites, use the **conditional fallback** shape:
   ```php
   $query = $storage->getQuery();
   if ($this->account !== null && !$this->account->isAnonymous()) {
       $query->setAccount($this->account);
   } else {
       $query->accessCheck(false);  // pre-auth lookup; documented in docs/security/sql-entity-query-access-check-bypass-audit.md
   }
   ```
4. For post-session sites, use shape 1: `->setAccount($this->account)`.
5. Update constructor to inject `AccountInterface $account` if not already present. Use the `private readonly AccountInterface $account` field shape.

**Files**: `src/Http/Controller/Auth/AuthController.php`, possibly `tests/App/Unit/Http/Controller/Auth/AuthControllerTest.php`.

**Validation**: `./vendor/bin/phpunit --filter 'Auth\\AuthController'` exits 0; `POST /login` flow (test or curl) succeeds for valid creds.

---

### T006 — Community controllers

**Purpose**: Bind account in `CommunityController` and `ContributorController`. Both run with `_account` in scope from `SessionMiddleware`.

**Steps**:
1. Read both controller files.
2. Add `AccountInterface $account` to constructor; store as `private readonly AccountInterface $account`.
3. For every `getQuery()` call, append `->setAccount($this->account)` immediately after the call (before any `->condition()` chain).
4. Confirm anonymous + authenticated paths both render correctly (anonymous account is still an `AccountInterface`).

**Files**: 2 controllers + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Community\\(Community|Contributor)Controller'` exits 0.

---

### T007 — Groups controllers

**Purpose**: Bind account in `GroupController` and `BusinessController`.

**Steps**: Same shape as T006. Note `GroupController` has nested queries for person/event/teaching IDs (lines 88+, 96+, 104+) — bind each of those `getQuery()` calls too.

**Files**: 2 controllers + tests.

**Validation**: `./vendor/bin/phpunit --filter 'Groups\\(Group|Business)Controller'` exits 0.

---

### T008 — Home controller

**Purpose**: Bind account in `HomeController`. This handles both `/` (anonymous → `home.html.twig`) and 302-to-`/feed` for authenticated.

**Steps**: Same shape as T006. The anonymous code path uses the anonymous-shaped `AccountInterface` from `SessionMiddleware`; `setAccount()` accepts it.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Home\\HomeController'` exits 0; `curl http://localhost:8080/` returns 200 with `home.html.twig` content for anonymous, 302 to `/feed` for authenticated.

---

### T009 — Language controller

**Purpose**: Bind account in `LanguageController` (dictionary lookups, language demo page).

**Steps**: Same shape as T006.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Language\\LanguageController'` exits 0.

---

### T010 — OralHistory controller

**Purpose**: Bind account in `OralHistoryController`. Note multiple nested `$collectionStorage->getQuery()` and `$storyStorage->getQuery()` patterns (lines 29, 36, 58, 69, 100, 125).

**Steps**: Same shape as T006. Bind each storage's query separately.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'OralHistory\\OralHistoryController'` exits 0.

---

### T011 — People controllers

**Purpose**: Bind account in `PeopleController` and `VolunteerController`.

**Steps**: Same shape as T006. `VolunteerController` may have role-gated queries — the bound account drives the access policy decision.

**Files**: 2 controllers + tests.

**Validation**: `./vendor/bin/phpunit --filter 'People\\(People|Volunteer)Controller'` exits 0.

---

### T012 — Teachings controller

**Purpose**: Bind account in `TeachingController`. Has nested queries at lines 90+, 98+ for event/person IDs.

**Steps**: Same shape as T006. Bind every nested storage query.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Teachings\\TeachingController'` exits 0.

---

### T013 — Events controller — tighten remaining sites

**Purpose**: `EventController` already adopts `->accessCheck(false)` at line 201. Audit all other `getQuery()` sites in the file and convert each to either `->setAccount($this->account)` (preferred) or `->accessCheck(false)` if there's a system-context justification.

**Steps**:
1. Read the file; list every `getQuery()` site.
2. For each, decide: user-context bind, or system bypass.
3. The existing line-201 bypass should be reviewed — is it actually a system-context lookup, or was it a quick workaround that should be a bind? If it's a workaround, switch to bind and remove the bypass.
4. Document any bypass kept in this WP for WP05's audit doc; add an inline `// system-context: <reason>` comment.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'Events\\EventController'` exits 0.

---

### T014 — ElderSupport controller

**Purpose**: Bind account in `ElderSupportController`. The workflow controller dispatches based on user role; the bound account is the actor's account, not the elder's.

**Steps**: Same shape as T006. The `ElderSupportRequest` access policy already routes coordinator vs requester views correctly off the bound account.

**Files**: 1 controller + test.

**Validation**: `./vendor/bin/phpunit --filter 'ElderSupport\\ElderSupportController'` exits 0.

---

## Definition of Done

- [ ] All 10 subtasks (T005–T014) complete.
- [ ] All 13 controller files modified to bind account on every `getQuery()` site.
- [ ] No `getQuery()` site in this WP's owned files is left without a bind, conditional fallback, or audited bypass.
- [ ] `./vendor/bin/phpunit --filter 'Http\\\\Controller\\\\(Auth|Community|Groups|Home|Language|OralHistory|People|Teachings|Events|ElderSupport)'` exits 0.
- [ ] No new `MissingQueryAccountException` traces in WP02-owned test runs.
- [ ] Tests added or updated where the bind changes behavior (e.g. coordinator-vs-requester filtering on ElderSupport).

## Risks

- **Constructor signature changes break existing tests.** Tests that instantiate the controller directly (not via the auto-injection container) must be updated to pass `AccountInterface` (use `$this->createMock(AccountInterface::class)`).
- **Access policy may filter previously-visible rows.** If a test expects a specific row count, and the test account's policy declines view, the row drops out. Update the test to use a fixture account that has view access.
- **EventController has an existing `accessCheck(false)` that should be re-examined.** Don't blindly keep it — assess whether it's a real system context (rare for a user-facing controller).

## Reviewer Guidance

- Every `getQuery()` site must have a follow-on `->setAccount(...)`, `->accessCheck(false)`, or be inside a conditional-fallback block.
- Constructor changes should be the minimal addition of `AccountInterface $account` — don't take this opportunity to refactor the constructor for other reasons.
- Run `grep -nE 'getQuery\(\)' src/Http/Controller/{Auth,Community,Groups,Home,Language,OralHistory,People,Teachings,Events,ElderSupport}/*.php` and verify every line has a follow-on bind/bypass within 2 lines of the call.
- Approve when test slice is green and the grep shows full adoption.
