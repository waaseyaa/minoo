# Frontend SSR Specification

Template and CSS architecture after the 2026-04-17 template/CSS restructure (Phases 0–6).

## Directory Layout

```
templates/
├── layouts/                          # Page shells (extended by pages/)
│   └── base.html.twig                # Header, sidebar, footer, flash, chat
├── pages/                            # Route-level templates (one domain per folder)
│   ├── home/index.html.twig
│   ├── feed/index.html.twig
│   ├── events/{index,show}.html.twig
│   ├── teachings/{index,show}.html.twig
│   ├── groups/{index,show}.html.twig
│   ├── people/{index,show}.html.twig
│   ├── businesses/{index,show}.html.twig
│   ├── communities/{index,show}.html.twig
│   ├── contributors/{index,show}.html.twig
│   ├── language/{index,search,show}.html.twig
│   ├── oral-histories/{index,collection,show}.html.twig
│   ├── elders/{index,volunteer,volunteer-confirmation,request,request-confirmation}.html.twig
│   ├── games/{index,agim,crossword,shkoda}.html.twig
│   ├── search/index.html.twig
│   ├── legal/index.html.twig
│   ├── newsletter/{edition.html.twig,editor/*,public/*}
│   ├── auth/{login,register,forgot-password,reset-password,verify-email,check-email}.html.twig
│   ├── account/index.html.twig
│   ├── admin/{ingestion,users}.html.twig
│   ├── dashboard/{coordinator,coordinator-applications,coordinator-users,volunteer,volunteer-edit}.html.twig
│   └── static/                       # Static content pages with no domain logic
│       ├── about.html.twig
│       ├── data-sovereignty.html.twig
│       ├── get-involved.html.twig
│       ├── how-it-works.html.twig
│       ├── journey.html.twig
│       ├── matcher.html.twig
│       ├── messages.html.twig
│       ├── safety.html.twig
│       ├── studio.html.twig
│       └── volunteer.html.twig
├── components/                       # Reusable partials (never extend base)
│   ├── shared/                       # Cross-domain fragments
│   │   ├── layout/                   # sidebar-nav, location-bar, breadcrumb, hero-image
│   │   ├── ui/                       # chat, language-switcher, pagination, theme-toggle, user-role-card
│   │   ├── data/                     # search-result-card, empty-state, protocol-notice
│   │   └── feedback/                 # flash-messages
│   └── domain/                       # Domain-scoped partials
│       ├── events/                   # card, list, feed, feed-section, filters, calendar
│       ├── teachings/                # card
│       ├── groups/                   # card
│       ├── people/                   # card
│       ├── businesses/               # card
│       ├── communities/              # contact-card
│       ├── feed/                     # card, create-post, engagement, sidebar-right
│       ├── language/                 # dictionary-entry-card
│       └── oral-histories/           # collection-card, card
├── email/                            # Mail bodies (framework AuthMailer renders these)
│   ├── welcome.{html,txt}.twig
│   ├── password-reset.{html,txt}.twig
│   └── email-verification.{html,txt}.twig
├── 403.html.twig                     # Framework hardcodes this path — do not move
└── 404.html.twig                     # Framework hardcodes this path — do not move
```

### Framework constraint: error pages
`vendor/waaseyaa/ssr/src/RenderController.php` hardcodes the paths `404.html.twig` and `403.html.twig` at the repo root. Do not relocate these two files into `pages/`.

## Inheritance

All page templates extend `layouts/base.html.twig` and override blocks:

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

## Routing

Controllers call `$this->twig->render('pages/<domain>/<view>.html.twig', ...)` explicitly. There is no path-based template auto-resolution. Each route in `App\Provider\AppServiceProvider` maps to a controller method; the method chooses its template path.

Static pages with no domain logic are served through `StaticPageController`, which renders templates from `pages/static/` or the appropriate `pages/<domain>/` folder.

## Component Conventions

Components live under `templates/components/`:

- `shared/` — Partials used across multiple domains. Grouped by role: `layout/`, `ui/`, `data/`, `feedback/`.
- `domain/<name>/` — Partials scoped to a specific entity domain.

Include with explicit `with`:

```twig
{% include "components/domain/events/card.html.twig" with {
  title: event.get('title'),
  type: event.get('type'),
  date: event.get('starts_at'),
  url: "/events/" ~ event.get('slug'),
} %}
```

