# V1 Staging Signoff & Release Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verify staging deployment, produce human signoff package, and prepare for the final V1 release decision.

**Architecture:** This is a verification + documentation plan. Staging auto-deploys on push to `main` (already triggered). Production deploys via `workflow_dispatch` with `deploy-v1` confirmation. No code changes expected.

**Tech Stack:** GitHub CLI, GitHub Actions, PHPUnit, Playwright, Deployer

---

## Task 1: Pre-Deployment Verification

- [ ] **Step 1: Confirm `main` is the authoritative release branch**

Run:
```bash
git branch --show-current
git log --oneline -1 origin/main
```
Expected: On `main`, HEAD matches `origin/main`.

- [ ] **Step 2: Confirm all Sprint 1–3 issues are closed**

Run:
```bash
# Sprint 1
for issue in 127 129 131 134 171 194 197 200; do
  echo -n "#$issue: "; gh issue view $issue --json state -q '.state'
done

# Sprint 2
for issue in 128 130 175 176 184 185 196; do
  echo -n "#$issue: "; gh issue view $issue --json state -q '.state'
done

# Sprint 3
for issue in 136 137 138 195 198 199 204; do
  echo -n "#$issue: "; gh issue view $issue --json state -q '.state'
done
```
Expected: All 22 issues return `CLOSED`.

If any are open, investigate and close with `gh issue close <N> --reason completed` if work is merged.

