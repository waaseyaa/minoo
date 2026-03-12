# Sprint 2 Governance Checkpoint

**Date:** 2026-03-12
**Sprint:** 2 of 3 (Core Features & Data Integrity)
**Milestone:** V1 Release (#19, target 2026-04-22)

## Deliverables Verified

| Issue | Title | Commit | Status |
|-------|-------|--------|--------|
| #128 | Replace hardcoded demo data with real controllers | `e7b2692` | Merged to main |
| #130 | Password reset flow (on-screen link) | `784a4e5` | Merged to main |
| #175 | Export communities as JSON for NC import | `4dc094c` | Merged to main |
| #176 | Replace local storage with NC API sync | `8a952b7` | Merged to main |
| #184 | SQLite cache layer for NC API | `1521ccd` | Merged to main |
| #185 | Cache NC API responses (resolved by #184) | `1521ccd` | Closed |
| #196 | Data sovereignty controls and consent metadata | `e46d662` | Merged to main |
| #204 | Doc: consent field scope (created during review) | — | Open |

**Tests:** 278 passing, 667 assertions
**Net change:** +1,717 / -1,251 lines across 48 files

## Governance Compliance

### Infrastructure

- [x] Branch protection on `main` — 5 required status checks (Lint, PHPUnit, Playwright, Security Audit, Commercial Use Check), 1 approver, CODEOWNER review
- [x] CI workflows — ci.yml, deploy.yml, deploy-production.yml
- [x] CODEOWNERS — @jonesrussell on all paths (10 rules)
- [x] Dual LICENSE — MIT (code) + CC BY-NC-SA 4.0 (content)

### Data Sovereignty (gate 3 of #202)

- [x] `consent_public` and `consent_ai_training` fields on `teaching` and `dictionary_entry`
- [x] Controllers filter by `consent_public` before rendering
- [x] Data sovereignty page live at `/data-sovereignty` with consent controls section
- [x] `robots.txt` blocks `/api/`, `/dashboard/`, `/login`, `/register`, `/forgot-password`, `/reset-password`
- [x] Events and groups intentionally lack consent fields (documented in #204)
- [x] CSRF middleware protects all POST routes (framework-level)

### Community Governance (gate 4 of #202)

- [x] No public data export API endpoint
- [x] `bin/export-communities` is CLI-only (server access required)
- [x] NC sync is read-only (communities flow in, not out)

### Security (partial, gate 5 deferred to Sprint 3 #198)

- [x] CSRF on all auth forms (framework CsrfMiddleware)
- [x] Parameterized queries throughout (PDO prepared statements)
- [x] Password reset tokens: 64-char hex, 1-hour expiry, single-use
- [x] Session-based auth (no tokens in URLs except reset flow)

## Human Review Required

The following files should be reviewed by PM/Project Lead before signing off gates 3 and 4 on issue #202:

### Data Sovereignty (gate 3)

- `templates/data-sovereignty.html.twig` — verify consent controls copy
- `src/Provider/TeachingServiceProvider.php` — consent field definitions
- `src/Provider/LanguageServiceProvider.php` — consent field definitions
- `public/robots.txt` — verify blocked paths

### Community Governance (gate 4)

- `bin/export-communities` — verify CLI-only, no public API endpoint
- `bin/sync-communities` — verify read-only NC sync

## Gate Readiness Summary

| Gate | Sprint 2 Contribution | Ready for Signoff? |
|------|----------------------|-------------------|
| 1. License Attribution | Sprint 1 (#194, #197) — no Sprint 2 changes | Awaiting staging review |
| 2. Media Copyright | Not addressed in Sprint 2 — Sprint 3 (#195) | Blocked on #195 |
| 3. Data Sovereignty | #196: consent fields, sovereignty page, robots.txt | Awaiting staging review |
| 4. Community Governance | #175: export script exists but no public API endpoint | Awaiting staging review |
| 5. Security Review | Sprint 3 (#198) — Sprint 2 added CSRF, parameterized queries | Blocked on #198 |

## Open Items for Sprint 3

1. **#204** — Document consent field scope in entity-model spec
2. **#198** — Full OWASP security review
3. **#195** — Media copyright flag workflow (blocks gate 2)
4. **#199** — Accessibility audit (WCAG 2.1 AA)
5. **#136, #137, #138** — Playwright e2e tests
6. **Epic #201** — Update sprint plan checkboxes
7. **Issue #202** — Signoffs happen after staging deploy

## Recommendation

Sprint 2 deliverables are verified and governance-compliant. Ready for human review of gates 3 (Data Sovereignty) and 4 (Community Governance) on staging. Gates 2 (Media Copyright) and 5 (Security) are blocked on Sprint 3 work.

**Next step:** Deploy to staging, then request signoff comments on #202.
