# Outreach: NorthCloud Contact Data Display

**Issue:** #179 — feat: outreach workflow with NC contact data
**Date:** 2026-03-14
**Status:** Design

## Summary

Surface NorthCloud band office and leadership contact data on community detail pages, and link community names on event/group/teaching cards to the community detail page where the full contact info lives. Display-only — no forms, no outreach tracking, no new entities.

## Scope

- **In scope:** Extract contact card component, add error handling, add community links to entity cards, testing
- **Out of scope:** Contact forms, outreach logging, stale data flagging, ingestion/sync jobs, authenticated-only features

## Current State (Already Implemented)

The core data flow is already working:

- **`CommunityController::show()`** already calls `NorthCloudClient::getPeople($ncId)` and `getBandOffice($ncId)`, passing `people` and `band_office` to the template
- **`templates/communities/detail.html.twig`** already renders leadership (chief + councillors) and band office contact data (address, phone, email, office hours, fax, toll-free) inline with `{% if %}` guards
- **`NorthCloudClient`** already has both methods with SQLite cache (`nc_api_cache`, TTL: 3600s)

## Remaining Work

### 1. Add `community_id` field to event, group, and teaching entities

These entities currently lack a community relationship. Add a `community_id` field (type: `entity_reference`, target: `community`, nullable) to each:

| Entity | Provider | Insert after | Weight |
|--------|----------|-------------|--------|
| `event` | `EventServiceProvider` | `location` | 12 |
| `group` | `GroupServiceProvider` | `region` | 16 |
| `teaching` | `TeachingServiceProvider` | `cultural_group_id` | 12 |

**Schema drift:** Adding fields to `fieldDefinitions` does not ALTER existing SQLite tables. Run `bin/waaseyaa schema:check` after, then `ALTER TABLE ... ADD COLUMN community_id` on any environment with existing tables.

### 2. Add try/catch to CommunityController::show()

The current `show()` method calls `getPeople()` and `getBandOffice()` without error handling. If NorthCloud is unreachable, the page crashes. Wrap the NC calls in try/catch so the page renders normally without the contact card when NC is unavailable.

### 3. Extract community-contact-card component

Refactor the existing inline leadership + band office markup in `detail.html.twig` (lines ~78–157) into a reusable `templates/components/community-contact-card.html.twig`. Include it from `detail.html.twig` with `{% include %}`. This enables future reuse and keeps the detail template focused.

### 4. Add community links to entity cards

The card templates (`event-card`, `group-card`, `teaching-card`) currently do **not** reference community data. The page templates that render them (`events.html.twig`, `groups.html.twig`, `teachings.html.twig`) pass pre-shaped variables like `title`, `url`, `excerpt` — but not community info.

Changes needed:
- Controllers/page templates must resolve the `community_id` field to a community entity, then pass `community_name` and `community_slug` to cards
- Card templates add a community name link to `/communities/{community_slug}` when data is present
- Cards without a community relationship render unchanged (field is nullable)

### 5. CSS for extracted component

Move existing contact card styles into a `.community-contact-card` component in `@layer components` in `minoo.css`. Follows existing patterns: logical properties, native nesting, container queries, `gap` for spacing.

### 6. Testing

**Unit tests:**
- `CommunityControllerTest`: verify try/catch — mock `NorthCloudClient` to throw exception, assert page still renders
- Verify `people` and `band_office` are passed when `nc_id` is present
- Verify graceful handling when `nc_id` is null
- Entity field definition tests for new `community_id` on event, group, teaching

**Playwright:**
- Smoke test: community detail page renders the contact card when data exists
- Smoke test: entity cards link community name when relationship exists

## Files to Create/Modify

| File | Action |
|------|--------|
| `src/Provider/EventServiceProvider.php` | Add `community_id` field definition |
| `src/Provider/GroupServiceProvider.php` | Add `community_id` field definition |
| `src/Provider/TeachingServiceProvider.php` | Add `community_id` field definition |
| `src/Controller/CommunityController.php` | Modify `show()` — add try/catch around NC calls |
| `templates/components/community-contact-card.html.twig` | Create — extract from `detail.html.twig` |
| `templates/communities/detail.html.twig` | Modify — replace inline markup with `{% include %}` |
| `templates/components/event-card.html.twig` | Modify — add optional community name link |
| `templates/components/group-card.html.twig` | Modify — add optional community name link |
| `templates/components/teaching-card.html.twig` | Modify — add optional community name link |
| `templates/events.html.twig` | Modify — pass community data to card includes |
| `templates/groups.html.twig` | Modify — pass community data to card includes |
| `templates/teachings.html.twig` | Modify — pass community data to card includes |
| `public/css/minoo.css` | Modify — add/move `.community-contact-card` component styles |
| `tests/Minoo/Unit/Controller/CommunityControllerTest.php` | Create/modify — error handling + NC data tests |
| `tests/Minoo/Unit/Entity/` | Modify — field definition assertions for community_id |
| `tests/playwright/community-contact.spec.ts` | Create — contact card + community link smoke tests |
