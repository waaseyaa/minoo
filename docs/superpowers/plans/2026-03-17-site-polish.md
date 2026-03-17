# Site-Wide Polish Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unify the site's visual identity — absorb the atlas green theme, consolidate card components, elevate typography, add editorial polish — so every page feels like one cohesive product.

**Architecture:** CSS-first changes in `minoo.css` (vanilla CSS, `@layer` system), paired with template class migrations where card consolidation or atlas absorption requires it. No build step, no new dependencies.

**Tech Stack:** Vanilla CSS (layers, custom properties, native nesting), Twig 3 templates, Alpine.js (communities list only — preserve, don't touch JS)

**Spec:** `docs/superpowers/specs/2026-03-17-site-polish-design.md`

**Convention notes:**
- Media queries use `max-width`/`min-width` (not logical equivalents) — this matches existing codebase precedent in minoo.css. CSS logical media queries (`width <=`) have limited browser support.
- `linear-gradient(to right, ...)` used for ribbon — `to inline-end` lacks browser support. This is the standard exception for gradient directions.
- Small font sizes (`0.65rem`, `0.7rem`) are used as literal values for labels/badges, matching existing patterns. A `--text-2xs` token could be added later.

---

## Task 1: Card System — Add Missing Modifiers

Add `.card--compact`, `.card--contact`, and card hover lift to `minoo.css`. These are prerequisites for atlas absorption.

**Files:**
- Modify: `public/css/minoo.css` — `@layer components` section

- [ ] **Step 1: Add `translateY` to card hover**

In `public/css/minoo.css`, find the `.card:hover` block (~line 667) and the `a.card:hover` block (~line 750). Add `transform: translateY(-1px)` and add `transform` to the transition shorthand.

```css
/* ~line 655: update .card transition to include transform */
.card {
  /* ... existing properties ... */
  transition: box-shadow var(--duration-fast) var(--ease-out),
              border-color var(--duration-fast) var(--ease-out),
              transform var(--duration-fast) var(--ease-out);
}

.card:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--card-accent, oklch(0.4 0 0));
  transform: translateY(-1px);
}

/* ~line 745: same for a.card */
a.card:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--card-accent, oklch(0.4 0 0));
  transform: translateY(-1px);
}
```

- [ ] **Step 2: Verify `.card--compact` exists or add it**

There's already a `.card--compact` at ~line 3176. Verify it has these properties. If missing, add:

```css
.card--compact {
  max-inline-size: none;
  padding: var(--space-xs);
}
.card--compact .card__title {
  font-size: var(--text-sm);
}
.card--compact .card__meta {
  font-size: 0.8rem;
}
```

- [ ] **Step 3: Add `.card--contact` modifier**

Add after `.card--compact` in `@layer components`:

```css
.card--contact {
  --card-accent: var(--color-communities);
  max-inline-size: none;
}
.card--contact .card--contact__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-sm);
}
.card--contact .card--contact__label {
  font-size: 0.7rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wide);
  margin-block-end: var(--space-3xs);
}
.card--contact .card--contact__value {
  font-size: var(--text-sm);
  font-weight: 500;
}
.card--contact .card--contact__value a {
  color: var(--color-communities);
  text-decoration: underline;
  text-underline-offset: 2px;
}
@media (max-width: 480px) {
  .card--contact .card--contact__grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 4: Add community section styles**

These replace `atlas-section`, `atlas-group__label`, etc. Add in `@layer components`:

```css
/* Community sections (replaces atlas-section) */
.community-section {
  padding: var(--space-md) 0;
  border-block-end: 1px solid var(--border-subtle);
}
.community-section:last-child {
  border-block-end: none;
}
.community-section__label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.09rem;
  color: var(--color-communities);
  font-weight: 600;
  margin-block-end: var(--space-xs);
}
.community-section__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-sm);
}
.community-section__field-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-block-end: var(--space-3xs);
}
.community-section__field-value {
  font-size: var(--text-sm);
  font-weight: 500;
}
.community-section__field-value a {
  color: var(--color-communities);
  text-decoration: underline;
  text-underline-offset: 2px;
}
@media (max-width: 480px) {
  .community-section__grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 5: Add community list styles (replaces atlas-header, atlas-filters, atlas-chips)**

```css
/* Community list (replaces atlas-header, atlas-filters, atlas-chips, atlas-list) */
.community-header {
  padding: var(--space-lg) var(--gutter);
  background: var(--surface-dark);
}
.community-header__label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.125rem;
  color: var(--text-muted);
  margin-block-end: var(--space-3xs);
}
.community-header__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--text-primary);
}
.community-header__meta {
  font-size: var(--text-sm);
  color: var(--text-secondary);
  margin-block-start: var(--space-3xs);
}

.community-filters {
  padding: var(--space-xs) var(--gutter);
  background: var(--surface-raised);
  border-block-end: 1px solid var(--border-subtle);
}
.community-search {
  inline-size: 100%;
  background: var(--surface-card);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-md);
  padding: var(--space-2xs) var(--space-xs);
  font-size: var(--text-sm);
  color: var(--text-primary);
  margin-block-end: var(--space-xs);
  outline: none;
}
.community-search:focus {
  border-color: var(--color-communities);
}
.community-search::placeholder {
  color: var(--text-muted);
}
.community-chips {
  display: flex;
  gap: var(--space-2xs);
  flex-wrap: wrap;
}
.community-chip {
  padding: var(--space-3xs) var(--space-xs);
  border-radius: var(--radius-full);
  font-size: 0.7rem;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid var(--border-subtle);
  background: var(--surface-card);
  color: var(--text-secondary);
  transition: background var(--duration-fast), color var(--duration-fast);
}
.community-chip--active {
  background: var(--color-communities);
  color: var(--surface-dark);
  border-color: var(--color-communities);
}
.community-chip-dropdown {
  position: relative;
}
.community-chip-dropdown__menu {
  position: absolute;
  inset-block-start: 100%;
  inset-inline-start: 0;
  margin-block-start: var(--space-3xs);
  background: var(--surface-card);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  min-inline-size: 12rem;
  max-block-size: 16rem;
  overflow-y: auto;
  z-index: 100;
  padding: var(--space-3xs) 0;
}
.community-chip-dropdown__item {
  padding: var(--space-2xs) var(--space-xs);
  font-size: var(--text-sm);
  color: var(--text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: var(--space-2xs);
}
.community-chip-dropdown__item:hover {
  background: var(--surface-raised);
  color: var(--text-primary);
}

.community-list {
  padding: var(--space-sm) var(--gutter);
}
.community-group__label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.09rem;
  color: var(--text-muted);
  font-weight: 600;
  margin: var(--space-md) 0 var(--space-xs);
}
.community-group__label:first-child {
  margin-block-start: 0;
}

/* Community map container (replaces atlas-map) */
.community-map {
  block-size: 40vh;
  min-block-size: 200px;
  max-block-size: 400px;
  background: var(--surface-raised);
  border-block-end: 1px solid var(--border-subtle);
  overflow: hidden;
}

/* Full-bleed for map pages */
.site-main:has(.community-map) {
  padding-inline: 0;
}

@media (max-width: 768px) {
  .community-header {
    padding: var(--space-sm) var(--gutter);
  }
  .community-header__title {
    font-size: var(--text-lg);
  }
  .community-map {
    block-size: 30vh;
    min-block-size: 180px;
  }
  .community-filters {
    padding: var(--space-xs) var(--gutter);
    overflow-x: hidden;
  }
  .community-chips {
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    max-inline-size: 100%;
  }
  .community-chip {
    white-space: nowrap;
    flex-shrink: 0;
  }
  .community-chip-dropdown__menu {
    min-inline-size: 0;
    inline-size: max-content;
    max-inline-size: calc(100vw - 2rem);
    inset-inline-end: 0;
    inset-inline-start: auto;
  }
  .community-list {
    padding: var(--space-xs) var(--gutter);
  }
}
```

- [ ] **Step 6: Add community detail styles (replaces atlas-detail-hero, atlas-detail-map, atlas-nearby)**

```css
/* Community detail (replaces atlas-detail-hero) */
.community-detail-hero {
  padding: var(--space-lg) var(--gutter) var(--space-md);
  background: var(--surface-dark);
}
.community-detail-hero__back {
  font-size: 0.7rem;
  color: var(--text-muted);
  text-decoration: none;
}
.community-detail-hero__back:hover {
  color: var(--text-primary);
}
.community-detail-hero__badge {
  display: inline-block;
  background: oklch(0.3 0.03 230 / 0.4);
  padding: var(--space-3xs) var(--space-2xs);
  border-radius: var(--radius-sm);
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.06rem;
  color: var(--color-communities);
  margin-block-start: var(--space-sm);
}
.community-detail-hero__name {
  font-family: var(--font-heading);
  font-size: var(--text-3xl);
  font-weight: 700;
  color: var(--text-primary);
  margin-block-start: var(--space-2xs);
}
.community-detail-hero__subtitle {
  font-size: var(--text-sm);
  color: var(--text-secondary);
  margin-block-start: var(--space-3xs);
}
.community-detail-hero__stats {
  display: flex;
  gap: var(--space-sm);
  margin-block-start: var(--space-sm);
  font-size: var(--text-sm);
  color: var(--text-muted);
}

/* Community detail map */
.community-detail-map {
  block-size: 200px;
  border-radius: var(--radius-md);
  margin-block-end: var(--space-xs);
  background: var(--surface-raised);
}
.community-detail-coords {
  display: flex;
  gap: var(--space-sm);
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.community-detail-coords a {
  color: var(--color-communities);
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* Community nearby grid */
.community-nearby-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2xs);
}
@media (max-width: 768px) {
  .community-nearby-grid {
    grid-template-columns: 1fr;
  }
  .community-detail-hero__name {
    font-size: var(--text-2xl);
  }
  .community-section {
    padding: var(--space-sm) 0;
  }
}

/* Community leader cards */
.community-leader-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2xs);
}
@media (max-width: 768px) {
  .community-leader-grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 7: Run dev server and visually verify card styles**

Run: `php -S localhost:8081 -t public`
Check: `/events`, `/groups` — cards should now have subtle lift on hover.

- [ ] **Step 8: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add card modifiers and community component styles for atlas absorption"
```

---

## Task 2: Atlas Absorption — Communities List Template

Migrate `communities/list.html.twig` from atlas classes to Minoo community classes.

**Files:**
- Modify: `templates/communities/list.html.twig`

- [ ] **Step 1: Remove atlas.css link**

Find line 6: `<link rel="stylesheet" href="/css/atlas.min.css?v=6">`
Delete it.

- [ ] **Step 2: Replace all atlas class names**

Apply these replacements throughout the template:

| Find | Replace |
|---|---|
| `atlas-header` | `community-header` |
| `atlas-header__label` | `community-header__label` |
| `atlas-header__title` | `community-header__title` |
| `atlas-header__meta` | `community-header__meta` |
| `atlas-map` | `community-map` |
| `atlas-filters` | `community-filters` |
| `atlas-search` | `community-search` |
| `atlas-chips` | `community-chips` |
| `atlas-chip--active` | `community-chip--active` |
| `atlas-chip-dropdown__menu` | `community-chip-dropdown__menu` |
| `atlas-chip-dropdown__item` | `community-chip-dropdown__item` |
| `atlas-chip-dropdown` | `community-chip-dropdown` |
| `atlas-chip` | `community-chip` |
| `atlas-list` | `community-list` |
| `atlas-group__label` | `community-group__label` |
| `atlas-card__name` | `card__title` |
| `atlas-card__meta` | `card__meta` |
| `atlas-card__badge` | `card__badge` |
| `atlas-card__stats` | `card__meta` (reuse) |
| `atlas-card__population` | `card__meta` (inline span) |
| `atlas-card__distance` | `card__meta` (inline span) |
| `atlas-card` | `card card--community` |

**Important:** The template uses Alpine.js (`x-data`, `x-for`, `x-text`, etc.). Only change CSS class names — do NOT touch any `x-` attributes, `:class` bindings, `@click` handlers, or `<template>` tags.

For `:class` bindings, update the class name strings inside the binding:
- `:class="{ 'atlas-chip--active': ... }"` → `:class="{ 'community-chip--active': ... }"`

- [ ] **Step 3: Update the empty state inline style**

Find: `style="text-align: center; padding: 2rem; color: #999;"`
Replace with: `class="homepage-empty"` (reuse existing empty state class)

- [ ] **Step 4: Run dev server and verify communities list**

Run: `php -S localhost:8081 -t public`
Check: `/communities` — header should be dark (not green), filters dark-themed, cards match site style.
Verify: map still renders, Alpine.js filtering still works, chip dropdowns still open.

- [ ] **Step 5: Commit**

```bash
git add templates/communities/list.html.twig
git commit -m "feat: migrate communities list from atlas to Minoo theme"
```

---

## Task 3: Atlas Absorption — Communities Detail + Contact Card

Migrate `communities/detail.html.twig` and `community-contact-card.html.twig` from atlas classes.

**Files:**
- Modify: `templates/communities/detail.html.twig`
- Modify: `templates/components/community-contact-card.html.twig`

- [ ] **Step 1: Remove atlas.css link from detail template**

Find line 6 in `templates/communities/detail.html.twig`: `<link rel="stylesheet" href="/css/atlas.min.css?v=6">`
Delete it.

- [ ] **Step 2: Replace atlas classes in detail template**

Apply these replacements throughout `communities/detail.html.twig`:

| Find | Replace |
|---|---|
| `atlas-detail-hero` | `community-detail-hero` |
| `atlas-detail-hero__back` | `community-detail-hero__back` |
| `atlas-detail-hero__badge` | `community-detail-hero__badge` |
| `atlas-detail-hero__name` | `community-detail-hero__name` |
| `atlas-detail-hero__subtitle` | `community-detail-hero__subtitle` |
| `atlas-detail-hero__stats` | `community-detail-hero__stats` |
| `atlas-section__label` | `community-section__label` |
| `atlas-section__grid` | `community-section__grid` |
| `atlas-section__field-label` | `community-section__field-label` |
| `atlas-section__field-value` | `community-section__field-value` |
| `atlas-section` | `community-section` |
| `atlas-detail-map` | `community-detail-map` |
| `atlas-detail-coords` | `community-detail-coords` |
| `atlas-nearby-grid` | `community-nearby-grid` |
| `atlas-nearby-card__name` | `card__title` |
| `atlas-nearby-card__meta` | `card__meta` |
| `atlas-nearby-card` | `card card--compact card--community` |

For local content sections (events, teachings, businesses, people), the cards already use `.card .card--compact` — verify these don't need changes.

- [ ] **Step 3: Replace atlas classes in community-contact-card component**

In `templates/components/community-contact-card.html.twig`, apply these replacements:

| Find | Replace |
|---|---|
| `atlas-section__label` | `community-section__label` |
| `atlas-section__field-label` | `card--contact__label` |
| `atlas-section__field-value` | `card--contact__value` |
| `atlas-section` | `community-section` |
| `atlas-leader-card atlas-leader-card--chief` | `card card--compact card--person` |
| `atlas-leader-card__role` | `card__badge` |
| `atlas-leader-card__name` | `card__title` |
| `atlas-leader-card` | `card card--compact card--person` |
| `atlas-councillor-grid` | `community-leader-grid` |
| `atlas-contact-card__grid` | `card--contact__grid` |
| `atlas-contact-card` | `card card--contact` |

Also replace the inline style on the chief card:
Find: `style="display: flex; justify-content: space-between; align-items: center;"`
Replace with: `style="display: flex; justify-content: space-between; align-items: center;"` (keep for now — or add a utility class later)

Find: `style="font-size: 0.6875rem; color: #999;"`
Replace with: `class="card__meta"` and remove the style attribute.

- [ ] **Step 4: Run dev server and verify community detail pages**

Run: `php -S localhost:8081 -t public`
Check: `/communities/sagamok-anishnawbek` (or any community with data)
Verify: dark hero (not green gradient), map still renders, leadership cards styled, band office contact card works, nearby communities grid works, local content sections intact.

- [ ] **Step 5: Commit**

```bash
git add templates/communities/detail.html.twig templates/components/community-contact-card.html.twig
git commit -m "feat: migrate communities detail and contact card from atlas to Minoo theme"
```

---

## Task 4: Delete Atlas CSS Files

Now that no templates reference atlas, remove the files.

**Files:**
- Delete: `public/css/atlas.css`
- Delete: `public/css/atlas.min.css`

- [ ] **Step 1: Verify no remaining atlas references**

Run: `grep -r 'atlas' templates/` — should return zero results.
Run: `grep -r 'atlas\.css\|atlas\.min\.css' .` — should return zero results (excluding git history and this plan).

- [ ] **Step 2: Delete atlas CSS files**

```bash
rm public/css/atlas.css public/css/atlas.min.css
```

- [ ] **Step 3: Run dev server and verify communities pages still work**

Run: `php -S localhost:8081 -t public`
Check: `/communities` and `/communities/sagamok-anishnawbek` — everything should look the same as after Task 3.

- [ ] **Step 4: Commit**

```bash
git add -A public/css/atlas.css public/css/atlas.min.css
git commit -m "chore: delete atlas.css — all styles absorbed into minoo.css"
```

---

## Task 5: Typography & Editorial Hierarchy

Refine typography across all page types for sharp editorial feel.

**Files:**
- Modify: `public/css/minoo.css` — `@layer base`, `@layer components`

- [ ] **Step 1: Improve global link styling**

In `@layer base`, find the `a` rule (~line 211). Add underline tuning:

```css
a {
  color: var(--link);
  text-decoration-color: oklch(0.6 0.15 25 / 0.4);
  text-underline-offset: 0.15em;
  text-decoration-thickness: 1px;
  transition: color var(--duration-fast) var(--ease-out);
}
a:hover {
  text-decoration-color: currentColor;
}
```

- [ ] **Step 2: Improve global list styling**

In `@layer base`, add after the link rule:

```css
ul, ol {
  padding-inline-start: 1.5em;
}
li + li {
  margin-block-start: var(--space-3xs);
}
li::marker {
  color: var(--text-muted);
}
```

- [ ] **Step 3: Elevate listing hero typography**

Find `.listing-hero` (~line 3080). Update:

```css
.listing-hero {
  margin-block-end: var(--space-lg);
}
.listing-hero h1 {
  font-size: var(--text-3xl);
  letter-spacing: var(--tracking-tight);
  margin-block-end: var(--space-2xs);
}
.listing-hero__subtitle {
  font-size: var(--text-lg);
  color: var(--text-secondary);
  line-height: var(--leading-normal);
}
```

- [ ] **Step 4: Elevate detail hero typography**

Find `.detail-hero` (~line 3097). Update `.detail-hero__title`:

```css
.detail-hero__title {
  font-size: var(--text-3xl);
  font-weight: 700;
}
```

- [ ] **Step 5: Constrain detail body to prose width**

Find `.detail__body` (~line 1013). Add:

```css
.detail__body {
  max-inline-size: var(--width-prose);
  line-height: var(--leading-loose);
}
```

- [ ] **Step 6: Standardize related section labels**

Find `.related-section` (~line 3158). Ensure the `h2` styling:

```css
.related-section {
  margin-block-start: var(--space-lg);
  padding-block-start: var(--space-lg);
  border-block-start: 1px solid var(--border-subtle);
}
.related-section h2 {
  font-size: var(--text-lg);
  margin-block-end: var(--space-sm);
}
```

- [ ] **Step 7: Update homepage hero and featured card typography**

Find `.homepage-hero-tagline` (~line 2326). Ensure:

```css
.homepage-hero-tagline {
  font-family: var(--font-heading);
  font-size: var(--text-3xl);
  line-height: var(--leading-tight);
}
```

Find `.featured-card__headline` (~line 2495). Change font:

```css
.featured-card__headline {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--text-primary);
}
```

- [ ] **Step 8: Tighten homepage tab typography**

Find `.homepage-tab` (~line 2534). Add letter-spacing and bolder active weight:

```css
.homepage-tab {
  /* existing properties... */
  letter-spacing: var(--tracking-tight);
}
.homepage-tab.active {
  /* existing properties... */
  font-weight: 600;
}
```

- [ ] **Step 9: Run dev server and verify typography changes**

Run: `php -S localhost:8081 -t public`
Check pages: `/`, `/events`, `/events/{slug}`, `/groups`, `/teachings`, `/about`
Verify: larger listing heroes, prose-width body text, consistent related section dividers, featured cards use Fraunces.

- [ ] **Step 10: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: elevate typography hierarchy — editorial sizing, prose width, link tuning"
```

---

## Task 6: Ribbon & Section Transitions

Elevate the ribbon from empty div to subtle editorial divider. Add section rhythm.

**Files:**
- Modify: `public/css/minoo.css` — ribbon in `@layer layout`, transitions in `@layer components`

- [ ] **Step 1: Restyle the ribbon**

Find `.ribbon` (~line 529). Replace with:

```css
.ribbon {
  margin-block: var(--space-lg);
  display: flex;
  justify-content: center;
}
.ribbon::after {
  content: '';
  display: block;
  inline-size: 60%;
  max-inline-size: 40rem;
  block-size: 1px;
  background: linear-gradient(to right, transparent, var(--border-subtle), transparent);
}
```

- [ ] **Step 2: Add section rhythm to homepage**

Find `.featured-section` (~line 2416). Add margin:

```css
.featured-section {
  margin-block-start: var(--space-xl);
}
```

Find `.homepage-tabs` (~line 2519). Add margin:

```css
.homepage-tabs {
  margin-block-start: var(--space-xl);
  /* ...existing properties... */
}
```

Find `.homepage-communities` — add top margin:

```css
.homepage-communities {
  margin-block-start: var(--space-xl);
}
```

- [ ] **Step 3: Add editorial inset to content-well on wide screens**

Find `.content-well` (~line 2988). Add wide-screen padding:

```css
@media (min-width: 80rem) {
  .content-well {
    padding-inline: var(--space-lg);
  }
}
```

- [ ] **Step 4: Run dev server and verify ribbon + spacing**

Check: `/` — ribbon should show thin gradient line between header/content and content/footer.
Check: homepage sections should have generous vertical rhythm.
Check: `/events/{slug}` — related sections have top border dividers.

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add editorial ribbon divider and section rhythm"
```

---

## Task 7: Detail Page Normalization

Ensure all domain detail pages have consistent meta spacing, section labels, and badge patterns.

**Files:**
- Modify: `public/css/minoo.css` — detail meta, badge normalization
- Modify: `templates/events.html.twig` — verify structure
- Modify: `templates/groups.html.twig` — related section label consistency
- Modify: `templates/teachings.html.twig` — same
- Modify: `templates/people.html.twig` — badge normalization
- Modify: `templates/businesses.html.twig` — section label normalization
- Modify: `templates/language.html.twig` — typography pass (unique structure, not standard detail anatomy)

- [ ] **Step 1: Normalize detail meta spacing in CSS**

In `public/css/minoo.css`, find `.detail__meta` (~line 1005). Ensure:

```css
.detail__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
  font-size: var(--text-sm);
  color: var(--text-muted);
  margin-block-start: var(--space-xs);
}
```

- [ ] **Step 2: Verify events detail page structure**

Read `templates/events.html.twig`. The detail section should already have:
- `.detail-hero` with back link, badge, title, meta
- `.content-well` with `.detail__body.flow`
- `.related-section` blocks

If any related sections are missing the `h2` tag or using inconsistent label patterns, normalize them.

- [ ] **Step 3: Normalize groups detail related sections**

Read `templates/groups.html.twig`. Ensure related sections (people, events, teachings) all use:
```html
<section class="related-section">
  <h2>{{ trans('groups.related_people') }}</h2>
  <div class="card-grid card-grid--compact">
```

- [ ] **Step 4: Normalize teachings detail related sections**

Same treatment as groups in `templates/teachings.html.twig`.

- [ ] **Step 5: Normalize people detail page badges**

Read `templates/people.html.twig`. Ensure `.role-badge` styling matches `.detail__badge` pattern. If role badges use different CSS, unify the visual treatment (same border-radius, font-size, text-transform).

In CSS, check `.role-badge` (~line 839) and `.detail__badge` (~line 976). Make them visually consistent:
- Both should use `font-size: 0.65rem`, `text-transform: uppercase`, `letter-spacing: 0.06rem`
- Role badges keep their domain colors

- [ ] **Step 6: Normalize businesses detail section labels**

Read `templates/businesses.html.twig`. Find any section headings that aren't using the uppercase label pattern. Update CSS or add classes as needed.

In CSS, ensure business detail sections use community-section-style labels where they have structured metadata sections.

- [ ] **Step 7: Typography pass on language page**

Read `templates/language.html.twig`. This page has a unique structure (dictionary entries, not standard detail anatomy). Apply:
- Verify headings use Fraunces via existing `h1`/`h2` inheritance
- Verify body text gets prose-width from `.content-well` or `.flow` classes if applicable
- Add `.listing-hero` pattern to the page header if not already present

- [ ] **Step 8: Run dev server and verify all detail pages**

Check: `/events/{slug}`, `/groups/{slug}`, `/teachings/{slug}`, `/people/{slug}`, `/businesses/{slug}`, `/language`
Verify: consistent hero sizing, meta spacing, prose-width body, related section dividers, badge styles.

- [ ] **Step 9: Commit**

```bash
git add public/css/minoo.css templates/events.html.twig templates/groups.html.twig templates/teachings.html.twig templates/people.html.twig templates/businesses.html.twig templates/language.html.twig
git commit -m "feat: normalize detail pages — consistent meta, sections, badges across all domains"
```

---

## Task 8: Remaining Template Typography Passes

Verify and adjust typography/spacing on pages not covered by domain detail normalization.

**Files:**
- Verify: `templates/page.html.twig` — homepage hero tagline uses Fraunces (CSS handles this, verify template has correct classes)
- Verify: `templates/search.html.twig` — spacing consistency with listing pages
- Verify: `templates/about.html.twig` — hero/section typography hierarchy
- Verify: `templates/how-it-works.html.twig` — same
- Verify: `templates/elders.html.twig` — typography/spacing if affected by global CSS changes
- Verify: `templates/volunteer.html.twig` — same

- [ ] **Step 1: Verify homepage template**

Read `templates/page.html.twig`. The homepage hero tagline should already get Fraunces from CSS (Task 5 updated `.homepage-hero-tagline`). Verify the class is present on the `h1`. No template change expected.

- [ ] **Step 2: Verify search page spacing**

Read `templates/search.html.twig`. Check that the page heading and search form have consistent spacing with listing heroes. If the search page uses `.flow-lg` or its own heading pattern, verify it visually aligns with other pages. Add `.listing-hero` wrapper around the heading if not already present.

- [ ] **Step 3: Verify about page typography**

Read `templates/about.html.twig`. Uses `.hero` + `.portal-section .flow` pattern. Verify headings use Fraunces via inheritance, body text is readable. These are CSS-driven — likely no template changes needed.

- [ ] **Step 4: Verify how-it-works page typography**

Read `templates/how-it-works.html.twig`. Same pattern as about. Verify visually.

- [ ] **Step 5: Verify elders and volunteer pages**

Read `templates/elders.html.twig` and `templates/volunteer.html.twig`. These are program pages. Verify typography and spacing align with the global CSS changes. Flag any inconsistencies.

- [ ] **Step 6: Run dev server and verify all pages**

Run: `php -S localhost:8081 -t public`
Check: `/`, `/search?q=test`, `/about`, `/how-it-works`, `/elders`, `/volunteer`
Verify: consistent heading sizes, spacing rhythm, no visual regressions.

- [ ] **Step 7: Commit if any template changes were needed**

```bash
git add templates/
git commit -m "feat: typography consistency pass on remaining pages"
```

Note: this commit may be empty if all changes were CSS-driven. That's fine — skip the commit in that case.

---

## Task 9: Mobile Refinements

Verify and refine mobile layouts after all the changes.

**Files:**
- Modify: `public/css/minoo.css` — media queries

- [ ] **Step 1: Force single-column compact card grids on small screens**

In `@layer components`, find or add after `.card--compact` styles:

```css
@media (max-width: 480px) {
  .card-grid--compact {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 2: Verify homepage mobile layout**

Check: featured cards stack single-column below 640px (already in CSS ~line 2508). Verify tab row doesn't clip.

- [ ] **Step 3: Verify detail page meta wraps properly**

The `flex-wrap: wrap` + `row-gap` from Task 7 should handle this. Check `/events/{slug}` on narrow viewport.

- [ ] **Step 4: Verify touch targets**

Spot-check: filter chips, tab links, card links, nav items. Ensure minimum 44px tap target via padding.

- [ ] **Step 5: Commit if changes were needed**

```bash
git add public/css/minoo.css
git commit -m "fix: mobile refinements — compact grid breakpoints, touch targets"
```

---

## Task 10: Motion & Micro-interactions

Add restrained motion as the final polish layer.

**Files:**
- Modify: `public/css/minoo.css` — `@layer components`
- Modify: `templates/base.html.twig` — IntersectionObserver script

- [ ] **Step 1: Verify existing card animation**

The CSS already has `@keyframes fadeInUp` and staggered card delays (~line 2252-2285) wrapped in `prefers-reduced-motion: no-preference`. Verify this still applies correctly after card changes.

- [ ] **Step 2: Add nav active state underline animation**

Find `.site-nav a` (~line 282). Add:

```css
.site-nav a {
  position: relative;
}
.site-nav a::after {
  content: '';
  position: absolute;
  inset-inline: 0;
  inset-block-end: -2px;
  block-size: 2px;
  background: var(--link);
  transform: scaleX(0);
  transition: transform var(--duration-fast) var(--ease-out);
  transform-origin: center;
}
.site-nav a:hover::after,
.site-nav a[aria-current="page"]::after {
  transform: scaleX(1);
}
```

- [ ] **Step 3: Add dropdown open transition**

Find dropdown menu styles. Add:

```css
.site-nav__dropdown-menu {
  opacity: 0;
  transform: translateY(-4px);
  transition: opacity var(--duration-fast) var(--ease-out),
              transform var(--duration-fast) var(--ease-out);
  pointer-events: none;
}
.site-nav__dropdown-menu.is-open {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
}
```

- [ ] **Step 4: Add IntersectionObserver for related section fade-in**

In `templates/base.html.twig`, add before the closing `</body>` tag (after chat script, before `{% block scripts %}`):

```html
<script>
if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.style.opacity = '1'; observer.unobserve(e.target); } });
  }, { threshold: 0.1 });
  document.querySelectorAll('.related-section').forEach(el => { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s ease'; observer.observe(el); });
}
</script>
```

- [ ] **Step 5: Run dev server and verify motion**

Check: card hover lifts, nav underline animates, related sections fade in on scroll.
Check: with `prefers-reduced-motion: reduce` in browser — no animations.

- [ ] **Step 6: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat: add editorial motion — nav underline, dropdown transitions, scroll fade-in"
```

---

## Task 11: Final Cleanup & Verification

Remove any dead CSS from atlas absorption, verify everything works.

**Files:**
- Modify: `public/css/minoo.css` — remove dead code
- Modify: `public/css/minoo.min.css` — regenerate if applicable

- [ ] **Step 1: Search for remaining atlas references in CSS**

Run: `grep -n 'atlas' public/css/minoo.css` — should return zero results.

- [ ] **Step 2: Remove dead selectors from atlas absorption**

Search for orphaned selectors that were only used by atlas templates:

```bash
grep -r 'community__nearby' templates/    # old nearby card
```

If zero results, delete the `.community__nearby-card`, `.community__nearby-name`, `.community__nearby-distance` blocks (~lines 1489-1530).

Also do a broader scan: extract all class names from minoo.css component section that contain `atlas`, `community__`, or look unused, and verify against templates.

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: all tests pass (tests don't cover CSS, but verify no template syntax errors).

- [ ] **Step 4: Full site visual walkthrough**

Check every major page at desktop width:
- `/` (homepage)
- `/communities` (list)
- `/communities/sagamok-anishnawbek` (detail)
- `/events`, `/events/{slug}`
- `/groups`, `/groups/{slug}`
- `/teachings`, `/teachings/{slug}`
- `/people`, `/people/{slug}`
- `/businesses`, `/businesses/{slug}`
- `/search?q=test`
- `/about`
- `/how-it-works`
- `/language`
- `/elders`
- `/volunteer`

- [ ] **Step 5: Mobile walkthrough**

Repeat the above at 375px viewport width. Check: no overflow, no clipping, cards stack single-column, touch targets adequate.

- [ ] **Step 6: Commit cleanup**

```bash
git add public/css/minoo.css
git commit -m "chore: remove dead atlas CSS selectors after absorption"
```
