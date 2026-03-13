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

### Flash message support for form success feedback

- **Issue:** [#132](https://github.com/waaseyaa/minoo/issues/132)
- **PR:** [#207](https://github.com/waaseyaa/minoo/pull/207)
- **Merge commit:** `882de41`
- **Merged:** 2026-03-13 (squash merge into `release/v1`)
- **Status:** Complete

**Verification summary:**
- PHPUnit: 302 tests, 725 assertions — all passing
- Playwright: flash-messages.spec.ts (2 tests) — login flash visible + consumed on navigation
- Lint, Security Audit, Commercial Use Check: all green
- No regressions in auth, dashboard, or form flows

**What was built:**
- `FlashMessageService` — session-backed multi-message queue with type allowlist filtering
- `FlashTwigExtension` — auto-injects `flash_messages()` Twig function
- `Flash` static facade — `success()`, `error()`, `info()` + deprecated backward compat
- `flash-messages.html.twig` component with a11y roles (`alert` for errors, `status` for success/info)
- CSS flash styles using `--color-water-*` design tokens
- Controller integration: AuthController, ElderSupportController, ElderSupportWorkflowController, VolunteerController, VolunteerDashboardController, CoordinatorDashboardController
- 19 new tests across PHPUnit (FlashMessageServiceTest, FlashTwigExtensionTest, FlashTest) and Playwright

### Location bar error state when geolocation fails

- **Issue:** [#133](https://github.com/waaseyaa/minoo/issues/133)
- **PR:** [#208](https://github.com/waaseyaa/minoo/pull/208)
- **Merge commit:** `9267410`
- **Merged:** 2026-03-13 (squash merge into `release/v1`)
- **Status:** Complete

**Verification summary:**
- PHPUnit: 302 tests, 725 assertions — all passing
- Playwright: location-bar.spec.ts (7 tests) — all pass
- No regressions in header, navigation, cookie handling, or any other page

**What was fixed:**
- Default location bar text changed from "Detecting location…" to "Set your location" (progressive enhancement)
- Geolocation error/timeout now falls back to "Set your location" instead of hanging indefinitely
- "Detecting location…" shown only during active geolocation request
- Added `<noscript>` fallback for no-JS users
- 3 new Playwright tests (error state, cookie render, dropdown click)

**Housekeeping:**
- Closed #139, #140, #141 (v0.14 copy issues) — all already implemented
