# Regalia Redesign Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform Minoo's visual identity from safe earth-tone cards to the bold "Regalia" dark-canvas design with vivid ceremony-color domain accents, new typography (Fraunces + DM Sans), and adaptive light content wells for detail pages.

**Architecture:** CSS-first redesign — the vast majority of changes happen in `minoo.css`. Template changes are minimal: font preloads in `base.html.twig`, a ribbon element, content-well wrappers in 6 detail templates + info pages, and a cache-bust version bump. The CSS layer architecture (`@layer reset, tokens, base, layout, components, utilities`) is preserved.

**Tech Stack:** Vanilla CSS (no build step), Twig 3 templates, self-hosted woff2 variable fonts.

**Spec:** `docs/superpowers/specs/2026-03-15-regalia-redesign-design.md`

---

## Chunk 1: Fonts & Tokens (Foundation)

### Task 1: Download and install new font files

**Files:**
- Create: `public/fonts/fraunces-variable.woff2`
- Create: `public/fonts/fraunces-italic-variable.woff2`
- Create: `public/fonts/dm-sans-variable.woff2`
- Remove: `public/fonts/atkinson-hyperlegible-next-400.woff2`
- Remove: `public/fonts/atkinson-hyperlegible-next-700.woff2`
- Modify: `public/css/minoo.css` (lines 1-15, @font-face declarations)
- Modify: `templates/base.html.twig` (lines 6-7, preload links)

- [ ] **Step 1: Download variable font files from Google Fonts**

Download woff2 variable font files. Google Fonts API serves variable fonts when you request a weight range:

```bash
# Fraunces variable (opsz 9-144, wght 400-900)
curl -o public/fonts/fraunces-variable.woff2 \
  "https://fonts.gstatic.com/s/fraunces/v31/6NUh8FyLNQOQZAnv9bYEvDiIdE9Ea92uemAk_WBq8U_9v0c2Wa0K7iN7hzFUPJH58nk.woff2"

# Fraunces italic variable
curl -o public/fonts/fraunces-italic-variable.woff2 \
  "https://fonts.gstatic.com/s/fraunces/v31/6NVf8FyLNQOQZAnv9ZwNjucMHVn85Ni7emAe9lKqZTnbB-gzTK0K1ChJdt9vIVYX9G37lvQ.woff2"

# DM Sans variable (wght 400-700)
curl -o public/fonts/dm-sans-variable.woff2 \
  "https://fonts.gstatic.com/s/dmsans/v15/rP2tp2ywxg089UriI5-g4vlH9VoD8CmcqZG40F9JadbnoEwAopxhS23PjyAo3C8.woff2"
```

If these URLs don't resolve (Google CDN rotates them), use the google-webfonts-helper or fontsource npm packages to get the files. The key requirement: variable woff2 format.

- [ ] **Step 2: Remove old font files**

```bash
rm public/fonts/atkinson-hyperlegible-next-400.woff2
rm public/fonts/atkinson-hyperlegible-next-700.woff2
```

- [ ] **Step 3: Update @font-face declarations in minoo.css**

Replace lines 1-15 of `public/css/minoo.css`:

```css
@font-face {
  font-family: 'Fraunces';
  src: url('/fonts/fraunces-variable.woff2') format('woff2');
  font-weight: 400 900;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'Fraunces';
  src: url('/fonts/fraunces-italic-variable.woff2') format('woff2');
  font-weight: 400 900;
  font-style: italic;
  font-display: swap;
}

@font-face {
  font-family: 'DM Sans';
  src: url('/fonts/dm-sans-variable.woff2') format('woff2');
  font-weight: 400 700;
  font-style: normal;
  font-display: swap;
}
```

- [ ] **Step 4: Update font preloads in base.html.twig**

Replace lines 6-7 of `templates/base.html.twig`:

```html
<link rel="preload" href="/fonts/fraunces-variable.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/dm-sans-variable.woff2" as="font" type="font/woff2" crossorigin>
```

(Only preload the two most critical fonts — normal weight Fraunces and DM Sans. Italic Fraunces loads on demand.)

- [ ] **Step 5: Commit**

```bash
git add public/fonts/ templates/base.html.twig public/css/minoo.css
git commit -m "feat: replace Atkinson Hyperlegible with Fraunces + DM Sans variable fonts"
```