## CSS Architecture

`public/css/minoo.css` is a **manifest** — no rules, only the layer declaration and `@import` statements. Each imported file owns one layer (or the un-layered remainder):

```
public/css/
├── minoo.css          # Manifest: @layer order + @imports
├── reset.css          # @layer reset
├── tokens.css         # @layer tokens  (:root, [data-theme="light"], prefers-color-scheme)
├── base.css           # @layer base
├── layout.css         # @layer layout
├── components.css     # @layer components
├── utilities.css      # @layer utilities
└── orphans.css        # Un-layered rules (see below)
```

### Manifest

```css
@layer reset, tokens, base, layout, components, utilities;

@import "reset.css";
@import "tokens.css";
@import "base.css";
@import "layout.css";
@import "components.css";
@import "utilities.css";
@import "orphans.css";
```

### orphans.css

Rules that exist outside any `@layer` block in the pre-split history live in `orphans.css` — `@font-face` declarations plus a few legacy unlayered rule sets and `@media print` blocks. They are imported **after** the layered files so that, being un-layered, they retain the highest cascade priority (same behavior as before the split). Do not move rules into or out of `orphans.css` without understanding the cascade implications.

### Layer Contents

| Layer | Purpose |
|-------|---------|
| `reset` | Box-sizing, margin reset, media elements, font inheritance |
| `tokens` | Design tokens: colors, typography, spacing, widths, radii, shadows |
| `base` | Body, headings, links, code, lists — use tokens only |
| `layout` | Page shell: header, sidebar, grid surfaces, app shell |
| `components` | Cards, detail views, filters, pagination, domain UI blocks |
| `utilities` | `.flow`, `.flow-lg`, `.sr-only`, single-purpose helpers |

### Design Tokens

OKLCH earth / forest / water / sun / berry palette with shadcn-style Tier-2 semantic aliases (`--background`, `--foreground`, `--muted`, `--card`, `--popover`, `--destructive`, `--ring`, `--input`). Typography uses fluid `clamp()` scales; spacing uses a 1.5-ratio fluid scale; widths are exposed as `--width-prose`, `--width-content`, `--width-narrow`, `--width-card`.

### CSS Conventions

- Logical properties only (`margin-block`, `padding-inline`) — no `left`/`right`.
- `gap` for sibling spacing.
- Native CSS nesting.
- Container queries on components; media queries reserved for page shell.
- No `!important`.

### Cache Busting

`base.html.twig` references the manifest with a version query: `<link rel="stylesheet" href="/css/minoo.css?v=N">`. Bump `N` after any CSS change.

## Smoke Test Routes

After substantial changes, hit the key SSR routes:

- Public / feed: `/`, `/feed`, `/home`
- Listings and detail: `/events`, `/events/{slug}`, `/groups`, `/teachings`, `/teachings/{slug}`, `/people`, `/businesses`, `/communities`, `/communities/{slug}`, `/contributors`
- Language / oral histories: `/language`, `/oral-histories`
- Games: `/games`, `/shkoda`, `/games/shkoda`, `/agim`, `/games/agim`, `/crossword`, `/games/crossword`, `/matcher`, `/games/matcher`, `/journey`, `/games/journey`
- Elders: `/elders`, `/elders/request`, `/elders/volunteer`
- Static: `/about`, `/how-it-works`, `/data-sovereignty`, `/safety`, `/get-involved`, `/journey`, `/matcher`, `/messages`, `/studio`, `/volunteer`
- Legal: `/legal`, `/legal/privacy`, `/legal/terms`, `/legal/accessibility`
- Newsletter: `/newsletter`, `/newsletter/{slug}`, `/newsletter/submit`, editor routes
- Account / dashboards: `/account`, `/dashboard/volunteer`, `/dashboard/coordinator`, `/admin/users`, `/admin/ingestion`
- Auth: `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`
- Errors: force 403 / 404 scenarios

## Edge Cases

- `403.html.twig` and `404.html.twig` must stay at the templates root (framework constraint).
- Mail templates under `templates/email/` are rendered by `vendor/waaseyaa/user/src/AuthMailer.php` — keep the paths stable.
- Games have two routes each (`/<game>` and `/games/<game>`) registered with distinct names (`games.<name>` and `games.<name>.short`).
