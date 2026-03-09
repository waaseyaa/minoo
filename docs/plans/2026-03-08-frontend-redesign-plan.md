# Frontend Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Redesign Minoo's frontend with refined typography, visual depth, component differentiation, and atmospheric warmth — while preserving the existing color palette, CSS architecture, and template inheritance.

**Architecture:** CSS-only changes + template markup updates. No PHP changes. Self-hosted Atkinson Hyperlegible Next web font. New tokens, refined components, hero with decorative pattern, expanded footer. All motion gated behind `prefers-reduced-motion`.

**Tech Stack:** Vanilla CSS (oklch, @layer, native nesting, container queries), Twig 3 templates, WOFF2 web fonts.

**Design doc:** `docs/plans/2026-03-08-frontend-redesign-design.md`

**Existing tests to preserve:**
- PHPUnit: `./vendor/bin/phpunit` (238 tests) — no PHP changes, these are unaffected
- Playwright: `npx playwright test` (28 tests across 6 spec files) — CSS class selectors must survive

**Key Playwright selectors to preserve:**
- `.hero__title`, `.hero__actions`, `.btn--primary`, `.btn--secondary`
- `.skip-link`, `.card-grid .card`, `.card__title`
- `.site-nav`, `.site-nav__dropdown-toggle`, `.site-nav__dropdown-menu`
- `a[href="/elders"]`, `a[href="/communities"]`, etc.

---

### Task 1: Download and set up Atkinson Hyperlegible Next font files

**Files:**
- Create: `public/fonts/atkinson-hyperlegible-next-400.woff2`
- Create: `public/fonts/atkinson-hyperlegible-next-700.woff2`

**Step 1: Download font files**

Download Atkinson Hyperlegible Next from the official Braille Institute source. We need two WOFF2 files: regular (400) and bold (700).

```bash
mkdir -p public/fonts
# Download from the official Google Fonts source (Atkinson Hyperlegible Next is on Google Fonts)
curl -L "https://fonts.gstatic.com/s/atkinsonhyperlegiblenext/v1/W_8gHIoABsYbfuxqbbo3OQ8SGEYOQ35JCwMbBszzXLIJF0wd.woff2" -o public/fonts/atkinson-hyperlegible-next-400.woff2
curl -L "https://fonts.gstatic.com/s/atkinsonhyperlegiblenext/v1/W_8gHIoABsYbfuxqbbo3OQ8SGEYOQ35JCwMbBszzXLIJbEkd.woff2" -o public/fonts/atkinson-hyperlegible-next-700.woff2
```

If the URLs don't resolve, use the Google Fonts CSS API to find the current WOFF2 URLs:

```bash
curl -H "User-Agent: Mozilla/5.0" "https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;700&display=swap" 2>/dev/null | grep -o 'https://[^)]*\.woff2'
```

Then download those URLs to `public/fonts/`.

**Step 2: Verify files exist and are valid WOFF2**

```bash
file public/fonts/atkinson-hyperlegible-next-400.woff2
file public/fonts/atkinson-hyperlegible-next-700.woff2
ls -la public/fonts/
```

Expected: both files exist, are >10KB each, identified as WOFF2 or "Web Open Font Format (Version 2)".

**Step 3: Commit**

```bash
git add public/fonts/
git commit -m "chore: add Atkinson Hyperlegible Next font files (400, 700)"
```

---

### Task 2: Add font-face declarations and new design tokens to CSS

**Files:**
- Modify: `public/css/minoo.css` (tokens layer, lines 51-141)

**Step 1: Add @font-face rules at the very top of the file, before the @layer line**

Insert at line 1, before the existing `@layer reset, tokens, base, layout, components, utilities;`:

```css
@font-face {
  font-family: 'Atkinson Hyperlegible Next';
  src: url('/fonts/atkinson-hyperlegible-next-400.woff2') format('woff2');
  font-weight: 400;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'Atkinson Hyperlegible Next';
  src: url('/fonts/atkinson-hyperlegible-next-700.woff2') format('woff2');
  font-weight: 700;
  font-style: normal;
  font-display: swap;
}
```

**Step 2: Update tokens layer with new tokens and font-body change**

