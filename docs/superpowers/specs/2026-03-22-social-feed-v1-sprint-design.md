# Social Feed v1 Sprint Design

**Date:** 2026-03-22
**Milestone:** Social Feed v1
**Status:** 9 open issues, 11 closed
**Sprint structure:** 9 PRs across 3 phases (Bugs → Features → Tests)

## Current State

Assessed via production DB pull + Playwright inspection (2026-03-22):

**Working:** Three-column layout (sidebar nav | feed | right sidebar). Feed card types: featured items, community-attributed events, local businesses, "Communities Near You" discovery card. Filter buttons (All/Events/Groups/Businesses/People) with client-side filtering. Geo-aware location bar. Login prompt for unauthenticated users. "You're all caught up" end-of-feed marker.

**Broken:** Engagement API returns 404 at `/api/engagement/react` — "Interested" button shows "Error" state. Silent failures in 6+ catch blocks mask database errors. Entity validation gaps (no constructor validation, unconstrained reaction types, missing community_id on Post, wrong created_at defaults). Polish work lost in squash merge of #414.

## Design Decisions

### Sprint Sequencing: Bugs First (Option A)
Fix the broken engagement API and validation issues first to restore a working baseline. Then ship visible features. Write tests last against stable, complete behavior.

### User Posts Visibility: Public-Read, Auth-to-Create
- Anonymous visitors see posts in the feed, cannot create/react/comment
- Authenticated users can create posts, react, and comment
- Same contract as events, teachings, communities
- No branching logic in FeedAssembler — posts are always included
- Access policy: canView=always, canCreate=authenticated, canEdit/Delete=author only

### Post Card Design: User-Attributed (Option A)
Posts are attributed to the user, not the community. "Russell Jones shared a post · 2h ago" with user avatar and distinct accent color. This differentiates posts from community-attributed events/businesses and centers the individual voice for personal content.

### Responsive Breakpoints: Existing CSS
Breakpoints already implemented in `minoo.css`:
- **>1200px:** 3 columns (left sidebar + feed + right sidebar)
- **1024–1199px:** 2 columns (left sidebar + feed, right sidebar hidden)
- **<1024px:** 1 column (feed only, both sidebars hidden)

#398 scope is component polish at each breakpoint, not adding breakpoints.

### Polish Recovery: Single PR
#406 (diagnostic) and #416 (recovery) are the same work. One branch, one PR, closes both.

### #412 Split: Hotfix + Full Cleanup
Engagement API 404 gets an isolated hotfix PR first. Full validation/performance pass follows as a separate PR.

---

## Phase 1 — Bug Fixes (restore working baseline)

### PR 1 — Hotfix: Engagement API 404
**Scope:** Minimal fix to restore working engagement interactions.
- Fix FeedAssembler → EngagementCounter type mismatch (string IDs vs array{type,id})
- Verify route registration for `/api/engagement/react`
- Verify controller method signatures match route definitions
- No refactors, no validation changes

**Files likely affected:**
- `src/Feed/FeedAssembler.php`
- `src/Controller/EngagementController.php`
- Route registration in relevant service provider

**Estimate:** ~1 session

### PR 2 — #412: Entity Validation + Type Integrity
**Scope:** All 6 sub-items from the issue.

1. **Constructor validation:** Add validation in engagement entity constructors — `new Reaction([])` must not silently succeed with no user/target
2. **Reaction type rename:** `emoji` → `reaction_type` with allowlist validation (e.g. `like`, `interested`, `recommend`)
3. **Post community_id:** Add `community_id` to Post entity class defaults and provider field definitions
4. **created_at defaults:** Default to `time()` in all engagement entities instead of 0 (epoch 1970)
5. **N+1 query batching:** EngagementCounter batch queries with `IN` clauses instead of 2 queries per feed item
6. **Assembler type cleanup:** Complete any remaining type alignment between FeedAssembler and EngagementCounter beyond what PR 1's hotfix addressed (PR 1 fixes the 404; this item handles deeper structural cleanup)

**Files likely affected:**
- `src/Entity/Reaction.php`, `src/Entity/Comment.php`, `src/Entity/Post.php`, `src/Entity/Follow.php`
- `src/Provider/EngagementServiceProvider.php` (field definitions)
- `src/Feed/EngagementCounter.php`
- `src/Feed/FeedAssembler.php`
- Migration for `emoji` → `reaction_type` column rename

