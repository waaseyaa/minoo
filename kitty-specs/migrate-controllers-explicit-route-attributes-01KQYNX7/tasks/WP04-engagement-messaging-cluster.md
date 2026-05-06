---
work_package_id: WP04
title: Engagement + Messaging cluster — migrate to MapRoute/MapQuery
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
- T019
- T020
- T021
- T022
- T023
- T024
history:
- timestamp: '2026-05-06T13:10:35Z'
  event: scaffolded by /spec-kitty.tasks
authoritative_surface: src/Controller/
execution_mode: code_change
mission_id: 01KQYNX7DWR7QNFK6XAZRKMWHV
mission_slug: migrate-controllers-explicit-route-attributes-01KQYNX7
owned_files:
- src/Controller/BlockController.php
- src/Controller/ChatController.php
- src/Controller/EngagementController.php
- src/Controller/FeedController.php
- src/Controller/MessagingController.php
tags: []
---

# WP04 — Engagement + Messaging cluster: migrate to `#[MapRoute]` / `#[MapQuery]`

## Objective

Migrate 5 controllers in the Engagement + Messaging cluster (Block, Chat, Engagement, Feed, Messaging) to explicit `#[MapRoute]` / `#[MapQuery]` attributes. Two of these are large (`MessagingController` 28 entries, `EngagementController` 18 entries) and write-heavy (mostly POST endpoints).

## Context

- **Mission**: `01KQYNX7`. **Spec**: [`../spec.md`](../spec.md). **Plan**: [`../plan.md`](../plan.md). **Quickstart**: [`../quickstart.md`](../quickstart.md).
- **Migration tool contract**: [`../contracts/migrate-cli.md`](../contracts/migrate-cli.md).
- **Pattern reference**: [`WP01-auth-account-cluster.md`](./WP01-auth-account-cluster.md) for the canonical recipe.
- **Issue #753 inventory**: ~68 method×param entries (28 Messaging + 18 Engagement + 6 Block + 2 Chat + 6 Feed = 60 + tweaks per Feed routes).

## Branch Strategy

- Planning base / merge target: `main`.
- Execution worktree: per `lanes.json`.
- PR title: `migrate(#753): wp04 engagement+messaging → MapRoute/MapQuery`.
- PR body: `Part of #753`.

## Subtasks

### T019 — Recreate transient migration tool locally

Same as WP01 T001 / WP02 T007 / WP03 T013. Implement contract; add to `.git/info/exclude`; smoke-test.

### T020 — Apply migration to WP04 cluster

**Steps**:

1. Dry-run:
   ```bash
   php scripts/migrate-controller-attributes.php --cluster wp04 --dry-run | tee /tmp/wp04-preview.diff
   ```
   Sanity-check: 5 files; 10 use-stmt insertions; ~60 attribute splices.
2. Apply: `php scripts/migrate-controller-attributes.php --cluster wp04 --apply`.
3. Idempotency check (dry-run again → empty).
4. Syntax check:
   ```bash
   for f in src/Controller/{Block,Chat,Engagement,Feed,Messaging}Controller.php; do
     php -l "$f"
   done
   ```

**WP-specific note**: Reaction field rename (`emoji` → `reaction_type`, migration `20260322_120000`, per CLAUDE.md gotcha) is unrelated to this migration. Verify the diff does not touch `reaction_type` or `emoji` strings — the migration only modifies parameter declarations. If the diff somehow includes those strings, the migration tool has a bug; revert and investigate.

### T021 — Run `./vendor/bin/phpunit`

Run `./vendor/bin/phpunit`; expect green. Engagement + messaging tests are some of the densest in the suite (per CLAUDE.md: 914 baseline includes engagement coverage). Pay attention to any newly-failing test — it likely indicates a binding regression in the high-write controllers.

### T022 — Cold-boot smoke routes

**Steps**:

1. Cold-boot:
   ```bash
   WAASEYAA_LOG_LEVEL=notice \
     php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp04-server.log
   ```
2. Smoke:
   ```bash
   for url in \
     "http://localhost:8080/feed" \
     "http://localhost:8080/api/engagement/comments?target_type=post&target_id=1" \
     "http://localhost:8080/messages" \
     "http://localhost:8080/account/blocks"; do
       curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
   done
   ```

**WP-specific notes**:
- **ChatController** is POST-only (`/api/chat/send`). The smoke for chat is "did the dev server boot without crashing despite ChatController being autoloaded" — a non-chat URL hitting any other migrated controller serves as a proxy. Skip a direct chat probe.
- **FeedController** has `/feed` (auth-only) and `/feed/explore` (anon). For unauthenticated smoke, `/feed` returns 302 to `/`; `/feed/explore` returns 200 with a body.
- **EngagementController** has many POST endpoints (`/api/engagement/react`, `/follow`, `/comment`). Hitting the GET `/api/engagement/comments` exercises one of the read paths.
- **MessagingController** is auth-only; `/messages` returns 302/401 with body.

**Validation**: All URLs return non-zero body; statuses in {200, 302, 401}.

### T023 — Cold-boot log scan

```bash
grep -F 'dispatcher.deprecation' /tmp/wp04-server.log | grep -E 'BlockController|ChatController|EngagementController|FeedController|MessagingController'
```

**Validation**: zero matches.

### T024 — Commit, push, open PR

**Steps**:

1. Stage:
   ```bash
   git add src/Controller/BlockController.php \
           src/Controller/ChatController.php \
           src/Controller/EngagementController.php \
           src/Controller/FeedController.php \
           src/Controller/MessagingController.php
   ```
2. Confirm no migration script in staged diff.
3. Commit:
   ```bash
   git commit -m "migrate(#753): wp04 engagement+messaging → MapRoute/MapQuery

   Decorates ~60 array \$params / array \$query parameters across 5
   controllers in the Engagement + Messaging cluster (Block, Chat,
   Engagement, Feed, Messaging) with explicit #[MapRoute] /
   #[MapQuery] attributes.

   Part of #753 (v0.14 milestone).

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
   ```
4. Push `--no-verify`; open PR (title and body per WP convention).

## Definition of Done

- [ ] T019..T024 complete.
- [ ] PR opened with `Part of #753`.
- [ ] CI green.
- [ ] PR diff: 5 files; no scripts.
- [ ] Idempotency empty.

## Reviewer Guidance

- Spot-check `MessagingController.php` (28 entries — largest in this WP) and `ChatController.php` (smallest at 2 — proves the tool handles small files too).
- Confirm `reaction_type` is not anywhere in the diff (unrelated to this WP).
- Pull, phpunit, smoke `/feed/explore`, log silence.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Engagement/Messaging tests are sensitive to dispatcher binding | Full PHPUnit run; spot-check engagement tests if any fail |
| ChatController POST-only — hard to smoke directly | Smoke a different cluster route to prove server boot; rely on phpunit for ChatController binding |
| Reaction field rename gotcha | Visual diff review confirms no `reaction_type`/`emoji` lines touched |
| Feed homepage/redirect logic | `/feed` returns 302 anonymous; that's expected — confirm 302 + non-zero body |

## Activity Log

- 2026-05-06T16:58:37Z – unknown – Done override: PR #757 squash-merged