In the `:root` block inside `@layer tokens`, make these changes:

a) Change `--font-body` (line 92):
```css
--font-body: 'Atkinson Hyperlegible Next', system-ui, -apple-system, sans-serif;
```

b) After the existing `--shadow-lg` token (line 140), add new tokens:

```css
    /* Warm surfaces */
    --surface-warm:    oklch(0.96 0.01 70);
    --surface-dark:    var(--color-earth-900);
    --text-on-dark:    oklch(0.92 0.005 70);
    --text-muted:      oklch(0.55 0.015 70);

    /* Domain accents */
    --domain-events:    var(--color-water-600);
    --domain-groups:    var(--color-forest-500);
    --domain-teachings: var(--color-earth-700);
    --domain-language:  var(--color-forest-700);
    --domain-people:    oklch(0.45 0.06 160);
    --domain-elders:    oklch(0.45 0.10 145);

    /* Motion */
    --ease-out:    cubic-bezier(0.25, 0.46, 0.45, 0.94);
    --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --duration-fast: 150ms;
    --duration-normal: 250ms;

    /* Extended radii */
    --radius-xl: 1.5rem;
```

**Step 3: Update heading styles in base layer**

In `@layer base`, update `h1` to have tighter tracking:

```css
  h1 {
    font-size: var(--text-3xl);
    letter-spacing: -0.02em;
  }
```

**Step 4: Verify the dev server renders with new font**

```bash
php -S localhost:8081 -t public &
sleep 2
curl -s http://localhost:8081/ | grep -c "Atkinson"
curl -s http://localhost:8081/css/minoo.css | head -20
kill %1
```

Expected: the CSS file begins with `@font-face` declarations, and the homepage HTML loads without errors.

**Step 5: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add design tokens, Atkinson Hyperlegible Next font"
```

---

### Task 3: Add font preload to base template

**Files:**
- Modify: `templates/base.html.twig` (lines 3-9, `<head>` block)

**Step 1: Add preload links in `<head>`, after the viewport meta tag (line 5) and before the `<title>` tag (line 6)**

Insert after `<meta name="viewport" content="width=device-width, initial-scale=1">`:

```html
  <link rel="preload" href="/fonts/atkinson-hyperlegible-next-400.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/fonts/atkinson-hyperlegible-next-700.woff2" as="font" type="font/woff2" crossorigin>
```

**Step 2: Verify preload renders in HTML**

```bash
php -S localhost:8081 -t public &
sleep 2
curl -s http://localhost:8081/ | grep "preload"
kill %1
```

Expected: two `<link rel="preload">` lines in the output.

**Step 3: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat: preload Atkinson Hyperlegible Next font files"
```

---

### Task 4: Redesign header with wordmark and refined nav

**Files:**
- Modify: `templates/base.html.twig` (lines 14-45, header section)
- Modify: `public/css/minoo.css` (layout layer, `.site-header` through `.site-nav` — lines 186-398)

**Step 1: Replace text site-name with wordmark SVG in `base.html.twig`**

Change line 16 from:
```html
        <a href="/" class="site-name">Minoo</a>
```
To:
```html
        <a href="/" class="site-name" aria-label="Minoo — home">
          <img src="/img/minoo-wordmark.svg" alt="Minoo" class="site-name__wordmark" width="100" height="28">
        </a>
```

**Step 2: Update header CSS in `@layer layout`**

Update `.site-header` padding:
```css
  .site-header {
    padding-block: var(--space-sm);
    padding-inline: var(--gutter);
    border-block-end: 1px solid var(--border);
  }
```

Add wordmark sizing:
```css
  .site-name__wordmark {
    block-size: 1.75rem;
    inline-size: auto;
  }
```

Update `.site-nav a` to use bottom-border active indicator instead of background:
```css
  .site-nav a {
    padding: var(--space-2xs) var(--space-3xs);
    border-radius: 0;
    text-decoration: none;
    font-size: var(--text-sm);
    color: var(--text-secondary);
    position: relative;
    transition: color var(--duration-fast) var(--ease-out);

    &:hover {
      color: var(--text-primary);
      background-color: transparent;
    }

    &::after {
      content: '';
      position: absolute;
      inset-inline: var(--space-3xs);
      inset-block-end: -2px;
      block-size: 2px;
      background-color: var(--accent);
      transform: scaleX(0);
      transition: transform var(--duration-normal) var(--ease-out);
    }

    &:hover::after {
      transform: scaleX(1);
    }

    &[aria-current="page"] {
      color: var(--accent);
      background-color: transparent;

      &::after {
        transform: scaleX(1);
      }
    }
  }
```

