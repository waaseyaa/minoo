---
work_package_id: WP05
title: Static + Communities + Misc cluster — migrate to MapRoute/MapQuery
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
- T025
- T026
- T027
- T028
- T029
- T030
history:
- timestamp: '2026-05-06T13:10:35Z'
  event: scaffolded by /spec-kitty.tasks
authoritative_surface: src/Controller/
execution_mode: code_change
mission_id: 01KQYNX7DWR7QNFK6XAZRKMWHV
mission_slug: migrate-controllers-explicit-route-attributes-01KQYNX7
owned_files:
- src/Controller/BusinessController.php
- src/Controller/CommunityController.php
- src/Controller/ContributorController.php
- src/Controller/EventController.php
- src/Controller/GroupController.php
- src/Controller/HomeController.php
- src/Controller/LanguageController.php
- src/Controller/LocationController.php
- src/Controller/OpenGraphController.php
- src/Controller/OralHistoryController.php
- src/Controller/PeopleController.php
- src/Controller/StaticPageController.php
- src/Controller/TeachingController.php
tags: []
---

# WP05 — Static + Communities + Misc cluster: migrate to `#[MapRoute]` / `#[MapQuery]`

## Objective

Migrate 13 controllers — the largest cluster — covering static pages, community detail pages, and miscellaneous public-facing controllers. The diff is the broadest of any WP, but per-controller volume is modest (mostly 2–4 entries each, with `StaticPageController` at 28 as the outlier).

## Context

- **Mission**: `01KQYNX7`. **Spec**: [`../spec.md`](../spec.md). **Plan**: [`../plan.md`](../plan.md). **Quickstart**: [`../quickstart.md`](../quickstart.md).
- **Migration tool contract**: [`../contracts/migrate-cli.md`](../contracts/migrate-cli.md).
- **Pattern reference**: [`WP01-auth-account-cluster.md`](./WP01-auth-account-cluster.md).
- **Issue #753 inventory**: ~64 method×param entries across 13 controllers, dominated by StaticPageController (28).

## Branch Strategy

- Planning base / merge target: `main`.
- Execution worktree: per `lanes.json`.
- PR title: `migrate(#753): wp05 static+communities+misc → MapRoute/MapQuery`.
- PR body: `Part of #753`.

## Subtasks

### T025 — Recreate transient migration tool locally

Same as WP01 T001. Implement contract; add to `.git/info/exclude`.

### T026 — Apply migration to WP05 cluster

**Steps**:

1. Dry-run preview:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp05 --dry-run | tee /tmp/wp05-preview.diff
   wc -l /tmp/wp05-preview.diff
   ```
   Sanity-check: 13 files modified; 26 use-stmt insertions; ~64 attribute splices.
2. **Spend extra time reading the diff**. This is the broadest WP; missed splices here are the most likely place for drift later.
3. Apply: `php scripts/migrate-controller-attributes.php --cluster wp05 --apply`.
4. Idempotency: `php scripts/migrate-controller-attributes.php --cluster wp05 --dry-run` → empty.
5. Syntax check all 13 files:
   ```bash
   for f in src/Controller/{Business,Community,Contributor,Event,Group,Home,Language,Location,OpenGraph,OralHistory,People,StaticPage,Teaching}Controller.php; do
     php -l "$f"
   done
   ```

**WP-specific note**: `StaticPageController` has 28 method×param entries (14 methods × 2 params) — half the cluster's diff in one file. Spot-check it specifically in the dry-run.

### T027 — Run `./vendor/bin/phpunit`

Expect green.

### T028 — Cold-boot smoke routes

**Steps**:

1. Cold-boot:
   ```bash
   WAASEYAA_LOG_LEVEL=notice \
     php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp05-server.log
   ```
2. Smoke (per `quickstart.md` WP05 table — slug-bearing routes use `example-slug` placeholders that 404 with non-zero body):
   ```bash
   for url in \
     "http://localhost:8080/" \
     "http://localhost:8080/about" \
     "http://localhost:8080/communities/sagamok-anishnawbek" \
     "http://localhost:8080/contributors/example-slug" \
     "http://localhost:8080/events/example-slug" \
     "http://localhost:8080/groups/example-slug" \
     "http://localhost:8080/teachings/example-slug" \
     "http://localhost:8080/language" \
     "http://localhost:8080/api/location/current" \
     "http://localhost:8080/og/event/example-slug.png" \
     "http://localhost:8080/oral-history/example-slug" \
     "http://localhost:8080/people/example-slug" \
     "http://localhost:8080/communities/sagamok-anishnawbek/business/example-slug"; do
       curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
   done
   ```

**WP-specific notes**:

- **HomeController `/`**: per CLAUDE.md, anonymous users see `home.html.twig`; authenticated users get 302 to `/feed`. Smoke unauthenticated → expect 200 + non-zero body. If you observe a 302, the test environment has a stale auth cookie; clear it.
- **CommunityController `/communities/sagamok-anishnawbek`**: real community in the DB; expect 200 + non-zero body.
- **Slug-bearing routes** (`/contributors/example-slug`, `/events/example-slug`, etc.): a 404 with non-zero body proves the controller booted, the dispatcher mapped, and the framework rendered a 404 page. That's success for the migration's verification purpose.
- **OpenGraphController `/og/event/example-slug.png`**: returns a PNG (binary). `curl -w "%{size_download}"` confirms non-zero size; do not pipe the body to a terminal.
- **LocationController `/api/location/current`**: returns 200 + JSON body (or 302 if redirected to login depending on the route's auth).

### T029 — Cold-boot log scan

```bash
grep -F 'dispatcher.deprecation' /tmp/wp05-server.log | \
  grep -E 'BusinessController|CommunityController|ContributorController|EventController|GroupController|HomeController|LanguageController|LocationController|OpenGraphController|OralHistoryController|PeopleController|StaticPageController|TeachingController'
