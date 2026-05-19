# Quickstart: Verify the alpha.182 Upgrade Locally

**Mission**: `01KS0WZ7MX6P96NP0V95RBTPG2`
**Audience**: Operator or reviewer who just pulled the merged mission and wants to sanity-check the upgrade before deploying.

This is the **post-merge** verification recipe. For mission-internal WP verification, each WP file has its own targeted check.

---

## Prerequisites

- Local Minoo checkout on the merge commit (post-mission).
- PHP 8.5+, Composer.
- Sibling `waaseyaa/` checkout (only needed if you want to cross-reference framework changes; not required for the smoke).

## Step 1 — Install bumped dependencies

```bash
composer install
```

Expected:
- No errors. `vendor/waaseyaa/*` directories are present at `v0.1.0-alpha.182`.
- Verify with: `composer show 'waaseyaa/*' | head -5` — every line should show `v0.1.0-alpha.182`.

## Step 2 — Run the full test suite

```bash
./vendor/bin/phpunit
```

Expected:
- All tests pass.
- Test count is at or above the 914 baseline (mission may have added regression tests).
- **No `MissingQueryAccountException` traces in the output.** If you see one, a `getQuery()` site was missed during the mission — file an issue with the failing test name and the call-site file path.

## Step 3 — Boot a local dev server

```bash
php -S 0.0.0.0:8080 -t public public/index.php
```

Leave the server running in another terminal.

## Step 4 — Anonymous SSR smoke (NFR-002)

In a fresh terminal:

```bash
curl -sS -o /tmp/minoo-home.html -w "%{http_code}/%{size_download}\n" http://localhost:8080/
```

Expected:
- `200/<N>` where `N > 1000`.
- `grep -c '<title>' /tmp/minoo-home.html` returns ≥ 1.
- The homepage should be the anonymous-visitor variant (`templates/home.html.twig`), not a 302 to `/feed`.

## Step 5 — Authenticated feed smoke (NFR-003)

You need a session cookie. Either log in via the browser at `http://localhost:8080/` then copy the cookie from devtools, or use the test harness account.

```bash
COOKIE='waaseyaa_session=...'  # paste your session cookie
curl -sS -b "$COOKIE" -o /tmp/minoo-feed.html -w "%{http_code}/%{size_download}\n" http://localhost:8080/feed
```

Expected:
- `200/<N>` where `N > 1000`.
- `grep -c 'feed' /tmp/minoo-feed.html` returns ≥ 1.

## Step 6 — Run the static-analysis gates

```bash
composer phpstan
composer cs-fixer
```

Both should exit 0.

## Step 7 — Repository boundary check

```bash
bin/check-milestones
```

Expected: `OK: No boundary violations detected.`

## Step 8 — Review the new security audit doc

Open `docs/security/sql-entity-query-access-check-bypass-audit.md`. Confirm:
- The doc exists.
- Every `accessCheck(false)` site in `src/` is listed.
- Each row has a one-line justification and a `Last reviewed` date.

Cross-reference with: `grep -rn 'accessCheck(false)' src/ --include='*.php'` — the count of matches should equal the number of rows in the audit doc.

---

## Smoke summary

If steps 1–8 all pass, the upgrade is live and the access-checking contract is enforced. You can deploy to production via the existing GitHub Actions pipeline (push to `main`).

If any step fails:
1. Don't deploy.
2. File an issue with the step number, command output, and any failing file paths.
3. Cross-reference the framework's own audit doc at `../waaseyaa/docs/security/sql-entity-query-access-check-bypass-audit.md` to confirm whether the failing call site has a known framework-side counterpart pattern.

## Production verification (after deploy)

After GitHub Actions deploys to `minoo.live`:

```bash
curl -sS -o /tmp/prod-home.html -w "%{http_code}/%{size_download}\n" https://minoo.live/
grep -o '<title>[^<]*</title>' /tmp/prod-home.html
```

Expected: `200/<N>` with `N > 1000` and a non-empty `<title>`. **Do not deploy without this check.** Per the CLAUDE.md gotcha "Verify production with body size, not HTTP status", a 200 with `size_download=0` is the classic post-deploy WSOD signature.
