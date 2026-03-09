# Minoo Frontend Redesign — "Rooted & Warm"

Date: 2026-03-08

## Context

Minoo's current frontend is functional with a solid CSS foundation (oklch colors, fluid type/spacing, layers, logical properties, container queries). The redesign preserves the color palette and CSS architecture while dramatically improving typography, visual rhythm, atmosphere, component differentiation, and overall coherence.

## Design Direction

**Tone:** Warm, grounded, unhurried — like sitting at a kitchen table in a community hall. The design evokes northern Ontario's forests, water, earth, and seasons.

**Aesthetic pillars:**
1. Textured warmth — subtle noise/grain, warm shadows, earthy gradient accents
2. Organic geometry — generous radii, breathing cards, gentle section curves
3. Typographic character — Atkinson Hyperlegible Next body + bold Charter headings
4. Domain color language — each content domain gets consistent accent treatment
5. Generous negative space — content breathes

**Layout philosophy:** Content-first single-column flow with strategic card grids. Mobile-primary, desktop gets wider grids and more breathing room.

## Tokens

### New tokens (existing palette untouched)

```css
--surface-warm:    oklch(0.96 0.01 70);
--surface-dark:    var(--color-earth-900);
--text-on-dark:    oklch(0.92 0.005 70);
--text-muted:      oklch(0.55 0.015 70);

--domain-events:    var(--color-water-600);
--domain-groups:    var(--color-forest-500);
--domain-teachings: var(--color-earth-700);
--domain-language:  var(--color-forest-700);
--domain-people:    oklch(0.45 0.06 160);
--domain-elders:    oklch(0.45 0.10 145);

--ease-out:    cubic-bezier(0.25, 0.46, 0.45, 0.94);
--ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
--duration-fast: 150ms;
--duration-normal: 250ms;

--radius-xl: 1.5rem;
```

## Typography

```css
--font-body: 'Atkinson Hyperlegible Next', system-ui, -apple-system, sans-serif;
--font-heading: Charter, 'Bitstream Charter', 'Sitka Text', Cambria, serif; /* unchanged */
```

- Atkinson Hyperlegible Next self-hosted, WOFF2 only, two weights (400, 700), ~40KB total
- `font-display: swap`, preloaded in `<head>`
- h1 gets `letter-spacing: -0.02em` at display sizes
- Hero title uses `<em>` on "Living" for italic warmth
- Section h2s: `font-weight: 400` for editorial lightness

## Layout

### Header
- Wordmark SVG replaces text "Minoo"
- Nav active indicator: bottom border accent (2px solid) instead of background
- Increased padding: `--space-sm` block
- Mobile: full-screen overlay nav with dark backdrop, centered links, close button, animated

### Hero (homepage)
- Full-bleed, breaks out of `--width-content`
- Background: warm gradient `oklch(0.96 0.01 70)` → `oklch(0.94 0.015 80)`
- SVG decorative pattern: thin-line concentric circles, bottom-right, `opacity: 0.08`, forest color. Inline CSS data URI.
- Noise texture: CSS `feTurbulence` pseudo-element, `opacity: 0.03`
- Padding: `--space-2xl` top, `--space-xl` bottom
- Content constrained to `--width-prose`, centered

### Section rhythm
- Sections separated by `--space-xl`
- Explore section gets `--surface-warm` background
- Section h2s get decorative `::before` line: 3px thick, `--accent`, 3rem wide

### Footer
- Dark background: `--color-earth-900`, `--text-on-dark` text
- Top row: light wordmark + tagline
- Bottom row: copyright left, legal links right
- Separated by gentle curve (`clip-path` pseudo-element, 20-30px arc)

## Components

### Cards
- Left accent border: `border-inline-start: 3px solid var(--card-accent, var(--border))`
- Domain variants set `--card-accent` to domain color
- Radius: `--radius-lg`
- Hover: `translateY(-2px)` + `shadow-lg`, `--duration-normal`, `--ease-out`
- Default padding: `--space-md`

### Explore cards (homepage)
- Larger navigation portal cards, icon-led
- 32x32 decorative SVG icon per domain (inline in template)
- Icon micro-interaction: `translateX(2px)` on hover
- Grid: `repeat(auto-fill, minmax(min(100%, 16rem), 1fr))`

### Buttons
- Radius: `--radius-md`
- Primary: subtle box-shadow for depth
- New `.btn--ghost` variant: text-only, underline on hover
- Hover transitions: `--duration-fast`

### Location bar
- Softer: `--radius-md`, warmer background
- Emoji replaced with inline SVG map-pin

### Forms
- Input radius: `--radius-md`
- Focus outline-offset: 3px

## Motion

All CSS-only, gated behind `@media (prefers-reduced-motion: no-preference)`:

- **Page load**: cards staggered `fadeInUp` (10px translateY, 200ms, 50ms stagger)
- **Card hover**: translateY(-2px) + shadow elevation
- **Explore card hover**: icon shifts right, card lifts
- **Nav links**: underline slides in from left (pseudo-element width transition)
- **Mobile nav**: overlay fades in, links slide up with stagger

## Accessibility

- All motion gated behind `prefers-reduced-motion`
- SVG icons `aria-hidden="true"`
- Color contrast: 4.5:1 minimum maintained (palette unchanged)
- Focus-visible: 2px solid accent, 3px offset
- Skip link unchanged

## SEO

- h1 singular per page (unchanged)
- h2 → h3 hierarchy maintained
- `<em>` on "Living" is semantically correct
- No structural changes to heading hierarchy

## Files Modified

1. `public/css/minoo.css` — tokens, components, hero, explore, footer, motion
2. `templates/base.html.twig` — font preload, wordmark header, expanded footer, mobile nav overlay
3. `templates/page.html.twig` — hero restructure, explore section with icons
4. `templates/components/event-card.html.twig` — domain card class
5. `templates/components/teaching-card.html.twig` — domain card class
6. `templates/components/group-card.html.twig` — domain card class
7. `templates/components/dictionary-entry-card.html.twig` — domain card class
8. `templates/components/resource-person-card.html.twig` — domain card class
9. `templates/components/location-bar.html.twig` — SVG icon

## New Files

1. `public/fonts/atkinson-hyperlegible-next-400.woff2`
2. `public/fonts/atkinson-hyperlegible-next-700.woff2`

## Untouched

- All other page templates (inherit improvements via base + CSS)
- All PHP code
- All tests (Playwright smoke tests should pass — no route/content changes)
- Color palette values
- Template inheritance structure

## Dark Mode

Deferred. Token architecture supports it — swap semantic aliases in `@media (prefers-color-scheme: dark)` when ready. Scale colors stay untouched.

## Risk

Low. CSS + template markup only. Visual regression caught by Playwright screenshots. Domain card accents are additive with fallback to `--border`. Rollback is a single git revert.
