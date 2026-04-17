# Frontend SSR Specification

> **Note — restructure in progress.** A `layouts/ + pages/<domain>/ + components/{shared,domain}/` reorganization is planned per the 2026-04-17 template/CSS architecture validation report. This spec reflects the **current** (pre-restructure) layout; it will be rewritten per-phase. Tier-2 semantic tokens (shadcn-style: `--background`, `--foreground`, `--muted`, `--card`, `--popover`, `--destructive`, `--ring`, `--input`, plus missing domain tokens) have been added as of Phase 0 without any structural moves.

## File Map

| File | Purpose |
|------|---------|
| `templates/base.html.twig` | Page shell: header, nav, footer, mobile menu JS |
| `templates/feed.html.twig` | Authenticated homepage/feed layout (extends base) |
| `templates/home.html.twig` | Public homepage layout if used (extends base) |
| `templates/403.html.twig` | Permission-denied page (extends base) |
| `templates/404.html.twig` | Not-found page (extends base) |
| `templates/events.html.twig` | Events listing + detail (extends base) |
| `templates/groups.html.twig` | Groups listing + detail (extends base) |
| `templates/teachings.html.twig` | Teachings listing + detail (extends base) |
| `templates/language.html.twig` | Language demo page (extends base) |
| `templates/people.html.twig` | People listing + detail (extends base) |
| `templates/businesses.html.twig` | Businesses listing + detail (extends base) |
| `templates/communities/list.html.twig` | Communities listing hub (extends base) |
| `templates/communities/detail.html.twig` | Community detail page (extends base) |
| `templates/search.html.twig` | Search page with facets + pagination (extends base) |
| `templates/games.html.twig` | Games hub — featured game cards + "coming soon" grid (extends base) |
| `templates/shkoda.html.twig` | Shkoda word game — campfire, keyboard, reveal screen (extends base) |
| `templates/crossword.html.twig` | Crossword game surface (extends base) |
| `templates/elders.html.twig` | Elders hub page (extends base) |
| `templates/elders/request.html.twig` | Request support form (extends base) |
| `templates/elders/volunteer.html.twig` | Volunteer signup form (extends base) |
| `templates/account/home.html.twig` | Authenticated account home/dashboard (extends base) |
| `templates/dashboard/*` | Volunteer/coordinator dashboards (extends base) |
| `templates/admin/*` | Admin surfaces (extends base) |
| `templates/newsletter/public/*` | Public newsletter listing + edition templates (extends base) |
| `templates/newsletter/editor/*` | Editor newsroom and submission flow (extends base) |
| `templates/auth/*.html.twig` | Auth pages (login, register, reset, verify) (extend base) |
| `templates/email/*.html.twig` | HTML email bodies (do not extend base) |
| `templates/email/*.txt.twig` | Plain-text email bodies (do not extend base) |
| `templates/about.html.twig` | About page (extends base) |
| `templates/how-it-works.html.twig` | How it works page (extends base) |
| `templates/data-sovereignty.html.twig` | Data sovereignty explainer (extends base) |
| `templates/legal.html.twig` | Legal/terms hub (extends base) |
| `templates/safety.html.twig` | Safety page (extends base) |
| `templates/get-involved.html.twig` | Get involved page (extends base) |
| `templates/volunteer.html.twig` | Volunteer landing page (extends base) |
| `templates/messages.html.twig` | Messages/chat page (extends base) |
| `templates/studio.html.twig` | Studio/creation tools page (extends base) |
| `templates/components/event-card.html.twig` | Event card partial |
| `templates/components/event-list.html.twig` | Event list wrapper + grouping partial |
| `templates/components/event-feed.html.twig` | Event feed section partial |
| `templates/components/event-feed-section.html.twig` | Event feed section wrapper partial |
| `templates/components/event-filters.html.twig` | Event filter bar partial |
| `templates/components/event-calendar.html.twig` | Event calendar month view partial |
| `templates/components/group-card.html.twig` | Group card partial |
| `templates/components/teaching-card.html.twig` | Teaching card partial |
| `templates/components/dictionary-entry-card.html.twig` | Dictionary entry card partial |
| `templates/components/search-result-card.html.twig` | Search result card partial |
| `templates/components/collection-card.html.twig` | Cultural collection card partial |
| `templates/components/oral-history-card.html.twig` | Oral history card partial |
| `templates/components/business-card.html.twig` | Business card partial |
| `templates/components/community-contact-card.html.twig` | Community contact card partial |
| `templates/components/user-role-card.html.twig` | User role card partial |
| `templates/components/feed-card.html.twig` | Feed post summary card partial |
| `templates/components/post-card.html.twig` | Full post card partial |
| `templates/components/feed-create-post.html.twig` | Create-post form partial |
| `templates/components/feed-engagement.html.twig` | Reactions/comments UI partial |
| `templates/components/feed-sidebar-left.html.twig` | Feed left-rail partial |
| `templates/components/feed-sidebar-right.html.twig` | Feed right-rail partial |
| `templates/components/sidebar-nav.html.twig` | Primary app navigation sidebar |
| `templates/components/user-menu.html.twig` | Header user menu / account dropdown |
| `templates/components/language-switcher.html.twig` | Header language switcher |
| `templates/components/theme-toggle.html.twig` | Standalone theme toggle control |
| `templates/components/location-bar.html.twig` | Location selection/status bar |
| `templates/components/flash-messages.html.twig` | Flash message display |
| `templates/components/protocol-notice.html.twig` | Protocol / content-notice block |
| `templates/components/hero-image.html.twig` | Page hero image block |
| `templates/components/breadcrumb.html.twig` | Reusable breadcrumb trail |
| `templates/components/pagination.html.twig` | Pagination control |
| `templates/components/empty-state.html.twig` | Generic empty/zero-state block |
| `templates/components/chat.html.twig` | Chat widget shell |
| `templates/components/resource-person-card.html.twig` | Resource person card partial |
| `public/css/minoo.css` | Design system: tokens, layout, components (~9500 lines) |
| `public/js/shkoda.js` | Shkoda game engine: modes, keyboard, campfire state, share |

