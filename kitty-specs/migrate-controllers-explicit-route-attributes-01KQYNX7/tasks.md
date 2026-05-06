# Tasks: Migrate Controllers to Explicit Route Attributes

**Mission**: `migrate-controllers-explicit-route-attributes-01KQYNX7` (mid8: `01KQYNX7`)
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Research**: [research.md](./research.md) · **Quickstart**: [quickstart.md](./quickstart.md)
**Closes**: waaseyaa/minoo#753 (v0.14 milestone)

## Subtask Index

| ID    | Description                                                                                              | WP    | Parallel |
|-------|----------------------------------------------------------------------------------------------------------|-------|----------|
| T001  | Create transient migration tool locally per `contracts/migrate-cli.md` (NOT committed)                   | WP01  |          |
| T002  | Apply migration to WP01 cluster (Auth + Account, 6 controllers)                                          | WP01  |          |
| T003  | Run `./vendor/bin/phpunit`; expect green baseline                                                         | WP01  |          |
| T004  | Cold-boot smoke routes per `quickstart.md` WP01 table                                                     | WP01  |          |
| T005  | Cold-boot log scan; zero `dispatcher.deprecation` entries for cluster controllers                        | WP01  |          |
| T006  | Commit, push, open PR with "Part of #753"                                                                 | WP01  |          |
| T007  | Recreate transient migration tool locally                                                                 | WP02  | [P]      |
| T008  | Apply migration to WP02 cluster (Games, 6 controllers)                                                   | WP02  | [P]      |
| T009  | Run `./vendor/bin/phpunit`                                                                                | WP02  | [P]      |
| T010  | Cold-boot smoke routes per `quickstart.md` WP02 table                                                     | WP02  | [P]      |
| T011  | Cold-boot log scan; zero entries for cluster                                                              | WP02  | [P]      |
| T012  | Commit, push, open PR with "Part of #753"                                                                 | WP02  | [P]      |
| T013  | Recreate transient migration tool locally                                                                 | WP03  | [P]      |
| T014  | Apply migration to WP03 cluster (Newsletter, 3 controllers)                                              | WP03  | [P]      |
| T015  | Run `./vendor/bin/phpunit`                                                                                | WP03  | [P]      |
| T016  | Cold-boot smoke routes per `quickstart.md` WP03 table                                                     | WP03  | [P]      |
| T017  | Cold-boot log scan                                                                                        | WP03  | [P]      |
| T018  | Commit, push, open PR with "Part of #753"                                                                 | WP03  | [P]      |
| T019  | Recreate transient migration tool locally                                                                 | WP04  | [P]      |
| T020  | Apply migration to WP04 cluster (Engagement + Messaging, 5 controllers)                                  | WP04  | [P]      |
| T021  | Run `./vendor/bin/phpunit`                                                                                | WP04  | [P]      |
| T022  | Cold-boot smoke routes per `quickstart.md` WP04 table                                                     | WP04  | [P]      |
| T023  | Cold-boot log scan                                                                                        | WP04  | [P]      |
| T024  | Commit, push, open PR with "Part of #753"                                                                 | WP04  | [P]      |
| T025  | Recreate transient migration tool locally                                                                 | WP05  | [P]      |
| T026  | Apply migration to WP05 cluster (Static + Communities + Misc, 13 controllers)                            | WP05  | [P]      |
| T027  | Run `./vendor/bin/phpunit`                                                                                | WP05  | [P]      |
| T028  | Cold-boot smoke routes per `quickstart.md` WP05 table                                                     | WP05  | [P]      |
| T029  | Cold-boot log scan                                                                                        | WP05  | [P]      |
| T030  | Commit, push, open PR with "Part of #753"                                                                 | WP05  | [P]      |
| T031  | Recreate transient migration tool locally                                                                 | WP06  |          |
| T032  | Apply migration to WP06 cluster (Elder Support + Ingestion, 4 controllers)                               | WP06  |          |
| T033  | Create `scripts/check-implicit-array-params.php` per `contracts/check-cli.md` (committed long-lived)     | WP06  |          |
| T034  | Run `./vendor/bin/phpunit`                                                                                | WP06  |          |
| T035  | Cold-boot smoke routes per `quickstart.md` WP06 table                                                     | WP06  |          |
| T036  | Cold-boot log scan for cluster controllers                                                               | WP06  |          |
| T037  | Final reconciliation: extractor exit 0 / count 0 against full repo; cold-boot log clean for `App\Controller\*` | WP06  |          |
| T038  | Commit, push, open PR with "Closes #753"                                                                  | WP06  |          |

