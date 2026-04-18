# Playwright Triage — 2026-04-17

## Context

Deploy pipeline is ungated on CI. To gate it, the Playwright suite must earn its
keep: every failing test either catches a real regression or gets fired. Audit
ran against `main` at `4a3eaf7` with `APP_ENV=testing`,
`WAASEYAA_DEV_FALLBACK_ACCOUNT=false`, PHP-FPM served at `localhost:8081`.

## Current totals (per-spec JSON reporter, `--ignore-snapshots`)

| Spec | Pass | Fail | Skip | Notes |
|---|---:|---:|---:|---|
| accessibility | 13 | 0 | 0 | |
| account | 1 | 2 | 0 | login-flow timeouts |
| admin-surface | 4 | 0 | 0 | |
| auth | 16 | 6 | 0 | login timeouts + dashboard redirect + 403 link |
| chat | 2 | 0 | 0 | |
| communities | 3 | 0 | 8 | 8 already skipped |
| community-contact | 1 | 1 | 1 | 1 flaky `ERR_NETWORK_CHANGED` |
| content-pages | 21 | 2 | 0 | subtitle regex drift |
| elders | 15 | 1 | 0 | `/safety` link removed |
| empty-states | 5 | 2 | 0 | events has data + action timeout |
| events | 2 | 0 | 4 | |
| flash-messages | 0 | 2 | 0 | depends on login flow |
| homepage | 3 | 8 | 0 | asserts pre-Phase-6 class names |
| info-pages | 4 | 0 | 0 | |
| language-search | 2 | 1 | 4 | `makwa` has no data |
| legal | 4 | 1 | 0 | `.site-footer` wrapper renamed |
| light-mode | 14 | 0 | 0 | passes with `--ignore-snapshots` |
| location-bar | 7 | 0 | 0 | |
| map-entity-filter | 3 | 0 | 0 | |
| messages | ? | ? | ? | **entire suite times out (>3 min)** |
| newsletter | 1 | 2 | 0 | `/newsletter` & `/newsletter/submit` → 404 |
| people | 2 | 0 | 0 | |
| search | 3 | 0 | 0 | |
| social-feed | 1 | 6 | 0 | tests `/feed` without login |
| volunteer | 10 | 2 | 0 | signup redirect doesn't match UUID pattern |
| **Total** | **~137** | **~36 + messages** | **~17** | |

The `--ignore-snapshots` CI flag hides light-mode snapshot failures entirely —
they'd show as "snapshot mismatch" in a real run. That's the CI behavior we
inherit, so we keep it.

## Environment-facts the triage relies on

- `/feed` 302-redirects anonymous visitors to `/` (works as designed).
- `/dashboard/coordinator` and `/dashboard/volunteer` return **403** for anonymous visitors (not a redirect to `/login`). The framework-side `requireRole()` gate 403s on unauth, same as it does on wrong-role.
- `/newsletter` and `/newsletter/submit` both return **404**. The routes ARE registered (`src/Provider/AppServiceProvider.php:3468`/`3477`) but the page 404s — this needs root-cause investigation, not a test fix.
- `bin/seed-test-user` exists and seeds `test@minoo.test` (volunteer) and `member@minoo.test` (no roles).
- `login` success redirects to `/feed` (per `LoginController` behavior). Several tests `waitForURL('/')` — which will hang until the 30 s test timeout.

## Classification table

Legend: **R**egression · **S**trict (rewrite/loosen) · **P**oor (rewrite) · **U**nnecessary (delete) · **F**eature-incomplete (skip + issue) · **K**eep (flaky, rerun)

### Real regressions (fix the code)

| File:Line | Test | Action |
|---|---|---|
| newsletter.spec.ts:4 | list page renders with Elder Newsletter heading | **R** `/newsletter` returns 404. Route registered but controller/template resolution fails. Investigate `NewsletterController::index` — likely a config-driven guard (e.g. no default community configured in test env). Fix so /newsletter renders the public list. |
| newsletter.spec.ts:11 | submit page redirects when not logged in | **R** Same root cause — `/newsletter/submit` → 404 instead of 302 to `/login?redirect=...`. Fix the route, not the test. |
| volunteer.spec.ts:43 | submitting valid signup redirects to confirmation | **R** Form submits but stays on `/elders/volunteer`. Missing fields (community_id? CSRF re-validate?) cause controller to re-render form with errors. Reproduce via curl, add the missing field to the test, and/or fix the controller error surface. |
| volunteer.spec.ts:67 | skills selection appears on confirmation page | **R** Same root cause as above — if signup never lands on `/elders/volunteer/{uuid}`, this test can't assert anything. Fix once the signup redirect is green. |
| auth.spec.ts:121 | coordinator dashboard redirects unauth users to /login | **R** Framework returns 403 for anonymous, same as wrong-role. Fix framework middleware to redirect anonymous → `/login?redirect=…`, keep 403 for authenticated-but-denied. **Ask approval: this is a waaseyaa/framework PR, not a minoo PR.** |
| auth.spec.ts:126 | volunteer dashboard redirects unauth users to /login | **R** Same as above. |
| auth.spec.ts:131 | redirect preserves intended destination in query param | **R** Same middleware change delivers this. |
| auth.spec.ts:171 | 403 page includes link to homepage | **R** `templates/403.html.twig` is missing `<a href="/">` back-home link. Add it — this is genuinely useful. |