**Step 3: Update mobile nav to overlay style**

Replace the mobile nav media query (lines 355-398) with:

```css
  @media (max-width: 60em) {
    .site-nav {
      display: none;

      &.is-open {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--space-sm);
        position: fixed;
        inset: 0;
        background-color: oklch(0.25 0.02 70 / 0.95);
        padding: var(--space-lg);
        z-index: 50;
      }

      &.is-open a {
        color: var(--text-on-dark);
        font-size: var(--text-lg);
        padding: var(--space-xs) var(--space-sm);
      }

      &.is-open a:hover {
        color: white;
      }

      &.is-open a[aria-current="page"] {
        color: white;
      }

      &.is-open a::after {
        display: none;
      }
    }

    .nav-toggle {
      display: block;
      background: none;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: var(--space-3xs) var(--space-2xs);
      cursor: pointer;
      font-size: var(--text-sm);
      color: var(--text-secondary);
      z-index: 51;
    }

    .site-nav__dropdown-menu {
      position: static;
      box-shadow: none;
      border: none;
      padding-inline-start: var(--space-sm);
      background: transparent;

      .is-open & a {
        color: var(--text-on-dark);
        font-size: var(--text-base);
      }
    }

    .site-nav__dropdown-toggle {
      .is-open & {
        color: var(--text-on-dark);
        font-size: var(--text-lg);
      }
    }

    .site-nav__utility {
      margin-inline-start: 0;
    }

    .site-header__inner {
      position: relative;
    }
  }
```

**Step 4: Add motion gate for mobile nav**

In `@layer components` (or at the end of layout), add:

```css
  @media (prefers-reduced-motion: no-preference) {
    .site-nav.is-open {
      animation: fadeIn var(--duration-normal) var(--ease-out);
    }
  }
```

And in `@layer utilities` or `@layer components`, add the keyframe:

```css
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
```

**Step 5: Run Playwright to verify nav tests pass**

```bash
npx playwright test tests/playwright/homepage.spec.ts
```

Expected: all 6 homepage tests pass. Key selectors preserved: `.site-nav`, `.site-nav__dropdown-toggle`, `.site-nav__dropdown-menu`, `.btn--primary`, `.btn--secondary`.

**Step 6: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css
git commit -m "feat: redesign header with wordmark and refined nav indicators"
```

---

### Task 5: Redesign footer with dark background and expanded content

**Files:**
- Modify: `templates/base.html.twig` (lines 55-64, footer section)
- Modify: `public/css/minoo.css` (layout layer, `.site-footer` — lines 317-348)

**Step 1: Update footer markup in `base.html.twig`**

Replace lines 55-64 (the `<footer>` block) with:

```html
    <footer class="site-footer">
      <div class="site-footer__inner">
        <div class="site-footer__brand">
          <img src="/img/minoo-wordmark.svg" alt="Minoo" class="site-footer__wordmark" width="80" height="22">
          <p class="site-footer__tagline">A living map of community</p>
        </div>
        <div class="site-footer__bottom">
          <p>&copy; {{ "now"|date("Y") }} Minoo</p>
          <nav class="site-footer__links" aria-label="Legal">
            <a href="/legal/privacy">Privacy</a>
            <a href="/legal/terms">Terms</a>
            <a href="/legal/accessibility">Accessibility</a>
          </nav>
        </div>
      </div>
    </footer>