```

**Validation**: zero matches.

### T030 — Commit, push, open PR

**Steps**:

1. Stage all 13 files:
   ```bash
   git add src/Controller/BusinessController.php \
           src/Controller/CommunityController.php \
           src/Controller/ContributorController.php \
           src/Controller/EventController.php \
           src/Controller/GroupController.php \
           src/Controller/HomeController.php \
           src/Controller/LanguageController.php \
           src/Controller/LocationController.php \
           src/Controller/OpenGraphController.php \
           src/Controller/OralHistoryController.php \
           src/Controller/PeopleController.php \
           src/Controller/StaticPageController.php \
           src/Controller/TeachingController.php
   ```
2. Confirm no migration script staged.
3. Commit:
   ```bash
   git commit -m "migrate(#753): wp05 static+communities+misc → MapRoute/MapQuery

   Decorates ~64 array \$params / array \$query parameters across 13
   controllers (StaticPage, Community, Business, Contributor, Event,
   Group, Teaching, Language, Location, OpenGraph, OralHistory,
   People, Home) with explicit #[MapRoute] / #[MapQuery] attributes.

   Largest cluster in the migration mission.

   Part of #753 (v0.14 milestone).

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
   ```
4. Push `--no-verify`; open PR (title `migrate(#753): wp05 static+communities+misc → MapRoute/MapQuery`; body `Part of #753`).

## Definition of Done

- [ ] T025..T030 complete.
- [ ] PR opened.
- [ ] CI green.
- [ ] PR diff: 13 files; no scripts.
- [ ] Idempotency empty.

## Reviewer Guidance

- 13 files is a lot; spot-check `StaticPageController.php` (28 entries — biggest), `CommunityController.php` (6 entries), `HomeController.php` (2 entries — smallest).
- Confirm `home.html.twig` and other templates are NOT in the diff.
- Pull, run phpunit; smoke `/`, `/about`, `/communities/sagamok-anishnawbek`; confirm log silence for all 13 cluster controllers.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Largest cluster — easy to miss a file in the apply step | Use `--cluster wp05` (hardcoded list); verify file count in diff before commit |
| StaticPageController has 28 splice points in one file | Manual review of `/tmp/wp05-preview.diff` before applying |
| HomeController `/` ↔ `/feed` redirect logic depends on auth state | Smoke unauthenticated; expect 200 to home page |
| OpenGraphController returns PNG bytes | `curl -o /dev/null -w "%{size_download}"` to confirm size without dumping bytes |
| Slug-bearing routes 404 with non-zero body | 404 + body > 0 = success; the migration verifies dispatcher binding, not record existence |

## Activity Log

- 2026-05-06T17:06:42Z – unknown – Done override: PR #758 squash-merged
