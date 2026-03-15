# Minoo Redesign: Regalia

**Date:** 2026-03-15
**Status:** Approved
**Scope:** Full visual redesign of minoo.css and all Twig templates

## Problem

As Minoo grew from a handful of pages to 17 templates with 12 components, the original earth-tone design became homogeneous. Cards, listings, and detail pages blur together. The aesthetic feels safe and generic — not distinctive enough for an Indigenous knowledge platform.

## Design Direction

**Regalia** — dark canvas with vivid ceremony-color accents. Modern Indigenous pride: bold, confident, contemporary. Content commands attention against darkness, like regalia against the night.

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Aesthetic | Regalia — dark canvas, vivid accents | Bold and distinctive; differentiates from generic "warm neutral" platforms |
| Content strategy | Adaptive — dark listings, light reading wells | Dark pages for browsing/discovery, warm light wells for long-form reading comfort |
| Typography | Fraunces (display) + DM Sans (body/UI) | Fraunces has warmth and personality at display sizes; DM Sans is clean and confident; both are Google Fonts with variable weight support |
| Color system | Ceremony Colors — 7 domain-specific accents | Each content domain gets its own color identity, solving the "everything looks the same" problem |

## Color System

### Surfaces

| Token | Value | Usage |
|-------|-------|-------|
| `--surface-dark` | `#0a0a0a` | Page background (listings, homepage) |
| `--surface-card` | `#141414` | Card backgrounds |
| `--surface-raised` | `#1a1a1a` | Elevated elements (search bar, filters, pills) |
| `--surface-light` | `#f5f0eb` | Content well background (detail pages) |
| `--surface-light-card` | `#fff` | Cards within light content wells |

### Text

| Token | Value | Usage |
|-------|-------|-------|
| `--text-primary` | `#f0ece6` | Primary text on dark surfaces |
| `--text-secondary` | `#aaa` | Secondary text, metadata (5.3:1 on dark — WCAG AA) |
| `--text-muted` | `#777` | Tertiary text, placeholders (3.5:1 on dark — AA large text) |
| `--text-dark` | `#1a1a1a` | Primary text in light content wells |
| `--text-dark-secondary` | `#666` | Secondary text in light content wells (different from --text-muted to avoid confusion) |

### Domain Accent Colors

Each content domain gets a dedicated accent color used for card left-borders, type badges, page titles on listing pages, and nav active states.

| Domain | Token | Value | Name | Existing entities |
|--------|-------|-------|------|-------------------|
| Events | `--color-events` | `#e63946` | Ceremony Red | event, event_type |
| Teachings | `--color-teachings` | `#f4a261` | Fire Amber | teaching, teaching_type, cultural_collection |
| Language | `--color-language` | `#2a9d8f` | Cedar Teal | dictionary_entry, example_sentence, word_part, speaker |
| Communities | `--color-communities` | `#6a9caf` | Deep Water | community, group, group_type, cultural_group |
| People | `--color-people` | `#8338ec` | Berry Purple | resource_person |
| Programs | `--color-programs` | `#c77dff` | Sweetgrass | elder_support_request, volunteer |
| Search | `--color-search` | `#d4a373` | Birch Tan | ingest_log (NorthCloud results) |

**Old → new token mapping:**

| Old CSS token / class | New token |
|-----------------------|-----------|
| `--domain-events` | `--color-events` |
| `--domain-teachings` | `--color-teachings` |
| `--domain-language` | `--color-language` |
| `--domain-groups`, `--domain-communities` | `--color-communities` |
| `--domain-person` | `--color-people` |
| `--domain-elders` | `--color-programs` |
| `.card--event` | Uses `--color-events` |
| `.card--group`, `.card--community` | Uses `--color-communities` |
| `.card--teaching` | Uses `--color-teachings` |
| `.card--language` | Uses `--color-language` |
| `.card--person` | Uses `--color-people` |
| `.card--elder` | Uses `--color-programs` |

### Ribbon

A horizontal gradient bar using all domain colors in sequence, placed below the site header and above the site footer. Signature visual element.