### Task 2: Replace color tokens (oklch → hex, new domain colors)

**Files:**
- Modify: `public/css/minoo.css` (lines 68-184, @layer tokens)

- [ ] **Step 1: Replace the entire `:root` block inside `@layer tokens`**

The existing token block (lines 69-183) defines oklch color scales, semantic aliases, font stacks, spacing, and domain colors. Replace it with the new Regalia token set. Keep all spacing, sizing, radius, and shadow tokens unchanged — only colors, fonts, and domain tokens change.

Replace the color palette section (lines 70-95 approximately — the `/* Color palette */` through `--color-berry-100`) with:

```css
    /* ── Surfaces ── */
    --surface-dark: #0a0a0a;
    --surface-card: #141414;
    --surface-raised: #1a1a1a;
    --surface-light: #f5f0eb;
    --surface-light-card: #fff;

    /* ── Text ── */
    --text-primary: #f0ece6;
    --text-secondary: #aaa;
    --text-muted: #777;
    --text-dark: #1a1a1a;
    --text-dark-secondary: #666;

    /* ── Domain accent colors ── */
    --color-events: #e63946;
    --color-teachings: #f4a261;
    --color-language: #2a9d8f;
    --color-communities: #6a9caf;
    --color-people: #8338ec;
    --color-programs: #c77dff;
    --color-search: #d4a373;

    /* ── Borders ── */
    --border-dark: #1e1e1e;
    --border-subtle: #2a2a2a;
    --border-light: #e0dbd4;
```

Replace the semantic aliases section (lines 96-115 approximately) with:

```css
    /* ── Semantic aliases ── */
    --surface:        var(--surface-dark);
    --surface-raised: var(--surface-raised);
    --border:         var(--border-dark);
    --accent:         var(--color-events);
    --link:           var(--color-events);
    --error:          #ff4d5a;
    --warning:        var(--color-teachings);
    --info:           var(--color-communities);
```

Replace the domain color tokens (lines 167-172) with:

```css
    --domain-events:    var(--color-events);
    --domain-groups:    var(--color-communities);
    --domain-teachings: var(--color-teachings);
    --domain-language:  var(--color-language);
    --domain-people:    var(--color-people);
    --domain-elders:    var(--color-programs);
```

Replace font stack tokens with:

```css
    --font-body:    'DM Sans', system-ui, -apple-system, sans-serif;
    --font-heading: 'Fraunces', Charter, 'Bitstream Charter', Cambria, serif;
    --font-mono:    ui-monospace, 'Cascadia Code', 'Source Code Pro', monospace;
```

- [ ] **Step 2: Verify the dev server renders with new tokens**

```bash
php -S localhost:8081 -t public &
sleep 1
curl -s http://localhost:8081/ | head -20
kill %1
```

Verify no 500 errors. The site will look broken at this stage (dark backgrounds, wrong text colors in some places) — that's expected and will be fixed in subsequent tasks.

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: replace oklch color tokens with Regalia hex palette and domain accents"
```

### Task 3: Update base layer (body, headings, links)

**Files:**
- Modify: `public/css/minoo.css` (lines 185-229, @layer base)

- [ ] **Step 1: Update body and base element styles**

In `@layer base`, update the body rule to use the dark surface:

```css
  body {
    font-family: var(--font-body);
    font-size: var(--text-base);
    line-height: var(--leading-normal);
    color: var(--text-primary);
    background-color: var(--surface-dark);
  }
```

Update heading rules to use Fraunces:

```css
  h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-heading);
    line-height: var(--leading-tight);
    letter-spacing: var(--tracking-tight);
    color: var(--text-primary);
  }
```

Update link default:

```css
  a {
    color: var(--link);
    text-decoration-color: transparent;
    transition: color 0.15s ease, text-decoration-color 0.15s ease;
  }
  a:hover {
    text-decoration-color: currentColor;
  }
```

- [ ] **Step 2: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: update base layer for dark surface, Fraunces headings, DM Sans body"
```

---

## Chunk 2: Layout Shell (Header, Footer, Ribbon)

### Task 4: Restyle site header for dark canvas

**Files:**
- Modify: `public/css/minoo.css` (lines 243-270, `.site-header` and related)

