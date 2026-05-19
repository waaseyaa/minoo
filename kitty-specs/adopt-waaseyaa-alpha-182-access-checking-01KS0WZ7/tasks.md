# Tasks: Adopt Waaseyaa alpha.182 Access-Checking Contract

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2` (`mid8: 01KS0WZ7`)
**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md) · **Quickstart**: [quickstart.md](quickstart.md)

**Planning branch**: `main` · **Merge target**: `main`
**Mission branch (execution lanes)**: `kitty/mission-adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7-lane-{a,b,...}`

## Branch Strategy

All work packages execute on the mission branch (per lane); the mission squash-merges to `main` only when every WP is approved and the full quality gate is green (NFR-001..007). `main` stays green throughout because WP01's composer bump deliberately leaves the mission branch red, and WPs 02–05 bring it back to green incrementally.

## Subtask Index

| ID | Description | WP | Parallel? |
|---|---|---|---|
| T001 | Bump `composer.json` constraints from `^0.1.0-alpha.180` to `^0.1.0-alpha.182` (40 packages) | WP01 | — | [D] |
| T002 | `composer update 'waaseyaa/*' --with-all-dependencies` and commit `composer.lock` | WP01 | — | [D] |
| T003 | Boot kernel via reflection-style smoke script; confirm autoload + class loading succeed | WP01 | — | [D] |
| T004 | Verify framework `AccountInterface` auto-injection still resolves an anonymous account for unauth requests | WP01 | — | [D] |
| T005 | Bind account in `src/Http/Controller/Auth/AuthController.php` (conditional fallback — pre-auth context) | WP02 | [D] |
| T006 | Bind account in `src/Http/Controller/Community/{CommunityController,ContributorController}.php` | WP02 | [D] |
| T007 | Bind account in `src/Http/Controller/Groups/{GroupController,BusinessController}.php` | WP02 | [D] |
| T008 | Bind account in `src/Http/Controller/Home/HomeController.php` (anonymous + authenticated paths) | WP02 | [D] |
| T009 | Bind account in `src/Http/Controller/Language/LanguageController.php` | WP02 | [D] |
| T010 | Bind account in `src/Http/Controller/OralHistory/OralHistoryController.php` | WP02 | [D] |
| T011 | Bind account in `src/Http/Controller/People/{PeopleController,VolunteerController}.php` | WP02 | [D] |
| T012 | Bind account in `src/Http/Controller/Teachings/TeachingController.php` | WP02 | [D] |
| T013 | Tighten remaining sites in `src/Http/Controller/Events/EventController.php` (already partially adopted) | WP02 | [D] |
| T014 | Bind account in `src/Http/Controller/ElderSupport/ElderSupportController.php` | WP02 | [D] |
| T015 | Bind account in `src/Http/Controller/Social/EngagementController.php` (authenticated only) | WP03 | [D] |
| T016 | Bind account in `src/Http/Controller/Social/{BlockController,MessagingController}.php` | WP03 | [D] |
| T017 | Bind account in `src/Http/Controller/Games/{CrosswordController,MatcherController,ShkodaController}.php` | WP03 | [D] |
| T018 | Bind account in `src/Http/Controller/Dashboard/{CoordinatorDashboardController,RoleManagementController,VolunteerDashboardController}.php` | WP03 | [D] |
| T019 | Bind account in `src/Http/Controller/Newsletter/NewsletterAdminApiController.php` | WP03 | [D] |
| T020 | Bind account in `src/Http/Controller/Feed/FeedController.php` | WP03 | [D] |
| T021 | Bind account in `src/Http/Controller/Ingestion/IngestionDashboardController.php` | WP03 | [D] |
| T022 | Thread account into `src/Domain/Feed/{EntityLoaderService,EngagementCounter}.php` + `Scoring/{AffinityCalculator,EngagementCalculator}.php` (4 files) | WP04 | [D] |
| T023 | Thread account into `src/Domain/{Events/Service/EventFeedBuilder,Games/GameStatsCalculator,Geo/Service/LocationService,Newsletter/Service/NewsletterAssembler}.php` (4 files) | WP04 | [D] |
| T024 | Thread account or bypass in `src/Infrastructure/{Fixture/FixtureResolver,OpenGraph/CrisisOgImageService,OpenGraph/PublicOgEntityLoader}.php` (3 files) | WP04 | [D] |
| T025 | Audit + bypass with audit-doc comment in `src/Ingestion/IngestMaterializer.php` (system context) | WP04 | [D] |
| T026 | Verify all account-threaded service entry points receive an account from every caller (controllers must pass one) | WP04 | — | [D] |
| T027 | Verify + tighten `src/Console/GenealogyDemoSeedHandler.php` bypass (already adopted) | WP05 | — | [D] |
| T028 | Add bypass + audit-doc comment to `src/Console/MessageDigestCommand.php` | WP05 | — | [D] |
| T029 | Write `docs/security/sql-entity-query-access-check-bypass-audit.md` mirroring framework doc, enumerating every Minoo `accessCheck(false)` site | WP05 | — | [D] |
| T030 | Verify every `accessCheck(false)` site in `src/` carries an inline comment referencing the audit doc + matches a row in the doc | WP05 | — | [D] |
| T031 | Update `CLAUDE.md` sync line to `Waaseyaa alpha.182` + add highlights paragraph for alpha.181 (access checking) and alpha.182 (follow-up fixes) | WP06 | — |
| T032 | Update auto-memory `MEMORY.md` project state line (outside-repo file at `~/.claude/projects/-home-jones-dev-minoo/memory/MEMORY.md`) | WP06 | — |
| T033 | File 3 GitHub issues for out-of-scope alpha.181 surfaces (#1496 agent executor, #1499 2FA endpoints, #1500 dead-code Phase 4) | WP06 | [P] |
| T034 | Run full quality gate: `./vendor/bin/phpunit`, `composer phpstan`, `composer cs-fixer`, `bin/check-milestones` — all exit 0 | WP06 | — |
| T035 | Run curl-based smoke for anonymous `/` (NFR-002) and authenticated `/feed` (NFR-003); confirm `200/` with body size > 1000 and `<title>` present | WP06 | — |
| T036 | Mark mission ready for review; ensure WP01..WP05 are all approved | WP06 | — |

Total: **36 subtasks across 6 work packages.**

---

## Work Package WP01 — Bump Composer

**Prompt:** [`tasks/WP01-bump-composer-to-alpha-182.md`](tasks/WP01-bump-composer-to-alpha-182.md)
**Goal:** Bump all `waaseyaa/*` package constraints to `^0.1.0-alpha.182`, refresh `composer.lock`, and verify the kernel still boots. Test suite is **expected to be red** after this WP; WPs 02–05 bring it back.
**Priority:** P0 (mission unblocks nothing without this — `setAccount()` doesn't exist on alpha.180).
**Independent test:** `composer install` from a clean state succeeds; `php -r "require __DIR__.'/vendor/autoload.php'; echo class_exists('Waaseyaa\\\\EntityStorage\\\\Exception\\\\MissingQueryAccountException') ? 'OK' : 'MISSING';"` prints `OK`.

**Included subtasks**:

- [x] T001 Bump composer.json constraints to alpha.182 (WP01)
- [x] T002 composer update + commit lockfile (WP01)
- [x] T003 Kernel boot smoke (WP01)
- [x] T004 Verify anonymous AccountInterface auto-injection (WP01)

**Implementation sketch**: edit `composer.json` (`sed`-friendly — all 40 constraints share the same shape), run `composer update 'waaseyaa/*' --with-all-dependencies`, commit. Write a 10-line PHP smoke script (kernel boot via reflection, same shape as `scripts/populate_featured.php`) and run it to confirm autoload + boot succeed.

**Risks**: Packagist propagation may lag the tag — verify `composer show 'waaseyaa/foundation' -a` lists `v0.1.0-alpha.182` before locking. If propagation has not yet completed, add a `repositories` entry temporarily pointing at the sibling waaseyaa checkout (revert before commit).

**Parallel opportunities**: None — this is a single sequenced step.

**Dependencies**: None.

**Estimated prompt size**: ~250 lines.

---

## Work Package WP02 — Bind Public SSR Controllers

**Prompt:** [`tasks/WP02-bind-public-ssr-controllers.md`](tasks/WP02-bind-public-ssr-controllers.md)
**Goal:** Convert every `getQuery()` call site in the 10 public-SSR controller files to one of the three legal shapes (bind, conditional fallback, or audited bypass). After this WP, anonymous and authenticated requests to all public pages (`/`, `/communities`, `/events`, `/groups`, `/teachings`, `/language`, `/oral-history`, `/elder-support`, etc.) succeed without throwing `MissingQueryAccountException`.
**Priority:** P0 (bulk of the regression risk; covers the homepage).
**Independent test:** `./vendor/bin/phpunit --filter 'Http\\\\Controller\\\\(Community|Language|Groups|Teachings|OralHistory|Events|People|Home|ElderSupport|Auth)'` exits 0.

**Included subtasks**:

- [x] T005 Auth controller (WP02)
- [x] T006 Community controllers (WP02)
- [x] T007 Groups controllers (WP02)
- [x] T008 Home controller (WP02)
- [x] T009 Language controller (WP02)
- [x] T010 OralHistory controller (WP02)
- [x] T011 People controllers (WP02)
- [x] T012 Teachings controller (WP02)
- [x] T013 Events controller — tighten existing (WP02)
- [x] T014 ElderSupport controller (WP02)

**Implementation sketch**: For each controller, add `AccountInterface $account` to the constructor (or reuse the existing field if already injected), store on `$this->account`, append `->setAccount($this->account)` to every `getQuery()` chain. Auth controller likely needs a conditional fallback (some sites run pre-session). Home controller serves both anonymous and authenticated visitors — same `->setAccount($this->account)` works for both.

**Risks**: A controller may currently lack a constructor entirely; you must add one with the framework auto-injected services. Look at `EventController` (partially adopted) for the canonical shape.

**Parallel opportunities**: All 10 subtasks touch disjoint files and can run in parallel lanes if desired.

**Dependencies**: WP01.

**Estimated prompt size**: ~400 lines.

---

## Work Package WP03 — Bind Authenticated API & Admin Controllers

**Prompt:** [`tasks/WP03-bind-auth-api-controllers.md`](tasks/WP03-bind-auth-api-controllers.md)
**Goal:** Convert every `getQuery()` call site in the 11 authenticated/admin controller files (Social/, Newsletter/, Games/, Dashboard/, Feed/, Ingestion/) to bind the request's account.
**Priority:** P0 (covers the feed and authenticated game/dashboard flows).
**Independent test:** `./vendor/bin/phpunit --filter 'Http\\\\Controller\\\\(Social|Newsletter|Games|Dashboard|Feed|Ingestion)'` exits 0.

**Included subtasks**:

- [x] T015 Social/EngagementController (WP03)
- [x] T016 Social/Block + Messaging controllers (WP03)
- [x] T017 Games controllers (3 files) (WP03)
- [x] T018 Dashboard controllers (3 files) (WP03)
- [x] T019 Newsletter admin API controller (WP03)
- [x] T020 Feed controller (WP03)
- [x] T021 Ingestion dashboard controller (WP03)

**Implementation sketch**: Same constructor-DI pattern as WP02. These controllers are routed behind auth middleware, so `setAccount($this->account)` is always valid (no anonymous bind branch needed). Games controllers must also continue calling `$this->gate->denies('update', $session, $account)` per the CLAUDE.md gotcha — this WP does not change that.

**Risks**: Dashboard controllers may run filtered queries on behalf of coordinators viewing other users' records; the bound account is the coordinator's, not the target user's — the existing access policy already routes this correctly.

**Parallel opportunities**: All 7 subtasks touch disjoint files.

**Dependencies**: WP01.

**Estimated prompt size**: ~350 lines.

---

## Work Package WP04 — Thread Account Through Domain Services & Infrastructure

**Prompt:** [`tasks/WP04-thread-account-through-services.md`](tasks/WP04-thread-account-through-services.md)
**Goal:** Convert every `getQuery()` call site in domain services (`src/Domain/**`), infrastructure adapters (`src/Infrastructure/**`), and ingestion (`src/Ingestion/**`) — total 12 files. User-facing entry points accept account as a method parameter; system-context call sites bypass with an audit-doc comment.
**Priority:** P0 (services back the controllers; without these the controllers' queries still throw via the service layer).
**Independent test:** `./vendor/bin/phpunit --filter '(Domain|Infrastructure|Ingestion)'` exits 0.

**Included subtasks**:

- [x] T022 Feed services (EntityLoaderService, EngagementCounter, Scoring) (WP04)
- [x] T023 Domain services (EventFeedBuilder, GameStatsCalculator, LocationService, NewsletterAssembler) (WP04)
- [x] T024 Infrastructure adapters (FixtureResolver, CrisisOgImageService, PublicOgEntityLoader) (WP04)
- [x] T025 Ingestion materializer (system-context bypass) (WP04)
- [x] T026 Audit caller sites (WP04)

**Implementation sketch**: For each service method that emits `getQuery()`, add an `AccountInterface $account` parameter and thread it to `->setAccount($account)`. For methods called from controllers (WP02/WP03), the controller bind layer already has the account — just add the parameter. For methods called purely from CLI/console contexts, use `->accessCheck(false)` with an audit-doc inline comment.

**Risks**: Some services have many callers — changing a method signature requires updating every call site. Audit `T026` exists to catch missed updates.

**Parallel opportunities**: Subtasks T022–T025 touch disjoint files; T026 must run after T022–T025.

**Dependencies**: WP01.

**Cross-WP coordination note**: Controllers in the WP02/WP03 family adopt service-signature changes from this WP; running this WP in parallel with WP02/WP03 is supported when multi-lane execution is chosen.

**Estimated prompt size**: ~400 lines.

---

## Work Package WP05 — Console Bypasses + Security Audit Doc

**Prompt:** [`tasks/WP05-console-bypass-and-audit-doc.md`](tasks/WP05-console-bypass-and-audit-doc.md)
**Goal:** Verify and tighten system-context bypasses in `src/Console/**`, write Minoo's `docs/security/sql-entity-query-access-check-bypass-audit.md` mirroring the framework doc, and confirm every `accessCheck(false)` site in `src/` is documented.
**Priority:** P1 (security posture documentation; not user-facing but required for FR-009).
**Independent test:** `grep -rn 'accessCheck(false)' src/ --include='*.php'` count equals row count in `docs/security/sql-entity-query-access-check-bypass-audit.md`.

**Included subtasks**:

- [x] T027 Verify GenealogyDemoSeedHandler bypass (WP05)
- [x] T028 Add bypass to MessageDigestCommand (WP05)
- [x] T029 Write Minoo security audit doc (WP05)
- [x] T030 Verify audit-doc / call-site parity (WP05)

**Implementation sketch**: Read every `src/Console/*.php` file, decide per `getQuery()` site whether it's system-context (bypass) or user-context (thread account from CLI args — uncommon). Write `docs/security/sql-entity-query-access-check-bypass-audit.md` modeled on the framework doc, with sections "Unconditional bypass — pure system context" and "Conditional fallback — set account when available". Each row has file, line, justification, last-reviewed date (`2026-05-19` for this batch).

**Risks**: Audit-doc drift — easy to forget to update the doc when a future WP adds a bypass. Add a final-WP grep-check in WP06.

**Parallel opportunities**: T027 + T028 can run in parallel; T029 + T030 are sequential.

**Dependencies**: WP01, and benefits from WP02/WP03/WP04 having mostly landed so the doc enumerates the final set of bypasses.

**Estimated prompt size**: ~250 lines.

---

## Work Package WP06 — Documentation, Issue Tracking, Final Gate

**Prompt:** [`tasks/WP06-docs-issues-final-gate.md`](tasks/WP06-docs-issues-final-gate.md)
**Goal:** Update CLAUDE.md and MEMORY.md for the version bump, file three GitHub issues for out-of-scope alpha.181 surfaces, and run the full mission-acceptance quality gate (phpunit + phpstan + cs-fixer + boundary-check + curl smokes for NFR-002/003).
**Priority:** P1 (mission acceptance gate).
**Independent test:** `./vendor/bin/phpunit && composer phpstan && composer cs-fixer && bin/check-milestones` exits 0 across all four; curl smokes return `200/<N>` with `N > 1000` for both `/` and `/feed`.

**Included subtasks**:

- [ ] T031 CLAUDE.md sync line + highlights (WP06)
- [ ] T032 MEMORY.md project state line (WP06)
- [ ] T033 File 3 GitHub issues for out-of-scope surfaces (WP06)
- [ ] T034 Run full quality gate (WP06)
- [ ] T035 Run curl smokes (NFR-002, NFR-003) (WP06)
- [ ] T036 Mark mission ready for review (WP06)

**Implementation sketch**: Sequential. Update docs first, file issues in parallel via `gh issue create`, run gates last. If any gate fails, the WP returns "for_review" with a remediation note; the implement-review loop rolls into a fix cycle.

**Risks**: Curl smokes require a running dev server; ensure `php -S 0.0.0.0:8080 -t public public/index.php` is up before running the smoke. The MEMORY.md file lives outside the repo (in `~/.claude/...`); list it in the WP description but not in `owned_files` (the lefthook pre-commit only validates repo files).

**Parallel opportunities**: T033 (file 3 issues) can run in parallel.

**Dependencies**: WP01, WP02, WP03, WP04, WP05.

**Estimated prompt size**: ~300 lines.

---

## MVP Scope Recommendation

The MVP is not WP01 alone — WP01 puts the mission branch into a known-red state. The true MVP for an early-merge candidate would be **WP01 + WP02 + WP04** (bump + public SSR + the services they call), which would render the anonymous homepage and feed-less public surface functional. WPs 03 + 05 + 06 are required for mission acceptance per the spec's success criteria.

**Recommendation**: Run all 6 WPs through the implement-review loop sequentially on the mission branch; do not split the mission into early-merge halves. The composer bump is atomic — partial merges leave `main` in a state where it depends on an unreleased framework constraint.

## Parallelization Map

- **WP01** runs alone (sequenced).
- **WP02, WP03, WP04** can run in parallel lanes (lane-a, lane-b, lane-c) — they touch disjoint files. The mission branch tip after parallel merge is the combined state.
- **WP05** depends on WP02/WP03/WP04 enough that running it sequentially after the parallel batch is the simplest path (its audit doc enumerates final bypass state).
- **WP06** runs last and sequentially (gate runs require all prior WPs landed).

## Next Step

Run `spec-kitty agent mission finalize-tasks --json --mission adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7` to parse dependencies, normalize frontmatter, and commit. Then `spec-kitty next --agent <name> --mission 01KS0WZ7` to begin the implement-review loop.
