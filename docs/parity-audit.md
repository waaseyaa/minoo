# Light/dark + EN/OJ parity audit (#805)

Audited 2026-06-14 across the public surface (home, dictionary, search, games,
community map, static pages).

## Theming (light/dark) — VERIFIED

- Colours come from oklch design tokens in `public/css/tokens.css` with distinct
  `[data-theme="light"]` / `[data-theme="dark"]` values; components consume the
  tokens (no hardcoded page colours). `components.css` carries 46 theme-scoped
  rules.
- The theme is applied before first paint by an inline script in the layout
  (reads `localStorage` / `prefers-color-scheme`), and toggled by the header
  control. Every page extends the same layout, so both themes apply everywhere.
- The Leaflet community map uses light OSM tiles in both themes (maps read fine
  light-on-dark); the map container background is a token.

## EN ⇄ OJ switching — VERIFIED

- The language switcher links to `/oj/…` (path-prefixed locale). Spot-checked
  `/oj/`, `/oj/language`, `/oj/communities`, `/oj/games` — all 200 with the OJ
  switcher active.
- **Graceful English fallback confirmed**: a key that is absent from `oj.php`
  (e.g. `games.agim_title`) and a key with an empty OJ value (e.g.
  `usermenu.profile`) both render their English text in the OJ locale — never a
  raw key or a blank. So missing OJ copy is never a user-visible defect.
- Regression-locked in `tests/App/Unit/I18n/TranslationParityTest.php`: every
  `trans()` key used in a template exists in `en.php` (no raw-key leaks) and
  `oj.php` never drifts ahead of `en.php`.

## OJ copy gaps — TRACKED (needs fluent speakers)

Anishinaabemowin copy must come from speakers — we never machine-translate or
invent it (house rule). The remaining work is translation, not engineering:

- `oj.php` has 990 keys; ~134 are explicit `// needs translation` placeholders
  and ~30 active keys fall back to English because they are not yet in `oj.php`
  (e.g. `account.elder_*`, `roles.*`, `admin.users_*`, `games.agim_*`,
  `events.pagination_*`, `sidebar.*`). All render English today.
- Hardcoded-English pages with **no** `trans()` plumbing yet: the homepage games
  section, the community living-map pages, and the elder-support form. These
  show English in both locales; giving them OJ needs both `trans()` keys and
  speaker copy. Prose pages (about, data-sovereignty) are essays best authored
  in OJ directly rather than key-by-key.

## Hygiene follow-up (not parity-blocking)

`en.php` carries ~119 stale keys for features removed in the slimming (feed,
businesses, etc.) that no template references. Harmless (English source only)
but worth pruning in a cleanup pass.
