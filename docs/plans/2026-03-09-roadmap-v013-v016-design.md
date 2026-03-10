# Minoo Roadmap Design: v0.13 – v0.16

**Date:** 2026-03-09
**Scope:** Multi-repo milestone plan across `waaseyaa/minoo`, `waaseyaa/framework`, and `jonesrussell/north-cloud`
**Status:** Approved for implementation planning

---

## Originating Business Requirement

Laura asked: *"If I wanted to send an introduction email from our company to the 634 First Nations across Canada, what would be the best way?"*

The answer is to build it properly: assemble a complete, structured dataset of all 634 First Nations first, then build a coordinator-friendly mass emailer on top of it. This is the direct origin of:

- **v0.14** — SendGrid mass emailer (the tool Laura needs)
- **v0.14.5-NC** — NorthCloud structured metadata API (the data source)
- **v0.14.5** — Minoo dataset sync (the hydration pipeline)

Without the dataset, any emailer is only as good as whatever contacts are entered by hand. Without the emailer, the dataset has no activation path. These three milestones are a single end-to-end capability delivered in sequence.

---

## Current State (v0.13 — Elder Support)

Elder Support is substantially built. The following are complete:

- `elder_support_request` and `volunteer` entities (9 fields each)
- Public request form and volunteer signup form
- Coordinator dashboard with geo-ranked volunteer assignment
- Volunteer dashboard with profile editing
- 6-state workflow machine: open → assigned → in_progress → completed → confirmed/cancelled
- `VolunteerRanker` domain service (proximity + skill match scoring)
- `ElderSupportAccessPolicy` (public submit, coordinator workflow, volunteer self-service)
- 13 PHPUnit tests + 2 Playwright specs

Three blockers remain, gated in sequence:

1. **#148** (framework) — `HttpKernel::dispatch()` parses all POST bodies as JSON, breaking HTML form submission
2. **#149** (Minoo) — Coordinator and volunteer dashboards return JSON 403 to unauthenticated users instead of redirecting to `/login`
3. **#150** (Minoo) — Volunteer signup creates a `volunteer` entity but does not create a linked user account

---

## Architecture Decisions

### Multi-repo issue ownership

Each repo owns its own issues. Minoo issues reference NorthCloud issues via cross-repo links.

| Repo | Milestone | Scope |
|------|-----------|-------|
| `waaseyaa/framework` | v0.13-FW | HTTP kernel bug fixes |
| `waaseyaa/minoo` | v0.13 | Elder Support completion (depends on v0.13-FW) |
| `waaseyaa/minoo` | v0.14 | SendGrid mass emailer |
| `jonesrussell/north-cloud` | v0.14.5-NC | Structured metadata API (communities, regions, categories, orgs) |
| `waaseyaa/minoo` | v0.14.5 | NorthCloud dataset sync (depends on v0.14.5-NC) |
| `waaseyaa/minoo` | v0.15 | Grandparent Program MVP (depends on v0.13 + v0.14 + v0.14.5) |
| `waaseyaa/minoo` | v0.16 | Multi-community rollout (depends on v0.15) |

### NorthCloud data access strategy

NorthCloud's existing public API (`/api/v1/search`, `/api/v1/sources/indigenous`, `/api/v1/cities`) exposes indexed content, not structured community or organizational metadata. Building dedicated structured endpoints in `jonesrussell/north-cloud` is the correct long-term approach. These endpoints will be public read-only (no auth required for reads), matching the pattern of existing public endpoints.

### SendGrid integration scope

Minoo has no background worker infrastructure. Email sends are triggered synchronously by coordinator action or via a CLI command suitable for cron. No daemon or queue worker is required at v0.14 scope.

### Elder profile vs. elder request

The existing `elder_support_request` entity captures a one-time service request. v0.15 introduces a persistent `elder_profile` entity — a reusable record for elders who participate in ongoing programs like Grandparent. These are complementary, not replacements.

---

## Milestone Sequencing

```
v0.13-FW ──┐
           ├──► v0.13 ──────────────────────────────────────┐
v0.14 ─────┘(parallel)                                      │
                                                             ▼
v0.14.5-NC ──► v0.14.5 ────────────────────────────────► v0.15 ──► v0.16
```

v0.14 and v0.14.5-NC can be worked in parallel with v0.13.

---

## Issue Breakdown

### `waaseyaa/framework` — Milestone: v0.13-FW

**Goal:** Fix HTTP kernel bug blocking all HTML form submission in production.

