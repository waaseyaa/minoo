# Frontend SSR Specification

## File Map

| File | Purpose |
|------|---------|
| `templates/base.html.twig` | Page shell: header, nav, footer, mobile menu JS |
| `templates/page.html.twig` | Default page (extends base) |
| `templates/404.html.twig` | Not-found page (extends base) |
| `templates/events.html.twig` | Events listing + detail (extends base) |
| `templates/groups.html.twig` | Groups listing + detail (extends base) |
| `templates/teachings.html.twig` | Teachings listing + detail (extends base) |
| `templates/language.html.twig` | Language demo page (extends base) |
| `templates/search.html.twig` | Search page with facets + pagination (extends base) |
| `templates/games.html.twig` | Games hub — featured game cards + "coming soon" grid (extends base) |
| `templates/shkoda.html.twig` | Shkoda word game — campfire, keyboard, reveal screen (extends base) |
| `templates/components/event-card.html.twig` | Event card partial |
| `templates/components/group-card.html.twig` | Group card partial |
| `templates/components/teaching-card.html.twig` | Teaching card partial |
| `templates/components/dictionary-entry-card.html.twig` | Dictionary entry card partial |
| `templates/components/search-result-card.html.twig` | Search result card partial |
| `public/css/minoo.css` | Design system: tokens, layout, components (~5500 lines) |
| `public/js/shkoda.js` | Shkoda game engine: modes, keyboard, campfire state, share |

## Template Architecture

### Inheritance
All pages extend `base.html.twig` and override blocks:

```
base.html.twig
  {% block title %}Minoo{% endblock %}
  {% block head %}{% endblock %}
  {% block content %}{% endblock %}
```

### Path-Based Routing
Framework `RenderController::tryRenderPathTemplate()` maps URL segments to templates:
- `/events` → `events.html.twig` with `path = '/events'`
- `/events/summer-solstice-powwow` → `events.html.twig` with `path = '/events/summer-solstice-powwow'`
- `/search` → `search.html.twig` with `path = '/search'`

### Base Template Variables
- `path` (string): Current request path, set by framework

### Navigation
6 nav items: Events, Groups, Teachings, Language, Search. Active state via `aria-current="page"` when `path starts with '/events'` etc.

Mobile toggle: vanilla JS toggles `.is-open` on `.site-nav` and `aria-expanded` on `.nav-toggle`.

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

Currently uses hardcoded static data arrays. Will switch to entity queries when framework entity listing is ready.

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
```css
@layer reset, tokens, base, layout, components, utilities;
```

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