```

**Step 2: Update footer CSS in `@layer layout`**

Replace the existing `.site-footer` rules (lines 317-348) with:

```css
  .site-footer {
    position: relative;
    padding-block: var(--space-lg) var(--space-md);
    padding-inline: var(--gutter);
    background-color: var(--surface-dark);
    color: var(--text-on-dark);
    font-size: var(--text-sm);

    &::before {
      content: '';
      position: absolute;
      inset-inline: 0;
      inset-block-start: -1.5rem;
      block-size: 1.5rem;
      background-color: var(--surface-dark);
      clip-path: ellipse(55% 100% at 50% 100%);
    }
  }

  .site-footer__inner {
    max-inline-size: var(--width-content);
    margin-inline: auto;
    display: grid;
    gap: var(--space-md);
  }

  .site-footer__brand {
    display: flex;
    flex-direction: column;
    gap: var(--space-3xs);
  }

  .site-footer__wordmark {
    block-size: 1.4rem;
    inline-size: auto;
    filter: brightness(0) invert(0.9);
  }

  .site-footer__tagline {
    color: oklch(0.7 0.01 70);
    font-style: italic;
    font-family: var(--font-heading);
  }

  .site-footer__bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-xs);
    padding-block-start: var(--space-sm);
    border-block-start: 1px solid oklch(0.35 0.02 70);
  }

  .site-footer__links {
    display: flex;
    gap: var(--space-sm);
    font-size: var(--text-sm);
  }

  .site-footer__links a {
    color: oklch(0.7 0.01 70);
    text-decoration: none;
    transition: color var(--duration-fast) var(--ease-out);
  }

  .site-footer__links a:hover {
    color: var(--text-on-dark);
  }
```

**Step 3: Run Playwright to verify no breakage**

```bash
npx playwright test
```

Expected: all 28 tests pass. Footer is not directly tested by selectors, but pages must still load and render.

**Step 4: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css
git commit -m "feat: redesign footer with dark background, brand, tagline"
```

---

### Task 6: Refine card component with domain accents and hover motion

**Files:**
- Modify: `public/css/minoo.css` (components layer, `.card` — lines 401-500+)

**Step 1: Update base card styles**

Replace the existing `.card` block (starting at line 402) with:

```css
  .card {
    container-type: inline-size;
    background-color: var(--surface-raised);
    border: 1px solid var(--border);
    border-inline-start: 3px solid var(--card-accent, var(--border));
    border-radius: var(--radius-lg);
    padding: var(--space-md);
    box-shadow: var(--shadow-sm);
    max-inline-size: var(--width-card);
    transition: transform var(--duration-normal) var(--ease-out),
                box-shadow var(--duration-normal) var(--ease-out);
  }
```

Remove the container query that bumps padding (lines 452-456) — it's now `--space-md` by default.

**Step 2: Update `a.card` hover**

Replace:
```css
  a.card:hover {
    box-shadow: var(--shadow-md);
  }
```
With:
```css
  a.card:hover {
    box-shadow: var(--shadow-lg);
  }

  @media (prefers-reduced-motion: no-preference) {
    a.card:hover {
      transform: translateY(-2px);
    }
  }
```

**Step 3: Add domain card variant rules**

After the existing `.card__badge--person` rule, add:

```css
  .card--event     { --card-accent: var(--domain-events); }
  .card--group     { --card-accent: var(--domain-groups); }
  .card--community { --card-accent: var(--domain-groups); }
  .card--teaching  { --card-accent: var(--domain-teachings); }
  .card--language  { --card-accent: var(--domain-language); }
  .card--person    { --card-accent: var(--domain-people); }
  .card--elder     { --card-accent: var(--domain-elders); }
```

Note: `.card--person` already exists for the person card layout — that's fine, CSS will merge both rule sets. The existing `.card--person .card__header-row` etc. rules are unaffected.

**Step 4: Add staggered card entrance animation**

In the keyframes section (added in Task 4), we already have `fadeInUp`. Now add the stagger in cards:

```css
  @media (prefers-reduced-motion: no-preference) {
    .card-grid > .card {
      animation: fadeInUp var(--duration-normal) var(--ease-out) both;
    }

    .card-grid > .card:nth-child(1) { animation-delay: 0ms; }
    .card-grid > .card:nth-child(2) { animation-delay: 50ms; }
    .card-grid > .card:nth-child(3) { animation-delay: 100ms; }
    .card-grid > .card:nth-child(4) { animation-delay: 150ms; }
    .card-grid > .card:nth-child(5) { animation-delay: 200ms; }
    .card-grid > .card:nth-child(6) { animation-delay: 250ms; }
    .card-grid > .card:nth-child(n+7) { animation-delay: 300ms; }
  }
```