> **`[P]` parallelism note:** All non-WP01 WPs are file-disjoint and may execute in parallel. WP01 must complete first only because the implementer drafts the migration tool from `contracts/migrate-cli.md` for the first time there; subsequent WPs derive the same tool independently from the same contract. (No code dependency between WPs.)

---

## Setup / Foundational

No foundational WP. The mission's design (transient migration tool, file-disjoint clusters) means every WP is self-contained.

---

## Work Packages

### WP01 — Auth + Account cluster

- **Goal**: Migrate the Auth + Account controller cluster to explicit `#[MapRoute]` / `#[MapQuery]` attributes; first WP, drafts the transient migration tool.
- **Priority**: P0 (first to land; sets the migration pattern reviewers will compare every later WP against)
- **Independent test**: `./vendor/bin/phpunit` green; cold-boot smoke routes for all 6 controllers return non-zero body; cold-boot log shows zero `dispatcher.deprecation` notices naming any of the 6 cluster controllers.
- **Estimated prompt size**: ~430 lines
- **Owned files**:
  - `src/Controller/AccountHomeController.php`
  - `src/Controller/AuthController.php`
  - `src/Controller/CoordinatorDashboardController.php`
  - `src/Controller/RoleManagementController.php`
  - `src/Controller/VolunteerController.php`
  - `src/Controller/VolunteerDashboardController.php`
- **Authoritative surface**: `src/Controller/`
- **Requirement refs**: FR-001, FR-002, FR-003, FR-004, NFR-001, NFR-002, NFR-004, C-001, C-002, C-005

#### Included subtasks

- [ ] T001 Create transient migration tool locally per `contracts/migrate-cli.md` (NOT committed) (WP01)
- [ ] T002 Apply migration to WP01 cluster (Auth + Account, 6 controllers) (WP01)
- [ ] T003 Run `./vendor/bin/phpunit`; expect green baseline (WP01)
- [ ] T004 Cold-boot smoke routes per `quickstart.md` WP01 table (WP01)
- [ ] T005 Cold-boot log scan; zero `dispatcher.deprecation` entries for cluster controllers (WP01)
- [ ] T006 Commit, push, open PR with "Part of #753" (WP01)

#### Implementation sketch

1. Read `contracts/migrate-cli.md` and draft `scripts/migrate-controller-attributes.php` as a local working file (added to `.git/info/exclude` or simply never `git add`-ed).
2. Run `--cluster wp01 --dry-run`, sanity-check the diff (12 use-stmt insertions + 62 attribute splices across 6 controllers).
3. Apply, then re-run dry-run to prove idempotency (empty diff).
4. Run PHPUnit; assert green.
5. Cold-boot the dev server with `WAASEYAA_LOG_LEVEL=notice`; hit each smoke route from the WP01 table; confirm body size > 0 and HTTP status matches expected.
6. Tail the log, grep `dispatcher.deprecation`, confirm zero matches naming WP01 controllers.
7. Commit only `src/Controller/*.php` changes; push `--no-verify`; open PR.

#### Risks

- **Tool drafting bug** silently mis-splices a parameter, producing valid PHP that binds the wrong value. Mitigation: dry-run review + idempotency check + PHPUnit + cold-boot smoke.
- **CSRF / auth blocks the smoke** — some Auth+Account routes require authenticated sessions. Treat 302/401 + non-zero body as success (the dispatcher booted, the controller mapped, the response was emitted).
- **Migration tool accidentally committed**. Mitigation: explicit "do NOT commit" subtask; staging-time check that `scripts/migrate-controller-attributes.php` is not in the diff.

#### Dependencies

None.

#### Prompt file

[`tasks/WP01-auth-account-cluster.md`](./tasks/WP01-auth-account-cluster.md)

---

### WP02 — Games cluster

- **Goal**: Migrate the Games controller cluster (Shkoda, Crossword, Agim, Journey, Matcher, GuessPrice).
- **Priority**: P1
- **Independent test**: PHPUnit green; smoke routes for all 6 game pages return 200 + non-zero body; cold-boot log shows zero `dispatcher.deprecation` for cluster controllers.
- **Estimated prompt size**: ~340 lines
- **Owned files**:
  - `src/Controller/AgimController.php`
  - `src/Controller/CrosswordController.php`
  - `src/Controller/GuessPriceController.php`
  - `src/Controller/JourneyController.php`
  - `src/Controller/MatcherController.php`
  - `src/Controller/ShkodaController.php`
