# V1 Production Deployment Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy Minoo V1 to production after human signoff, verify the deployment, publish release notes, and close the V1 milestone.

**Architecture:** Production deploys via GitHub Actions `workflow_dispatch` with `deploy-v1` confirmation. The workflow checks out both `minoo` and `waaseyaa/framework`, runs `composer install --no-dev`, builds an artifact via rsync, and deploys with PHP Deployer over SSH to `minoo.live`. Rollback is available via `dep rollback production`.

**Tech Stack:** GitHub Actions, PHP Deployer, Caddy, PHP-FPM, SQLite

---

## Task 1: Prerequisites Check

**BLOCKER: Do not proceed until all 5 human signoffs are recorded on #202.**

- [ ] **Step 1: Verify human signoffs on #202**

Run:
```bash
gh issue view 202 --json comments -q '.comments[].body' | grep -c "APPROVED"
```
Expected: `5` (one per gate). If fewer than 5, STOP. Do not proceed.

Verify each gate individually:
```bash
gh issue view 202 --json comments -q '.comments[].body' | grep "APPROVED"
```
Expected output should include:
- `APPROVED: License Attribution`
- `APPROVED: Media Copyright`
- `APPROVED: Data Sovereignty`
- `APPROVED: Community Governance`
- `APPROVED: Security Review`

- [ ] **Step 2: Verify staging deploy completed successfully**

Run:
```bash
gh run view 22990609209 --json status,conclusion -q '{status: .status, conclusion: .conclusion}'
```
Expected: `{status: completed, conclusion: success}`

If failed, check logs and fix before proceeding:
```bash
gh run view 22990609209 --log-failed
```

- [ ] **Step 3: Verify no new commits since signoff package**

Run:
```bash
git fetch origin
git log --oneline origin/main -1
```
Expected: HEAD is `ff9f3f7` (signoff package commit) or the CI fix commit. No unexpected commits.

- [ ] **Step 4: Run PHPUnit**

Run:
```bash
./vendor/bin/phpunit
```
Expected: `OK (287 tests, 685 assertions)`

- [ ] **Step 5: Run composer audit**

Run:
```bash
composer audit
```
Expected: No security vulnerability advisories found.

- [ ] **Step 6: Verify release/v1 untouched**

Run:
```bash
git log --oneline -1 release/v1
```
Expected: `e46d662 feat(#196): Data sovereignty controls and consent metadata`

---

## Task 2: Trigger Production Deployment

- [ ] **Step 1: Trigger the deploy-v1 workflow**

Run:
```bash
gh workflow run deploy-production.yml -f confirm=deploy-v1
```

- [ ] **Step 2: Get the run ID**

Run:
```bash
sleep 5
gh run list --workflow=deploy-production.yml --limit=1 --json databaseId,status -q '.[0]'
```
Expected: Returns run ID with status `in_progress` or `queued`.

- [ ] **Step 3: Monitor deployment**

Run:
```bash
gh run watch <run-id>
```
Expected: Completes with `success`. If it fails, check logs:
```bash
gh run view <run-id> --log-failed
```

If deployment fails, do NOT retry without investigating. Check:
1. SSH connectivity to minoo.live
2. Deployer configuration
3. PHP version on server
4. SQLite permissions

---

## Task 3: Post-Deployment Verification

- [ ] **Step 1: Verify production is live**

Run:
```bash
curl -s -o /dev/null -w '%{http_code}' https://minoo.live/
```
Expected: `200`

- [ ] **Step 2: Verify security headers**

Run:
```bash
curl -sI https://minoo.live/ | grep -i "x-content-type-options\|x-frame-options\|referrer-policy\|permissions-policy"
```
Expected: All 4 headers present:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

- [ ] **Step 3: Verify content pages**

Run:
```bash
for path in / /teachings /events /groups /language /communities /data-sovereignty /elder-support /login /register /forgot-password; do
  echo -n "$path: "
  curl -s -o /dev/null -w '%{http_code}' "https://minoo.live$path"
  echo
done
```
Expected: All return `200`.

- [ ] **Step 4: Verify 404 handling**

Run:
```bash
curl -s -o /dev/null -w '%{http_code}' https://minoo.live/teachings/nonexistent-slug
```
Expected: `404`

- [ ] **Step 5: Verify robots.txt**

Run:
```bash
curl -s https://minoo.live/robots.txt | grep "Disallow"
```
Expected: `/api/`, `/dashboard/`, `/login`, `/register`, `/forgot-password`, `/reset-password`

- [ ] **Step 6: Verify accessibility skip-link**

Run:
```bash
curl -s https://minoo.live/ | grep -o 'class="skip-link"'
```
Expected: `class="skip-link"`

- [ ] **Step 7: Verify copyright filtering (spot check)**

Run:
```bash
curl -s https://minoo.live/teachings | grep -c "copyright_status" || echo "0 (field not exposed in HTML — correct)"
```
Expected: `0` — copyright_status is a backend filter, not rendered in HTML.

---

## Task 4: Release Documentation

- [ ] **Step 1: Create final release notes**

Create `docs/releases/v1-release-notes.md`. Base content on the existing draft at `docs/plans/2026-03-11-v1-release-notes.md`, updated with final Sprint 2-3 additions:

