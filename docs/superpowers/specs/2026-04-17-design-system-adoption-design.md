# Design System Full Adoption

**Date:** 2026-04-17
**Source:** `Minoo Live Design System` handoff bundle (Claude Design export)
**Approach:** Incremental layer-by-layer migration (Approach B)

## Summary

Adopt the full Minoo Live Design System into the production codebase. The design introduces an app-shell layout (sticky header + 240px sidebar + main content area + footer with ribbon), refined component patterns, and updated typography/token foundations. The current site's header-based flow layout is replaced with a sidebar-driven navigation model.

## Source of Truth

The handoff bundle contains:
- `colors_and_type.css` — brand tokens (already ~95% aligned with current `minoo.css` tokens layer)
- `kit.css` — component CSS for app shell, cards, badges, buttons, hero, detail pages, filters, messages
- `ui_kits/web/*.jsx` — React prototypes showing component structure and data flow
- `project/SKILL.md` — design system rules and fast-path orientation
- `project/assets/` — favicon, OG images, wordmark (already deployed)
- `project/fonts/` — Fraunces + DM Sans variable woff2 (already in `public/fonts/`)

## Phase 1: Token & Font-Face Updates (additive, nothing breaks)

### Font-face additions
- Add `@font-face` for Fraunces italic (100-900, `fraunces-italic-variable.woff2`)
- Add `@font-face` for DM Sans (100-1000, `dm-sans-variable.woff2`)
- Update existing Fraunces normal range from `400 900` to `100 900`
- Preload for DM Sans already exists in `base.html.twig`

### New tokens to add to `:root`
- `--surface` (alias): `var(--surface-dark)` — semantic shorthand
- `--border` (alias): `var(--border-dark)`
- `--accent`: `var(--color-events)`
- `--link`: `var(--color-events)`
- `--error`: `#ff4d5a`
- `--warning`: `var(--color-teachings)`
- `--info`: `var(--color-communities)`
- `--success`: `var(--color-language)`
- `--font-body`: `'DM Sans', system-ui, -apple-system, sans-serif`
- `--font-heading`: `'Fraunces', Charter, 'Bitstream Charter', Cambria, serif`
- `--font-mono`: `ui-monospace, 'Cascadia Code', 'Source Code Pro', monospace`
- `--leading-tight`: `1.2`
- `--leading-normal`: `1.6`
- `--leading-loose`: `1.8`
- `--tracking-tight`: `-0.01em`
- `--tracking-wide`: `0.02em`
- `--space-3xs`: `clamp(0.25rem, 0.23rem + 0.11vw, 0.313rem)`

### Verify existing tokens match
Already aligned: all color tokens, surfaces, text colors, borders, spacing (xs through 2xl), radii, shadows, motion, widths. No changes needed.

## Phase 2: App Shell Layout (base.html.twig restructure)

### New CSS components to add

```
.app          — min-height: 100vh; flex column
.app__body    — grid: 240px sidebar + 1fr main
.app__main    — padding, max-width, centered

.hdr          — sticky header, border-bottom, z-100
.hdr__bar     — flex row: menu button, logo, search, locale, theme toggle, avatar
.hdr__menu    — hamburger button
.hdr__logo    — 22px wordmark, dark mode invert filter
.hdr__search  — pill-shaped search input
.hdr__locale  — EN/OJ language toggle
.hdr__theme   — sun/moon theme toggle
.hdr__avatar  — 32px circle with initial

.sbx          — sidebar nav, border-right
.sbx__group   — nav group with label
.sbx__label   — uppercase 11px group header
.sbx__item    — nav link: icon + label, hover/active states
.sbx__item--active — domain-colored left border + text

.ftr          — footer with ribbon above
.ftr__inner   — flex row: logo+copyright | nav links
```

### Template changes

**`base.html.twig`:**
- Wrap everything in `<div class="app">`
- Add `<header class="hdr">` with ribbon below
- Add sidebar `<nav class="sbx">` inside `<div class="app__body">`
- Wrap `{% block content %}` in `<main class="app__main">`
- Add `<footer class="ftr">` with ribbon above

**`templates/components/sidebar-nav.html.twig`:**
- Restructure to use `.sbx` classes
- Groups: "Explore" (Home, Communities, Events, Teachings, People, Businesses, Oral Histories) and "You" (Messages, Games)
- Active state via current route matching