| Issue | Title | Acceptance Criteria |
|-------|-------|---------------------|
| FW-1 | `fix: Skip JSON decode for form-encoded POST bodies in HttpKernel::dispatch()` | When `Content-Type: application/x-www-form-urlencoded`, dispatch routes correctly without attempting `json_decode`; elder request form and volunteer signup POST successfully in production |

---

### `waaseyaa/minoo` — Milestone: v0.13

**Goal:** Complete Elder Support end-to-end once framework fix lands.

| Issue | Title | Acceptance Criteria |
|-------|-------|---------------------|
| #149 | `fix: Redirect unauthenticated dashboard users to /login` | Coordinator and volunteer dashboard routes return `302 /login` for unauthenticated requests; no JSON 403 response |
| #150 | `feat: Link volunteer signup to user account creation` | Submitting volunteer signup creates both a `volunteer` entity and a user account; volunteer can log in and reach their dashboard |

---

### `jonesrussell/north-cloud` — Milestone: v0.14.5-NC

**Goal:** Expose structured community and organizational metadata as public JSON endpoints for downstream consumers (Minoo and others).

| Issue | Title | Response Shape |
|-------|-------|---------------|
| NC-1 | `feat: GET /api/v1/communities` | `[{id, name, region_id, lat, lon, nation, slug}]` |
| NC-2 | `feat: GET /api/v1/regions` | `[{id, name, communities: [{id, name}]}]` |
| NC-3 | `feat: GET /api/v1/service-categories` | `[{id, name, slug, parent_id}]` |
| NC-4 | `feat: GET /api/v1/organizations` | `[{id, name, type, community_id, website, contact}]` |
| NC-5 | `docs: OpenAPI/markdown schema for all metadata endpoints` | Covers request params, response shapes, pagination |
| NC-6 | `chore: Auth strategy for metadata endpoints` | Decision: public read-only; all four endpoints accessible without JWT |

---

### `waaseyaa/minoo` — Milestone: v0.14

**Goal:** Build the coordinator-friendly mass emailer Laura needs to reach all 634 First Nations.

This milestone delivers: a SendGrid-backed email system where a coordinator can select a contact segment, pick a template, preview the message, and send — with full delivery tracking and unsubscribe compliance.

| Issue | Title | Notes |
|-------|-------|-------|
| M14-1 | `chore: Add SENDGRID_API_KEY to .env config and waaseyaa.php` | Config key + service registration |
| M14-2 | `feat: SendGrid PHP client service` | Wraps SendGrid v3 API; dynamic templates; per-send category tagging for analytics |
| M14-3 | `feat: email_job entity` | Fields: segment_id, template_id, subject, payload (JSON), scheduled_at, status (queued/sending/completed/failed), sent_count, bounce_count, created_at |
| M14-4 | `feat: Contact segment engine` | Dynamic query-based recipient lists: all volunteers, approved volunteers, elders, by community, pending onboarding, etc. Segments are defined in code, not stored |
| M14-5 | `feat: Coordinator email compose UI` | `/dashboard/email/compose` — pick segment → pick template → preview rendered output → send or schedule |
| M14-6 | `feat: SendGrid webhook receiver` | `POST /webhooks/sendgrid` — handles bounce, spam report, unsubscribe events; updates delivery log |
| M14-7 | `feat: Unsubscribe preference entity + suppression sync` | Per-contact, per-category unsubscribe flag; syncs to SendGrid suppression list on change |
| M14-8 | `feat: Email send log entity` | Per-message: job_id, recipient_email, sendgrid_message_id, status, delivered_at |
| M14-9 | `feat: Email analytics dashboard` | Coordinator view: send history per job, open rate, bounce rate, unsubscribe count |
| M14-10 | `feat: bin/waaseyaa mail:process-queue CLI command` | Processes queued email jobs; suitable for cron |
| M14-11 | `chore: DKIM/SPF documentation` | Setup notes for northcloud.one sending domain |

---

### `waaseyaa/minoo` — Milestone: v0.14.5

**Goal:** Automatically hydrate Minoo's dataset from NorthCloud's structured metadata endpoints, including all 634 First Nations, service categories, regions, and organizations.

Depends on: `jonesrussell/north-cloud` milestone v0.14.5-NC fully merged.

