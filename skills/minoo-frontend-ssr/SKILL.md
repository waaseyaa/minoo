---
name: minoo:frontend-ssr
description: Use when working on Minoo templates, CSS design system, or SSR rendering in templates/, public/css/, or src/Controller/ render methods
---

# Minoo Frontend / SSR Specialist

## Scope

Files: `templates/`, `public/css/minoo.css`, SSR rendering in `src/Controller/`
Tests: Playwright E2E in `e2e/`

> **Note — restructure in progress.** A `layouts/ + pages/<domain>/ + components/{shared,domain}/` reorganization is planned per the 2026-04-17 validation report. Phases 0 + 1 are complete (Tier-2 semantic tokens + collapsed `@layer components` + explicit routes for previously path-resolved static pages). Template and component directories have **not yet moved** — continue following the current conventions documented below.

## Template Architecture

All templates extend `base.html.twig` which defines the page shell (`.site` > `.site-header` + `.site-main` + `.site-footer`). It exposes nine blocks:
`title`, `meta_description`, `og_title`, `og_description`, `og_image`, `og_type`, `head`, `content`, `scripts`.

```twig
{% extends "base.html.twig" %}
{% block title %}Page Title{% endblock %}
{% block content %}
  {# Page content here #}
{% endblock %}
```

**Path-based routing:** Framework `RenderController::tryRenderPathTemplate()` maps `/path` to `path.html.twig`. Multi-segment paths (e.g. `/events/slug`) fall back to first segment (`events.html.twig`) with full `path` variable available.

**Listing+detail in one template:** Use conditionals inside `{% block content %}`:
```twig
{% block content %}
  {% set slug = path|split('/')|last %}
  {% if slug != 'events' %}
    {# Detail view #}
  {% else %}
    {# List view #}
  {% endif %}
{% endblock %}
```

## Template Map

| Template | Purpose | Key variables |
|----------|---------|---------------|
| `base.html.twig` | Shell: header, nav, footer, location bar | `path`, `account` |
| `page.html.twig` | Home page | `events`, `communities`, `location` |
| `events.html.twig` | Events list + detail | `events`, `path` |
| `groups.html.twig` | Groups list + detail | `groups`, `path` |
| `teachings.html.twig` | Teachings list + detail | `teachings`, `path` |
| `communities.html.twig` | Communities list + detail | `communities`, `path`, `location` |
| `people.html.twig` | Resource people list + detail | `people`, `path` |
| `language.html.twig` | Dictionary demo | `entries`, `path` |
| `search.html.twig` | Search results | `results`, `query` |
| `elders.html.twig` | Elder support landing | — |
| `elders/request.html.twig` | Support request form | `errors`, `values` |
| `elders/volunteer.html.twig` | Volunteer signup form | `errors`, `values` |
| `volunteer.html.twig` | Volunteer info page | — |
| `legal.html.twig` | Legal/privacy page | — |
| `auth/login.html.twig` | Login form | `errors`, `values` |
| `auth/register.html.twig` | Register form | `errors`, `values` |
| `dashboard/volunteer.html.twig` | Volunteer dashboard | `requests`, `volunteer` |
| `dashboard/coordinator.html.twig` | Coordinator dashboard | `requests`, `volunteers` |
| `404.html.twig` | Not found | `path` |
| `components/*.html.twig` | Reusable card partials | varies per card |

## CSS Design System

Single file `public/css/minoo.css` (~9500 lines) — no build step, no preprocessor.

**Layer order:** `@layer reset, tokens, base, layout, components, utilities;` — **must be the first non-`@font-face` statement in the file** to pin cascade priority before any `@layer` block opens. All component rules live in a **single** `@layer components { ... }` block (collapsed in Phase 0).

**Color palette (OKLCH):**
- Earth tones: `--color-earth-{50,100,200,700,900}` — primary neutrals
- Forest: `--color-forest-{100,500,700}` — nature green
- Water: `--color-water-{100,600}` — blue accent
- Sun: `--color-sun-500` — warm accent
- Berry: `--color-berry-600` — error/danger

