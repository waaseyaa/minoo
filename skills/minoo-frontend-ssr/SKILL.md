---
name: minoo:frontend-ssr
description: Use when working on Minoo templates, CSS design system, or SSR rendering in templates/, public/css/, or src/Controller/ render methods
---

# Minoo Frontend / SSR Specialist

## Scope

Files: `templates/`, `public/css/*.css`, SSR rendering in `src/Controller/`
Tests: Playwright E2E in `e2e/`

## Directory Layout

```
templates/
├── layouts/base.html.twig              # Page shell (only layout; extended by all pages)
├── pages/<domain>/<view>.html.twig     # Route-level templates
│   └── static/                         # Static content pages (about, how-it-works, etc.)
├── components/
│   ├── shared/{layout,ui,data,feedback}/   # Cross-domain partials
│   └── domain/<name>/                      # Domain-scoped partials
├── email/                              # Mail bodies rendered by framework AuthMailer
├── 403.html.twig                       # Framework hardcodes path — do not move
└── 404.html.twig                       # Framework hardcodes path — do not move
```

**Framework constraint:** `vendor/waaseyaa/ssr/src/RenderController.php` hardcodes `403.html.twig` and `404.html.twig` at the templates root. Do not relocate.

## Routing

**No path-based template auto-resolution.** Controllers call `$this->twig->render('pages/<domain>/<view>.html.twig', ...)` explicitly. Each route in `App\Provider\AppServiceProvider` maps to a controller method that chooses its template path.

Static pages with no domain logic are served through `StaticPageController`, which renders templates from `pages/static/` or the appropriate `pages/<domain>/` folder.

## Template Inheritance

All page templates extend `layouts/base.html.twig` and override blocks:

```twig
{% extends "layouts/base.html.twig" %}
{% block title %}Page Title{% endblock %}
{% block content %}
  {# Page content here #}
{% endblock %}
```

Available blocks: `title`, `meta_description`, `og_title`, `og_description`, `og_image`, `og_type`, `head`, `content`, `scripts`.

## Component Conventions

Components live under `templates/components/`:

- `shared/layout/` — sidebar-nav, location-bar, breadcrumb, hero-image
- `shared/ui/` — chat, language-switcher, pagination, theme-toggle, user-role-card
- `shared/data/` — search-result-card, empty-state, protocol-notice
- `shared/feedback/` — flash-messages
- `domain/<name>/` — cards, filters, and domain-scoped partials (events/, teachings/, groups/, people/, businesses/, communities/, feed/, language/, oral-histories/)

Include components with explicit `with` and `only`:

```twig
{% include "components/domain/events/card.html.twig" with {
  title: event.get('title'),
  type: event.get('type'),
  date: event.get('starts_at'),
  url: "/events/" ~ event.get('slug'),
} only %}
```

## CSS Architecture

`public/css/minoo.css` is a **manifest** — no rules, only the layer declaration and `@import` statements.

```
public/css/
├── minoo.css          # Manifest: @layer order + @imports
├── reset.css          # @layer reset
├── tokens.css         # @layer tokens
├── base.css           # @layer base
├── layout.css         # @layer layout
├── components.css     # @layer components
├── utilities.css      # @layer utilities
└── orphans.css        # Un-layered rules (font-face, legacy rules, @media print)
```

**Manifest content:**
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

**orphans.css:** Rules that existed outside any `@layer` block in the pre-split history — `@font-face` declarations, a few legacy unlayered rule sets, and `@media print` blocks. Imported **after** the layered files so they retain highest cascade priority (same behavior as before the split). Do not move rules in or out without understanding the cascade implications.

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

OKLCH earth / forest / water / sun / berry palette with shadcn-style Tier-2 semantic aliases (`--background`, `--foreground`, `--muted`, `--card`, `--popover`, `--destructive`, `--ring`, `--input`). Typography uses fluid `clamp()` scales; spacing uses a 1.5-ratio fluid scale; widths: `--width-prose: 65ch`, `--width-content: 80rem`, `--width-narrow: 40rem`, `--width-card: 25rem`.

### CSS Conventions

- Logical properties only (`margin-block`, `padding-inline`) — no `left`/`right`
- `gap` for sibling spacing
- Native CSS nesting
- Container queries on components; media queries reserved for page shell
- No `!important`

### Cache Busting

`layouts/base.html.twig` references the manifest with a version query: `<link rel="stylesheet" href="/css/minoo.css?v=N">`. Bump `N` after any CSS change.

## Component Patterns

**Cards:** `.card` with `container-type: inline-size`. Variants: `.card--event`, `.card--group`, `.card--community`, `.card--teaching`, `.card--language`, `.card--person`, `.card--elder`, `.card--detail`, `.card--dashboard`. Parts: `.card__badge`, `.card__title`, `.card__meta`, `.card__body`, `.card__tags`. Grid: `.card-grid`.

**Forms:** `.form` > `.form__field` > `.form__label` + `.form__input`. Error state: `.form__input--error` + `.form__error[role="alert"]`. Buttons: `.form__submit--primary`, `--secondary`, `--danger`, `--sm`.

**Buttons:** `.btn` with `--primary`, `--secondary`, `--accent`, `--ghost`, `--lg`.

**Layout:** `.flow` / `.flow-lg` for vertical rhythm, `.prose` for readable text, `.content-section` for page sections.

**Detail pages:** `.detail` > `.detail__back`, `.detail__header`, `.detail__meta`, `.detail__body`.

## Common Twig Patterns

Entity field access via `.get()`:
```twig
{{ item.get('title') }}
{% if item.get('status') == 1 %}Published{% endif %}
```

Active nav via `aria-current`:
```twig
<a href="/events"{% if path starts with '/events' %} aria-current="page"{% endif %}>
```

## Common Mistakes

- **Moving 403/404 out of templates root** — framework hardcodes these paths; moves break error handling
- **Dot notation on entities** — use `entity.get('field')`, not `entity.field`
- **CSS `left`/`right`** — always use logical properties (`margin-inline-start`, `padding-block-end`)
- **`margin` for spacing** — use `gap` in flex/grid; `.flow` pattern for vertical stacks
- **Media queries on components** — use container queries (`@container`); media queries only for page shell
- **Missing `path` variable** — every SSR controller must pass `path` in `LayoutTwigContext::withAccount(...)`
- **Editing `minoo.css` directly** — it's a manifest; add rules to the matching layer file (`components.css`, `layout.css`, etc.)
- **Moving rules into/out of `orphans.css`** — unlayered rules outrank all `@layer` rules; understand the cascade impact before touching
- **Forgetting the `?v=N` bump** — stale CSS after deploy is the #1 "it looks broken" cause
- **Duplicate route names in `AppServiceProvider`** — later registration silently wins. Games use `games.<name>` for `/games/<name>` and `games.<name>.short` for `/<name>`

## Related Specs

- `docs/specs/frontend-ssr.md` — authoritative directory layout, inheritance, CSS architecture, smoke test routes
- Framework: `waaseyaa_get_spec api-layer` — Response types, RenderController
