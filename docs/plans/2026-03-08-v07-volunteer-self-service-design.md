# v0.7 — Volunteer Self-Service & Request Lifecycle

**Date:** 2026-03-08
**Status:** Approved

## Goal

Give volunteers control over their own profile and availability, add request cancellation for coordinators, surface completed request history, and add a public "How It Works" page. Backed by Playwright smoke tests.

## Issues

### 1. Volunteer Profile Edit (feature, P1)

Volunteers can update their contact info, availability text, skills, and max_travel_km from their dashboard.

- New routes: GET/POST `/dashboard/volunteer/edit`
- Controller: `VolunteerDashboardController::editForm()` + `submitEdit()`
- Template: `templates/dashboard/volunteer-edit.html.twig`
- Uses existing Volunteer entity fields — no schema changes
- Link from volunteer dashboard ("Edit Profile" button)

### 2. Volunteer Availability Toggle (feature, P1)

Volunteers can mark themselves "unavailable" from their dashboard.

- Toggles `status` field between `active` and `unavailable`
- POST route: `/dashboard/volunteer/toggle-availability`
- Controller: `VolunteerDashboardController::toggleAvailability()`
- Coordinator dashboard filters out unavailable volunteers from assignment dropdown
- Visual indicator on volunteer dashboard showing current status

### 3. Request Cancellation (feature, P1)

Coordinators can cancel any open or assigned request with a reason.

- Adds `cancelled` as a valid status in the workflow
- Adds `cancelled_reason` field to ElderSupportRequest entity (additive, text_long)
- POST route: `/elders/request/{esrid}/cancel`
- Controller: `ElderSupportWorkflowController::cancelRequest()`
- Cancelled requests shown in separate "Cancelled" section on coordinator dashboard
- Only coordinators can cancel (access policy enforced)

### 4. Request History in Dashboards (enhancement, P2)

Both dashboards show confirmed and cancelled requests in a "History" section.

- Volunteer dashboard: shows confirmed requests with completion date
- Coordinator dashboard: shows confirmed + cancelled with reason
- Collapsible or separate section to avoid clutter

### 5. Elder Support "How It Works" Page (feature, P2)

Public template at `/elders` explaining the program.

- Template: `templates/elders/how-it-works.html.twig`
- Content: what the program does, who it's for, how to request help, how to volunteer, what happens after
- Links from nav "Elder Support" entry and from both forms
- Nav link updated to point to `/elders` instead of `/elders/request`

### 6. Playwright Smoke Tests (tests, P1)

Automated browser tests covering critical user flows.

- Navigation links resolve correctly
- Elder request form loads and submits
- Volunteer signup form loads and submits
- Login and register pages load
- Resource directory loads with filters
- Communities page loads with filters
- Dashboard pages require auth

## Schema Changes

One additive field:

```
elder_support_request.cancelled_reason  (text_long, nullable)
```

No breaking changes. Existing data unaffected.

## Files Touched (estimated)

| Area | Files |
|------|-------|
| Controllers | `VolunteerDashboardController.php`, `ElderSupportWorkflowController.php` |
| Providers | `ElderSupportServiceProvider.php` (new routes + field) |
| Templates | `dashboard/volunteer.html.twig`, `dashboard/volunteer-edit.html.twig` (new), `dashboard/coordinator.html.twig`, `elders/how-it-works.html.twig` (new), `base.html.twig` (nav link) |
| CSS | `public/css/minoo.css` (form styles, status badges) |
| Tests | New unit tests for each controller action, Playwright smoke tests |

## Out of Scope

- Admin CRUD for ResourcePerson (v0.8)
- Email/SMS notifications (v0.8+)
- Bulk operations (v0.8+)
- Volunteer-to-elder messaging (v0.8+)
