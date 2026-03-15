# Outreach: NorthCloud Contact Data Display

**Issue:** #179 — feat: outreach workflow with NC contact data
**Date:** 2026-03-14
**Status:** Design

## Summary

Surface NorthCloud band office and leadership contact data on community detail pages, and link community names on event/group/teaching cards to the community detail page where the full contact info lives. Display-only — no forms, no outreach tracking, no new entities.

## Scope

- **In scope:** Community contact card (leadership + band office), community name links on related entity cards
- **Out of scope:** Contact forms, outreach logging, stale data flagging, ingestion/sync jobs, authenticated-only features

## Architecture: Controller-Level Fetch

No new entities, tables, or providers. The existing `NorthCloudClient` and SQLite cache layer handle all NC communication.

### Data Flow

1. `CommunityController::show()` loads the community entity
2. If the community has an `nc_id`:
   - Calls `NorthCloudClient::getPeople($ncId)` → array of current leadership
   - Calls `NorthCloudClient::getBandOffice($ncId)` → band office contact object (or null)
   - Both calls use the existing SQLite cache (`nc_api_cache`, TTL: 3600s)
3. Passes `leadership` and `bandOffice` to the template alongside the community entity
4. If `nc_id` is null or API returns empty/error — no contact card rendered, graceful no-op

### Error Handling

The NC API calls in `CommunityController::show()` are wrapped in try/catch. If NorthCloud is unreachable or returns an error, the page renders normally without the contact card. The community's own entity data is unaffected.

## Template Changes

### New Component: `templates/components/community-contact-card.html.twig`

Full contact card rendered on the community detail page. Sections:

- **Leadership:** Chief/council names + roles (from `getPeople` response)
- **Band Office:** Address, phone, email, office hours (from `getBandOffice` response)
- Each section only renders if the corresponding data exists

### Existing Card Updates

`event-card.html.twig`, `group-card.html.twig`, `teaching-card.html.twig` — where these cards already display a community name, make it a link to `/communities/{slug}`. No inline contact data on cards.

## CSS

New `.community-contact-card` component in `@layer components` in `public/css/minoo.css`. Follows existing card patterns:

- Logical properties (`margin-block`, `padding-inline`)
- Native nesting
- Container queries
- `gap` for spacing

## Controller Changes

**Modified:** `CommunityController::show()` only.

Adds two `NorthCloudClient` calls when `nc_id` is present, passes results to the community detail template. Try/catch for graceful degradation.

**No changes to:** `EventController`, `GroupController`, `TeachingController`. The community name link is a template-level change using data already available in those templates.

## Testing

### Unit Tests

- `CommunityControllerTest`: Mock `NorthCloudClient` to verify `leadership` and `bandOffice` are passed to the template when `nc_id` is present
- Verify graceful handling when `nc_id` is null
- Verify graceful handling when NC API returns empty/error

### Playwright

- Smoke test: community detail page renders the contact card when NC data exists

## Files to Create/Modify

| File | Action |
|------|--------|
| `src/Controller/CommunityController.php` | Modify `show()` — add NC client calls |
| `templates/components/community-contact-card.html.twig` | Create — full contact card component |
| `templates/communities.html.twig` | Modify — include contact card component |
| `templates/components/event-card.html.twig` | Modify — link community name |
| `templates/components/group-card.html.twig` | Modify — link community name |
| `templates/components/teaching-card.html.twig` | Modify — link community name |
| `public/css/minoo.css` | Modify — add `.community-contact-card` component |
| `tests/Minoo/Unit/Controller/CommunityControllerTest.php` | Create/modify — NC data tests |
| `tests/playwright/community-contact.spec.ts` | Create — contact card smoke test |