- [ ] **Step 1: Update site-header styles**

The header should be dark with domain-colored nav links. Update `.site-header`, `.site-header__inner`, `.site-name`, and nav link styles:

```css
  .site-header {
    background-color: var(--surface-dark);
    border-block-end: 1px solid var(--border-dark);
    padding-block: var(--space-xs);
    padding-inline: var(--gutter);
  }
```

Update `.site-name__wordmark` — the existing SVG wordmark may need a CSS filter to invert for dark backgrounds, or we can set it to `filter: brightness(0) invert(1)` to make it white.

Update nav links to use domain colors:

```css
  .site-nav a {
    color: var(--text-secondary);
    font-family: var(--font-body);
    font-size: var(--text-sm);
    font-weight: 500;
    letter-spacing: 0.02em;
    text-decoration: none;
    transition: color 0.15s ease;
  }
  .site-nav a:hover {
    color: var(--text-primary);
  }
  .site-nav a[aria-current="page"] {
    color: var(--accent);
  }

  /* Domain-colored nav links — each section colored by its domain */
  .site-nav a[href*="/communities"] { color: var(--color-communities); }
  .site-nav a[href*="/people"] { color: var(--color-people); }
  .site-nav a[href*="/teachings"] { color: var(--color-teachings); }
  .site-nav a[href*="/events"] { color: var(--color-events); }
  .site-nav a[href*="/elders"],
  .site-nav a[href*="/volunteer"] { color: var(--color-programs); }
  .site-nav a[href*="/language"] { color: var(--color-language); }
```

- [ ] **Step 2: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: restyle site header for Regalia dark canvas"
```

### Task 5: Restyle site footer

**Files:**
- Modify: `public/css/minoo.css` (footer styles, around lines 456-630)

- [ ] **Step 1: Update footer styles**

```css
  .site-footer {
    background-color: var(--surface-dark);
    border-block-start: 1px solid var(--border-dark);
    padding-block: var(--space-lg);
    padding-inline: var(--gutter);
    color: var(--text-muted);
    font-size: var(--text-sm);
  }

  .site-footer a {
    color: var(--text-secondary);
  }
  .site-footer a:hover {
    color: var(--text-primary);
  }
```

- [ ] **Step 2: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: restyle site footer for Regalia dark canvas"
```

### Task 6: Add ribbon element

**Files:**
- Modify: `templates/base.html.twig` (after header, before footer)
- Modify: `public/css/minoo.css` (add ribbon styles in @layer layout)

- [ ] **Step 1: Add ribbon markup to base.html.twig**

After the closing `</header>` tag (line 78) and before the location bar include (line 80), add:

```html
    <div class="ribbon" aria-hidden="true"></div>
```

Before the `<footer>` tag (line 89), add:

```html
    <div class="ribbon" aria-hidden="true"></div>
```

- [ ] **Step 2: Add ribbon CSS in @layer layout**

```css
  .ribbon {
    block-size: 3px;
    background: linear-gradient(90deg,
      var(--color-events) 0% 16.6%,
      var(--color-teachings) 16.6% 33.3%,
      var(--color-language) 33.3% 50%,
      var(--color-communities) 50% 66.6%,
      var(--color-people) 66.6% 83.3%,
      var(--color-programs) 83.3% 100%);
  }
```

- [ ] **Step 3: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css
git commit -m "feat: add ceremony-color ribbon element to header and footer"
```

---

## Chunk 3: Card & Component Restyling

### Task 7: Restyle cards for dark canvas with domain accent borders

**Files:**
- Modify: `public/css/minoo.css` (card styles, lines 642-830)

- [ ] **Step 1: Update base card styles**

Update `.card` (around line 642):

```css
  .card {
    background-color: var(--surface-card);
    border-radius: 0.5rem;
    padding: var(--space-sm);
    border-inline-start: 3px solid var(--card-accent, var(--border-subtle));
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  }
```

- [ ] **Step 2: Update card variant accent colors**

Update each `.card--*` variant (lines 778-783) to use new domain tokens:

```css
  .card--event { --card-accent: var(--color-events); }
  .card--group { --card-accent: var(--color-communities); }
  .card--community { --card-accent: var(--color-communities); }
  .card--teaching { --card-accent: var(--color-teachings); }
  .card--language { --card-accent: var(--color-language); }
  .card--person { --card-accent: var(--color-people); }
  .card--elder { --card-accent: var(--color-programs); }