**Step 5: Update button radius**

Find `.btn` (line ~1276) and change `border-radius: var(--radius-sm)` to:
```css
    border-radius: var(--radius-md);
```

Add primary button shadow:
```css
  .btn--primary {
    background-color: var(--color-primary);
    color: white;
    box-shadow: 0 1px 3px oklch(0.40 0.08 155 / 0.3);
  }
```

Add ghost variant after `.btn--accent`:
```css
  .btn--ghost {
    background-color: transparent;
    color: var(--text-secondary);
    padding-inline: var(--space-xs);
  }
  .btn--ghost:hover { color: var(--text-primary); text-decoration: underline; }
```

**Step 6: Run Playwright**

```bash
npx playwright test
```

Expected: all 28 tests pass. Card class selectors preserved (`.card`, `.card-grid .card`, `.card__title`).

**Step 7: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: refine cards with domain accents, hover motion, staggered entrance"
```

---

### Task 7: Add domain classes to card component templates

**Files:**
- Modify: `templates/components/event-card.html.twig`
- Modify: `templates/components/group-card.html.twig`
- Modify: `templates/components/teaching-card.html.twig`
- Modify: `templates/components/dictionary-entry-card.html.twig`

**Step 1: Update event-card.html.twig**

Change line 1 from:
```html
<article class="card">
```
To:
```html
<article class="card card--event">
```

**Step 2: Update group-card.html.twig**

Change line 1 from:
```html
<article class="card">
```
To:
```html
<article class="card card--group">
```

**Step 3: Update teaching-card.html.twig**

Change line 1 from:
```html
<article class="card">
```
To:
```html
<article class="card card--teaching">
```

**Step 4: Update dictionary-entry-card.html.twig**

Change line 1 from:
```html
<article class="card">
```
To:
```html
<article class="card card--language">
```

**Step 5: Run Playwright**

```bash
npx playwright test
```

Expected: all 28 tests pass.

**Step 6: Commit**

```bash
git add templates/components/
git commit -m "feat: add domain accent classes to card component templates"
```

---

### Task 8: Redesign homepage hero with decorative pattern and atmosphere

**Files:**
- Modify: `templates/page.html.twig` (lines 1-14, hero section)
- Modify: `public/css/minoo.css` (components layer, `.hero` — lines 1246-1273)

**Step 1: Update hero markup in `page.html.twig`**

Replace lines 7-14 (the `<section class="hero">` block) with:

```html
    <section class="hero hero--home">
      <div class="hero__inner">
        <h1 class="hero__title">Minoo: A <em>Living</em> Map of Community</h1>
        <p class="hero__subtitle">Discover communities, people, teachings, and programs that connect and support where you live.</p>
        <div class="hero__actions">
          <a href="/communities" class="btn btn--primary btn--lg">Explore Communities</a>
          <a href="/people" class="btn btn--secondary btn--lg">Browse Community Resources</a>
        </div>
      </div>
    </section>
