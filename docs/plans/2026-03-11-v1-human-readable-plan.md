# Minoo V1 Release Plan

**Target Date:** April 22, 2026 | **Sprints:** 3 x 2 weeks | **Status:** Draft

---

## Executive Summary

Minoo V1 is a stable, non-commercial release of an Indigenous knowledge platform serving northern Ontario First Nations communities. It delivers four core capabilities: Elder matching, searchable community registry, NorthCloud-backed community data, and leadership/band office information. V1 enforces CC BY-NC-SA compliance, data sovereignty controls, and passes OWASP and WCAG audits.

**26 existing issues + 7 new compliance/production items = 33 total backlog items.**
Triaged as: **18 Must, 9 Should, 6 Could.**

---

## 6-Week Timeline

```
Week 1-2  Sprint 1: Foundation & Compliance
          CI/CD pipeline, CC BY-NC-SA attribution, critical bug fixes
          (#134, #129, #127, #131, #171, NEW-1, NEW-4, NEW-7)

Week 3-4  Sprint 2: Core Features & Data Integrity
          NC migration, caching, data sovereignty, password reset, real content
          (#176, #184, #185, #175, #130, #128, NEW-3)

Week 5-6  Sprint 3: Production Hardening & Release
          Security review, accessibility audit, Playwright e2e, media copyright, release
          (NEW-5, NEW-6, NEW-2, #136, #137, #138)

Apr 22    V1 RELEASE → 2-week hotfix window begins
```

---

## Must Items (18)

| ID | Title | Sprint | Est |
|----|-------|--------|-----|
| NEW-7 | CI/CD pipeline | 1 | L |
| NEW-1 | CC BY-NC-SA attribution metadata | 1 | M |
| NEW-4 | No-commercial-use enforcement gate | 1 | S |
| #134 | Prevent duplicate volunteer signups | 1 | S |
| #129 | Fix community autocomplete JSON | 1 | S |
| #127 | Friendly 403/401 error pages | 1 | S |
| #131 | Volunteer edit form validation | 1 | S |
| #171 | Fix bin/sync-communities | 1 | S |
| #176 | Replace local storage with NC API sync | 2 | L |
| #184 | SQLite cache layer for NC API | 2 | M |
| #185 | Cache NC API responses | 2 | M |
| NEW-3 | Data sovereignty controls + consent fields | 2 | L |
| #130 | Password reset flow | 2 | M |
| #128 | Replace hardcoded demo data | 2 | M |
| #175 | Export communities as JSON | 2 | M |
| NEW-5 | Security review (OWASP top 10) | 3 | L |
| NEW-6 | Accessibility audit (WCAG 2.1 AA) | 3 | M |
| NEW-2 | Media copyright flag + approval | 3 | L |
| #136 | Playwright tests: auth flows | 3 | M |
| #137 | Playwright tests: form submissions | 3 | M |
| #138 | Playwright tests: content browsing | 3 | M |

---

## Team Composition

| Role | FTE | Responsibilities |
|------|-----|------------------|
| PM/PO | 1.0 | Triage, acceptance, compliance signoffs, community liaison |
| Backend Dev 1 | 1.0 | NC migration, data sovereignty, security review, media copyright |
| Backend Dev 2 | 1.0 | Cache layer, bug fixes, CLI tools, CI jobs |
| Frontend Dev | 1.0 | Error pages, form UX, password reset, accessibility, demo data replacement |
| QA | 1.0 | Playwright e2e, accessibility scan, regression, smoke testing |
| DevOps | 0.5 | CI/CD setup, deploy pipeline, monitoring, rollback plan |

**Assumptions:** 8 story points per dev per sprint. S=2, M=5, L=8. ~32 points capacity per sprint across 3 devs.

---

## Release Gates (Human Signoff Required)

These items require explicit human approval before V1 ships:

1. **License attribution verification** — PM confirms CC BY-NC-SA text visible on all OPD pages
2. **Media copyright review** — PM confirms all media entities have copyright_status set and non-approved items are hidden
3. **Data sovereignty signoff** — PM confirms consent fields enforced and data sovereignty page live
4. **Community governance review** — PM confirms no data export without governance approval documented
5. **Security review complete** — Backend lead signs off on OWASP checklist

---

## Framework Blockers

| Task | Effort | Why |
|------|--------|-----|
| Pin Waaseyaa framework to tested commit | S | Reproducible V1 builds |
| Framework CI green (PHP 8.4) | S | Minoo CI depends on framework checkout |

---

## Post-Release

- **Hotfix window:** 2 weeks (Apr 22 – May 6). Security/data bugs only.
- **Rollback:** `deployer rollback` for instant revert. All schema changes are additive (no destructive migrations).
- **90-day roadmap:** Copy polish → Leadership scraping → Grandparent Program planning
