---
work_package_id: WP03
title: Newsletter cluster — migrate to MapRoute/MapQuery
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
- T013
- T014
- T015
- T016
- T017
- T018
history:
- timestamp: '2026-05-06T13:10:35Z'
  event: scaffolded by /spec-kitty.tasks
authoritative_surface: src/Controller/
execution_mode: code_change
mission_id: 01KQYNX7DWR7QNFK6XAZRKMWHV
mission_slug: migrate-controllers-explicit-route-attributes-01KQYNX7
owned_files:
- src/Controller/NewsletterAdminApiController.php
- src/Controller/NewsletterController.php
- src/Controller/NewsletterEditorController.php
tags: []
---

# WP03 — Newsletter cluster: migrate to `#[MapRoute]` / `#[MapQuery]`

## Objective

Migrate 3 controllers in the Newsletter cluster (NewsletterAdminApi, Newsletter, NewsletterEditor) to explicit `#[MapRoute]` / `#[MapQuery]` attributes. The smallest cluster by file count, but `NewsletterAdminApiController` (24 entries) and `NewsletterEditorController` (18 entries) make the diff dense.

## Context

- **Mission**: `01KQYNX7`. **Spec**: [`../spec.md`](../spec.md). **Plan**: [`../plan.md`](../plan.md). **Quickstart**: [`../quickstart.md`](../quickstart.md).
- **Migration tool contract**: [`../contracts/migrate-cli.md`](../contracts/migrate-cli.md).
- **Pattern reference**: see [`WP01-auth-account-cluster.md`](./WP01-auth-account-cluster.md) for the canonical recipe.
- **Issue #753 inventory**: 56 method×param entries (24 NewsletterAdminApi + 14 Newsletter + 18 NewsletterEditor).

## Branch Strategy

- Planning base / merge target: `main`.
- Execution worktree: per `lanes.json`.
- PR title: `migrate(#753): wp03 newsletter → MapRoute/MapQuery`.
- PR body: `Part of #753`.

## Subtasks

### T013 — Recreate transient migration tool locally

**Purpose**: Recreate `scripts/migrate-controller-attributes.php` per contract; not committed.

**Steps**: Same as WP01 T001 / WP02 T007. Read `contracts/migrate-cli.md`; implement the contract; add to `.git/info/exclude`.

**Validation**: `php scripts/migrate-controller-attributes.php --filter NewsletterController --dry-run` emits a non-empty diff.

### T014 — Apply migration to WP03 cluster

**Purpose**: Migrate 3 newsletter controllers.

**Steps**:

1. Dry-run preview:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp03 --dry-run | tee /tmp/wp03-preview.diff
   ```
   Sanity-check: 3 files; 6 use-stmt insertions; 56 attribute splices.
2. Apply: `php scripts/migrate-controller-attributes.php --cluster wp03 --apply`.
3. Idempotency: `php scripts/migrate-controller-attributes.php --cluster wp03 --dry-run` → empty.
4. Syntax check:
   ```bash
   for f in src/Controller/Newsletter*.php; do php -l "$f"; done
   ```

**WP-specific note**: The diff is dense per file. Spend extra time reading `/tmp/wp03-preview.diff` before applying — `NewsletterAdminApiController::*` has 12 methods × 2 params = 24 splice points in one file.

**Validation**: 3 files modified; idempotency empty; all `php -l` pass.

### T015 — Run `./vendor/bin/phpunit`

Run `./vendor/bin/phpunit`; expect green.

### T016 — Cold-boot smoke routes

**Steps**:

1. Cold-boot:
   ```bash
   WAASEYAA_LOG_LEVEL=notice \
     php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp03-server.log
   ```
2. Smoke:
   ```bash
   for url in \
     "http://localhost:8080/newsletter" \
     "http://localhost:8080/admin/newsletter/api/editions" \
     "http://localhost:8080/admin/newsletter/editor"; do
       curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
   done
   ```

**WP-specific notes**:
- `/admin/newsletter/api/editions` returns 401 unauthenticated — that's the dispatcher booting + auth middleware blocking. Body is non-zero (JSON error response). Expected.
- `/admin/newsletter/editor` returns 302 to login — same idea.
- `/newsletter` (public) returns 200 + body.

**Validation**: All three URLs return non-zero body; statuses 200/401/302.

### T017 — Cold-boot log scan

**Steps**:

```bash
grep -F 'dispatcher.deprecation' /tmp/wp03-server.log | grep -E 'NewsletterAdminApiController|NewsletterController|NewsletterEditorController'
```

**Validation**: zero matches.

### T018 — Commit, push, open PR

**Steps**:

1. Stage:
   ```bash
   git add src/Controller/NewsletterAdminApiController.php \
           src/Controller/NewsletterController.php \
           src/Controller/NewsletterEditorController.php
   ```
2. Confirm no migration script staged.
3. Commit:
   ```bash
   git commit -m "migrate(#753): wp03 newsletter → MapRoute/MapQuery

   Decorates 56 array \$params / array \$query parameters across 3
   newsletter controllers (NewsletterAdminApi, Newsletter,
   NewsletterEditor) with explicit #[MapRoute] / #[MapQuery]
   attributes.

   Part of #753 (v0.14 milestone).

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
   ```
4. Push: `git push --no-verify`.
5. Open PR with title `migrate(#753): wp03 newsletter → MapRoute/MapQuery` and body containing `Part of #753`.

**Validation**: PR diff shows 3 files; no scripts; CI green.

## Definition of Done

- [ ] T013..T018 complete.
- [ ] PR opened with `Part of #753` body.
- [ ] CI green.
- [ ] PR diff: 3 controller files; no scripts.
- [ ] Idempotency empty after merge.

## Reviewer Guidance

- Spot-check `NewsletterAdminApiController.php` (24 entries — largest single-file diff in this WP).
- Confirm `use Waaseyaa\SSR\Attribute\MapRoute;` and `use Waaseyaa\SSR\Attribute\MapQuery;` are alphabetical among other `Waaseyaa\` uses.
- Pull, run phpunit, smoke `/newsletter`, confirm log silence.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Dense per-file diffs (24 splice points in one file) — easy to miss one | Token-aware splice + idempotency check; PHPUnit + smoke as backstops |
| Admin newsletter routes require auth | 401/302 + non-zero body is success |
| Newsletter generation logic is complex (PDF, email, Mailgun) — migration must not affect dispatch | Pure parameter decoration; no behavioral change |

## Activity Log

- 2026-05-06T15:19:17Z – unknown – Done override: PR #756 squash-merged