## Template Architecture

### Inheritance
All pages extend `base.html.twig` and override blocks. `base.html.twig` exposes
nine blocks (not two as earlier drafts suggested):

```
base.html.twig
  {% block title %}Minoo{% endblock %}
  {% block meta_description %}...{% endblock %}
  {% block og_title %}{{ block('title') }}{% endblock %}
  {% block og_description %}...{% endblock %}
  {% block og_image %}...{% endblock %}
  {% block og_type %}website{% endblock %}
  {% block head %}{% endblock %}
  {% block content %}{% endblock %}
  {% block scripts %}{% endblock %}
```

### Path-Based Routing
Framework `RenderController::tryRenderPathTemplate()` maps URL segments to templates:
- `/events` → `events.html.twig` with `path = '/events'`
- `/events/summer-solstice-powwow` → `events.html.twig` with `path = '/events/summer-solstice-powwow'`
- `/search` → `search.html.twig` with `path = '/search'`

### Base Template Variables
- `path` (string): Current request path, set by framework

### Navigation
Primary navigation is sidebar-first in `templates/components/sidebar-nav.html.twig`, rendered from `templates/base.html.twig` as part of `app-layout`. Active state is path-driven (`current_path == '/...'` or `starts with '/...'` checks).

Header behavior is minimal: logo, search, and user menu; sidebar toggle is mobile-only and controls `.app-sidebar--open` plus `.app-sidebar__overlay--visible`.

## Homepage-As-Source Layout Policy

`/` is rendered by `FeedController::index()` using `templates/feed.html.twig`, and is the source-of-truth for current SSR composition rhythm.

### Canonical layout surfaces
- **Shell layout (`base.html.twig`):** `app-layout` with `app-sidebar` and `app-main`
- **Homepage content (`feed.html.twig`):** `feed-layout` with primary content plus optional right rail
- **Listing hubs (`events`, `groups`, `teachings`, `people`, etc.):** `flow-lg` + `listing-hero` + `card-grid`

### Column policy
- Treat **page layout columns** and **card-grid columns** as separate concerns.
- `feed-layout` can be multi-region (main + right rail) when there is real supporting content.
- `card-grid` uses shared width tokens; do not add a page-specific third card column unless introducing a shared density variant across multiple surfaces.