```
linear-gradient(90deg,
  var(--color-events) 0% 16.6%,
  var(--color-teachings) 16.6% 33.3%,
  var(--color-language) 33.3% 50%,
  var(--color-communities) 50% 66.6%,
  var(--color-people) 66.6% 83.3%,
  var(--color-programs) 83.3% 100%)
```

All 6 primary domain colors in equal segments (search excluded from ribbon).

### Semantic Aliases

Existing semantic tokens (`--accent`, `--link`, `--error`, `--warning`, `--info`) remap to the new palette:

| Token | Maps to |
|-------|---------|
| `--accent` | `--color-events` (ceremony red as primary accent) |
| `--link` | `--color-events` on dark surfaces; domain color in context |
| `--error` | `#ff4d5a` (shifted brighter than events red to distinguish error states) |
| `--warning` | `--color-teachings` (fire amber) |
| `--info` | `--color-communities` (deep water) |
| `--border` | `#1e1e1e` on dark surfaces; `#e0dbd4` on light |

## Typography

### Font Stack

| Role | Font | Weights | Usage |
|------|------|---------|-------|
| Display/Headlines | Fraunces | 700, 900 | Page titles, card titles, hero text |
| Body/UI | DM Sans | 400, 500, 700 | Body text, navigation, labels, metadata |

Self-hosted as woff2 variable font files (matching existing pattern). Fraunces is a variable font with optical size axis:

| Context | Font size range | `font-variation-settings` |
|---------|----------------|--------------------------|
| Hero headlines | 2.5rem+ | `'opsz' 144` (max contrast) |
| Page titles | 1.5–2.5rem | `'opsz' 48` |
| Card titles | 0.9–1.2rem | `'opsz' 18` (subtle contrast) |

### Replacements

| Current | New |
|---------|-----|
| Atkinson Hyperlegible Next (body) | DM Sans 400/500/700 |
| Charter / Bitstream Charter (headings) | Fraunces 700/900 |

### Scale

Keep the existing fluid `clamp()` scale — the values are well-tuned. Only the font families change.

### Type Patterns

- **Hero headlines:** Fraunces 900, large scale, may use italic `<em>` in accent color for emphasis
- **Page titles (listings):** Fraunces 900, colored with domain accent
- **Card titles:** Fraunces 700
- **Type badges/labels:** DM Sans 700, uppercase, wide letter-spacing (0.08-0.1em), small size
- **Nav items:** DM Sans 500, colored per domain
- **Body text:** DM Sans 400, 1.6-1.7 line-height

## Page Types

### Dark Pages (Listings, Homepage, Search)

- Background: `--surface-dark`
- Cards: `--surface-card` with 3px left border in domain color
- Card hover: `translateY(-2px)` + deeper shadow
- Type badges: domain-colored uppercase micro-labels
- Page title: Fraunces 900, domain-colored on listing pages
- Filter buttons: `--surface-raised` with subtle border, active state fills with domain color

### Adaptive Pages (Detail/Reading)

- **Dark shell:** Header, navigation, ribbon, back link, badge, title, metadata — all on dark background
- **Light content well:** `--surface-light` background with `border-radius: 12px 12px 0 0` creating a visual "opening" into the reading space
- Content well uses `--text-dark` for body text, Fraunces for subheadings, DM Sans for paragraphs
- Info cards within the well: white background, domain-colored left border, subtle shadow
- Tags: warm neutral background (`#e8e3dc`), muted text

### Transition Point

The content well begins immediately after the detail header metadata. No gap — the rounded top of the light surface creates a natural visual transition from the dark header into the reading area.

### Content Well Placement per Template

Each listing template has a detail conditional (`{% if detail_entity %}`). Inside that conditional:

**Outside `.content-well` (stays on dark surface):**
- Back link (`.detail__back`)
- Type badge (`.detail__badge`)
- Title (`.detail__title`)
- Metadata row (`.detail__meta`)

**Inside `.content-well` (light surface):**
- All body paragraphs (the `for paragraph in paragraphs` loops)
- Info cards (structured data like times, contact)
- Tags
- Related content sections

The `.content-well` div opens after the metadata row and closes before the end of the detail conditional. Template change is a single `<div class="content-well">` wrapper in each of: `events.html.twig`, `groups.html.twig`, `teachings.html.twig`, `language.html.twig`, `people.html.twig`.

