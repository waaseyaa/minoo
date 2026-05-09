# Quickstart: Per-WP Execution Recipe

**Mission**: `01KQYNX7` · **Date**: 2026-05-06

This document is the operating manual for executing one work package end-to-end. Every WP follows the same shape; only the cluster name and smoke-route table differ.

## Prerequisites

- `git` — clean working tree on `main` (or whichever WP base branch was created by `spec-kitty next`).
- `php` ≥ 8.4, with `tokenizer` and `dom` extensions (default in standard PHP).
- `composer install` already run (so `vendor/bin/phpunit` exists).
- Worktree created by `spec-kitty next --agent <name> --mission 01KQYNX7` (which runs `spec-kitty agent action implement WP## --agent <name>`).
- For WP01 only: `scripts/migrate-controller-attributes.php` does not yet exist; the WP01 prompt creates it.
- For WP02–WP06: the migration script is on the parent branch via WP01's worktree branch, copied or recreated locally.

## Step 1 — Sync migration script

```bash
# WP01: create the script (prompt-defined)
# WP02..WP06: the script may not be in the worktree branch
# (because WPs are file-disjoint from each other). Copy from WP01's branch:
git checkout migrate-controllers-explicit-route-attributes-01KQYNX7-lane-a -- scripts/migrate-controller-attributes.php
```

(Alternative: re-derive from `contracts/migrate-cli.md`. The contract is normative.)

## Step 2 — Dry-run preview

```bash
php scripts/migrate-controller-attributes.php --cluster wp## --dry-run | tee /tmp/wp##-preview.diff
```

Read the diff. Sanity-check:
- Two `use` statements added per file.
- One `#[MapRoute] ` insertion per `array $params` parameter, one `#[MapQuery] ` per `array $query`.
- No collateral changes (no whitespace drift, no other parameters touched).

## Step 3 — Apply

```bash
php scripts/migrate-controller-attributes.php --cluster wp## --apply
```

## Step 4 — Idempotency check

```bash
php scripts/migrate-controller-attributes.php --cluster wp## --dry-run
```

Expected: empty diff (script is idempotent — running twice produces no second-pass changes).

## Step 5 — PHPUnit

```bash
./vendor/bin/phpunit
```

Expected: `OK (914 tests, 2568 assertions)` (or higher baseline if `main` has advanced).

If failures appear, **stop**. Do not proceed. Diagnose:
- Are tests asserting controller-method shapes? (Likely.) Update the test expectations to match the new attributed signatures only if absolutely necessary; the binding contract should be unchanged.
- Are tests using reflection on parameter attributes? (Less likely.) Tests should now find `MapRoute`/`MapQuery` and may need updates.
- Is the migration script's splice subtly wrong? Diff the file by hand against the inventory in #753.

## Step 6 — Cold-boot smoke routes

In one terminal:

```bash
# Tail the log in a second shell, then start the server
WAASEYAA_LOG_LEVEL=notice \
  php -S 0.0.0.0:8080 -t public public/index.php 2>&1 | tee /tmp/wp##-server.log
```

In another terminal, hit the smoke route table for the WP:

```bash
# Example for WP01 (Auth+Account)
for url in \
  "http://localhost:8080/login" \
  "http://localhost:8080/register" \
  "http://localhost:8080/logout" \
  "http://localhost:8080/account" \
  "http://localhost:8080/admin/coordinator" \
  "http://localhost:8080/admin/coordinator/applications" \
  "http://localhost:8080/admin/role-management" \
  "http://localhost:8080/volunteer/signup" \
  "http://localhost:8080/account/volunteer"; do
    curl -sS -o /tmp/page -w "%{url}: %{http_code}/%{size_download}\n" "$url"
done
```

Expected: each URL returns either 200 (public), 302 (redirect for auth-protected), or 401 (auth required) — but **never** 0-byte responses. Body size > 0.

(See "Smoke route table" below for per-cluster route lists.)

## Step 7 — Cold-boot log scan

```bash
grep -F 'dispatcher.deprecation' /tmp/wp##-server.log | grep -F 'App\\Controller\\' | sort -u
```

Expected: zero lines naming any controller in the WP's cluster. Lines naming controllers from *other* WPs are acceptable until those WPs land.

If lines appear naming WP-cluster controllers, **stop**. The migration missed a method. Re-run:

```bash
php scripts/check-implicit-array-params.php --path src/Controller --quiet
```

Use the output to identify the missed method, fix manually or via `--filter <ControllerName>`, re-run from Step 4.

## Step 8 — Commit and push

```bash
git add src/Controller/<files-changed>
git commit -m "migrate(#753): wp## <cluster-name> → MapRoute/MapQuery"
git push --no-verify  # husky pre-push assumes vendor/, which worktrees lack
```