### Width policy
- Shared content rhythm should come from shared tokens/utilities (for example a shared max content width utility), not one-off per-template hard-coded widths.

## Template Directory Conventions

The `templates/` directory is organized into three conceptual layers:

- **Page templates** (path or controller entrypoints): Top-level `.html.twig` files at the root of `templates/` and within feature folders such as `auth/`, `elders/`, `communities/`, `account/`, `dashboard/`, `admin/`, and `newsletter/`. These extend `base.html.twig` (except email bodies).
- **Feature-scoped templates**: Deeper views in a feature area, e.g. `newsletter/public/*`, `newsletter/editor/*`, `elders/request.html.twig`, `elders/volunteer.html.twig`, `communities/list.html.twig`, `communities/detail.html.twig`. These still extend `base.html.twig` but live in subdirectories to keep domains separated.
- **Components/partials**: Reusable pieces that are only included from other templates and always live in `templates/components/`. They never define `<html>` or `<body>`, and they do not extend `base.html.twig`.

Naming conventions:

- Page templates use route-aligned names in kebab-case or simple nouns, e.g. `events.html.twig`, `get-involved.html.twig`, `data-sovereignty.html.twig`.
- Reusable components use structured suffixes:
  - `*-card.html.twig` for entity cards (events, groups, teachings, communities, people, businesses, resource persons).
  - `*-section.html.twig` for larger layout sections that group multiple cards or blocks.
  - `*-list.html.twig` for list wrappers around one or more cards.
  - `*-feed.html.twig` or `*-feed-section.html.twig` for feed-like collections with pagination or infinite scroll semantics.
  - `*-filters.html.twig` for filter bars.
  - `*-calendar.html.twig` for calendar/grid date views.
  - `*-bar.html.twig` for horizontal bars or toolbars (e.g. `location-bar`).
  - Cross-cutting utilities like `breadcrumb`, `hero-image`, `pagination`, `empty-state`, `flash-messages` live directly under `components/` with short, descriptive names.

All new shared UI should follow the same placement and naming rules: page entrypoint in `templates/` or a feature folder, reusable fragments in `templates/components/` with a clear suffix.

## Listing + Detail Pattern

Pages like events, groups, teachings use a single template for both listing and detail views, using path conditionals inside `{% block content %}`:

```twig
{% block content %}
  {% set items = [ ... ] %}  {# Static data inside block #}
  {% set slug = path|replace({'/events/': '', '/events': ''})|trim('/') %}

  {% if path == '/events' %}
    {# Listing view: card grid #}
  {% elseif current_item %}
    {# Detail view: full content #}
  {% else %}
    {# Not found fallback #}
  {% endif %}
{% endblock %}
```

**Key constraint:** `{% set %}` must be inside the block, and only one `{% block %}` per name. Use conditionals inside the block, not multiple blocks.

Historically this pattern used hardcoded static data arrays. Minoo now builds its listings and details from real entities and controllers:

- Controllers are responsible for resolving the current entity (e.g. event, group, teaching, person) and passing it to the template as `event`, `group`, `teaching`, `person`, etc.
- Controllers also precompute any view models and feed objects (e.g. `feed`, `filters`, `communities`, `related_teachings`, `similar_upcoming`) so that Twig can stay focused on simple branching and presentation.

## Smoke Test Routes

After substantial template or CSS changes, manually smoke test the main SSR routes to catch regressions:

- Public and feed:
  - `/` (redirect to `/feed` for authenticated users)
  - `/feed`
  - `/home` (if configured as an alias)
- Listings and detail hubs:
  - `/events`, `/events/{slug}`
  - `/groups`, `/groups/{slug}`
  - `/teachings`, `/teachings/{slug}`
  - `/people`, `/people/{slug}`
  - `/businesses`, `/businesses/{slug}`
  - `/communities`, `/communities/{slug}`
- Other public surfaces:
  - `/language`
  - `/games`, `/shkoda`, `/crossword`
  - `/elders`, `/elders/request`, `/elders/volunteer`
  - `/about`, `/how-it-works`, `/data-sovereignty`, `/safety`, `/get-involved`
