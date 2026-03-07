# Elder Support MVP — Design

**Date:** 2026-03-07
**Scope:** Waaseyaa framework (HttpKernel) + Minoo (new Elder Support domain)
**Milestone:** Minoo v0.5

## Problem

Minoo has no system for coordinating Elder support requests or matching volunteers. Community members need a way to request help (rides, groceries, chores, visits) and volunteers need a way to sign up and indicate their skills.

## Solution

Add two new content entities (`elder_support_request`, `volunteer`), a taxonomy (`volunteer_skill`), public-facing form controllers, and SSR templates. This is the first Minoo feature to use POST form handling via controllers, which requires a small framework enhancement to pass `HttpRequest` to controller methods.

## Design

### Framework change: HttpRequest in controllers

Modify `dispatchAppController()` in `HttpKernel.php` to pass `HttpRequest` as the 4th argument to controller methods:

```php
$instance->{$method}($params, $query, $account, $httpRequest);
```

Controller method signature becomes:

```php
public function action(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
```

Update existing `PeopleController` to accept the new parameter.

### Entities

Two content entities in a single `elders` group:

| Entity | Type ID | PK | Keys | Group |
|--------|---------|-----|------|-------|
| `ElderSupportRequest` | `elder_support_request` | `esrid` (auto) | id->esrid, uuid->uuid, label->name | `elders` |
| `Volunteer` | `volunteer` | `vid` (auto) | id->vid, uuid->uuid, label->name | `elders` |

**ElderSupportRequest fields:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `phone` | string | yes | |
| `community` | string | no | |
| `type` | string | yes | ride, groceries, chores, visit |
| `notes` | text_long | no | |
| `status` | string | yes | Default "open". Values: open, assigned, completed |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

**Volunteer fields:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `phone` | string | yes | |
| `availability` | string | no | |
| `skills` | entity_reference | no | target_type: taxonomy_term, multi |
| `notes` | text_long | no | |
| `status` | string | yes | Default "active". Values: active, inactive |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

### Provider

Single `ElderSupportServiceProvider` registers both entity types in `register()` and all 6 routes in `routes()`. Added to `composer.json` extra.waaseyaa.providers.

### Access policy

Single `ElderSupportAccessPolicy` with `#[PolicyAttribute(entityType: ['elder_support_request', 'volunteer'])]`:

- **createAccess**: Always `allowed()` for both types (public form submission)
- **access (view)**: Allowed on any specific entity by ID (confirmation URL). No public listing — prevents enumeration.
- **access (update/delete)**: Admin only (`administer content` permission)

### Controllers

Both in `Minoo\Controller`, constructor receives `EntityTypeManager` and `Twig\Environment`.

**ElderSupportController:**

| Method | Action |
|--------|--------|
| `requestForm` | GET — render form template |
| `submitRequest` | POST — validate, create entity, redirect 302 to confirmation |
| `requestDetail` | GET — load entity by esrid, render confirmation |

**VolunteerController:**

| Method | Action |
|--------|--------|
| `signupForm` | GET — render form template |
| `submitSignup` | POST — validate, create entity, redirect 302 to confirmation |
| `signupDetail` | GET — load entity by vid, render confirmation |

POST handlers use `$request->request->get('field')` for form data. Return form with inline errors on validation failure. Redirect to confirmation page on success.

### Routes

All registered in `ElderSupportServiceProvider::routes()`, all `.allowAll()`:

| Name | Method | Path | Controller |
|------|--------|------|------------|
| `elders.request.form` | GET | `/elders/request` | `ElderSupportController::requestForm` |
| `elders.request.submit` | POST | `/elders/request` | `ElderSupportController::submitRequest` |
| `elders.request.detail` | GET | `/elders/request/{esrid}` | `ElderSupportController::requestDetail` |
| `elders.volunteer.form` | GET | `/elders/volunteer` | `VolunteerController::signupForm` |
| `elders.volunteer.submit` | POST | `/elders/volunteer` | `VolunteerController::submitSignup` |
| `elders.volunteer.detail` | GET | `/elders/volunteer/{vid}` | `VolunteerController::signupDetail` |

### Templates

All extend `base.html.twig`:

| Template | Purpose |
|----------|---------|
| `templates/elders/request.html.twig` | Form: name, phone, community, type (select), notes. Shows validation errors. |
| `templates/elders/request-confirmation.html.twig` | Confirmation with submitted request details |
| `templates/elders/volunteer.html.twig` | Form: name, phone, availability, skills (checkboxes), notes |
| `templates/elders/volunteer-confirmation.html.twig` | Confirmation with submitted volunteer details |

CSS additions in `minoo.css` components layer: `.form`, `.form__field`, `.form__label`, `.form__input`, `.form__select`, `.form__textarea`, `.form__error`, `.form__submit`. Badge color `.card__badge--elder`.

### Seeder

`VolunteerSkillSeeder` with static method returning vocabulary + 4 terms:

- Rides
- Groceries
- Chores
- Visits / Companionship

### Tests

**PHPUnit:**
- `ElderSupportRequestTest` — entity creation, defaults, field access
- `VolunteerTest` — entity creation, defaults, field access
- `ElderSupportAccessPolicyTest` — public create allowed, admin operations, view allowed
- `VolunteerSkillSeederTest` — correct vocabulary and 4 terms

**Playwright:**
- Submit elder support request form, verify confirmation page
- Submit volunteer signup form, verify confirmation page
- Verify SSR pages load with correct titles
- Verify admin SPA auto-detects both entities via JSON:API schema

## Files changed

### Framework (waaseyaa)

| File | Change |
|------|--------|
| `packages/foundation/src/Kernel/HttpKernel.php` | Pass `$httpRequest` as 4th arg in `dispatchAppController()` |

### Minoo

| Action | File |
|--------|------|
| Modify | `src/Controller/PeopleController.php` — add `HttpRequest` param |
| Modify | `composer.json` — add `ElderSupportServiceProvider` |
| Modify | `public/css/minoo.css` — add form component styles |
| Create | `src/Entity/ElderSupportRequest.php` |
| Create | `src/Entity/Volunteer.php` |
| Create | `src/Provider/ElderSupportServiceProvider.php` |
| Create | `src/Access/ElderSupportAccessPolicy.php` |
| Create | `src/Controller/ElderSupportController.php` |
| Create | `src/Controller/VolunteerController.php` |
| Create | `src/Seed/VolunteerSkillSeeder.php` |
| Create | `templates/elders/request.html.twig` |
| Create | `templates/elders/request-confirmation.html.twig` |
| Create | `templates/elders/volunteer.html.twig` |
| Create | `templates/elders/volunteer-confirmation.html.twig` |
| Create | `tests/Minoo/Unit/Entity/ElderSupportRequestTest.php` |
| Create | `tests/Minoo/Unit/Entity/VolunteerTest.php` |
| Create | `tests/Minoo/Unit/Access/ElderSupportAccessPolicyTest.php` |
| Create | `tests/Minoo/Unit/Seed/VolunteerSkillSeederTest.php` |
| Create | `tests/Playwright/elder-support.spec.ts` |

## Scope

- Framework: ~2 lines in 1 file
- Minoo: 2 entities, 1 provider, 1 access policy, 2 controllers, 4 templates, 1 seeder, CSS additions, 5 test files
- No new dependencies, no new packages