**`templates/components/theme-toggle.html.twig`:**
- Update to `.hdr__theme` pattern

## Phase 3: Homepage Migration

### CSS additions
```
.hero          — padding, max-width 65ch
.hero__eyebrow — 11px uppercase, muted color
.hero h1 em    — italic in teachings color (signature move)
.hero__ctas    — flex row of buttons

.sec           — section with top margin
.sec__head     — flex row: h2 + "View all" link, border-bottom
.grid          — auto-fill grid, minmax(280px, 1fr)
```

### Card system update
```
.card          — 3px left border in domain color (signature element)
.card__eyebrow — domain-colored uppercase label
.card__title   — Fraunces heading
.card__meta    — secondary text
.card__body    — body text

.card--media   — variant with thumbnail area
.card__thumb   — 16:10 aspect ratio placeholder
.card__content — padding below thumb
```

### Badge component
```
.badge — inline pill with domain-tinted bg + fg color
```

### Template changes

**`home.html.twig`:**
- Replace `home-hero` with `hero` section: eyebrow "Aanii . Welcome", h1 with italic `<em>community</em>`, subtitle, CTA buttons
- Replace `home-section` blocks with `sec` + `sec__head` pattern
- "Upcoming Events" section with `MediaCard` grid
- "Recent Teachings" section with `Card` grid
- "Our Communities" section with `Card` grid

## Phase 4: Events & Teachings Pages

### CSS additions
```
.detail        — detail page container
.detail__hero  — 21:9 hero image/gradient area
.detail__meta  — icon + text metadata row
.detail__layout — 2-column grid: body + 280px aside
.detail__body  — prose content area
.detail__aside — sticky sidebar

.aside-card    — info card in aside column
.keeper        — person attribution: avatar + name + role
.keeper__avatar — 40px circle with domain color bg
.keeper__name  — bold name
.keeper__role  — muted role text

.filters       — flex row of filter chips
.chip          — bordered pill button
.chip--active  — filled with domain color
```

### Template changes

**`events.html.twig`:**
- Add hero section with location eyebrow
- Use `.filters` + `.chip` for event type filtering
- Use `.grid` with `.card--media` for event listing
- Event detail: `.detail__hero` + `.detail__layout` with `.aside-card` for host community info

**`teachings.html.twig`:**
- Similar hero + grid pattern
- Teaching detail: Knowledge Keeper attribution in `.aside-card`, audio player placeholder

## Phase 5: Cleanup

- Remove dead CSS classes (`home-hero`, `home-section`, old card variants)
- Bump CSS cache version in `base.html.twig`
- Verify all pages render correctly in both themes

## Design Rules (from SKILL.md)

- **Ribbon:** 3px, five hard color stops (events/teachings/language/communities/people). Never gradient.
- **Domain colors are meaningful:** Each content type owns one ribbon color. Shows as badge bg, card left-border, hover accent.
- **Typography:** Fraunces italic for emphasis on key nouns in headlines. DM Sans for body.
- **Dark is default.** Light mode is warm cream `#f5f0eb`, not white.
- **Cards:** 3px left border in domain color is the signature pattern.
- **No emoji** in product UI.
- **Hover:** cards translate Y -1px with shadow upgrade. Buttons darken. Links thicken underline.
- **Focus:** 2px solid accent outline, 2px offset.

## Out of Scope

- Messages page (`.msgs`, `.bubble`, `.composer`) — existing messages implementation differs significantly, defer to separate PR
- Watch Live page — no backend support yet
- Mobile sidebar collapse — will need JS toggle, defer to follow-up
- Icon system migration — keep existing inline SVG approach, don't switch to Lucide CDN

## Files Modified

- `public/css/minoo.css` — token updates, new component CSS, dead class removal
- `templates/base.html.twig` — app shell restructure
- `templates/home.html.twig` — homepage redesign
- `templates/events.html.twig` — events page update
- `templates/teachings.html.twig` — teachings page update (if detail view exists)
- `templates/components/sidebar-nav.html.twig` — sidebar restructure
- `templates/components/event-card.html.twig` — card pattern update
- `templates/components/teaching-card.html.twig` — card pattern update