### Too strict (rewrite the assertion)

| File:Line | Test | Action |
|---|---|---|
| content-pages.spec.ts:47 (`/events`) | has concise intro matching `/region/` | **S** Phase 6 subtitle copy dropped the word "region". Loosen to `expect(subtitle).not.toBeEmpty()` and drop the per-page regex — we're testing that a subtitle *exists*, not that marketing copy stays static. |
| content-pages.spec.ts:47 (`/teachings`) | has concise intro matching `/right now/` | **S** Same — drop regex. |
| legal.spec.ts:27 | footer has legal links | **S** Footer wrapper is `.ftr` not `.site-footer`. Update selector to `.ftr` (stable, Phase-6-blessed class). The test itself is valuable — footer legal links are a compliance surface. |
| empty-states.spec.ts:7 | events page renders empty state or card grid | **S** In dev DB events *do* have data, so `.card-grid` is present. Current assertion already branches (`hasCards || hasEmptyState`). Failure is inside the `if (hasEmptyState)` block — but that branch shouldn't execute when cards are present. Root cause: count check is racy. Rewrite to `await` both counts inside a `Promise.all`. |
| elders.spec.ts:59 | request form has safety link | **S** `/safety` link was removed from the elders request form. **Decide:** keep the link (restore in template — user safety is a real concern), or drop the test. Recommend restore + keep test (cheap, protects a safety-critical link from disappearing by accident). |

### Poorly written (rewrite for correctness)

| File:Line | Test | Action |
|---|---|---|
| messages.spec.ts:20 (helper `login`) | `waitForURL('/')` after login | **P** Login redirects to `/feed`, not `/`. This single-line bug hangs every authenticated test in the spec. Fix helper to `waitForURL('/feed')`. |
| flash-messages.spec.ts:9 | login shows success flash | **P** Uses same broken login helper pattern + `waitForURL('/feed')` — actually correct here. Failure is that login takes >30 s. Likely CSRF token is missing or rate-limit not cleared per-test. Rewrite: clear rate-limits in beforeEach, assert CSRF token exists before submit, add 60 s timeout for first login. |
| flash-messages.spec.ts:29 | availability toggle shows success flash | **P** Same pattern. |
| account.spec.ts:11 | member login redirects to feed then account page works | **P** Same login-helper issue. |
| account.spec.ts:33 | volunteer login redirects to feed | **P** Same. |
| auth.spec.ts:41 | login success redirects to feed | **P** Same — fix once test-user login flow is known-working. |
| auth.spec.ts:106 | logout destroys session and redirects to homepage | **P** Depends on login-first. Fix once login is fixed. |
| empty-states.spec.ts:85 | empty state action links are valid | **P** Iterates all listings, `page.goto(href)` for each. Times out. Rewrite as request-only (`page.request.get(href)`) — no page navigation needed to verify status. |

### Unnecessary (delete)

| File:Line | Test | Action |
|---|---|---|
| homepage.spec.ts:4 | shows homepage hero for anonymous visitors | **U** Asserts `.home-hero` / `.home-hero__title`; template uses `.hero` (Phase 6). Tests *class names*, not behavior. Replace the whole "Homepage (anonymous)" describe block with one smoke test: `await expect(page.getByRole('heading', { level: 1 })).toBeVisible()` + `await expect(page).toHaveTitle(/Minoo/)`. |
| homepage.spec.ts:16 | homepage has call-to-action section | **U** `.home-cta` doesn't exist in any template (only the CSS is left over). Delete. |
| homepage.spec.ts:22 | homepage has navigation links to key sections | **U** Asserts `.home-hero__actions` selector. Delete (the links themselves are covered by `accessibility.spec.ts` nav tests). |
| homepage.spec.ts:28 | sidebar shows primary navigation items | **U** Selector `.sidebar-nav` — the sidebar's `nav` has `aria-label`, no class. Replace with `page.getByRole('navigation', { name: /navigate/i })`. Or delete in favor of the sidebar coverage in `location-bar.spec.ts` / `accessibility.spec.ts`. |
| homepage.spec.ts:50, 56, 64, 72 | feed-layout / feed-chips / filter switching / sidebar Programs | **U** These hit `/feed` unauthenticated — `FeedController` 302s anonymous users to `/`. Tests would need a login helper. Social-feed spec (below) is the same mistake; we don't need both. **Delete all four and keep the authenticated social-feed spec after it's fixed**. |
| social-feed.spec.ts:4, 11, 17, 24, 51 | feed layout / cards / chips / filter / sidebar | **U or P** Same problem — no login before `/feed`. Option A: delete the whole spec (feed is covered by homepage smoke for anonymous, and authenticated feed is exercised by `messages.spec.ts` login helper indirectly). Option B: rewrite to add a `beforeEach` login. **Recommend: delete social-feed.spec.ts entirely** (6 tests) — it duplicates homepage.spec.ts coverage once that's rewritten. |
| social-feed.spec.ts:42 | sidebar is off-screen on mobile | **U** CSS responsive behavior test — covered by light-mode visual snapshots. Delete. |

