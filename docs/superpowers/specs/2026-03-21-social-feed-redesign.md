# Social Feed Redesign

**Date:** 2026-03-21
**Status:** Approved
**Scope:** Full social feed — three-column layout, enriched cards, engagement system, user posts

## Overview

Transform Minoo's homepage feed from a content directory into a social community hub. The feed should feel like Facebook: content attributed to communities, inline engagement (reactions, comments), a create-post box, and a three-column layout with sidebar navigation and contextual widgets.

## Prerequisites & Existing Infrastructure

- **Auth**: Waaseyaa framework provides `Waaseyaa\User\User` and `Waaseyaa\Access\AccountInterface`. Users have integer IDs. Session-based auth with CSRF protection via framework's `CsrfTokenManager`.
- **Business**: Not a separate entity type — businesses are `group` entities with `type=business`, queried via `GroupServiceProvider` storage with a type condition. The feed and card system must handle this as a subtype of `group`.
- **Community**: `Minoo\Entity\Community` exists. 637 First Nations seeded from CIRNAC. Each community has a name, slug, and location data.
- **People/Contributors**: `Minoo\Entity\Contributor` and `Minoo\Entity\ResourcePerson` exist (registered via `ContributorServiceProvider`). "People" in the feed refers to contributors. Contributors have a `community_id` field.
- **Community attribution**: Events, groups, teachings, contributors, and oral histories all have a `community_id` entity reference field. The existing `FeedAssembler` already resolves community names for feed items via the `communityField` mapping. The card redesign builds on this existing data — no new field migrations needed for attribution.
- **Feed ordering**: Reverse chronological (newest first), matching the existing `FeedService` cursor-based pagination. No algorithmic ranking.
- **CSRF for API endpoints**: All POST/DELETE API endpoints must include a CSRF token. The token is rendered into a `<meta>` tag in `base.html.twig` and sent via `X-CSRF-Token` header from JavaScript.

## Layout

### Three-Column Structure

```
┌──────────┬────────────────────────────┬───────────┐
│  Left    │      Center Feed           │   Right   │
│  Sidebar │      (max 600px)           │  Sidebar  │
│  (220px) │                            │  (260px)  │
│          │  ┌──────────────────────┐   │           │
│  Nav     │  │ Create Post Box      │   │ Trending  │
│  shorts  │  └──────────────────────┘   │           │
│          │  ┌──────────────────────┐   │ Upcoming  │
│  Your    │  │ Filter Chips         │   │ Events    │
│  Comms   │  └──────────────────────┘   │           │
│          │  ┌──────────────────────┐   │ Suggested │
│          │  │ Feed Card            │   │ Comms     │
│          │  │ Feed Card            │   │           │
│          │  │ ...                  │   │           │
│          │  └──────────────────────┘   │           │
└──────────┴────────────────────────────┴───────────┘
```

**Responsive breakpoints:**
- `>= 1200px`: Three columns visible
- `1024px–1199px`: Left sidebar + feed (right sidebar hidden)
- `< 1024px`: Feed only (left sidebar collapses to hamburger menu, which already exists)

### Left Sidebar

Fixed-position nav shortcuts:
- Events (red dot), Communities, Teachings, People, Businesses, Volunteer, Elder Support
- Divider
- "Your Communities" — list of communities the user follows (or nearby communities for anonymous users)

Each shortcut uses the domain accent color for its icon.

### Right Sidebar

Three widget sections:
1. **Trending** — Top 5 entities by reaction count in last 7 days. Shows entity title, type badge, reaction count. **Fallback**: When no reactions exist (early launch), shows the 5 newest entities instead.
2. **Upcoming Events** — Next 3 events by date. Compact format: title, date, location.
3. **Suggested Communities** — Communities near the user's location (uses the same location cookie as the location bar). Shows name and distance. On early launch (before follows exist), all nearby communities are shown. When user has follows, followed communities are excluded.

Each widget has a "See all" link to the relevant listing page.

## Card Design — Community-Attributed Hybrid

### Structure

```
┌─────────────────────────────────────────┐
│ ▬▬▬▬▬▬▬▬▬▬ (3px domain color top bar) ▬▬│
│                                         │
│  [🏘]  Sagamok Anishnawbek              │
│        posted an event · 2h ago         │
│                                         │
│  Sagamok Annual Powwow     (serif h3)   │
│                                         │
│  Annual powwow celebration at Sagamok   │
│  Anishnawbek. Grand entry at 12 PM.    │
│                                         │
│  ┌─────────────────────────────────┐    │
│  │ 📅 July 18, 2026 at 10:00 AM   │    │
│  │ 📍 Sagamok Powwow Grounds      │    │
│  └─────────────────────────────────┘    │
│                                         │
│  👍 24 interested        3 comments     │
│  ─────────────────────────────────────  │
│  👍 Interested   💬 Comment   ↗ Share   │
└─────────────────────────────────────────┘
```

### Card Elements