```

- [ ] **Step 3: Update card__badge styles**

Update `.card__badge` (around line 742) and all badge variants to use domain colors:

```css
  .card__badge {
    display: inline-block;
    font-family: var(--font-body);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--card-accent, var(--text-secondary));
    padding-block: 0;
    padding-inline: 0;
    background: none;
    border-radius: 0;
  }
```

The badges become text-only micro-labels colored by domain (not filled pills). The existing badge variant classes (`.card__badge--event`, etc.) can use the inherited `--card-accent` from the parent card, so they simplify to just inheriting color.

- [ ] **Step 4: Update card__title, card__meta, card__body text colors**

```css
  .card__title {
    font-family: var(--font-heading);
    font-size: var(--text-base);
    font-weight: 700;
    color: var(--text-primary);
    font-variation-settings: 'opsz' 18;
  }

  .card__meta {
    font-size: var(--text-sm);
    color: var(--text-muted);
  }

  .card__body {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    line-height: 1.5;
  }
```

- [ ] **Step 5: Run dev server and visually check a listing page**

```bash
php -S localhost:8081 -t public &
```

Open `http://localhost:8081/events` — cards should show on dark background with colored left borders and micro-label type badges.

- [ ] **Step 6: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: restyle cards with dark canvas, domain accent borders, Fraunces titles"
```

### Task 8: Update button, pill, filter, and search bar styles

**Files:**
- Modify: `public/css/minoo.css` (button/pill/search components)

- [ ] **Step 1: Update .btn styles for dark canvas**

```css
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-body);
    font-weight: 700;
    font-size: var(--text-sm);
    padding-block: var(--space-2xs);
    padding-inline: var(--space-sm);
    border-radius: var(--radius-md);
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.15s ease, transform 0.1s ease;
  }
  .btn--primary {
    background-color: var(--color-events);
    color: #fff;
  }
  .btn--primary:hover {
    background-color: #cc2f3b;
  }
  .btn--secondary {
    background-color: var(--surface-raised);
    color: var(--text-secondary);
    border: 1px solid var(--border-subtle);
  }
  .btn--secondary:hover {
    border-color: #444;
    color: var(--text-primary);
  }
```

- [ ] **Step 2: Update homepage pills and search-related styles**

Community pills:

```css
  .homepage-pill {
    background-color: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-full);
    padding-block: var(--space-3xs);
    padding-inline: var(--space-xs);
    font-size: var(--text-sm);
    color: var(--text-secondary);
    text-decoration: none;
    transition: border-color 0.15s ease, color 0.15s ease;
  }
  .homepage-pill:hover {
    border-color: var(--color-communities);
    color: var(--color-communities);
  }
```

Homepage search bar:

```css
  .homepage-search {
    background-color: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    overflow: hidden;
  }
  .homepage-search input {
    background: transparent;
    border: none;
    color: var(--text-primary);
    padding: var(--space-xs) var(--space-sm);
  }
  .homepage-search input::placeholder {
    color: var(--text-muted);
  }
  .homepage-search button {
    background-color: var(--color-events);
    color: #fff;
    border: none;
    font-weight: 700;
    padding: var(--space-xs) var(--space-sm);
  }
```

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: restyle buttons, pills, and search bar for Regalia dark canvas"
```

### Task 9: Update homepage-specific styles

**Files:**
- Modify: `public/css/minoo.css` (homepage hero, tabs, grid)

- [ ] **Step 1: Update homepage hero and tab styles**

The homepage hero needs dark treatment with accent kicker text:

```css
  .homepage-hero {
    padding-block: var(--space-xl) var(--space-lg);
    padding-inline: var(--gutter);
    text-align: center;
    position: relative;
  }
  .homepage-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(230, 57, 70, 0.06) 0%, transparent 60%);
    pointer-events: none;
  }
  .homepage-hero__title {
    font-family: var(--font-heading);
    font-size: var(--text-3xl);
    font-weight: 900;
    font-variation-settings: 'opsz' 144;
    line-height: 1.05;
    color: var(--text-primary);
  }
  .homepage-hero__title em {
    font-style: italic;
    color: var(--color-events);
  }
```

