# v0.6 Design: Auth Activation, Elder Support Assignment, Community Taxonomy

## Milestone Summary

**v0.6 -- Auth Activation + Elder Support Assignment + Community Taxonomy**

Three pillars deliver Minoo's transition from anonymous content platform to authenticated community operating system.

| Pillar | Deliverables |
|--------|-------------|
| Auth Activation | Login, logout, volunteer self-registration, roles, session-based auth |
| Elder Support v0.2 | Coordinator dashboard, volunteer dashboard, assignment workflow, status transitions |
| Community Taxonomy | `community` content entity, entity references, autocomplete, dashboard filtering |

### Out of Scope

- Email verification / password reset
- Volunteer self-select / claim workflow
- GIS maps or travel-time calculations
- NorthCloud community sync
- Admin user management UI (admins use CLI)
- Notifications (email, SMS)

---

## Design Decisions

Each decision was explored with 3-4 options and trade-offs before selection.

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Registration model | Hybrid -- volunteers self-register, coordinators admin-created | Low-friction onboarding + controlled coordinator access |
| Assignment workflow | Coordinator-assigned only | Coordinators hold real-world context (language, geography, trust) the system lacks |
| Dashboard paradigm | Integrated SSR with separate routes | Same design system, same emotional tone, simple architecture |
| Community data model | Content entity | Supports coordinator editing, GIS enrichment, NorthCloud sync, cross-domain references |
| Login flow | Email + password, immediate access | Assignment (not registration) is the trust boundary; no SMTP dependency |

---

## 1. Auth Activation

### Framework Infrastructure (already built)

Waaseyaa provides everything needed:

- `User` entity with password hashing, roles, permissions, status
- `AccountInterface` with `id()`, `hasPermission()`, `getRoles()`, `isAuthenticated()`
- `AnonymousUser` for unauthenticated visitors
- `SessionMiddleware` resolving `waaseyaa_uid` from `$_SESSION`
- `BearerAuthMiddleware` for API access (JWT + API keys)
- Route-level access control (`_permission`, `_role`, `_public`)
- Entity-level access policies
- `DevAdminAccount` for `php -S` dev server

### Application Layer (Minoo builds)

#### AuthController Routes

| Route | Method | Access | Purpose |
|-------|--------|--------|---------|
| `GET /login` | `loginForm` | public | Login form |
| `POST /login` | `submitLogin` | public | Validate credentials, create session |
| `GET /logout` | `logout` | authenticated | Destroy session, redirect to `/` |
| `GET /register` | `registerForm` | public | Volunteer registration form |
| `POST /register` | `submitRegister` | public | Create user with `volunteer` role, login, redirect to dashboard |

#### Session Management

- Login: `$_SESSION['waaseyaa_uid'] = $user->id()`
- Logout: `session_destroy()`, redirect to `/`
- Framework `SessionMiddleware` resolves User entity on every subsequent request

#### Roles and Permissions

| Role | Created by | Permissions |
|------|------------|-------------|
| `volunteer` | Self-registration at `/register` | `view own assignments`, `update assignment status` |
| `elder_coordinator` | Admin via CLI | `view all requests`, `view volunteer pool`, `assign volunteers`, `confirm completions` |
| `admin` | Existing | `administer content`, `administer users` |

#### User Entity Extensions

Minoo adds fields to the framework's User entity:

- `phone` (string, optional) -- contact number
- `community` (entity_reference to `community`, optional) -- home community

#### Registration Form Fields

- Name (required)
- Email (required, unique)
- Password (required, min 8 chars)
- Phone (optional)
- Community (optional, autocomplete)

#### Login Form Fields

- Email (required)
- Password (required)

---

## 2. Community Content Entity

### Entity Definition

**Type:** `community` (content entity)
**Provider:** `CommunityServiceProvider`
**Access:** `CommunityAccessPolicy`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `cid` | auto-increment | PK | Entity key |
| `uuid` | uuid | yes | |
| `name` | string | yes | Label field |
| `community_type` | string | yes | Enum: `first_nation`, `town`, `region` |
| `latitude` | float | no | Future GIS |
| `longitude` | float | no | Future GIS |
| `population` | integer | no | |
| `external_ids` | json | no | `{"inac": "...", "statscan": "..."}` |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

### Access Policy

- View: public (anyone can see community names)
- Create / Update / Delete: `administer content` only

### Autocomplete API

`GET /api/communities/autocomplete?q={query}` returns JSON:

```json
[{"id": 1, "name": "Sagamok Anishnawbek"}, ...]
```

Filters by name prefix, case-insensitive, limit 10 results.

### Seed Data

Initial North Shore communities:

| Name | Type |
|------|------|
| Sagamok Anishnawbek | first_nation |
| Serpent River First Nation | first_nation |
| Mississauga First Nation | first_nation |
| Thessalon First Nation | first_nation |
| Garden River First Nation | first_nation |
| Batchewana First Nation | first_nation |
| Elliot Lake | town |
| Blind River | town |
| Thessalon | town |
| North Shore | region |

### Cross-Domain References

Every domain will eventually reference communities via `entity_reference`:

- `ElderSupportRequest.community` (migrate from free-text)
- `Volunteer.community` (new field)
- `User.community` (new field)
- Future: People, Teachings, Language, Events, Groups

All community references are single-valued and optional.

### Migration from Free-Text