## Component Patterns

### Cards

```
Background: --surface-card (#141414)
Border-left: 3px solid [domain-color]
Border-radius: 8px
Padding: 1rem
Hover: translateY(-2px), box-shadow 0 8px 24px rgba(0,0,0,0.3)

Existing class names are preserved (no rename):
  .card__badge  — domain-colored uppercase micro-label (was type-colored, now domain-colored)
  .card__title  — Fraunces 700 (was Charter)
  .card__meta   — DM Sans, --text-muted
  .card__body   — DM Sans, --text-secondary (excerpt text)
```

### Navigation

- Domain-colored links (each nav item colored by its domain)
- Active state: domain color + underline
- Mobile: same hamburger pattern, dark surface

### Ribbon

- 3-5px horizontal gradient bar
- Placed below site header, above site footer
- All 6 primary domain colors in equal segments (search excluded)
- Signature brand element — appears on every page

### Search Bar

- `--surface-raised` background with subtle border
- Domain-colored submit button (ceremony red by default)
- Light placeholder text

### Badges

- Filled background in domain color, white text
- Used on detail pages
- Uppercase, small, rounded

### Pills (Community Shortcuts)

- `--surface-raised` background, subtle border
- Hover: border and text shift to `--color-communities`

### Content Well Info Cards

- White background on light surface
- Domain-colored left border
- Subtle shadow
- Used for structured data (event times, contact info, etc.)

## What Changes

### CSS (`public/css/minoo.css`)

- **@font-face:** Replace Atkinson Hyperlegible with Fraunces + DM Sans
- **@layer tokens:** New color tokens (surfaces, domain accents, text colors for dark/light)
- **@layer base:** Dark body background, light text defaults, updated font families
- **@layer layout:** Dark header/footer, ribbon element, content-well styles for detail pages
- **@layer components:** Updated card styles (dark background, left-border accents), badge system, filter buttons, pills, search bar, detail header, content well, info cards within wells
- **Preserve:** Layer architecture, fluid `clamp()` scale, spacing tokens, logical properties, container queries, accessibility utilities

### Templates

- **base.html.twig:** Update font preloads (Fraunces + DM Sans woff2), add ribbon element after header and before footer
- **Listing templates** (events, groups, teachings, language, people): No structural changes — styling comes from CSS
- **Detail views** (inside listing templates): Wrap long-form content in a `.content-well` div
- **page.html.twig (homepage):** No structural changes needed
- **Components:** No structural changes — card markup stays the same, CSS handles the visual transformation

### Assets

- Remove: `fonts/atkinson-hyperlegible-next-*.woff2`
- Add: `fonts/fraunces-variable.woff2`, `fonts/fraunces-italic-variable.woff2`, `fonts/dm-sans-variable.woff2` (self-hosted, matching existing pattern)
- Font loading: `@font-face` with `font-display: swap` and `<link rel="preload">` in base.html.twig (same approach as current Atkinson Hyperlegible)

### What Does NOT Change

- Template structure and inheritance
- Twig logic and conditionals
- Entity types, controllers, routing
- JavaScript behavior
- Accessibility features (skip link, ARIA, focus management)
- CSS layer architecture and methodology — **all new colors defined in hex** (migrating from oklch; hex is simpler to maintain and debug, and the oklch gamut benefits were not being used for these earth-tone values)
- Fluid spacing and type scales
- Logical properties, container queries, native nesting

## Migration Strategy

This is a CSS-first redesign. The vast majority of changes happen in `minoo.css`. Template changes are minimal:

1. **Phase 1: Tokens & Base** — Replace font-face, update color tokens, update base layer. This instantly transforms the entire site's surface colors and typography.
2. **Phase 2: Components** — Update card, badge, nav, button, and pill component styles. Domain-color system.
3. **Phase 3: Adaptive Layout** — Add content-well styles, update detail views in templates to wrap content in `.content-well`.
4. **Phase 4: Polish** — Ribbon element, hover states, transitions, shadow refinement.

## Mockups

Visual mockups (homepage, listing, detail) available at:
`.superpowers/brainstorm/2961917-1773614261/full-design-preview.html`