(Per CLAUDE.md gotcha: `.husky/pre-push` runs `vendor/bin/phpunit` but worktrees don't have `vendor/` linked. Tests already passed locally in Step 5 so `--no-verify` is safe here.)

## Step 9 — Open PR

```bash
gh pr create \
  --title "migrate(#753): wp## <cluster-name> → MapRoute/MapQuery" \
  --body "$(cat <<EOF
## Summary
- Decorates \`array \$params\` with \`#[MapRoute]\` and \`array \$query\` with \`#[MapQuery]\` across the <cluster-name> cluster.
- <N> controllers, <M> methods, <P> parameters.

## Verification
- [x] PHPUnit green (914 tests / 2568 assertions baseline)
- [x] Cold-boot smoke routes return 200/302/401 with non-zero body
- [x] Cold-boot log emits zero \`dispatcher.deprecation\` notices for cluster controllers
- [x] Migration script idempotent (second \`--dry-run\` empty)

Part of #753 (v0.14 milestone).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

## Step 10 — Mark WP for review

In the spec-kitty runtime, the implementer agent transitions the WP to `for_review`. The reviewer claims it via `spec-kitty agent action review WP## --agent <reviewer>`.

## After all 6 WPs land

WP06 prompt includes the **final reconciliation** step:

```bash
# 1. Run the extractor against the full repo
php scripts/check-implicit-array-params.php

# Expected exit 0, count 0
echo $?  # 0

# 2. Cold-boot smoke + log scan one more time, hitting at least one route per controller
# (or a representative sample). Expect zero dispatcher.deprecation notices for App\Controller\*.

# 3. Remove the transient migration script
git rm scripts/migrate-controller-attributes.php

# 4. Commit and push
git add scripts/check-implicit-array-params.php
git rm scripts/migrate-controller-attributes.php
git commit -m "migrate(#753): wp06 elder support + ingestion + extractor; closes #753"
```

The WP06 PR description should include `Closes #753` so the issue auto-closes on merge.

---

## Smoke route table

One representative URL per controller. For `POST` routes and routes requiring CSRF, the smoke is "boot the server, request the GET form that would precede the POST" (which still loads the controller class and exercises the dispatcher).

> **Note (post-WP01)**: Some URLs in this table are stale. Always cross-check against `src/Provider/Routing/*.php` for the live path before smoking. Confirmed corrections from WP01 (Auth + Account):
> - `/admin/coordinator` → `/dashboard/coordinator`
> - `/admin/coordinator/applications` → `/dashboard/coordinator/applications`
> - `/admin/role-management` → `/staff/users`
> - `/volunteer/signup` → `/elders/volunteer`
> - `/account/volunteer` → `/dashboard/volunteer`
>
> Subsequent WPs should follow the same pattern: read the live route provider before smoking.

### WP01 — Auth + Account

| Controller | URL | Expected |
|---|---|---|
| AuthController | `GET /login` | 200, body > 0 |
| AccountHomeController | `GET /account` | 302 (auth) or 200, body > 0 |
| RoleManagementController | `GET /admin/role-management` | 302/401, body > 0 |
| CoordinatorDashboardController | `GET /admin/coordinator` | 302/401, body > 0 |
| VolunteerController | `GET /volunteer/signup` | 200, body > 0 |
| VolunteerDashboardController | `GET /account/volunteer` | 302/401, body > 0 |

### WP02 — Games

| Controller | URL | Expected |
|---|---|---|
| ShkodaController | `GET /games/shkoda` | 200, body > 0 |
| CrosswordController | `GET /games/crossword` | 200, body > 0 |
| AgimController | `GET /games/agim` | 200, body > 0 |
| JourneyController | `GET /games/journey` | 200, body > 0 |
| MatcherController | `GET /games/matcher` | 200, body > 0 |
| GuessPriceController | `GET /games/guess-price` | 200, body > 0 |

### WP03 — Newsletter

| Controller | URL | Expected |
|---|---|---|
| NewsletterController | `GET /newsletter` | 200, body > 0 |
| NewsletterAdminApiController | `GET /admin/newsletter/api/editions` | 401/302, body > 0 |
| NewsletterEditorController | `GET /admin/newsletter/editor` | 401/302, body > 0 |

### WP04 — Engagement + Messaging

| Controller | URL | Expected |
|---|---|---|
| FeedController | `GET /feed` | 302 (anon) or 200 (auth), body > 0 |
| EngagementController | `GET /api/engagement/comments?target_type=post&target_id=1` | 200/401, body > 0 |
| MessagingController | `GET /messages` | 302/401, body > 0 |
| ChatController | `POST /api/chat/send` | (boot via different route; chat is auth-only POST) |
| BlockController | `GET /account/blocks` | 302/401, body > 0 |

### WP05 — Static + Communities + Misc

| Controller | URL | Expected |
|---|---|---|
| StaticPageController | `GET /about` | 200, body > 0 |
| CommunityController | `GET /communities/sagamok-anishnawbek` | 200, body > 0 |
| BusinessController | `GET /communities/sagamok-anishnawbek/business/example-slug` | 200/404, body > 0 |
| ContributorController | `GET /contributors/example-slug` | 200/404, body > 0 |
| EventController | `GET /events/example-slug` | 200/404, body > 0 |
| GroupController | `GET /groups/example-slug` | 200/404, body > 0 |
| TeachingController | `GET /teachings/example-slug` | 200/404, body > 0 |
| LanguageController | `GET /language` | 200, body > 0 |
| LocationController | `GET /api/location/current` | 200, body > 0 |
| OpenGraphController | `GET /og/event/example-slug.png` | 200/404 |
| OralHistoryController | `GET /oral-history/example-slug` | 200/404, body > 0 |
| PeopleController | `GET /people/example-slug` | 200/404, body > 0 |
| HomeController | `GET /` | 200 (anon) or 302 (auth), body > 0 |

### WP06 — Elder Support + Ingestion

| Controller | URL | Expected |
|---|---|---|
| ElderSupportController | `GET /elder-support/request` | 200, body > 0 |
| ElderSupportWorkflowController | `GET /admin/elder-support` | 302/401, body > 0 |
| IngestionApiController | `GET /api/ingestion/status?id=1` | 200/404/401, body > 0 |
| IngestionDashboardController | `GET /admin/ingestion` | 302/401, body > 0 |

> Slug-bearing routes (`example-slug`) are tolerant of 404 because the smoke is verifying that the controller boots and the dispatcher binds — not that any specific record exists. A 404 with a non-zero body proves both.
