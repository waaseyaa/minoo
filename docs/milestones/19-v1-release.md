# Milestone #19 — V1 Release

**Target:** 2026-04-22
**Branch:** `release/v1`

## Sprint 1 Items

### Account Home dashboard for non-volunteer users

- **Issue:** [#205](https://github.com/waaseyaa/minoo/issues/205)
- **PR:** [#206](https://github.com/waaseyaa/minoo/pull/206)
- **Merge commit:** `4a05b91`
- **Merged:** 2026-03-12 (squash merge into `release/v1`)
- **Status:** Complete

**Verification summary:**
- PHPUnit: 289 tests, 580 assertions — all passing
- Playwright: 2 passed, 1 skipped (unauthenticated redirect skipped in dev)
- Lint, Security Audit, Commercial Use Check: all green
- RBAC audit: no regressions to volunteer/coordinator routes

**CI guardrails:**
- All 5 CI gates required: PHPUnit, Lint, Playwright, Security Audit, Commercial Use Check
- Branch protection on `release/v1` enforces CODEOWNER review + all checks

**Notes:**
- Playwright rate-limit mitigation: `bin/seed-test-user` now creates `rate_limits` table if missing and clears it before seeding, preventing CI flakiness from accumulated login attempts across test files
- Tests consolidated to 2 login sessions (1 member, 1 volunteer) to stay within 5-per-300s rate limit