| Issue | Title | Notes |
|-------|-------|-------|
| M145-1 | `chore: Add NORTHCLOUD_API_URL to config` | Joins existing `NORTHCLOUD_SEARCH_URL` pattern in `waaseyaa.php` |
| M145-2 | `feat: NorthCloud metadata API client` | PHP HTTP client covering `/communities`, `/regions`, `/service-categories`, `/organizations`; configurable timeout + retry; references jonesrussell/north-cloud NC-1–NC-4 |
| M145-3 | `feat: Community import job` | Pulls `/api/v1/communities`, upserts community taxonomy terms; detects unchanged records and skips |
| M145-4 | `feat: Region mapping import job` | Pulls `/api/v1/regions`, builds region → community hierarchy in taxonomy |
| M145-5 | `feat: Service category import job` | Pulls `/api/v1/service-categories`, populates intake form option vocabulary (replaces hardcoded ride/groceries/chores/visit) |
| M145-6 | `feat: Organization import job` | Pulls `/api/v1/organizations`, creates or updates organization entities |
| M145-7 | `feat: Sync orchestrator` | Runs all 4 import jobs in sequence; aggregates per-job results into a summary |
| M145-8 | `feat: Sync audit log entity` | Fields: job_name, source_url, records_imported, records_updated, records_skipped, records_failed, ran_at, duration_ms |
| M145-9 | `feat: Coordinator sync UI` | `/dashboard/sync` — trigger manual sync, view last-run summary, link to full audit log entries |
| M145-10 | `feat: bin/waaseyaa sync:northcloud CLI command` | Runs full sync; exit code 1 on any job failure; suitable for deploy hooks or cron |

---

### `waaseyaa/minoo` — Milestone: v0.15

**Goal:** Grandparent Program MVP — structured elder profiles, volunteer onboarding with safety checks, formalized matching, and email notifications.

Depends on: v0.13 (auth works), v0.14 (emailer works), v0.14.5 (communities imported).

| Issue | Title | Notes |
|-------|-------|-------|
| M15-1 | `feat: elder_profile entity` | Persistent elder record: preferred_name, age_range, contact_method (phone/sms/email), consent_photo, consent_volunteer_visit, consent_messaging, support_categories (multi), emergency_contact, status (active/paused/archived), community_id, created_at |
| M15-2 | `feat: Volunteer onboarding workflow` | Steps: vulnerable_sector_check → orientation → references; each step has status (pending/submitted/approved/rejected); coordinator sees completion state per volunteer |
| M15-3 | `feat: Vulnerable sector check tracking` | Upload document (file reference), expiry_date, coordinator approval action; volunteers blocked from assignment until approved |
| M15-4 | `feat: Matching algorithm v1` | Formalizes `VolunteerRanker` — score = proximity (from imported communities) + skill overlap + availability match; coordinator sees ranked list with score breakdown |
| M15-5 | `feat: Match notification emails` | Coordinator emailed when new request arrives; assigned volunteer emailed on assignment (uses v0.14 emailer + dynamic templates) |
| M15-6 | `feat: Elder request confirmation email` | Transactional email sent to elder (or representative) on form submission |
| M15-7 | `feat: Volunteer onboarding reminder emails` | Scheduled nudge to volunteers who haven't completed all onboarding steps after 7 days |
| M15-8 | `feat: /grandparent public landing page` | Program description, elder intake CTA, volunteer signup CTA; extends base.html.twig |
| M15-9 | `feat: Coordinator reporting dashboard` | Match rate, active pairs, completed requests, response time — filterable by community |

---

### `waaseyaa/minoo` — Milestone: v0.16

**Goal:** Multi-community deployment — scope data and coordinator access by community, with community-specific messaging and analytics.

Depends on: v0.15.

| Issue | Title | Notes |
|-------|-------|-------|
| M16-1 | `feat: Community-scoped entity queries` | All elder/volunteer/request queries filtered by community_id; coordinators only see their community's data |
| M16-2 | `feat: Per-community coordinator RBAC` | `elder_coordinator` role scoped to a specific `community_id`; access policy enforces scope |
| M16-3 | `feat: Community-specific SendGrid templates` | Per-community template override (logo, name, language); falls back to default |
| M16-4 | `feat: Community contact segments` | Email segments filter volunteers and elders by community; coordinator only reaches their community |
| M16-5 | `feat: Community analytics dashboard` | Per-community: volunteer count, elder count, active requests, match rate, last sync date |
| M16-6 | `feat: Community admin UI` | Manage coordinators per community; assign/remove community coordinators; view community health summary |

---

## What This Delivers End-to-End

When v0.16 is complete, a coordinator at any First Nation community can:

1. Log in and see their community's Elder Support requests and volunteers
2. Import their community's data automatically from NorthCloud (communities, categories, organizations)
3. Reach out to any or all of the 634 First Nations via a coordinator-friendly email compose UI
4. Run the Grandparent Program with structured elder profiles, safety-checked volunteers, and geo-matched assignments
5. Track all outreach with delivery analytics, audit logs, and per-community reporting

This is the platform Laura's question pointed to.