`ElderSupportRequest.community` changes from `string` to `entity_reference`. Existing free-text values are preserved until manually reconciled by coordinators.

---

## 3. Elder Support v0.2 -- Dashboards and Assignment

### Status Model

Five-step workflow:

```
open --> assigned --> in_progress --> completed --> confirmed
```

| Status | Meaning | Set by |
|--------|---------|--------|
| `open` | Request submitted, no volunteer | System (on creation) |
| `assigned` | Coordinator assigned a volunteer | Coordinator |
| `in_progress` | Volunteer started work | Volunteer |
| `completed` | Volunteer finished work | Volunteer |
| `confirmed` | Coordinator verified completion | Coordinator |

### Assignment Data Model

`ElderSupportRequest` gains two fields:

- `assigned_volunteer` (entity_reference to `volunteer`, nullable)
- `assigned_at` (timestamp, nullable)

### Status Transition Routes

| Route | Method | Access | Transition |
|-------|--------|--------|------------|
| `POST /elders/request/{esrid}/assign` | `assignVolunteer` | `elder_coordinator` | `open` to `assigned` |
| `POST /elders/request/{esrid}/start` | `startRequest` | `volunteer` (own assignment) | `assigned` to `in_progress` |
| `POST /elders/request/{esrid}/complete` | `completeRequest` | `volunteer` (own assignment) | `in_progress` to `completed` |
| `POST /elders/request/{esrid}/confirm` | `confirmRequest` | `elder_coordinator` | `completed` to `confirmed` |
| `POST /elders/request/{esrid}/reassign` | `reassignVolunteer` | `elder_coordinator` | any to `assigned` (new volunteer) |

### Volunteer Dashboard

**Route:** `GET /dashboard/volunteer`
**Controller:** `VolunteerDashboardController`
**Access:** `_role: volunteer`

Displays:

- **My Assignments** -- requests assigned to the logged-in volunteer, grouped by status
- Each card shows: elder name, request type, community, status, date
- Actions per status:
  - `assigned`: "Mark In Progress" button
  - `in_progress`: "Mark Complete" button
  - `completed`: read-only, waiting for coordinator confirmation

### Coordinator Dashboard

**Route:** `GET /dashboard/coordinator`
**Controller:** `CoordinatorDashboardController`
**Access:** `_role: elder_coordinator`

Displays:

- **Open Requests** -- all requests with status `open`, newest first
- **Assigned Requests** -- all `assigned` or `in_progress`, grouped by volunteer
- **Completed (Pending Confirmation)** -- requests in `completed` status
- **Volunteer Pool** -- all active volunteers with name, phone, community, skills

Actions:

- On open request: "Assign" button with volunteer select dropdown
- On completed request: "Confirm" button
- On any assigned request: "Reassign" button

---

## 4. Templates

| Template | Purpose |
|----------|---------|
| `auth/login.html.twig` | Login form |
| `auth/register.html.twig` | Volunteer registration form |
| `dashboard/volunteer.html.twig` | Volunteer assignment view |
| `dashboard/coordinator.html.twig` | Coordinator management view |

All templates extend `base.html.twig` and use the existing CSS design system.

---

## 5. Navigation Changes

### base.html.twig Nav Updates

- **Unauthenticated:** existing nav + "Login" link
- **Volunteer:** existing nav + "My Dashboard" link + "Logout" link
- **Coordinator:** existing nav + "Dashboard" link + "Logout" link

### Post-Action Redirects

| Action | Redirect |
|--------|----------|
| Successful login (volunteer) | `/dashboard/volunteer` |
| Successful login (coordinator) | `/dashboard/coordinator` |
| Successful login (admin) | `/` |
| Successful registration | `/dashboard/volunteer` |
| Logout | `/` |

---

## 6. New Files Summary

### Entities

- `src/Entity/Community.php`

### Providers

- `src/Provider/CommunityServiceProvider.php` (entity type + autocomplete route)
- `src/Provider/AuthServiceProvider.php` (auth routes)
- `src/Provider/DashboardServiceProvider.php` (dashboard routes)

### Access Policies

- `src/Access/CommunityAccessPolicy.php`

### Controllers

- `src/Controller/AuthController.php`
- `src/Controller/VolunteerDashboardController.php`
- `src/Controller/CoordinatorDashboardController.php`

### Seeders

- `src/Seed/CommunitySeeder.php`

### Templates

- `templates/auth/login.html.twig`
- `templates/auth/register.html.twig`
- `templates/dashboard/volunteer.html.twig`
- `templates/dashboard/coordinator.html.twig`

### Modified Files

- `src/Entity/ElderSupportRequest.php` -- add `assigned_volunteer`, `assigned_at` fields
- `src/Provider/ElderSupportServiceProvider.php` -- add status transition routes, update field definitions
- `templates/base.html.twig` -- conditional nav based on auth state
- `public/css/minoo.css` -- dashboard component styles
- `composer.json` -- register new providers

---

## 7. Future Evolution (Not in v0.6)

| Feature | Target |
|---------|--------|
| Email verification | v0.7 |
| Password reset | v0.7 |
| Volunteer self-select / claim | v0.7 |
| Admin user management UI | v0.7 |
| Notifications (email/SMS) | v0.7 |
| GIS maps | v0.8 |
| Travel-time matching | v0.8 |
| NorthCloud community sync | v0.8 |
| Volunteer profiles with availability calendars | v0.8 |
