# Performance budget (#808)

A small, vanilla SSR app should stay fast by default. These are the budgets we
hold the public surface to, plus the measurements taken on 2026-06-14 (local
`php -S`, uncompressed unless noted). Production serves behind Caddy with
gzip/brotli, so over-the-wire sizes are roughly 20–30% of the uncompressed CSS.

## Budgets

| Resource | Budget | Notes |
|---|---|---|
| HTML per page | ≤ 50 KB | SSR; no client framework on content pages |
| App CSS (total, uncompressed) | ≤ 320 KB | single design system, `@import`-aggregated; gzips to ~60 KB |
| Page JS (excl. map) | ≤ 60 KB | per-page script only; no global SPA bundle |
| Map JS (Leaflet) | ≤ 200 KB | loaded ONLY on `/communities` |
| Render-blocking requests | minoo.css + 2 preloaded fonts | no blocking JS in `<head>` |
| Web fonts | 2 (preloaded woff2) | `fraunces`, `dm-sans` |

## Measured (2026-06-14)

- HTML: `/` 19 KB · `/language` 26 KB · `/games` 18 KB · `/communities` 15 KB · `/language/{slug}` 12 KB — all within budget.
- App CSS total ≈ 279 KB uncompressed (`components.css` 150 KB + `utilities.css` 84 KB the bulk) → within the 320 KB budget; ~60 KB over the wire.
- Page JS: `/` and `/language` load **no** page script (only the small inline UX handlers in the layout). `/games/crossword` loads `games-common.js` (4 KB) + `crossword.js` (38 KB). `/communities` loads `leaflet.js` (147 KB) + `community-map.js` (2 KB).
- Fonts preloaded; CSS is a single cache-busted (`?v=`) entry; SVG icons are inline (no icon font); the analytics seam (#777) is empty and never blocks render or game init.

## Keeping inside the budget

- New CSS goes in the existing `@layer` files; watch `components.css` growth.
- Heavy libraries (maps, future charts) load only on the page that needs them — never globally.
- Bump the `?v=` cache-buster on CSS changes (see CLAUDE.md gotcha).
- Prefer SSR + small progressive-enhancement scripts over client frameworks.

## Known cleanup (no runtime cost, repo hygiene)

These scripts remain in `public/js/` from cut features and are referenced by **no
template** (verified), so they add zero page weight, but should be deleted:
`guess-price.js`, `atlas-discovery.js`, `atlas-detail.js`, `business-map.js`,
`media-carousel.js`, `alpine.min.js`. The Leaflet MarkerCluster plugin
(`leaflet.markercluster.js`, `MarkerCluster*.css`) is also currently unused
(the community map uses plain markers) — keep only if a clustered map is planned.

## Accessibility baseline (#808)

Verified on the public pages (home, dictionary, games, communities):

- `<html lang>` is set from the active locale; a skip-link targets `#main-content`.
- All `<img>` carry `alt`; decorative SVGs are `aria-hidden`; the community map
  has `role="img"` + an `aria-label`.
- Icon-only controls (sidebar toggle, theme toggle, account menu) have
  `aria-label` + `aria-expanded`/`aria-controls`.
- Form inputs have associated labels; the layout search input gained an
  `aria-label` in this pass (it previously relied on a placeholder only).
- Colour comes from oklch design tokens with distinct light/dark values; theme
  parity is tracked in #805.