```

Note: `hero__title` and `hero__actions` class names preserved for Playwright.

**Step 2: Update hero CSS**

Replace the existing `.hero` rules (starting at line ~1246) with:

```css
  .hero {
    text-align: center;
    padding-block: var(--space-xl) var(--space-lg);
    max-inline-size: var(--width-prose);
    margin-inline: auto;
  }

  .hero--home {
    max-inline-size: unset;
    margin-inline: calc(-1 * var(--gutter));
    padding-block: var(--space-2xl) var(--space-xl);
    padding-inline: var(--gutter);
    background: linear-gradient(
      to bottom right,
      oklch(0.97 0.005 70),
      oklch(0.94 0.015 80)
    );
    position: relative;
    overflow: hidden;

    &::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 600'%3E%3Ccircle cx='450' cy='450' r='80' fill='none' stroke='oklch(0.55 0.10 155)' stroke-width='1'/%3E%3Ccircle cx='450' cy='450' r='140' fill='none' stroke='oklch(0.55 0.10 155)' stroke-width='0.8'/%3E%3Ccircle cx='450' cy='450' r='200' fill='none' stroke='oklch(0.55 0.10 155)' stroke-width='0.6'/%3E%3Ccircle cx='450' cy='450' r='260' fill='none' stroke='oklch(0.55 0.10 155)' stroke-width='0.4'/%3E%3Ccircle cx='450' cy='450' r='320' fill='none' stroke='oklch(0.55 0.10 155)' stroke-width='0.3'/%3E%3C/svg%3E") no-repeat bottom right / 60%;
      opacity: 0.08;
      pointer-events: none;
    }

    &::after {
      content: '';
      position: absolute;
      inset: 0;
      background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
      pointer-events: none;
    }
  }

  .hero__inner {
    max-inline-size: var(--width-prose);
    margin-inline: auto;
    position: relative;
    z-index: 1;
  }

  .hero__title {
    font-family: var(--font-heading);
    font-size: var(--text-3xl);
    line-height: var(--leading-tight);
    letter-spacing: -0.02em;

    em {
      font-style: italic;
    }
  }

  .hero__subtitle {
    font-size: var(--text-lg);
    color: var(--text-secondary);
    margin-block-start: var(--space-xs);
    line-height: var(--leading-normal);
  }

  .hero__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: var(--space-sm);
    margin-block-start: var(--space-md);
  }
```

**Step 3: Run Playwright**

```bash
npx playwright test tests/playwright/homepage.spec.ts
```

Expected: all 6 homepage tests pass. `.hero__title` still contains "Minoo". `.hero__actions .btn--primary` still has `href="/communities"`. `.hero__actions .btn--secondary` still has `href="/people"`.

**Step 4: Commit**

```bash
git add templates/page.html.twig public/css/minoo.css
git commit -m "feat: redesign homepage hero with gradient, pattern, and noise texture"
```

---

### Task 9: Redesign Explore Minoo section with icons and warm background

**Files:**
- Modify: `templates/page.html.twig` (lines 49-83, Explore section)
- Modify: `public/css/minoo.css` (add explore section styles)

**Step 1: Update Explore section markup in `page.html.twig`**

Replace lines 49-83 (the Explore Minoo section) with:

```html
    <section class="explore-section">
      <h2 class="section-heading">Explore Minoo</h2>
      <div class="explore-grid">
        <a href="/communities" class="card explore-card">
          <svg class="explore-card__icon" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="16" cy="10" r="4"/><circle cx="7" cy="20" r="3.5"/><circle cx="25" cy="20" r="3.5"/><path d="M12 12c-2 2-4 5-4 8m12-8c2 2 4 5 4 8M14.5 13.5c-.5 1-1 3-1 5m4-5c.5 1 1 3 1 5"/>
          </svg>
          <h3 class="card__title">Communities</h3>
          <p class="card__meta">Find First Nations and municipalities across the region</p>
        </a>
        <a href="/people" class="card explore-card">
          <svg class="explore-card__icon" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="16" cy="10" r="5"/><path d="M6 28c0-5.5 4.5-10 10-10s10 4.5 10 10"/>
          </svg>
          <h3 class="card__title">People</h3>
          <p class="card__meta">Community resource people, Elders, and knowledge keepers</p>
        </a>
        <a href="/teachings" class="card explore-card">
          <svg class="explore-card__icon" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 4c4-1 8 1 10 4 2-3 6-5 10-4v18c-4-1-8 1-10 3-2-2-6-4-10-3V4z"/><path d="M16 8v17"/>
          </svg>
          <h3 class="card__title">Teachings</h3>
          <p class="card__meta">Traditional knowledge and cultural resources</p>
        </a>
        <a href="/events" class="card explore-card">
          <svg class="explore-card__icon" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="6" width="24" height="22" rx="3"/><path d="M4 13h24M10 3v5m12-5v5"/><circle cx="16" cy="21" r="2"/>
          </svg>
          <h3 class="card__title">Events</h3>
          <p class="card__meta">Community gatherings and cultural events</p>
        </a>
        <a href="/elders" class="card explore-card explore-card--elder">
          <svg class="explore-card__icon" viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 6c-2 3-6 5-6 9a6 6 0 0012 0c0-4-4-6-6-9z"/><path d="M16 18v4m-3-2h6"/>
          </svg>
          <h3 class="card__title">Elder Support Program</h3>
          <p class="card__meta">Request help for an Elder or volunteer your time</p>
        </a>
      </div>
    </section>