- **Authoritative surface**: `src/Controller/`
- **Requirement refs**: FR-001, FR-002, FR-003, FR-004, NFR-001, NFR-002, NFR-004, C-001, C-002, C-005

#### Included subtasks

- [ ] T007 Recreate transient migration tool locally (WP02)
- [ ] T008 Apply migration to WP02 cluster (Games, 6 controllers) (WP02)
- [ ] T009 Run `./vendor/bin/phpunit` (WP02)
- [ ] T010 Cold-boot smoke routes per `quickstart.md` WP02 table (WP02)
- [ ] T011 Cold-boot log scan; zero entries for cluster (WP02)
- [ ] T012 Commit, push, open PR with "Part of #753" (WP02)

#### Risks

- **GameControllerTrait** — if the trait defines methods called by these controllers, they may carry implicit-array params too. Verify with extractor logic: `php -r '... token_get_all on src/Controller/GameControllerTrait.php ...'`. (Inventory in #753 does not list trait methods.) If the trait is migrated, declare it in `owned_files` too.
- **Crossword tier 500s** — practice-mode for medium/hard tiers returns 500 (no puzzles seeded; per CLAUDE.md gotcha #558/#560). The smoke target is the easy tier landing page only.

#### Dependencies

None.

#### Prompt file

[`tasks/WP02-games-cluster.md`](./tasks/WP02-games-cluster.md)

---

### WP03 — Newsletter cluster

- **Goal**: Migrate the Newsletter cluster (NewsletterAdminApi, Newsletter, NewsletterEditor).
- **Priority**: P1
- **Independent test**: PHPUnit green; smoke routes return 200/302/401 with non-zero body; log clean.
- **Estimated prompt size**: ~310 lines
- **Owned files**:
  - `src/Controller/NewsletterAdminApiController.php`
  - `src/Controller/NewsletterController.php`
  - `src/Controller/NewsletterEditorController.php`
- **Authoritative surface**: `src/Controller/`
- **Requirement refs**: FR-001, FR-002, FR-003, FR-004, NFR-001, NFR-002, NFR-004, C-001, C-002, C-005

#### Included subtasks

- [ ] T013 Recreate transient migration tool locally (WP03)
- [ ] T014 Apply migration to WP03 cluster (Newsletter, 3 controllers) (WP03)
- [ ] T015 Run `./vendor/bin/phpunit` (WP03)
- [ ] T016 Cold-boot smoke routes per `quickstart.md` WP03 table (WP03)
- [ ] T017 Cold-boot log scan (WP03)
- [ ] T018 Commit, push, open PR with "Part of #753" (WP03)

#### Risks

- **Smallest cluster (3 controllers)** but `NewsletterAdminApiController` has 24 method×param entries and `NewsletterEditorController` has 18, so the diff is dense. Spend extra time on dry-run review.
- **Admin routes** require an authenticated session; 401/302 is the expected smoke response.

#### Dependencies

None.

#### Prompt file

[`tasks/WP03-newsletter-cluster.md`](./tasks/WP03-newsletter-cluster.md)

---

### WP04 — Engagement + Messaging cluster

- **Goal**: Migrate the Engagement + Messaging cluster (Engagement, Messaging, Chat, Block, Feed).
- **Priority**: P1
- **Independent test**: PHPUnit green; smoke routes return expected status with non-zero body; log clean.
- **Estimated prompt size**: ~340 lines
- **Owned files**:
  - `src/Controller/BlockController.php`
  - `src/Controller/ChatController.php`
  - `src/Controller/EngagementController.php`
  - `src/Controller/FeedController.php`
  - `src/Controller/MessagingController.php`
- **Authoritative surface**: `src/Controller/`
- **Requirement refs**: FR-001, FR-002, FR-003, FR-004, NFR-001, NFR-002, NFR-004, C-001, C-002, C-005

#### Included subtasks

- [ ] T019 Recreate transient migration tool locally (WP04)
- [ ] T020 Apply migration to WP04 cluster (Engagement + Messaging, 5 controllers) (WP04)
- [ ] T021 Run `./vendor/bin/phpunit` (WP04)
- [ ] T022 Cold-boot smoke routes per `quickstart.md` WP04 table (WP04)
- [ ] T023 Cold-boot log scan (WP04)
- [ ] T024 Commit, push, open PR with "Part of #753" (WP04)

#### Risks

- **EngagementController + MessagingController** are large (18 + 28 method×param entries respectively) and both write-heavy. Many methods are POST-only — smoke is "GET the form/listing precursor" not the POST itself.
- **Reaction field rename gotcha** (CLAUDE.md): `emoji` was renamed to `reaction_type`. Migration must not touch this; `array $params` / `array $query` decoration is orthogonal to the field name. Verify with diff review.

#### Dependencies

None.

#### Prompt file

[`tasks/WP04-engagement-messaging-cluster.md`](./tasks/WP04-engagement-messaging-cluster.md)

---

### WP05 — Static + Communities + Misc cluster

- **Goal**: Migrate the largest cluster: Static pages + Communities + miscellaneous public controllers (13 controllers, ~64 method×param entries).
- **Priority**: P1
- **Independent test**: PHPUnit green; smoke routes return 200/404 with non-zero body; log clean.
- **Estimated prompt size**: ~470 lines (largest WP, but still under the 700-line cap because subtasks are mechanically identical to other WPs — the size grows because the smoke table is longer)
- **Owned files**:
  - `src/Controller/BusinessController.php`
  - `src/Controller/CommunityController.php`
  - `src/Controller/ContributorController.php`
  - `src/Controller/EventController.php`
  - `src/Controller/GroupController.php`
  - `src/Controller/HomeController.php`
  - `src/Controller/LanguageController.php`
  - `src/Controller/LocationController.php`
  - `src/Controller/OpenGraphController.php`
  - `src/Controller/OralHistoryController.php`
  - `src/Controller/PeopleController.php`
  - `src/Controller/StaticPageController.php`
  - `src/Controller/TeachingController.php`
- **Authoritative surface**: `src/Controller/`
- **Requirement refs**: FR-001, FR-002, FR-003, FR-004, NFR-001, NFR-002, NFR-004, C-001, C-002, C-005

#### Included subtasks

- [ ] T025 Recreate transient migration tool locally (WP05)
- [ ] T026 Apply migration to WP05 cluster (Static + Communities + Misc, 13 controllers) (WP05)
- [ ] T027 Run `./vendor/bin/phpunit` (WP05)
- [ ] T028 Cold-boot smoke routes per `quickstart.md` WP05 table (WP05)
- [ ] T029 Cold-boot log scan (WP05)
- [ ] T030 Commit, push, open PR with "Part of #753" (WP05)

#### Risks

- **HomeController** has the `/` ↔ `/feed` split (per CLAUDE.md). Smoke `GET /` and confirm the dispatch decision (200 anon → home.html.twig; 302 auth → /feed) is unchanged.
- **OpenGraphController** generates PNGs; smoke checks 200 + non-zero body but the body is a PNG (binary). Use `curl -sS -o /dev/null -w "%{http_code}/%{size_download}"` to validate without dumping bytes.
- **Slug-bearing routes** (events, groups, teachings, contributors, businesses, oral-history, people) are tolerant of 404 — the smoke is "controller booted and dispatcher bound", and 404 with non-zero body proves both.
- **StaticPageController** has 28 method×param entries — the largest single-file diff in this WP. Spend extra dry-run review time.

#### Dependencies

None.

#### Prompt file

[`tasks/WP05-static-communities-misc-cluster.md`](./tasks/WP05-static-communities-misc-cluster.md)

---

### WP06 — Elder Support + Ingestion cluster + extractor commit + reconciliation

- **Goal**: Migrate the Elder Support + Ingestion cluster, commit the long-lived `scripts/check-implicit-array-params.php` extractor, run final reconciliation, and close #753.
- **Priority**: P0 (closing WP — must be the last to land; #753 closes when this merges)
- **Independent test**: PHPUnit green; cluster smoke routes return expected status; cold-boot log clean for cluster; **AND** extractor exits 0 / count 0 across the full repo; **AND** cold-boot log clean for `App\Controller\*` (every controller, not just the cluster).
- **Estimated prompt size**: ~600 lines (largest WP — adds extractor authoring + reconciliation responsibilities)
- **Owned files**:
  - `src/Controller/ElderSupportController.php`
  - `src/Controller/ElderSupportWorkflowController.php`
  - `src/Controller/IngestionApiController.php`
  - `src/Controller/IngestionDashboardController.php`
  - `scripts/check-implicit-array-params.php`
- **Authoritative surface**: `src/Controller/`
- **Requirement refs**: FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-008, NFR-001, NFR-002, NFR-003, NFR-004, C-001, C-002, C-004, C-005, C-006

#### Included subtasks

- [ ] T031 Recreate transient migration tool locally (WP06)
- [ ] T032 Apply migration to WP06 cluster (Elder Support + Ingestion, 4 controllers) (WP06)
- [ ] T033 Create `scripts/check-implicit-array-params.php` per `contracts/check-cli.md` (committed long-lived) (WP06)
- [ ] T034 Run `./vendor/bin/phpunit` (WP06)
- [ ] T035 Cold-boot smoke routes per `quickstart.md` WP06 table (WP06)
- [ ] T036 Cold-boot log scan for cluster controllers (WP06)
- [ ] T037 Final reconciliation: extractor exit 0 / count 0 against full repo; cold-boot log clean for `App\Controller\*` (WP06)
- [ ] T038 Commit, push, open PR with "Closes #753" (WP06)

#### Risks

- **Drift** between #753 inventory (filed 2026-05-06) and `main` at WP06 execution time. Reconciliation step T037 catches this; if the extractor lists controllers not in the inventory, fix them in this WP (ownership permits — `src/Controller/` is authoritative-surface) and add them to the staged diff.
- **Extractor authoring bug** that under-reports offenders — would let the migration "complete" with implicit-array params still in the tree. Mitigation: hand-spot-check the extractor against a known offender (e.g. revert one decoration locally, run extractor, confirm it's flagged) before commit.
- **Closing-WP exposure**: this PR closes #753. Mistaken merge with reconciliation failures would mark the issue done while the migration is incomplete. Mitigation: explicit T037 sub-step "extractor exit 0 / count 0" must be verified; reviewer checks `php scripts/check-implicit-array-params.php` output in PR body.

#### Dependencies

WP06 should land **after** WP01..WP05 (otherwise the reconciliation step would correctly fail because earlier clusters haven't been migrated yet). Marked as `dependencies: [WP01, WP02, WP03, WP04, WP05]` in frontmatter.

#### Prompt file

[`tasks/WP06-elder-support-ingestion-extractor.md`](./tasks/WP06-elder-support-ingestion-extractor.md)

---

## Polish / Stabilisation

No separate polish WP. Stabilisation is encoded inside WP06's reconciliation subtask (T037) — running the extractor against the full repo and verifying the cold-boot log is clean for every controller.

---

## Branch Strategy (mission-wide)

- **Planning base branch**: `main`
- **Merge target branch**: `main`
- **Per-WP execution**: each WP claims its own worktree under `.worktrees/migrate-controllers-explicit-route-attributes-01KQYNX7-lane-{a..f}/` (computed by `finalize-tasks` into `lanes.json`)
- **PR strategy**: each WP opens an independent PR squash-merging to `main`. Six PRs total. WP06 PR includes `Closes #753`; WP01..WP05 use `Part of #753`.

## MVP scope

The smallest releasable subset that delivers value is:

1. **WP01 alone** — proves the migration approach works, removes ~62 deprecation notices, sets the pattern for reviewers. If WP01 lands and WPs 02..06 stall, the project still has a reduced shim-noise footprint and a documented migration recipe.

The full closing of #753 requires all 6 WPs.

## Parallelization

All 6 WPs are file-disjoint; lane-{a..f} can run in parallel. In practice, WP01 lands first (because it sets the reviewer's baseline for the migration pattern); WP02..WP05 may run in parallel; WP06 must run last (its reconciliation step needs the others to have already landed).

Recommended sequencing for a single-implementer pace:
- **Day 1**: WP01 lands (set the baseline)
- **Day 2-3**: WP02..WP05 in parallel or rapid succession (mechanically identical to WP01)
- **Day 4**: WP06 lands; #753 closes

## Sign-off

When all 6 PRs have squash-merged to `main` and #753 has auto-closed:
- The migration is complete.
- `scripts/check-implicit-array-params.php` is the regression guard. If a future PR introduces an implicit-array param, manual invocation catches it.
- `.github/workflows/*` integration is **not** wired (deferred per C-004); a follow-up issue should be filed if/when CI integration is desired.