Update homepage tab styles for dark surface with domain-colored active state:

```css
  .homepage-tabs [role="tab"] {
    font-family: var(--font-body);
    font-size: var(--text-sm);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    background: none;
    border: none;
    border-block-end: 2px solid transparent;
    padding-block: var(--space-2xs);
    padding-inline: var(--space-sm);
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
  }
  .homepage-tabs [role="tab"]:hover {
    color: var(--text-secondary);
  }
  .homepage-tabs [role="tab"][aria-selected="true"] {
    color: var(--color-events);
    border-block-end-color: var(--color-events);
  }
```

- [ ] **Step 2: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: restyle homepage hero and tabs for Regalia dark canvas"
```

---

## Chunk 4: Adaptive Content Wells & Detail Pages

### Task 10: Add content-well CSS

**Files:**
- Modify: `public/css/minoo.css` (add in @layer components)

- [ ] **Step 1: Add content-well component styles**

Add these styles in `@layer components`:

```css
  /* ── Content well (light reading surface on detail pages) ── */
  .content-well {
    background-color: var(--surface-light);
    border-start-start-radius: 0.75rem;
    border-start-end-radius: 0.75rem;
    margin-inline: var(--space-sm);
    padding-block: var(--space-lg) var(--space-xl);
    padding-inline: var(--space-lg);
    color: var(--text-dark);
  }

  .content-well h2,
  .content-well h3 {
    font-family: var(--font-heading);
    color: var(--text-dark);
  }

  .content-well p {
    color: var(--text-dark-secondary);
    line-height: 1.7;
  }

  .content-well a {
    color: var(--color-events);
  }

  /* Info cards inside content well */
  .content-well .detail__info-card {
    background-color: var(--surface-light-card);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    border-inline-start: 3px solid var(--card-accent, var(--color-events));
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  }

  /* Tags inside content well */
  .content-well .tag {
    background-color: #e8e3dc;
    color: #666;
  }

  @media (min-width: 60em) {
    .content-well {
      margin-inline: var(--space-md);
      padding-inline: var(--space-xl);
    }
  }
```

- [ ] **Step 2: Update detail header styles for dark shell**

Update `.detail` styles to work on the dark surface:

```css
  .detail__back {
    color: var(--text-muted);
    font-size: var(--text-sm);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: var(--space-3xs);
  }
  .detail__back:hover {
    color: var(--text-secondary);
  }

  .detail__badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding-block: 0.2rem;
    padding-inline: 0.6rem;
    border-radius: var(--radius-sm);
    background-color: var(--card-accent, var(--color-events));
    color: #fff;
  }

  .detail__title {
    font-family: var(--font-heading);
    font-size: var(--text-2xl);
    font-weight: 900;
    font-variation-settings: 'opsz' 48;
    color: var(--text-primary);
    line-height: 1.1;
  }

  .detail__meta {
    color: var(--text-secondary);
    font-size: var(--text-sm);
  }
```

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add content-well light reading surface and dark detail header styles"
```

### Task 11: Add content-well wrapper to detail templates

**Files:**
- Modify: `templates/events.html.twig` (wrap detail body)
- Modify: `templates/groups.html.twig` (wrap detail body)
- Modify: `templates/teachings.html.twig` (wrap detail body)
- Modify: `templates/language.html.twig` (wrap detail body)
- Modify: `templates/people.html.twig` (wrap detail body)
- Modify: `templates/elders.html.twig` (wrap detail body)

The pattern is the same for each: find the `<div class="detail__body flow">` and wrap it (plus everything after it within the detail conditional) in a `<div class="content-well">`.

- [ ] **Step 1: Update events.html.twig**

Find the detail section. Before `<div class="detail__body flow">` (line ~59), add:
```html
      <div class="content-well">
```

At the end of the detail section, before the closing `{% else %}` or `{% endif %}` for the detail conditional, close it:
```html
      </div>{# .content-well #}
```

- [ ] **Step 2: Update groups.html.twig**

Same pattern — wrap `<div class="detail__body flow">` and everything after it in `.content-well`.

- [ ] **Step 3: Update teachings.html.twig**

Same pattern.

- [ ] **Step 4: Update language.html.twig**

Same pattern.