### Feature-incomplete (skip + issue)

| File:Line | Test | Action |
|---|---|---|
| messages.spec.ts (entire suite) | Messaging spec | **F** 25 tests. The helper login bug (`waitForURL('/')`) is the primary cause of the suite timing out, but even after the fix, the test env needs `seed-test-user` to seed a *messageable* set of users + a "Member" user findable via `/api/messaging/users?q=Member`. Much of the suite is API-level. **Ask approval**: do we (a) fix the login helper and run the whole suite, (b) keep only the unauthenticated `.messages-empty` smoke test and `test.skip()` the rest with a GH issue, or (c) delete the whole spec and rewrite as a focused smoke? Recommend (b) for this sprint — we get CI gating today; full messaging coverage is its own milestone. |
| language-search.spec.ts:10 | search returns results for "makwa" | **F** Dictionary has no data in CI test DB. The spec already has a `test.skip(count === 0, 'No dictionary entries — run bin/sync-dictionary first')` pattern for the browse test. Apply same pattern to the search test. |

### Flaky (rerun, leave alone)

| File:Line | Test | Action |
|---|---|---|
| community-contact.spec.ts:20 | community detail page degrades gracefully without NC data | **K** `net::ERR_NETWORK_CHANGED` is a headless-Chromium network-race quirk. Add `retries: 1` at the spec level via `test.describe.configure({ retries: 1 })` (or rely on project-level config). |

## Contentious calls needing approval

1. **Delete `social-feed.spec.ts` entirely** (7 tests, 6 currently failing). They duplicate homepage coverage and none of them authenticate. The "one-true-test" is authenticated feed rendering, which isn't covered anywhere end-to-end yet. OK to delete?

2. **Delete 4 homepage.spec.ts tests outright** (`.home-hero`, `.home-cta`, `.home-hero__actions`, `.sidebar-nav` selectors — none exist). Rewrite the "shows homepage hero" test as one smoke check. Net: -4 tests. OK to delete?

3. **Skip-with-issue the 23 authenticated messages tests** (keep the 2 unauthenticated). Fix login helper but defer the thread/API coverage to a follow-up. OK?

4. **Framework change for auth 401→302** in `auth.spec.ts:121/126/131`. Anonymous visitors to role-gated routes should redirect to `/login?redirect=…`, not 403. This is a 1-file `RequireRoleMiddleware` change in `waaseyaa/framework`. OK to do as part of this sprint, or split into its own PR?

5. **Visual regression (`light-mode.spec.ts`)**: passes with `--ignore-snapshots` (14 tests), fails without. CI always runs with `--ignore-snapshots`, so it never gates. Options: (a) leave as-is (snapshot bitrot tolerated), (b) delete the snapshot project from `light-mode.spec.ts` (keep only the behavior tests), (c) keep snapshots but regenerate and gate on them. **Recommend (b)** — snapshot tests produce more noise than signal for this project. OK?

## Execution plan (after approval)

One commit per category, issues opened for skipped tests, PR title `chore(#TBD): gate deploy on CI green — playwright triage`.

1. Add `bin/seed-test-user` invocation to global `playwright.config.ts` `globalSetup` so every login-using spec gets a fresh user (currently each spec `beforeAll`s it independently, duplicating work).
2. Fix login helper in `messages.spec.ts` (`waitForURL('/feed')`). Apply the same fix to any copy-pasted helper.
3. Fix newsletter 404 root cause (investigate + patch controller/config).
4. Fix volunteer signup redirect root cause.
5. Restore `/safety` link in elders request form.
6. Update `templates/403.html.twig` with home link.
7. Framework PR: anonymous + role-gated = redirect to `/login` (if approved).
8. Rewrite too-strict tests (content-pages subtitles, legal footer, empty-states racy).
9. Rewrite poorly-written tests (account, flash-messages, auth login/logout, empty-states action links).
10. Delete unnecessary tests (homepage class-name tests, `social-feed.spec.ts`).
11. Skip-with-issue messages authenticated block.
12. Rerun full suite → target 0 unexpected failures.
13. Update `.github/workflows/deploy.yml`: change to `workflow_run` trigger on CI success, remove duplicate PHPUnit/Playwright steps, remove `continue-on-error`.
14. Push test commit, verify deploy only fires after CI completes green.

Expected diff at completion: **~36 failing → 0 failing**, **~30 fewer total tests** (deletions), **~25 skipped with issue links**, **1 framework PR**, **deploy.yml gated on CI**.
