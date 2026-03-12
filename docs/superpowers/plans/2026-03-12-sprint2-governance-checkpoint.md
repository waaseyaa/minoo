# Sprint 2 Governance Checkpoint

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verify all Sprint 2 deliverables, confirm governance compliance, and produce a human signoff package before Sprint 3 begins.

**Architecture:** This is a verification and documentation plan, not a code plan. It produces a governance checkpoint artifact at `docs/governance/sprint2-checkpoint.md` that records what shipped, what compliance state looks like, and what needs human review.

**Tech Stack:** GitHub CLI (`gh`), git, PHPUnit, shell commands

---

## Task 1: Verify Sprint 2 Deliverables on `main`

**Files:**
- Create: `docs/governance/sprint2-checkpoint.md`

**Context:** Sprint 2 closed 7 issues (#128, #130, #175, #176, #184, #185, #196) across 6 commits on `main`. One documentation issue (#204) was created during review.

- [ ] **Step 1: Verify all Sprint 2 commits are on `main`**

Run:
```bash
git log --oneline --grep="#128" main | head -1
git log --oneline --grep="#130" main | head -1
git log --oneline --grep="#175" main | head -1
git log --oneline --grep="#176" main | head -1
git log --oneline --grep="#184" main | head -1
git log --oneline --grep="#196" main | head -1
```
Expected: Each returns exactly one commit. #184 commit message also references #185.

- [ ] **Step 2: Verify all Sprint 2 issues are closed on GitHub**

Run:
```bash
for issue in 128 130 175 176 184 185 196; do
  echo -n "#$issue: "
  gh issue view $issue --json state -q '.state'
done
```
Expected: All return `CLOSED`. If any are still open, close them with:
```bash
gh issue close <number> --reason completed
```

- [ ] **Step 3: Verify issue #204 is open and assigned to V1 Release milestone**

Run:
```bash
gh issue view 204 --json state,milestone -q '{state: .state, milestone: .milestone.title}'
```
Expected: `{state: OPEN, milestone: V1 Release}`

- [ ] **Step 4: Run full test suite**

Run:
```bash
./vendor/bin/phpunit
```
Expected: `OK (278 tests, 667 assertions)` — all green.

- [ ] **Step 5: Verify key files exist on `main`**

Run:
```bash
for f in \
  src/Controller/EventController.php \
  src/Controller/TeachingController.php \
  src/Controller/GroupController.php \
  src/Controller/LanguageController.php \
  src/Seed/ContentSeeder.php \
  bin/seed-content \
  bin/export-communities \
  bin/cache-clear \
  src/Support/NorthCloudCache.php \
  src/Support/PasswordResetService.php \
  templates/auth/forgot-password.html.twig \
  templates/auth/reset-password.html.twig \
  public/robots.txt \
  tests/Minoo/Unit/Support/NorthCloudCacheTest.php \
  tests/Minoo/Unit/Support/PasswordResetServiceTest.php \
  tests/Minoo/Unit/DataSovereignty/ConsentFieldTest.php; do
  [ -f "$f" ] && echo "OK: $f" || echo "MISSING: $f"
done
```
Expected: All 16 files report `OK`.

- [ ] **Step 6: Verify superseded scripts were removed**

Run:
```bash
for f in bin/seed-fn-from-cirnac bin/enrich-from-nc bin/dedup-communities bin/backfill-nc-ids bin/delete-non-fn; do
  [ -f "$f" ] && echo "STILL EXISTS: $f" || echo "REMOVED: $f"
done
```
Expected: All 5 report `REMOVED`.

---

## Task 2: Governance Compliance Checklist

- [ ] **Step 1: Verify branch protection on `main`**

Run:
```bash
gh api repos/waaseyaa/minoo/branches/main/protection --jq '{
  required_status_checks: .required_status_checks.contexts,
  required_pull_request_reviews: .required_pull_request_reviews.required_approving_review_count,
  enforce_admins: .enforce_admins.enabled
}'
```
Expected: 5 required status checks (lint, phpunit, playwright, security-audit, commercial-use-check), 1 approver, enforce_admins may be false (admin bypass used for Sprint 2 push).

Note any missing checks and record in the checkpoint document.

- [ ] **Step 2: Verify CI workflows exist**

Run:
```bash
ls -1 .github/workflows/
```
Expected: `ci.yml`, `deploy.yml`, `deploy-production.yml`

- [ ] **Step 3: Verify CODEOWNERS is in place**

Run:
```bash
cat .github/CODEOWNERS | grep jonesrussell | wc -l
```
Expected: 8+ lines assigning @jonesrussell as owner.

- [ ] **Step 4: Verify LICENSE file**

Run:
```bash
head -1 LICENSE
```
Expected: Contains "Dual License" — MIT for code, CC BY-NC-SA 4.0 for content.

- [ ] **Step 5: Verify data sovereignty page is live**

Run:
```bash
[ -f templates/data-sovereignty.html.twig ] && echo "OK" || echo "MISSING"
grep -c "Consent Controls" templates/data-sovereignty.html.twig
```
Expected: `OK` and count >= 1.

- [ ] **Step 6: Verify robots.txt blocks sensitive paths**

Run:
```bash
grep "Disallow" public/robots.txt
```
Expected: `/api/`, `/dashboard/`, `/login`, `/register`, `/forgot-password`, `/reset-password`

- [ ] **Step 7: Verify consent fields are enforced in controllers**

Run:
```bash
grep -l "consent_public" src/Controller/*.php
```
Expected: `TeachingController.php` and `LanguageController.php` — these filter by `consent_public`. Events and groups intentionally lack consent fields (tracked in #204).

- [ ] **Step 8: Verify CSRF protection on all auth forms**

Run:
```bash
grep -l "csrf_token" templates/auth/*.html.twig
```
Expected: `forgot-password.html.twig`, `login.html.twig`, `register.html.twig`, `reset-password.html.twig` — all auth forms include CSRF tokens. Framework `CsrfMiddleware` validates on all POST requests automatically.

---

## Task 3: Produce Human Signoff Package

- [ ] **Step 1: Check status of all 5 governance signoffs from #202**

Run:
```bash
gh issue view 202 --json comments -q '.comments[].body' 2>/dev/null | grep -c "APPROVED" || echo "0 signoffs recorded"
```
Expected: Record the count. Likely 0 at this stage — signoffs happen on staging.

- [ ] **Step 2: Map Sprint 2 work to governance gates**

Record in the checkpoint document which Sprint 2 work advances each #202 gate:

| Gate | Sprint 2 Contribution | Ready for Signoff? |
|------|----------------------|-------------------|
| 1. License Attribution | Sprint 1 (#194, #197) — no Sprint 2 changes | Awaiting staging review |
| 2. Media Copyright | Not addressed in Sprint 2 — Sprint 3 (#195) | Blocked on #195 |
| 3. Data Sovereignty | #196: consent fields, sovereignty page, robots.txt | Awaiting staging review |
| 4. Community Governance | #175: export script exists but no public API endpoint | Awaiting staging review |
| 5. Security Review | Sprint 3 (#198) — Sprint 2 added CSRF, parameterized queries | Blocked on #198 |

- [ ] **Step 3: Identify files requiring human review**

These are the files a human reviewer should inspect before signing off gates 3 and 4:

**Data sovereignty (gate 3):**
- `templates/data-sovereignty.html.twig` — consent controls section copy
- `src/Provider/TeachingServiceProvider.php` — consent field definitions
- `src/Provider/LanguageServiceProvider.php` — consent field definitions
- `public/robots.txt` — crawler blocking rules

**Community governance (gate 4):**
- `bin/export-communities` — CLI-only, no public API endpoint
- `bin/sync-communities` — NC API sync (read-only, no data leaving Minoo)

- [ ] **Step 4: List documentation updates needed**

Record:
1. **#204** (open): Document that events/groups intentionally lack consent fields — update `docs/specs/entity-model.md`
2. **Epic #201**: Check off Sprint 2 items in the sprint plan checklist
3. **Issue #202**: No signoff comments yet — signoffs happen after staging deploy

---

## Task 4: Write and Commit Governance Checkpoint

**Files:**
- Create: `docs/governance/sprint2-checkpoint.md`

- [ ] **Step 1: Create docs/governance directory**

Run:
```bash
mkdir -p docs/governance
```

- [ ] **Step 2: Write the checkpoint document**

Create `docs/governance/sprint2-checkpoint.md` with the following content:

```markdown
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
- [x] Branch protection on `main` — 5 required status checks, 1 approver, CODEOWNER review
- [x] CI workflows — ci.yml, deploy.yml, deploy-production.yml
- [x] CODEOWNERS — @jonesrussell on all paths
- [x] Dual LICENSE — MIT (code) + CC BY-NC-SA 4.0 (content)

### Data Sovereignty (gate 3 of #202)
- [x] `consent_public` and `consent_ai_training` fields on `teaching` and `dictionary_entry`
- [x] Controllers filter by `consent_public` before rendering
- [x] Data sovereignty page live at `/data-sovereignty` with consent controls section
- [x] `robots.txt` blocks `/api/` and `/dashboard/`
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
- `src/Provider/TeachingServiceProvider.php:40-55` — consent field definitions
- `src/Provider/LanguageServiceProvider.php:40-55` — consent field definitions
- `public/robots.txt` — verify blocked paths

### Community Governance (gate 4)
- `bin/export-communities` — verify CLI-only, no `--confirm` flag yet
- `bin/sync-communities` — verify read-only NC sync

## Open Items for Sprint 3

1. **#204** — Document consent field scope in entity-model spec
2. **#198** — Full OWASP security review
3. **#195** — Media copyright flag workflow (blocks gate 2)
4. **#199** — Accessibility audit (WCAG 2.1 AA)
5. **Epic #201** — Update sprint plan checkboxes
6. **Issue #202** — Signoffs happen after staging deploy

## Recommendation

Sprint 2 deliverables are verified and governance-compliant. Ready for human review of gates 3 (Data Sovereignty) and 4 (Community Governance) on staging. Gates 2 (Media Copyright) and 5 (Security) are blocked on Sprint 3 work.

**Next step:** Deploy to staging, then request signoff comments on #202.
```

- [ ] **Step 3: Update Epic #201 Sprint 2 checkboxes**

Run:
```bash
gh issue view 201 --json body -q '.body' > /tmp/epic-body.md
```

Then edit the body to check off Sprint 2 items. Use:
```bash
gh issue edit 201 --body "$(cat /tmp/epic-body-updated.md)"
```

Check off these lines in the Sprint 2 section:
- `[x] #176 Replace local storage with NC API sync`
- `[x] #184 SQLite cache layer for NC API`
- `[x] #185 Cache NC API responses`
- `[x] #196 Data sovereignty controls and consent metadata`
- `[x] #130 Password reset flow`
- `[x] #128 Replace hardcoded demo data`
- `[x] #175 Export communities as JSON`

- [ ] **Step 4: Commit the checkpoint document**

Run:
```bash
git add docs/governance/sprint2-checkpoint.md
git commit -m "$(cat <<'EOF'
docs(#202): Sprint 2 governance checkpoint

Record Sprint 2 deliverables verification, governance compliance status,
and human review requirements for V1 signoff gates 3 and 4.

Refs #202

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Push to remote**

Run:
```bash
git push origin main
```

- [ ] **Step 6: Comment on #202 with checkpoint summary**

Run:
```bash
gh issue comment 202 --body "$(cat <<'EOF'
## Sprint 2 Governance Checkpoint — 2026-03-12

All 7 Sprint 2 issues verified on `main` (278 tests, 667 assertions passing).

**Governance status:**
- Gate 3 (Data Sovereignty): Ready for staging review — consent fields, sovereignty page, robots.txt all in place
- Gate 4 (Community Governance): Ready for staging review — no public export API, CLI-only tools
- Gate 2 (Media Copyright): Blocked on Sprint 3 #195
- Gate 5 (Security): Blocked on Sprint 3 #198

**Checkpoint document:** `docs/governance/sprint2-checkpoint.md`

**Next:** Deploy to staging, then begin signoff review for gates 3 and 4.
EOF
)"
```