**Semantic tokens (Tier 2, existing):** `--text-primary`, `--text-secondary`, `--surface`, `--surface-raised`, `--border`, `--accent`, `--link`, `--error`, `--warning`, `--info`, `--success`
**Semantic tokens (Tier 2, shadcn-style — added Phase 0):** `--background`, `--foreground`, `--surface-default`, `--surface-sunken`, `--surface-overlay`, `--text-inverse`, `--border-default`, `--border-strong`, `--accent-default`, `--accent-hover`, `--accent-foreground`, `--muted`, `--muted-default`, `--muted-foreground`, `--card`, `--card-foreground`, `--popover`, `--popover-foreground`, `--destructive`, `--destructive-foreground`, `--input`, `--ring`
**Domain tokens:** `--domain-events`, `--domain-groups`, `--domain-teachings`, `--domain-language`, `--domain-people`, `--domain-elders`, `--domain-businesses`, `--domain-communities`, `--domain-newsletter`, `--domain-feed`, `--domain-search`

**Type scale (fluid clamp):** `--text-sm` through `--text-3xl`
**Space scale (fluid clamp):** `--space-3xs` through `--space-2xl`, `--gutter: var(--space-sm)`
**Width tokens:** `--width-prose: 65ch`, `--width-content: 80rem`, `--width-narrow: 40rem`, `--width-card: 25rem`

## Component Patterns

**Cards:** `.card` with `container-type: inline-size` for container queries.
- Variants: `.card--event`, `.card--group`, `.card--community`, `.card--teaching`, `.card--language`, `.card--person`, `.card--elder`, `.card--detail`, `.card--dashboard`
- Parts: `.card__badge`, `.card__title`, `.card__meta`, `.card__body`, `.card__tags`
- Grid: `.card-grid` with staggered entry animations

**Forms:** `.form` > `.form__field` > `.form__label` + `.form__input`
- Error state: `.form__input--error` + `.form__error[role="alert"]`
- Buttons: `.form__submit--primary`, `--secondary`, `--danger`, `--sm`
- Fieldsets: `.form__fieldset`, `.form__checkboxes`, `.form__checkbox-label`

**Buttons:** `.btn` with `--primary`, `--secondary`, `--accent`, `--ghost`, `--lg`

**Layout:** `.flow` / `.flow-lg` for vertical rhythm (`> * + *` margin pattern), `.prose` for readable text, `.content-section` for page sections

**Detail pages:** `.detail` > `.detail__back`, `.detail__header`, `.detail__meta`, `.detail__body`

## Common Twig Patterns

Entity field access via `.get()`:
```twig
{{ item.get('title') }}
{% if item.get('status') == 1 %}Published{% endif %}
```

Component includes:
```twig
{% for event in events %}
  {% include "components/event-card.html.twig" with {
    title: event.get('title'),
    type: event.get('type'),
    slug: event.get('slug'),
  } %}
{% endfor %}
```

Type label maps with fallback:
```twig
{% set type_labels = {powwow: 'Powwow', gathering: 'Gathering'} %}
{{ type_labels[value]|default(value|replace({'_': ' '})|capitalize) }}
```

Active nav via `aria-current`:
```twig
<a href="/events"{% if path starts with '/events' %} aria-current="page"{% endif %}>
```

## Common Mistakes

- **Multiple blocks with same name**: Use conditionals inside the block, not multiple blocks in conditionals
- **`{% set %}` outside block**: Variables set outside `{% block %}` are not available inside it
- **Dot notation on entities**: Use `entity.get('field')`, not `entity.field`
- **CSS `left`/`right`**: Always use logical properties (`margin-inline-start`, `padding-block-end`)
- **CSS `margin` for spacing**: Use `gap` in flex/grid contexts; `.flow` pattern for vertical stacks
- **Media queries on components**: Use container queries (`@container`) on components; media queries only for page shell
- **Missing `path` variable**: Every SSR controller must pass `path` to the template context
- **`@layer` brace mismatch (#273)**: A premature `}` can close `@layer components` early, leaving subsequent styles unlayered (which outranks all layers in the cascade). Always verify brace balance when editing `minoo.css`
- **Path-based template routing**: `tryRenderPathTemplate()` matches single segments exactly and falls back to the first segment for multi-segment paths (e.g. `/events/slug` renders `events.html.twig` with `path` set to `/events/slug`). Requires framework#189
- **Listing+detail pattern**: Use path conditionals _inside_ `{% block content %}` — `{% set %}` must be inside the block, and only one `{% block %}` per name (use conditionals inside the block, not multiple blocks in conditionals)

## Related Specs

- `docs/specs/frontend-ssr.md` — full CSS token values, template conventions, component catalog
- Framework: `waaseyaa_get_spec api-layer` — SsrResponse, RenderController
