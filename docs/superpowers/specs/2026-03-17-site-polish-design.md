# Site-Wide Polish — Design Spec

**Date:** 2026-03-17
**Scope:** Visual consistency, design elevation, template normalization
**Approach:** Template + CSS unified pass (Approach B)
**Aesthetic:** Clean editorial — sharp typography hierarchy, generous whitespace, refined dividers, content-forward

## 1. Atlas/Communities Theme Absorption

Eliminate `atlas.css` as a separate design system. All communities pages adopt the Minoo dark palette.

### Changes

- **atlas-header / atlas-detail-hero**: Replace green gradients (`--atlas-deep`, `--atlas-forest`) with `var(--surface-dark)` + `--color-communities` (#6a9caf) accent. Fraunces for titles, DM Sans for meta.
- **atlas-card**: Migrate to `.card .card--community` with left-border accent pattern.
- **atlas-filters / atlas-chips**: Dark surfaces with subtle borders, matching search page filter pattern.
- **atlas-section**: Dark surface sections, `--color-communities` for section labels.
- **atlas-nearby-card / atlas-leader-card / atlas-contact-card**: Consolidate into `.card` variants.
- **Map container**: Keep Leaflet rendering. Remove green border and light background wrapper. Use `var(--surface-raised)` as fallback background.
- **Delete `atlas.css`** entirely when done. All styles move into `minoo.css` `@layer components`.

### Templates affected

- `communities/list.html.twig` — card class migration
- `communities/detail.html.twig` — full restyle to dark theme components

### Token mapping

| atlas.css token | Minoo equivalent |
|---|---|
| `--atlas-deep` | `--surface-dark` |
| `--atlas-forest` | `--color-communities` |
| `--atlas-sage` | `--text-muted` |
| `--atlas-mist` | `--text-primary` |
| `--atlas-cloud` | `--surface-raised` |
| `--atlas-border` | `--border-subtle` |
| `--atlas-chip-bg` | `--surface-card` |

## 2. Card System Consolidation

One `.card` system with modifiers.

### Cards being absorbed

| Current | Becomes | Template |
|---|---|---|
| `.atlas-card` | `.card .card--community` (modifier already exists in minoo.css) | `communities/list.html.twig` |
| `.atlas-nearby-card` | `.card .card--compact` | `communities/detail.html.twig` |
| `.atlas-leader-card` | `.card .card--compact .card--person` | `communities/detail.html.twig` |
| `.atlas-contact-card` | `.card .card--contact` (new modifier) | `communities/detail.html.twig` |
| `.community__nearby-card` | `.card .card--compact` | `communities/detail.html.twig` |

### Cards staying distinct

| Card | Reason |
|---|---|
| `.homepage-card` | Flat badge+title layout, no border-left accent, tabbed grid context |
| `.search-result-card` | Multi-badge row + snippet + metadata, sidebar layout |

### Shared card foundation (`.card`)

- `background: var(--surface-card)`, `border-inline-start: 3px solid var(--card-accent)`, `border-radius: var(--radius-md)`
- Hover: `translateY(-1px)`, `box-shadow: var(--shadow-md)`, border brightens
- `.card--compact`: reduced padding, no max-width, for inline grids
- `.card--contact`: 2-column grid interior for band office / contact info

## 3. Typography & Editorial Hierarchy

Sharp visual hierarchy. Let Fraunces breathe.

### Listing pages

- `.listing-hero h1`: `--text-3xl`, `--tracking-tight`, generous `margin-block-end`
- `.listing-hero__subtitle`: `--text-lg`, `--text-secondary`, breathing room below
- Section headings (h2): consistent `--text-xl`

### Detail pages (all 6 domains)

- `.detail-hero__title`: `--text-3xl`, Fraunces weight 700
- `.detail__meta`: standardize with `gap: var(--space-xs)`, `--text-sm`
- `.detail__body`: constrain to `--width-prose` (65ch), `--leading-loose`
- Related sections: uppercase label (`--text-sm`, `--tracking-wide`, `--text-muted`) then `h2` in `--text-lg`

### Homepage

- `.homepage-hero-tagline`: Fraunces at `--text-3xl`, generous line-height
- Tab labels: tighter tracking, bolder active state
- `.featured-card__headline`: Fraunces instead of DM Sans

### Global

- Links: tuned `text-underline-offset` and `text-decoration-thickness`
- Lists: generous `padding-inline-start`, proper marker styling

## 4. Ribbon & Section Transitions

### Ribbon

- 1px horizontal line with warm gradient: `transparent → var(--border-subtle) → transparent`
- Centered, `max-inline-size: 60%` of container
- `margin-block: var(--space-lg)`

### Section transitions

- **Detail pages**: related sections get top border `var(--border-subtle)` + `padding-block-start: var(--space-lg)`
- **Homepage**: `margin-block: var(--space-xl)` rhythm between major sections, no visible dividers
- **Listing pages**: `var(--space-lg)` gap between hero and card grid
- **Communities detail**: section borders restyled to `var(--border-subtle)`

### Content well

- Subtle side padding increase on wide screens for editorial inset
- `.flow` / `.flow-lg` spacing verified: `--space-sm` / `--space-md`

## 5. Detail Page Normalization

All 6 domains share one structural pattern.

### Standard anatomy

1. Breadcrumb
2. Detail hero — back link, badge(s), title (Fraunces `--text-3xl`), meta row
3. Hero image (when present)
4. Content body — prose-width (65ch), `flow` spacing
5. Metadata section — structured fields
6. Related sections — label + compact card grid

### Per-domain changes

| Domain | Changes |
|---|---|
| Events | Tighten meta spacing, prose-width body |
| Groups | Prose-width body, consistent related section labels |
| Teachings | Same treatment as groups |
| People | Normalize badge styling to match detail badges elsewhere |
| Businesses | Normalize section labels to uppercase pattern |
| Language | Typography pass only — page structure is unique (dictionary entries, not standard detail) |
| Communities | Full restyle — dark theme hero, standard sections, card consolidation (biggest lift) |

**Note:** People and Businesses are template-level pages (not formal entity domains in CLAUDE.md) but have detail views that follow the same pattern. Language has a distinct structure and gets typography treatment only, not the standard detail anatomy.

## 6. Motion & Micro-interactions

Restrained, high-impact. Editorial, not playful.

### Card hover

- Keep border-color change
- Add: `translateY(-1px)`, `box-shadow` transition to `--shadow-md`
- `--duration-fast`, `--ease-out`. No spring easing.

### Page load (listing pages)

- `@keyframes fadeUp`: opacity 0→1, translateY 8px→0
- Staggered `animation-delay` at 50ms increments, capped at 8 cards
- `prefers-reduced-motion: reduce` disables all animation
- All animation keyframes and motion rules live in `@layer components` (not utilities, due to #273)

### Navigation

- Active item: thin bottom accent line, `transition` on width
- Dropdown: `opacity` + `translateY(-4px)` transition, `--duration-fast`

### Detail pages

- Related sections: fade-in on scroll via `IntersectionObserver`, simple opacity
- One scroll-triggered effect, not scattered
- JS lives as inline `<script>` in `base.html.twig` (consistent with existing nav/location-bar scripts, no build step)

### Excluded

- No parallax, cursor effects, loading spinners, skeleton screens
- Ribbon is static

## 7. Mobile Refinements

### Cards

- `.card-grid`: auto-fill with minmax already correct
- `.card--compact` grids: force single column below 480px

### Detail pages

- Hero title: verify `--text-2xl` clamp floor
- Meta row: `flex-wrap: wrap` + `row-gap`
- Prose body: full width on small screens
- Related sections: single-column card grids

### Communities (post-absorption)

- Map: keep `30vh / min-height 180px`
- Leadership/contact grids: single column
- Filter chips: horizontal scroll with touch momentum, dark theme

### Homepage

- Tab row: verify no overflow after typography changes
- Featured cards: single column below 640px

### Global

- Touch targets: 44px minimum on all interactive elements
- No hover-dependent information disclosure

## Files Modified

### CSS

- `public/css/minoo.css` — all changes (tokens, layout, components layers)
- `public/css/atlas.css` — **deleted**
- `public/css/atlas.min.css` — **deleted**

### Templates

- `templates/base.html.twig` — ribbon markup, atlas.css link removal
- `templates/communities/list.html.twig` — card class migration
- `templates/communities/detail.html.twig` — full restyle
- `templates/events.html.twig` — typography/spacing adjustments
- `templates/groups.html.twig` — typography/spacing, related section labels
- `templates/teachings.html.twig` — same as groups
- `templates/people.html.twig` — badge normalization, typography
- `templates/businesses.html.twig` — section label normalization, typography
- `templates/page.html.twig` — homepage typography, featured card font
- `templates/search.html.twig` — spacing consistency
- `templates/about.html.twig` — typography hierarchy
- `templates/how-it-works.html.twig` — typography hierarchy
- `templates/components/homepage-card.html.twig` — possible featured font class
- `templates/components/hero-image.html.twig` — verify no changes needed
- `templates/language.html.twig` — typography pass
- `templates/elders.html.twig` — typography/spacing if affected by global changes
- `templates/volunteer.html.twig` — typography/spacing if affected by global changes

## Implementation Order

1. **Card consolidation** (Section 2) — other sections depend on the unified card system
2. **Atlas absorption** (Section 1) — biggest template change, uses consolidated cards
3. **Typography & hierarchy** (Section 3) — site-wide, no dependencies
4. **Ribbon & transitions** (Section 4) — builds on typography spacing
5. **Detail page normalization** (Section 5) — uses cards + typography from earlier steps
6. **Mobile refinements** (Section 7) — verify everything at small sizes
7. **Motion** (Section 6) — last, layered on top of stable layout

## Verification

- Playwright snapshots of all listing pages (events, groups, teachings, people, businesses, communities)
- Playwright snapshots of representative detail pages (one per domain)
- Homepage snapshot at desktop and mobile widths
- Communities list + detail at desktop and mobile widths (highest risk area)
- Verify `leaflet.css`, `MarkerCluster.css`, `MarkerCluster.Default.css` are loaded independently of atlas.css (they are — loaded in communities templates, not base)

## Constraints

- No build step — vanilla CSS only
- CSS layer order preserved: `reset, tokens, base, layout, components, utilities`
- `@layer utilities` bug (#273) still present — workaround by duplicating in components where needed
- All motion respects `prefers-reduced-motion`
- No new font files — Fraunces + DM Sans only
- Logical properties only (no `left`/`right`/`top`/`bottom` for spacing/sizing)