- [ ] **Step 5: Update people.html.twig**

Same pattern — note this template has more content in the detail section (contact info, offerings) — wrap all of it.

- [ ] **Step 6: Update elders.html.twig**

Same pattern — wrap the detail body in `.content-well`.

- [ ] **Step 7: Visually verify a detail page**

```bash
php -S localhost:8081 -t public &
```

Open `http://localhost:8081/events/[any-slug]` — should see dark header/metadata transitioning into warm light content well.

- [ ] **Step 8: Commit**

```bash
git add templates/events.html.twig templates/groups.html.twig templates/teachings.html.twig templates/language.html.twig templates/people.html.twig templates/elders.html.twig
git commit -m "feat: wrap detail page bodies in content-well for adaptive light reading surface"
```

---

## Chunk 5: Polish & Verification

### Task 12: Update remaining component styles (location bar, chat, flash messages, search page)

**Files:**
- Modify: `public/css/minoo.css` (location bar, chat widget, flash messages, search components)

- [ ] **Step 1: Update location bar for dark surface**

Update location bar styles in `public/css/minoo.css`:

```css
  .location-bar {
    background-color: var(--surface-raised);
    border-block-end: 1px solid var(--border-dark);
    padding-block: var(--space-3xs);
    padding-inline: var(--gutter);
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }
  .location-bar__text {
    color: var(--text-secondary);
  }
  .location-bar__toggle {
    color: var(--color-communities);
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
  }
  .location-bar__dropdown {
    background-color: var(--surface-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
  }
  .location-bar__search {
    background: transparent;
    border: 1px solid var(--border-subtle);
    color: var(--text-primary);
    border-radius: var(--radius-sm);
    padding: var(--space-2xs) var(--space-xs);
  }
  .location-bar__search::placeholder {
    color: var(--text-muted);
  }
  .location-bar__result {
    color: var(--text-primary);
    padding: var(--space-2xs) var(--space-xs);
    cursor: pointer;
  }
  .location-bar__result:hover {
    background-color: var(--surface-raised);
  }
```

- [ ] **Step 2: Update chat widget for dark surface**

```css
  .chat-widget__toggle {
    background-color: var(--color-events);
    color: #fff;
    border: none;
    border-radius: var(--radius-full);
    padding: var(--space-xs);
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  }
  .chat-widget__panel {
    background-color: var(--surface-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
  }
  .chat-widget__header {
    background-color: var(--surface-raised);
    color: var(--text-primary);
    border-block-end: 1px solid var(--border-dark);
  }
  .chat-widget__messages {
    background-color: var(--surface-dark);
  }
  .chat-widget__message--user {
    background-color: var(--color-events);
    color: #fff;
    border-radius: var(--radius-md);
  }
  .chat-widget__message--assistant {
    background-color: var(--surface-raised);
    color: var(--text-primary);
    border-radius: var(--radius-md);
  }
  .chat-widget__form {
    background-color: var(--surface-card);
    border-block-start: 1px solid var(--border-dark);
  }
  .chat-widget__input {
    background: transparent;
    border: 1px solid var(--border-subtle);
    color: var(--text-primary);
    border-radius: var(--radius-sm);
  }
  .chat-widget__input::placeholder {
    color: var(--text-muted);
  }
  .chat-widget__send {
    background-color: var(--color-events);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
  }
```

- [ ] **Step 3: Update flash messages**

```css
  .flash-message {
    border-radius: var(--radius-md);
    padding: var(--space-xs) var(--space-sm);
    font-size: var(--text-sm);
    border-inline-start: 3px solid;
  }
  .flash-message--success {
    background-color: rgba(42, 157, 143, 0.12);
    border-color: var(--color-language);
    color: var(--color-language);
  }
  .flash-message--error {
    background-color: rgba(255, 77, 90, 0.12);
    border-color: var(--error);
    color: var(--error);
  }
  .flash-message--info {
    background-color: rgba(106, 156, 175, 0.12);
    border-color: var(--color-communities);
    color: var(--color-communities);
  }
  .flash-message--warning {
    background-color: rgba(244, 162, 97, 0.12);
    border-color: var(--color-teachings);
    color: var(--color-teachings);
  }
```

- [ ] **Step 4: Update search page styles**