- [ ] **Step 3: Confirm governance gates (#202) have status comments**

Run:
```bash
gh issue view 202 --json comments -q '.comments | length'
gh issue view 202 --json comments -q '.comments[].body' | grep -c "Sprint.*Governance"
```
Expected: At least 2 governance update comments (Sprint 2 + Sprint 3).

- [ ] **Step 4: Confirm no untracked or stray files**

Run:
```bash
git status --short
```
Expected: Clean working tree (no output).

- [ ] **Step 5: Run PHPUnit**

Run:
```bash
./vendor/bin/phpunit
```
Expected: `OK (287 tests, 685 assertions)`

- [ ] **Step 6: Run Playwright tests**

Run:
```bash
npx playwright test
```
Expected: All tests pass. If dev server is needed, start it first:
```bash
php -S localhost:8081 -t public &
npx playwright test
kill %1
```

- [ ] **Step 7: Run composer audit**

Run:
```bash
composer audit
```
Expected: No security vulnerability advisories found.

---

## Task 2: Staging Deployment Verification

**Context:** Staging auto-deploys on push to `main` via `.github/workflows/deploy.yml`. The latest push (`e4b1b08`) should have triggered a deploy.

- [ ] **Step 1: Confirm staging deploy succeeded**

Run:
```bash
gh run list --workflow=deploy.yml --branch=main --limit=3
```
Expected: Most recent run shows `completed` with `success` status.

If failed, check logs:
```bash
gh run view <run-id> --log-failed
```

- [ ] **Step 2: Verify staging is live**

Run:
```bash
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/
```
Expected: `200`

If staging URL is different, check the GitHub environment settings:
```bash
gh api repos/waaseyaa/minoo/environments --jq '.environments[].name'
```

- [ ] **Step 3: Run post-deploy smoke tests on staging**

Verify each area manually or via curl:

```bash
# Homepage
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/

# Auth pages
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/login
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/register
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/forgot-password

# Content pages
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/teachings
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/events
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/groups
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/language
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/communities

# Compliance pages
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/data-sovereignty
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/elder-support

# robots.txt
curl -s https://staging.minoo.live/robots.txt | grep "Disallow"

# Security headers
curl -sI https://staging.minoo.live/ | grep -i "x-content-type-options\|x-frame-options\|referrer-policy"
```
Expected: All return `200`, robots.txt has Disallow rules, security headers present.

- [ ] **Step 4: Verify 404 handling**

Run:
```bash
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/teachings/nonexistent-slug
curl -s -o /dev/null -w '%{http_code}' https://staging.minoo.live/communities/nonexistent-slug
```
Expected: Both return `404`.

---

## Task 3: Human Signoff Package

- [ ] **Step 1: Write staging signoff document**

Create `docs/governance/v1-staging-signoff.md`:

```markdown
# V1 Staging Signoff Package

**Date:** [current date]
**Staging URL:** https://staging.minoo.live
**Release target:** V1 (Milestone #19, due 2026-04-22)

## Release Summary

Minoo V1 is an Indigenous knowledge platform built on the Waaseyaa CMS framework.
This release includes 22 issues across 3 sprints:

- **Sprint 1 (Foundation & Compliance):** CI/CD, CC BY-NC-SA attribution, error pages,
  volunteer signup fixes, community autocomplete, NorthCloud sync
- **Sprint 2 (Core Features & Data Integrity):** Real entity controllers, password reset,
  NC API cache, community export, data sovereignty controls
- **Sprint 3 (Production Hardening):** OWASP security review, WCAG 2.1 AA accessibility,
  media copyright controls, Playwright e2e coverage (auth, forms, content)

## Test Results

- **PHPUnit:** 287 tests, 685 assertions — all passing
- **Playwright:** [N] tests — all passing
- **composer audit:** zero vulnerabilities

## Governance Gate Status

| Gate | Description | Status | Evidence |
|------|-------------|--------|----------|
| 1. License Attribution | CC BY-NC-SA visible on OPD content | READY | Footer on all pages, LICENSE file |
| 2. Media Copyright | copyright_status field enforced | READY | 7 entities, controller filtering |
| 3. Data Sovereignty | Consent fields, sovereignty page | READY | consent_public/consent_ai_training, /data-sovereignty |
| 4. Community Governance | No public data export | READY | CLI-only tools, no API endpoint |
| 5. Security Review | OWASP top 10 audit | READY | Security checklist, headers, rate limiting |

## Reference Documents

- Sprint 2 checkpoint: `docs/governance/sprint2-checkpoint.md`
- Security checklist: `docs/governance/security-checklist.md`
- Epic: [#201](https://github.com/waaseyaa/minoo/issues/201)
- Governance gates: [#202](https://github.com/waaseyaa/minoo/issues/202)
- Implementation plans: `docs/superpowers/plans/`

## Human Signoff Required

Each gate requires a signed comment on [#202](https://github.com/waaseyaa/minoo/issues/202):

```
APPROVED: [gate name] — [date] — [notes]
```

All 5 signoffs must be recorded before production deployment is authorized.

## Production Deployment

After all signoffs are recorded:

1. Go to GitHub Actions → "Deploy Production" workflow
2. Click "Run workflow"
3. Type `deploy-v1` in the confirmation field
4. Click "Run workflow"

Rollback: `dep rollback production`
```

- [ ] **Step 2: Commit the signoff document**

```bash
git add docs/governance/v1-staging-signoff.md
git commit -m "docs(#202): V1 staging signoff package

Refs #202

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

- [ ] **Step 3: Push to remote (triggers staging redeploy)**

```bash
git push origin main
```

- [ ] **Step 4: Comment on #202 with staging readiness**

```bash
gh issue comment 202 --body "## V1 Staging Signoff Package — [date]

Staging deployment verified. All 22 Sprint 1–3 issues closed. All 5 governance
gates ready for human signoff.

**Signoff document:** \`docs/governance/v1-staging-signoff.md\`
**Staging URL:** https://staging.minoo.live

Each gate requires a signed comment on this issue:
\`APPROVED: [gate name] — [date] — [notes]\`

All 5 signoffs required before production deploy (\`deploy-v1\` workflow)."
```

---

## Task 4: Exit Criteria Verification

- [ ] **Step 1: Confirm staging is live and verified**

All smoke test URLs returned 200, security headers present, robots.txt correct.

- [ ] **Step 2: Confirm signoff package is committed**

Run:
```bash
git log --oneline -1
ls docs/governance/v1-staging-signoff.md
```
Expected: Latest commit is the signoff document, file exists.

- [ ] **Step 3: Confirm system is ready for V1 release decision**

Checklist:
```
[x] All 22 Sprint 1-3 issues closed
[x] 287 PHPUnit tests passing
[x] Playwright e2e tests passing
[x] composer audit clean
[x] Staging deployed and verified
[x] Security checklist documented
[x] All 5 governance gates READY
[x] Signoff package committed
[x] #202 comment posted with signoff instructions
[x] Production deploy requires manual workflow_dispatch with "deploy-v1"
```

**The system is ready for the final V1 release decision.** Production deployment is blocked until all 5 human signoffs are recorded on #202.