```

Note: each `<a>` has both `class="card explore-card"`, preserving the `.card-grid .card` selector for Playwright. Wait — the Playwright test is `section.locator('.card-grid .card')`. We're replacing `.card-grid` with `.explore-grid`. We need to check the Playwright test more carefully.

The test (homepage.spec.ts line 22) does: `const section = heading.locator('..');` then `section.locator('.card-grid .card')`. We're changing the class name. **We must update the Playwright test too.**

Actually, let's keep it simple: **use `card-grid` class on the explore grid** alongside the new class. The explore grid just gets additional styling.

Revised: change `<div class="explore-grid">` to `<div class="card-grid explore-grid">`.

Also, the Playwright test on line 27 checks `page.locator('.card-grid a[href="/elders"] .card__title')`. Our new markup has `<a href="/elders" class="card explore-card explore-card--elder">` with `<h3 class="card__title">` inside — this matches.

**Step 2: Add section heading and explore section CSS**

Add to `@layer components`:

```css
  /* Section headings with decorative line */
  .section-heading {
    font-family: var(--font-heading);
    font-size: var(--text-2xl);
    font-weight: 400;
    position: relative;
    padding-block-start: var(--space-sm);

    &::before {
      content: '';
      position: absolute;
      inset-block-start: 0;
      inset-inline-start: 0;
      inline-size: 3rem;
      block-size: 3px;
      background-color: var(--accent);
      border-radius: var(--radius-full);
    }
  }

  /* Center the line when section heading is centered */
  .hero .section-heading::before,
  .explore-section .section-heading::before {
    inset-inline-start: 50%;
    transform: translateX(-50%);
  }

  /* Explore section */
  .explore-section {
    background-color: var(--surface-warm);
    margin-inline: calc(-1 * var(--gutter));
    padding: var(--space-xl) var(--gutter);
    text-align: center;
  }

  .explore-grid {
    text-align: start;
    margin-block-start: var(--space-md);
  }

  .explore-card {
    max-inline-size: unset;
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
    padding: var(--space-md);
    border-inline-start-width: 1px;
  }

  .explore-card__icon {
    inline-size: 2rem;
    block-size: 2rem;
    color: var(--accent);
    transition: transform var(--duration-fast) var(--ease-out);
  }

  @media (prefers-reduced-motion: no-preference) {
    .explore-card:hover .explore-card__icon {
      transform: translateX(2px);
    }
  }

  .explore-card--elder {
    --card-accent: var(--domain-elders);
  }

  .explore-card--elder .explore-card__icon {
    color: var(--domain-elders);
  }
```

**Step 3: Run Playwright**

```bash
npx playwright test tests/playwright/homepage.spec.ts
```

Expected: all 6 homepage tests pass. `.card-grid .card` still matches (both classes on the grid), `a[href="/elders"] .card__title` still matches.

**Step 4: Commit**

```bash
git add templates/page.html.twig public/css/minoo.css
git commit -m "feat: redesign Explore Minoo section with icons and warm background"
```

---

### Task 10: Update location bar with SVG icon

**Files:**
- Modify: `templates/components/location-bar.html.twig`
- Modify: `public/css/minoo.css` (location bar styles)

**Step 1: Replace emoji with SVG in location-bar.html.twig**

Change line 3 from:
```html
    <span class="location-bar__icon" aria-hidden="true">&#x1F4CD;</span>
```
To:
```html
    <svg class="location-bar__icon" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
      <path d="M10 2a6 6 0 016 6c0 4.5-6 10-6 10S4 12.5 4 8a6 6 0 016-6z"/><circle cx="10" cy="8" r="2"/>
    </svg>
