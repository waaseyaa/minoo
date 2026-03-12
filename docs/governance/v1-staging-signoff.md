# V1 Staging Signoff Package

**Date:** 2026-03-12
**Staging URL:** https://staging.minoo.live
**Release target:** V1 (Milestone #19, due 2026-04-22)

## Release Summary

Minoo V1 is an Indigenous knowledge platform built on the Waaseyaa CMS framework.
This release includes 22 issues across 3 sprints:

- **Sprint 1 (Foundation & Compliance):** CI/CD pipeline, CC BY-NC-SA attribution,
  friendly error pages, volunteer signup fixes, community autocomplete, NorthCloud sync
- **Sprint 2 (Core Features & Data Integrity):** Real entity controllers replacing
  hardcoded data, password reset flow, NorthCloud API cache, community export,
  data sovereignty controls and consent metadata
- **Sprint 3 (Production Hardening):** OWASP security review, WCAG 2.1 AA accessibility
  audit, media copyright controls, Playwright end-to-end test coverage for auth flows,
  form submissions, and content browsing

## Test Results

- **PHPUnit:** 287 tests, 685 assertions — all passing
- **Playwright:** 70 passing, 6 skipped (dev-only auth redirect tests)
- **composer audit:** zero vulnerabilities

## Governance Gate Status

| Gate | Description | Status | Evidence |
|------|-------------|--------|----------|
| 1. License Attribution | CC BY-NC-SA visible on OPD content | READY | Footer on all pages, dual LICENSE file (#194, #197) |
| 2. Media Copyright | copyright_status field enforced | READY | 7 entities, controller filtering (#195) |
| 3. Data Sovereignty | Consent fields, sovereignty page | READY | consent_public/consent_ai_training, /data-sovereignty, robots.txt (#196) |
| 4. Community Governance | No public data export | READY | CLI-only tools, no API endpoint (#175, #176) |
| 5. Security Review | OWASP top 10 audit | READY | Security headers, rate limiting, session hardening (#198) |

## Reference Documents

- **Sprint 2 checkpoint:** `docs/governance/sprint2-checkpoint.md`
- **Security checklist:** `docs/governance/security-checklist.md`
- **Epic:** [#201](https://github.com/waaseyaa/minoo/issues/201) — all 22 items checked off
- **Governance gates:** [#202](https://github.com/waaseyaa/minoo/issues/202) — all 5 gates ready
- **Implementation plans:** `docs/superpowers/plans/`

## Files Requiring Human Review

### Gate 1 — License Attribution
- `LICENSE` — dual MIT + CC BY-NC-SA 4.0
- `templates/base.html.twig` — footer attribution text

### Gate 2 — Media Copyright
- `src/Provider/*ServiceProvider.php` — copyright_status field definitions (7 providers)
- `src/Controller/{Teaching,Event,Group}Controller.php` — copyright filtering logic

### Gate 3 — Data Sovereignty
- `templates/data-sovereignty.html.twig` — consent controls copy
- `public/robots.txt` — blocked paths
- `docs/specs/entity-model.md` — consent field scope documentation

### Gate 4 — Community Governance
- `bin/export-communities` — CLI-only, no public API
- `bin/sync-communities` — read-only NorthCloud sync

### Gate 5 — Security
- `docs/governance/security-checklist.md` — OWASP audit document
- `src/Middleware/SecurityHeadersMiddleware.php` — security headers
- `src/Middleware/RateLimitMiddleware.php` — rate limiting
- `public/index.php` — session cookie configuration

## Human Signoff Required

Each gate requires a signed comment on [#202](https://github.com/waaseyaa/minoo/issues/202):

```
APPROVED: [gate name] — [date] — [notes]
```

All 5 signoffs must be recorded before production deployment is authorized.

## Production Deployment

After all signoffs are recorded:

1. Go to GitHub Actions -> "Deploy Production" workflow
2. Click "Run workflow"
3. Type `deploy-v1` in the confirmation field
4. Click "Run workflow"

Rollback: `dep rollback production`