```markdown
# Minoo V1.0 Release Notes

**Release Date:** 2026-03-12
**Platform:** [minoo.live](https://minoo.live)

---

## What is Minoo?

Minoo is an Indigenous knowledge platform built by a First Nations developer in
northern Ontario. It connects communities, preserves language and teachings, and
supports Elder care through volunteer matching — all governed by community values,
not corporate ones.

---

## What V1 Delivers

### Elder Support Program
Submit requests for Elder assistance — rides, groceries, visits, companionship.
Volunteers sign up and are matched by proximity and skills. Coordinators manage
assignments through a dashboard with a 6-state workflow.

### Community Registry
637 First Nations communities seeded from CIRNAC open data. Community detail pages
display leadership and band office information sourced from NorthCloud API with
SQLite caching for performance.

### Language Preservation
Anishinaabemowin dictionary with entries, example sentences, word parts, and speaker
information. All dictionary content sourced from The Ojibwe People's Dictionary is
displayed with proper attribution under CC BY-NC-SA 4.0.

### Teachings & Events
Browse cultural teachings by category and community events by date. All content
rendered from real entity data with consent-based visibility controls.

### Search
Full-text search across all content types with Indigenous content prioritization
and community-based filtering.

### Password Reset
On-screen password reset flow with secure token generation (64-char hex, 1-hour
expiry, single-use). Email delivery planned for V1.1.

---

## Security & Compliance

### OWASP Top 10 Audit
- CSRF protection on all forms (framework-level middleware)
- Parameterized queries throughout (PDO prepared statements)
- Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
  Permissions-Policy
- Session cookies: HttpOnly, Secure, SameSite=Lax, strict mode
- Rate limiting: login (5 attempts/5 min), forgot-password (3 attempts/5 min)
- Dependency audit: zero critical/high vulnerabilities

### Accessibility (WCAG 2.1 AA)
- Skip-to-content link on all pages
- All form inputs have associated labels
- All images have alt text
- Color contrast meets 4.5:1 ratio (oklch palette)
- axe-core automated tests on all 11 public pages

### Data Sovereignty
- `consent_public` and `consent_ai_training` fields on cultural content entities
- Data sovereignty statement at /data-sovereignty
- robots.txt blocks API and dashboard routes from crawlers
- No bulk data export API — CLI-only tools require server access

### Media Copyright
- `copyright_status` field on all media-bearing entities (7 types)
- Non-approved media (requires_permission, unknown) filtered from public pages
- Default status is "unknown" (excluded from display until approved)

### Licensing
- Source code: MIT License
- Community content: CC BY-NC-SA 4.0
- OPD dictionary content displayed with attribution

---

## Technical Details

- **Framework:** Waaseyaa CMS (custom PHP 8.3+ framework)
- **Frontend:** Twig 3 SSR, vanilla CSS (oklch, container queries, logical properties)
- **Database:** SQLite
- **NorthCloud API:** Community data sync with SQLite cache layer
- **Tests:** 287 PHPUnit tests (685 assertions), 70+ Playwright e2e tests
- **CI/CD:** GitHub Actions (lint, PHPUnit, Playwright, security audit, commercial
  use check)
- **Deployment:** PHP Deployer via GitHub Actions with rollback support

---

## Known V1.1 Improvements

- Content-Security-Policy header (requires careful tuning per page)
- Rate limiter row pruning (prevent unbounded table growth)
- Rate limiter dependency injection (extract from controller)
- Copyright filtering trait (deduplicate across controllers)
- Email delivery for password reset flow
- Enhanced logging (A09 OWASP)
```

- [ ] **Step 2: Commit release notes**

```bash
mkdir -p docs/releases
git add docs/releases/v1-release-notes.md
git commit -m "docs: V1.0 release notes

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

- [ ] **Step 3: Push to remote**

```bash
git push origin main
```

---

## Task 5: Close V1 Milestone

- [ ] **Step 1: Close governance issue #202**

Run:
```bash
gh issue close 202 --reason completed --comment "All 5 governance gates approved. V1 deployed to production."
```

- [ ] **Step 2: Close epic #201**

Run:
```bash
gh issue close 201 --reason completed --comment "All 22 sprint items completed and merged. V1 released to production."
```

- [ ] **Step 3: Close milestone #19**

Run:
```bash
gh api repos/waaseyaa/minoo/milestones/19 -X PATCH -f state=closed
```

- [ ] **Step 4: Create GitHub release**

Run:
```bash
gh release create v1.0.0 \
  --title "Minoo V1.0" \
  --notes-file docs/releases/v1-release-notes.md \
  --target main
```

---

## Exit Criteria

- [ ] All 5 human signoffs recorded on #202
- [ ] Production deployed via `deploy-v1` workflow
- [ ] All smoke tests passing on production
- [ ] Security headers verified on production
- [ ] Release notes committed at `docs/releases/v1-release-notes.md`
- [ ] GitHub release `v1.0.0` created
- [ ] Issue #202 closed
- [ ] Epic #201 closed
- [ ] Milestone #19 closed
- [ ] V1 officially released