1. **Top bar**: 3px solid bar in domain accent color (replaces left border)
2. **Attribution row**: Community avatar (40px rounded square with domain-colored background + emoji/initial) + community name (colored link) + "posted a {type}" + relative timestamp
3. **Title**: Fraunces serif, 700 weight, links to detail page
4. **Body text**: 2-3 lines of description/summary, DM Sans
5. **Detail box** (conditional): Nested box with structured data — date/time + location for events, category for teachings, contact for businesses. Only shown when entity has structured fields.
6. **Engagement counts**: "N interested · N comments" — only shown when counts > 0
7. **Action buttons**: Three equal-width buttons separated by a top border

### Card Variants

| Entity Type | Top Bar Color | Avatar | Attribution | Primary Action | Detail Box |
|---|---|---|---|---|---|
| `event` | `--color-events` (#e63946) | 🎪 or community initial | "{community} posted an event" | Interested / Going | Date + Location |
| `teaching` | `--color-teachings` (#f4a261) | 📖 or community initial | "{community} shared a teaching" | Miigwech | Category |
| `group` (type=business) | `--color-businesses` | 🏪 or community initial | "{community} · local business" | Recommend | Contact / Hours |
| `group` (type=other) | `--color-communities` (#6a9caf) | 👥 or community initial | "{community} · community group" | Interested | — |
| `contributor` | `--color-people` (#8338ec) | 🧑 or community initial | "{community} · community member" | — | Role |
| `featured_item` | `--color-teachings` (#f4a261) | ⭐ | "Minoo · featured" | Interested | — |
| `post` (new) | `--color-communities` | User initial | "{user} in {community}" | Miigwech | — |
| communities (synthetic) | `--color-communities` | 🏘 | "Communities near you" | — | — (pill cloud) |

**Synthetic cards:** The "Communities near you" card is injected by the `FeedAssembler` at position 3 in the first page of results (after the 2nd real item). It contains up to 6 nearby communities based on the user's location cookie. It only appears once per feed session (not on subsequent infinite-scroll pages). This matches the existing behavior.

### Relative Timestamps

Display format: "just now", "2m ago", "1h ago", "3h ago", "Yesterday", "Mar 18", "Jan 5, 2025"

Computed server-side in the feed service. No client-side JS needed.

## Engagement System

### New Entity Types

#### `reaction`

| Field | Type | Description |
|---|---|---|
| `rid` | string (PK) | Reaction ID |
| `user_id` | int (FK) | User who reacted (from framework `users` table) |
| `target_type` | string | Entity type being reacted to |
| `target_id` | string | Entity ID being reacted to |
| `reaction_type` | string | One of: interested, going, miigwech, recommend |
| `created_at` | datetime | When the reaction was created |

**Unique constraint**: One reaction per user per target (user_id + target_type + target_id). Users can change reaction type but can't have multiple.

#### `comment`

| Field | Type | Description |
|---|---|---|
| `cid` | string (PK) | Comment ID |
| `user_id` | int (FK) | Comment author (from framework `users` table) |
| `target_type` | string | Entity type being commented on |
| `target_id` | string | Entity ID being commented on |
| `body` | text | Comment text (max 2000 chars) |
| `created_at` | datetime | When the comment was created |

No threading (flat comments only). No editing (delete and re-post).

#### `post`

| Field | Type | Description |
|---|---|---|
| `pid` | string (PK) | Post ID |
| `user_id` | int (FK) | Post author (from framework `users` table) |
| `community_id` | string | Community this post belongs to |
| `body` | text | Post text (max 5000 chars) |
| `status` | int | 1=published (all posts publish immediately) |
| `created_at` | datetime | When the post was created |

#### `follow`

| Field | Type | Description |
|---|---|---|
| `fid` | string (PK) | Follow ID |
| `user_id` | int (FK) | User who follows (from framework `users` table) |
| `target_type` | string | Entity type being followed (community) |
| `target_id` | string | Entity ID being followed |
| `created_at` | datetime | When the follow was created |

**Unique constraint**: One follow per user per target.

### Reaction Types by Domain

| Entity Type | Available Reactions |
|---|---|
| `event` | interested, going |
| `teaching` | miigwech |
| `group` (type=business) | recommend |
| `group` (type=other) | interested |
| `featured_item` | interested |
| `post` | miigwech |

### API Endpoints

All engagement endpoints require authentication.

```
POST   /api/react                      { target_type, target_id, reaction_type }
DELETE /api/react/{target_type}/{id}   (remove own reaction)
POST   /api/comment                    { target_type, target_id, body }
DELETE /api/comment/{cid}              (author only)
GET    /api/comments/{type}/{id}?page=1  → paginated comments
POST   /api/follow                     { target_type, target_id }
DELETE /api/follow/{target_type}/{id}  (unfollow)
POST   /api/post                       { community_id, body }
DELETE /api/post/{pid}                 (author only)
```

### Inline Comments

- Clicking "Comment" expands a comment section under the card (no page navigation)
- Shows latest 2 comments by default
- "View all N comments" link navigates to detail page
- Comment input: single-line text field + submit button
- Comments show: user name, relative timestamp, body text

## Create Post Box

Appears at the top of the feed, above the filter chips.

```
┌─────────────────────────────────────────┐
│  [User Initial]  What's happening in    │
│                  your community?        │
│                                         │
│  ────────────────────────────────────── │
│  📅 Event   📖 Teaching                 │
└─────────────────────────────────────────┘
```

- **Authenticated users**: Click opens an expandable form with textarea + community selector + post button. Posts are published immediately (no draft state).
- **Anonymous users**: Shows "Join the conversation — Log in to share with your community" with login link
- **Quick action buttons**: "Event" links to event creation, "Teaching" links to teaching submission (future features — just links for now)
- **Position**: Above the filter chips (matching the layout diagram)

## Feed Service Changes

The existing `FeedService` needs to:
1. Include `post` entities in the feed alongside events, businesses, etc.
2. Attach reaction counts and comment counts to each feed item
3. Attach community attribution data (name, slug, initial/emoji) to each item
4. Compute relative timestamps server-side
5. Support the existing cursor-based pagination
6. Include the user's own reaction state per item (for authenticated users — to show "You're interested")

## CSS Architecture

All new styles go in `@layer components` in `minoo.css`.

### New Component Classes

- `.feed-layout` — CSS Grid three-column container
- `.feed-sidebar` / `.feed-sidebar--right` — sidebar containers
- `.feed-create` — create-post box
- `.feed-card` (updated) — community-attributed card with top bar
- `.feed-card__attribution` — community avatar + name + action + time
- `.feed-card__detail-box` — nested structured data box
- `.feed-card__engagement` — reaction counts row
- `.feed-card__actions` — action buttons row
- `.feed-card__comments` — inline expandable comment section
- `.sidebar-widget` — right sidebar widget container
- `.sidebar-widget__title` — widget section heading
- `.sidebar-nav` — left sidebar navigation list

### Existing CSS Updates

- Remove `.feed-header` (search moves to site header, not duplicated in feed)
- Update `.feed-card` to use top bar instead of left border
- Update `.feed-container` to remove max-width (layout grid handles this)

## Template Changes

### Updated Templates

- `templates/feed.html.twig` — new three-column layout, create-post box, sidebar includes
- `templates/components/feed-card.html.twig` — community attribution, detail box, engagement row, action buttons

### New Templates

- `templates/components/feed-sidebar-left.html.twig` — nav shortcuts + your communities
- `templates/components/feed-sidebar-right.html.twig` — trending + upcoming + suggested
- `templates/components/feed-create-post.html.twig` — create post box
- `templates/components/feed-comments.html.twig` — inline comment section (loaded via JS)

## JavaScript

Minimal JS additions (progressive enhancement):

1. **Reaction toggling**: Click action button → `POST /api/react` → update count + button state. No page reload.
2. **Comment expansion**: Click "Comment" → show/fetch comment section under card
3. **Comment submission**: Submit form → `POST /api/comment` → append new comment to list
4. **Create post expansion**: Click create-post box → expand to form → submit → prepend to feed
5. **Follow toggling**: Click follow in sidebar → `POST /api/follow` → update button state

All JS is vanilla (no framework), follows existing patterns in `base.html.twig`.

### Interaction Details

- **Share button**: Uses `navigator.share({ url, title })` if available, falls back to copying the entity URL to clipboard with a brief "Link copied" toast.
- **Comment delete**: Trash icon visible only to comment author (and admins). Click → confirm dialog → `DELETE /api/comment/{cid}` → remove from DOM.
- **Create post community selector**: Defaults to the user's nearest community (from location cookie). Dropdown lists communities the user follows. If user has no follows and no location, shows all communities alphabetically. `community_id` is required — a post must belong to a community.

## Migration Path

### Database Migrations

```
migrations/
  create_reactions_table.php
  create_comments_table.php
  create_posts_table.php
  create_follows_table.php
```

### Entity Registration

New service provider:
- `EngagementServiceProvider` — registers all 4 engagement entity types (`reaction`, `comment`, `post`, `follow`) in a single provider to avoid provider sprawl (Minoo already has 13)

New access policies:
- `EngagementAccessPolicy` — covers `reaction`, `comment`, `post`, `follow` via array attribute (same pattern as `LanguageAccessPolicy`). Authenticated users can create; users can delete their own. Users with `admin` or `elder_coordinator` roles can delete any post or comment (basic moderation).

## Out of Scope

- Image/media uploads in posts (future milestone)
- Comment threading/replies
- Comment editing
- Notifications (reactions/comments on your content)
- Direct messaging
- User profiles with post history
- Content moderation tools (beyond delete-own)
- Social media share buttons (native share API only)

## Testing Strategy

- **Unit tests**: Entity classes, access policies, reaction type validation
- **Integration tests**: API endpoints (react, comment, post, follow), feed service with engagement data
- **Playwright tests**: Three-column layout rendering, reaction click → count update, comment expand → submit → display, create post flow