```

**Step 2: Update location bar CSS**

In the `.location-bar` styles, update:

```css
  .location-bar {
    background: var(--surface-warm);
    border-block-end: 1px solid var(--border);
    font-size: var(--text-sm);
  }

  .location-bar__inner {
    max-inline-size: var(--width-content);
    margin-inline: auto;
    padding-inline: var(--gutter);
    padding-block: var(--space-2xs);
    display: flex;
    align-items: center;
    gap: var(--space-2xs);
  }

  .location-bar__icon {
    color: var(--text-secondary);
    flex-shrink: 0;
  }
```

Also update `.location-bar__input` border-radius to `var(--radius-md)`.

**Step 3: Run Playwright**

```bash
npx playwright test tests/playwright/location-bar.spec.ts
```

Expected: all location bar tests pass.

**Step 4: Commit**

```bash
git add templates/components/location-bar.html.twig public/css/minoo.css
git commit -m "feat: update location bar with SVG icon and warmer styling"
```

---

### Task 11: Refine form inputs, section spacing, and add section-heading to listing pages

**Files:**
- Modify: `public/css/minoo.css` (form, spacing, and utility refinements)

**Step 1: Update form input radius**

In the `.form__input, .form__select, .form__textarea` rule, change `border-radius: var(--radius-sm)` to:
```css
    border-radius: var(--radius-md);
```

Update focus outline-offset to `3px`:
```css
    &:focus {
      outline: 2px solid var(--accent);
      outline-offset: 3px;
      border-color: var(--accent);
    }
```

**Step 2: Update `.flow-lg` spacing**

Change `.flow-lg` from `--space-md` to `--space-lg`:
```css
  .flow-lg > * + * {
    margin-block-start: var(--space-lg);
  }
```

**Step 3: Run full test suite**

```bash
npx playwright test
```

Expected: all 28 tests pass.

**Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: refine forms, spacing rhythm, and section headings"
```

---

### Task 12: Final integration test and visual verification

**Step 1: Run full PHPUnit suite**

```bash
./vendor/bin/phpunit
```

Expected: 238 tests, 586 assertions, all pass. (No PHP was changed, but verify nothing broke.)

**Step 2: Run full Playwright suite**

```bash
npx playwright test
```

Expected: all 28 tests pass.

**Step 3: Manual visual check of key pages**

Start dev server and check these pages in a browser:

```bash
php -S localhost:8081 -t public
```

Pages to verify:
- `/` — hero with gradient + pattern + noise, explore section with icons, dark footer
- `/communities` — card grid with domain accents
- `/people` — person cards with domain accents
- `/events` — event cards with water-blue accent
- `/teachings` — teaching cards with earth accent
- `/elders` — elder support page
- `/search` — search results
- `/legal/privacy` — legal page renders correctly

Check on mobile viewport (375px width): nav toggle works, overlay appears, hero stacks well, cards go single-column, footer readable.

**Step 4: Commit any final adjustments**

If visual check reveals issues, fix and commit. Otherwise, proceed.

**Step 5: Squash or keep commits as-is**

The 11 commits form a clean history: tokens → header → footer → cards → homepage → location → forms → verification. Keep as-is for a clean PR.

---

## Summary of all commits

| # | Message | Files |
|---|---------|-------|
| 1 | `chore: add Atkinson Hyperlegible Next font files` | `public/fonts/*` |
| 2 | `feat: add design tokens, Atkinson Hyperlegible Next font` | `minoo.css` |
| 3 | `feat: preload Atkinson Hyperlegible Next font files` | `base.html.twig` |
| 4 | `feat: redesign header with wordmark and refined nav` | `base.html.twig`, `minoo.css` |
| 5 | `feat: redesign footer with dark background, brand, tagline` | `base.html.twig`, `minoo.css` |
| 6 | `feat: refine cards with domain accents, hover motion, stagger` | `minoo.css` |
| 7 | `feat: add domain accent classes to card templates` | `components/*.html.twig` |
| 8 | `feat: redesign homepage hero with gradient, pattern, noise` | `page.html.twig`, `minoo.css` |
| 9 | `feat: redesign Explore Minoo with icons and warm bg` | `page.html.twig`, `minoo.css` |
| 10 | `feat: update location bar with SVG icon` | `location-bar.html.twig`, `minoo.css` |
| 11 | `feat: refine forms, spacing rhythm, section headings` | `minoo.css` |
| 12 | verification only — no commit unless fixes needed | — |