**Estimate:** ~2 sessions

### PR 3 — #410: Silent Failure Logging
**Scope:** Fix 6+ methods that swallow exceptions silently.

**Affected methods:**
- `FeedController::buildTrending()` (2 nested catches)
- `FeedController::buildUpcomingEvents()`
- `FeedController::buildSuggestedCommunities()`
- `FeedController::buildFollowedCommunities()`
- `EntityLoaderService::loadFeaturedItems()`
- `EntityLoaderService::loadPosts()`

**Fix pattern:**
1. Narrow catches from `\Throwable` to specific exceptions (`\PDOException`, entity-not-found)
2. Add `error_log()` with context to all catch blocks
3. Let database-level errors propagate where they indicate real problems (schema drift, connection failure)

**Estimate:** ~1 session

### PR 4 — #416: Polish Recovery (closes #406 + #416)
**Scope:** Re-apply all polish work lost in squash merge of #414.

**Items to recover:**
- 30+ translation keys in `resources/lang/en.php` (all `feed.*` and `nav.businesses` keys)
- Community name resolution via `resolveCommunityName()` in FeedItemFactory
- Date formatting via `formatEventDate()` in FeedController
- `RelativeTime::format()` type fix (int vs DateTimeImmutable)
- Card template rewrite: community-attributed design with engagement row
- SVG icons replacing emojis (sidebar nav, action buttons, detail boxes)
- CSS: card padding, action buttons with CSS mask icons, typography, spacing
- Sidebar alignment (left nav + right widget layout)
- Nav alignment (li margin reset, dropdown padding match)
- Feed grid alignment with header (removed double padding)
- Location bar: Change button beside location text
- Multi-colored ribbon restored
- Badge colors for Featured + Communities
- Business card descriptions
- Reduced header-to-feed gap
- Duplicate CSS cleanup