```css
  .search-form input[type="search"] {
    background-color: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    color: var(--text-primary);
    border-radius: var(--radius-md);
    padding: var(--space-xs) var(--space-sm);
  }
  .search-form input[type="search"]::placeholder {
    color: var(--text-muted);
  }
  .search-filters {
    background-color: var(--surface-card);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    border: 1px solid var(--border-dark);
  }
  .search-filters__heading {
    color: var(--text-primary);
    font-family: var(--font-heading);
    font-weight: 700;
  }
  .search-filters__group label {
    color: var(--text-secondary);
  }
  .search-badge {
    background-color: var(--surface-raised);
    color: var(--text-secondary);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-full);
    padding-block: var(--space-3xs);
    padding-inline: var(--space-xs);
    font-size: var(--text-sm);
  }
  .search-badge--active {
    background-color: var(--color-events);
    border-color: var(--color-events);
    color: #fff;
  }
```

- [ ] **Step 5: Commit CSS updates**

```bash
git add public/css/minoo.css
git commit -m "feat: update location bar, chat, flash messages, and search for Regalia"
```

- [ ] **Step 6: Add content-well to info/legal page templates**

These text-heavy pages need the light reading surface. Since they don't have listing/detail conditionals, wrap the entire `{% block content %}` body in a `.content-well` div.

Templates to modify:
- `templates/about.html.twig`
- `templates/legal.html.twig`
- `templates/safety.html.twig`
- `templates/how-it-works.html.twig`
- `templates/data-sovereignty.html.twig`
- `templates/volunteer.html.twig`

For each, inside `{% block content %}`, wrap the content:

```twig
{% block content %}
  <div class="content-well content-well--full">
    {# existing content unchanged #}
  </div>
{% endblock %}
```

Add the `--full` variant to CSS:

```css
  .content-well--full {
    border-radius: 0;
    margin-inline: 0;
  }
```

This gives info pages a full-width light reading surface (no rounded top corners or side margins, since there's no dark header section above the content on these pages).

- [ ] **Step 7: Commit template updates**

```bash
git add templates/about.html.twig templates/legal.html.twig templates/safety.html.twig templates/how-it-works.html.twig templates/data-sovereignty.html.twig templates/volunteer.html.twig public/css/minoo.css
git commit -m "feat: add content-well to info and legal pages for light reading surface"
```

### Task 13: Bump CSS cache version and final visual review

**Files:**
- Modify: `templates/base.html.twig` (line 17, cache version)

- [ ] **Step 1: Bump CSS cache version**

In `templates/base.html.twig`, update line 17:

```html
<link rel="stylesheet" href="/css/minoo.css?v=8">
```

- [ ] **Step 2: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All 253 tests pass (CSS/template changes should not break any tests).

- [ ] **Step 3: Run Playwright tests**

```bash
npx playwright test
```

Expected: 42 passing. Some visual tests may need screenshot updates if using visual regression.

- [ ] **Step 4: Visual review of all major page types**

Start dev server and check each page type:

```bash
php -S localhost:8081 -t public &
```

Check:
- Homepage (`/`) — dark hero, search bar, tabbed cards, community pills
- Events listing (`/events`) — dark cards with red left borders
- Event detail (`/events/[slug]`) — dark header → light content well
- Groups listing (`/communities`) — dark cards with blue left borders
- Teaching detail (`/teachings/[slug]`) — amber-accented detail
- People directory (`/people`) — purple-accented person cards
- Search (`/search`) — dark results, filter sidebar
- Legal pages (`/legal/privacy`) — light reading surface
- Volunteer (`/volunteer`) — program pages with sweetgrass accent

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat: bump CSS cache version for Regalia redesign"
```

### Task 14: Update the design doc reference

**Files:**
- Modify: `docs/plans/2026-03-06-visual-identity-layout-design.md` (add note about supersession)

- [ ] **Step 1: Add supersession note**

Add to the top of the old design doc:

```markdown
> **Superseded:** This design was replaced by the Regalia redesign (2026-03-15). See `docs/superpowers/specs/2026-03-15-regalia-redesign-design.md`.
```

- [ ] **Step 2: Final commit**

```bash
git add docs/plans/2026-03-06-visual-identity-layout-design.md
git commit -m "docs: mark original visual identity design as superseded by Regalia"
```