- Account and dashboards:
  - `/account`
  - `/dashboard/volunteer`, `/dashboard/volunteer/edit`
  - `/dashboard/coordinator`, `/dashboard/coordinator/users`, `/dashboard/coordinator/applications`
  - `/admin/users`, `/admin/ingestion`
- Newsletter:
  - `/newsletter` (public listing)
  - `/newsletter/{slug}` (public edition)
  - `/newsletter/submit`
  - Newsletter editor routes (newsroom/list/submissions) as configured
- Auth, errors, and system:
  - `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`
  - `403.html.twig` and `404.html.twig` via forced error scenarios

## Component Partials

Components accept variables via `{% include %}` with `with`:

```twig
{% include "components/event-card.html.twig" with {
  title: event.title, type: event.type, date: event.date,
  location: event.location, excerpt: event.excerpt, url: "/events/" ~ event.slug
} %}
```

### Component Interfaces

| Component | Required vars | Optional vars |
|-----------|--------------|---------------|
| `event-card` | `title`, `type`, `date`, `url` | `location`, `excerpt` |
| `group-card` | `title`, `type`, `url` | `region`, `excerpt` |
| `teaching-card` | `title`, `type`, `url` | `excerpt` |
| `dictionary-entry-card` | `word`, `definition`, `part_of_speech` | `stem`, `url` |
| `search-result-card` | `title`, `url` | `source_name`, `crawled_at`, `content_type`, `topics`, `highlight`, `og_image` |

## CSS Architecture

Single file `public/css/minoo.css` — no build step, no preprocessor.

### Layer Order

The layer-order declaration **must be the first non-`@font-face` statement** in
`minoo.css` to pin cascade priority before any `@layer` block opens:

```css
@layer reset, tokens, base, layout, components, utilities;
```

All component rules live in a **single** `@layer components { ... }` block.
Previous drafts scattered components across 12 separate `@layer components`
blocks, one of which opened before the order declaration and silently
demoted `components` to the lowest-priority layer (fixed; see git history).

### Layer Contents

| Layer | Purpose |
|-------|---------|
| `reset` | Box-sizing, margin reset, media elements, font inheritance |
| `tokens` | Design tokens: colors, typography, spacing, widths, radii, shadows |
| `base` | Body, headings, links, code, lists — use tokens only |
| `layout` | `.site`, `.site-header`, `.site-main`, `.site-footer`, `.card-grid` |
| `components` | `.card`, `.detail`, `.search-*`, `.filter-badge`, `.pagination` |
| `utilities` | `.flow`, `.flow-lg`, `.visually-hidden` |

### Design Tokens

**Colors** — oklch palette with 5 families:
- `earth` (warm neutral): 50, 100, 200, 700, 900
- `forest` (green accent): 100, 500, 700
- `water` (blue info): 100, 600
- `sun` (gold warning): 500
- `berry` (red error): 600

Semantic aliases: `--text-primary`, `--text-secondary`, `--surface`, `--surface-raised`, `--border`, `--accent`, `--accent-surface`, `--link`, `--error`, `--warning`, `--info`

**Typography** — fluid `clamp()` scale (1.2 ratio):
- `--text-sm` through `--text-3xl`
- Families: `--font-body` (system-ui), `--font-heading` (Charter/serif), `--font-mono`

**Spacing** — fluid `clamp()` scale (1.5 ratio):
- `--space-3xs` through `--space-2xl`
- `--gutter: var(--space-sm)`

**Widths:** `--width-prose` (65ch), `--width-content` (80rem), `--width-narrow` (40rem), `--width-card` (25rem)

### CSS Conventions
- Logical properties only: `margin-block`, `padding-inline`, never `left`/`right`
- `gap` for spacing (no margin-based spacing between siblings)
- Native nesting (no BEM naming needed)
- Container queries on components, media queries only for page shell
- No `!important`

## Edge Cases

- Path-based templates require framework#189
- `tryRenderPathTemplate()` matches first segment for multi-segment paths
- Mobile nav toggle is vanilla JS (no framework dependency)
- CSS uses oklch which requires modern browser support
- Static data in templates is temporary — will be replaced by entity queries
- Search template uses `query_param()` Twig function from framework