**Source:** First attempt recovery via `git reflog` for the `social-feed-smoke-test` branch (look for commits around the #414 squash merge). If reflog has expired, re-implement from the itemized list above — each item is self-contained and can be built from the issue descriptions in #406. The Playwright-verified production state (screenshot: `social-feed-homepage.png`) serves as the "before" baseline.

**Estimate:** ~1 session

---

## Phase 2 — Features (ship visible improvements)

### PR 5 — #390: User Posts in Feed
**Scope:** Complete the existing Post entity and integrate it into the homepage feed. `src/Entity/Post.php` already exists (PR 2 adds `community_id` and fixes `created_at`). This PR adds the missing pieces: access policy, feed integration, template, and create-post UI.

**Entity: `post` (already exists)**
- Key: `pid`
- Fields after PR 2: `user_id`, `community_id`, `body`, `created_at`, `updated_at`, `status`
- No new entity class needed — PR 2 handles field/validation fixes

**Provider:** Extend existing `EngagementServiceProvider` or create `PostServiceProvider`
- Register routes for create/edit/delete endpoints (if not already registered)

**Access Policy:** `PostAccessPolicy`
- `#[PolicyAttribute(entityType: 'post')]`
- `canView`: always true (public content)
- `canCreate`: user must be authenticated
- `canEdit`: author only (`$entity->get('user_id') === $currentUser->id`)
- `canDelete`: author only, OR user has coordinator role (admin override)

**Feed integration:**
- `FeedItemFactory::buildPost()` — user-attributed card with distinct accent color
- `FeedAssembler` includes posts in unified feed query (no auth branching)
- Post cards show: user avatar (initials), user name, "shared a post · {relative time}", body text, Like/Comment/Share actions

**Template:** `templates/components/post-card.html.twig`
- User attribution header (avatar + name + timestamp)
- Body text
- Engagement action row (Like, Comment, Share)
- Left accent bar in post color (distinct from event/business colors)

**Create post UI:**
- "Join the conversation" box for unauthenticated users (already exists)
- Text input + submit for authenticated users
- Community auto-selected based on user's location context

**Estimate:** ~2-3 sessions

### PR 6 — #398: CSS Responsive Polish
**Scope:** Polish all feed components at each existing breakpoint.

**Existing breakpoints to polish (no new breakpoints added):**
- **>1200px:** Full 3-column — verify all components properly sized
- **1024–1199px:** 2-column (right sidebar hidden) — feed cards expand to fill, sidebar nav remains
- **<1024px:** 1-column (both sidebars hidden) — cards full-width, touch targets ≥44px
- **375px viewport test:** Same 1-column, verify tighter padding and no horizontal overflow

**Components to polish:**
- Card attribution row (avatar + name + meta)
- Action buttons (hover/active states, touch targets on mobile)
- Engagement rows (spacing, icon sizing)
- Create-post box
- Feed filter chips (horizontal scroll on mobile if needed)
- Sidebar nav items
- Right sidebar widgets (Trending, Upcoming Events)

**Estimate:** ~1 session

### PR 7 — #415: Bookmarkable Filter URLs
**Scope:** Server-side filter support + client-side progressive enhancement.

**Server-side:**
- `FeedController::index()` reads `?filter=` query parameter
- Valid values: `all`, `event`, `group`, `business`, `people` (maps to `resource_person` entity type)
- Passes active filter to `FeedContext::activeFilter`
- Renders matching filter chip with active state

**Client-side:**
- Filter chips rendered as `<a href="?filter=event">` (works without JS)
- JS progressive enhancement: prevent default, `history.pushState()`, client-side filter
- Browser back/forward navigates between filter states via `popstate` event

**URLs:**
- `minoo.live/` or `minoo.live/?filter=all` → all content
- `minoo.live/?filter=event` → events only
- `minoo.live/?filter=group` → groups only
- `minoo.live/?filter=business` → businesses only
- `minoo.live/?filter=people` → people only (ResourcePerson entities)

**Estimate:** ~1 session

---

## Phase 3 — Tests (lock down stable behavior)

### PR 8 — #413: EngagementController Integration Tests
**Scope:** Comprehensive integration test coverage for the most security-sensitive new class.

**Test file:** `tests/Minoo/Integration/Controller/EngagementControllerTest.php`

**Test cases:**
- Auth enforcement: anonymous requests → 401 on all mutation endpoints
- CSRF validation: missing/invalid token → 403
- Input validation: invalid `target_type` rejected (whitelist), invalid `reaction_type` rejected, body length limits enforced
- CRUD: create reaction → 201, delete reaction → 204, create comment → 201, create post → 201, create follow → 201
- Ownership: can't delete another user's reaction/comment → 403
- Admin override: coordinator role can delete any content (matches PostAccessPolicy canDelete rule)
- Duplicate handling: second reaction = upsert (not duplicate), second follow = idempotent 200

**Estimate:** ~1-2 sessions

### PR 9 — #399: Playwright E2E Tests
**Scope:** End-to-end browser tests covering the full feed experience.

**Test scenarios:**
- Homepage loads with 3-column layout (sidebar nav, feed, right sidebar)
- Feed cards have attribution row and action buttons
- Filter chips work: clicking "Events" shows only events, "All" resets
- Filter URLs are bookmarkable: navigating to `/?filter=event` shows filtered feed
- Reaction click toggles active state (requires auth fixture)
- Comment expansion, text entry, submission, and display
- Create post flow: auth gate shown for anonymous, form shown for authenticated
- Responsive collapse: right sidebar hidden at 1024px, left sidebar hidden below 1024px
- Post cards show user attribution (not community attribution)

**Estimate:** ~1-2 sessions

---

## Dependencies

```
PR 1 (Hotfix 404)
  └→ PR 2 (#412 validation) — builds on fixed assembler
  └→ PR 3 (#410 logging) — independent, can parallel with PR 2
PR 4 (#416 polish) — independent of PR 2/3, depends on PR 1 for route fix
PR 5 (#390 posts) — depends on PR 2 (entity validation patterns)
PR 6 (#398 CSS) — depends on PR 4 (polish recovery establishes component styles)
PR 7 (#415 URLs) — depends on PR 4 (filter chip template changes)
PR 8 (#413 tests) — depends on PR 1 + PR 2 (tests stable API)
PR 9 (#399 e2e) — depends on all of Phase 1 + Phase 2
```

## Total Estimate

~10-13 sessions across 9 PRs. Each PR is independently deployable. Phase 1 can ship to production as each PR merges.
