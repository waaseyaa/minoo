> **Superseded:** This design was replaced by the Regalia redesign (2026-03-15). See `docs/superpowers/specs/2026-03-15-regalia-redesign-design.md`.

# Minoo Visual Identity & Global Layout Design

Issues: #5 (visual identity), #9 (global navigation and layout)

## Approach

Vanilla CSS with custom properties. No build step — ship a single `minoo.css` for the Twig SSR public site. Modern CSS features throughout: `oklch()`, `clamp()`, native nesting, `@layer`, logical properties, container queries.

## CSS Layer Structure

```css
@layer reset, tokens, base, layout, components, utilities;
```

| Layer | Purpose |
|-------|---------|
| `reset` | Minimal modern reset (box-sizing, margin strip, img max-width, inherit fonts on inputs) |
| `tokens` | All custom properties in `:root`, dark-mode swaps semantic aliases only |
| `base` | Element defaults (body, headings, links, lists) using tokens and logical properties |
| `layout` | Page shell: `.site-header`, `.site-main`, `.site-footer`, content wrapper |
| `components` | Cards, nav, entity displays, search results — scoped with `container-type: inline-size` |
| `utilities` | Minimal: `.visually-hidden`, `.flow > * + *`, `.compact` density modifier |

## Color Palette (oklch)

Earth tones grounded in the natural world — forest, water, stone, sky.

### Scale Colors

| Token | Value | Description |
|-------|-------|-------------|
| `--color-earth-900` | `oklch(0.25 0.02 70)` | Deep soil — primary text |
| `--color-earth-700` | `oklch(0.40 0.02 70)` | Worn bark — secondary text |
| `--color-earth-200` | `oklch(0.88 0.01 70)` | Dry clay — borders |
| `--color-earth-100` | `oklch(0.94 0.01 70)` | Sand — subtle backgrounds |
| `--color-earth-50`  | `oklch(0.97 0.005 70)` | Birch — page background |
| `--color-forest-700` | `oklch(0.40 0.08 155)` | Deep spruce — primary accent |
| `--color-forest-500` | `oklch(0.55 0.10 155)` | Cedar — links, interactive |
| `--color-forest-100` | `oklch(0.92 0.03 155)` | Sage — accent background |
| `--color-water-600` | `oklch(0.50 0.08 230)` | Lake — secondary accent |
| `--color-water-100` | `oklch(0.92 0.03 230)` | Mist — info backgrounds |
| `--color-sun-500`   | `oklch(0.70 0.12 85)` | Amber — warnings, highlights |
| `--color-berry-600` | `oklch(0.50 0.12 10)` | Cranberry — errors, destructive |

### Semantic Aliases

```
--text-primary:   var(--color-earth-900)
--text-secondary: var(--color-earth-700)
--surface:        var(--color-earth-50)
--surface-raised: white
--border:         var(--color-earth-200)
--accent:         var(--color-forest-500)
--accent-surface: var(--color-forest-100)
--link:           var(--color-forest-500)
--error:          var(--color-berry-600)
--warning:        var(--color-sun-500)
--info:           var(--color-water-600)
```

## Typography

System font stack with quality serif for headings. Fluid minor-third (1.2) scale from 320px to 1200px.

```
--font-body:    system-ui, -apple-system, sans-serif
--font-heading: Charter, 'Bitstream Charter', 'Sitka Text', Cambria, serif
--font-mono:    ui-monospace, 'Cascadia Code', 'Source Code Pro', monospace

--text-sm:   clamp(0.833rem, 0.816rem + 0.08vw, 0.889rem)
--text-base: clamp(1rem, 0.964rem + 0.18vw, 1.125rem)
--text-lg:   clamp(1.2rem, 1.132rem + 0.34vw, 1.406rem)
--text-xl:   clamp(1.44rem, 1.318rem + 0.61vw, 1.758rem)
--text-2xl:  clamp(1.728rem, 1.525rem + 1.01vw, 2.197rem)
--text-3xl:  clamp(2.074rem, 1.753rem + 1.6vw, 2.747rem)

--leading-tight:  1.2
--leading-normal: 1.6
--leading-loose:  1.8

--tracking-tight:  -0.01em
--tracking-normal: 0
--tracking-wide:   0.02em
```

## Spacing & Layout

Fluid 1.5-ratio scale. Baseline grid alignment.

```
--baseline: 0.25rem

--space-3xs: clamp(0.25rem, 0.23rem + 0.11vw, 0.313rem)
--space-2xs: clamp(0.5rem, 0.46rem + 0.23vw, 0.625rem)
--space-xs:  clamp(0.75rem, 0.68rem + 0.34vw, 0.938rem)
--space-sm:  clamp(1rem, 0.91rem + 0.45vw, 1.25rem)
--space-md:  clamp(1.5rem, 1.36rem + 0.68vw, 1.875rem)
--space-lg:  clamp(2rem, 1.82rem + 0.91vw, 2.5rem)
--space-xl:  clamp(3rem, 2.73rem + 1.36vw, 3.75rem)
--space-2xl: clamp(4rem, 3.64rem + 1.82vw, 5rem)

--gutter: var(--space-sm)

--width-prose:   65ch
--width-content: 80rem
--width-narrow:  40rem
--width-card:    25rem

--radius-sm:   0.25rem
--radius-md:   0.5rem
--radius-lg:   1rem
--radius-full: 9999px

--shadow-sm: 0 1px 2px oklch(0.25 0.02 70 / 0.06)
--shadow-md: 0 2px 4px oklch(0.25 0.02 70 / 0.08), 0 4px 12px oklch(0.25 0.02 70 / 0.04)
--shadow-lg: 0 4px 8px oklch(0.25 0.02 70 / 0.08), 0 8px 24px oklch(0.25 0.02 70 / 0.06)
```

## Breakpoints

In `em` for zoom-resilience:

```
--bp-sm: 40em   /* 640px */
--bp-md: 60em   /* 960px */
--bp-lg: 80em   /* 1280px */
```

Media queries for page-level layout shifts only. Container queries for component-level responsiveness.

## Density Modifier

```css
.compact { --space-scale: 0.6; }
```

Reduces spacing for list-heavy pages (dictionary search results, event listings).

## Template Targets

The initial Twig templates this design maps onto:

### Page Shell (`templates/base.html.twig`)
- `<link>` to `public/css/minoo.css`
- Semantic landmarks: `<header>`, `<main>`, `<footer>`
- Content wrapper constrained to `--width-content` with `--gutter` padding

### Site Header (`templates/partials/header.html.twig`)
- Site name/logo, primary navigation
- Responsive: hamburger menu below `--bp-md`

### Site Footer (`templates/partials/footer.html.twig`)
- Copyright, links, minimal

### Navigation (`templates/partials/nav.html.twig`)
- Top-level domain links: Events, Groups, Teachings, Language
- Active state using `--accent`

### First Component: Dictionary Entry Card
- Constrained to `--width-card`
- Uses `container-type: inline-size` for internal layout adaptation
- Demonstrates the full token system in a real component

## Design Principles

- **Logical properties only** — `margin-block`, `padding-inline`, never `left`/`right`
- **`gap` for spacing** — no margin hacks between siblings
- **Native CSS nesting** — no BEM, no preprocessor
- **Container queries on components** — media queries only for page shell
- **Dark-mode ready** — swap `@layer tokens` semantic aliases, scale colors untouched
- **Tailwind-compatible** — tokens map cleanly to utility classes if added later
- **No build step** — single CSS file served statically
