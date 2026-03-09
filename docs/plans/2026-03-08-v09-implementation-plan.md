# v0.9 — Public UX, Branding & Front-Facing Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform Minoo's public site into a polished, launch-ready product with portal pages, branding tokens, legal pages, accessibility, and Playwright coverage.

**Architecture:** Extend existing SSR templates and vanilla CSS design system. Add new path-based templates for new pages. Add Playwright for smoke testing. No build step, no framework changes needed.

**Tech Stack:** PHP 8.3, Twig 3, vanilla CSS (layers, oklch, container queries), Playwright (Node.js)

---

## Implementation Order

Issues are reordered for optimal dependency flow:

1. Task 1: Branding System (#108) — foundational tokens
2. Task 2: Homepage Portal (#103) — hero, CTAs, skip link
3. Task 3: Elders Portal (#104) — representative consent, redesign
4. Task 4: Volunteer Portal (#105) — new portal landing
5. Task 5: How It Works & Safety (#107) — new pages
6. Task 6: Legal Pages (#109) — footer links, privacy snippets
7. Task 7: Communities & People (#106) — polish
8. Task 8: Template Polish Sweep (#110) — unify everything
9. Task 9: Content & Copy Pass (#111) — final copy
10. Task 10: Playwright Smoke Tests (#112) — comprehensive testing

---

### Task 1: Branding System (#108)

**Branch:** `feature/v0.9-branding`

**Files:**
- Modify: `public/css/minoo.css:51-134` (tokens layer)
- Create: `public/img/minoo-wordmark.svg`
- Create: `public/favicon.svg`
- Modify: `templates/base.html.twig:6-8` (favicon, wordmark)

**Step 1: Add branding token aliases to CSS tokens layer**

In `public/css/minoo.css`, after the existing semantic aliases (line 82), add:

```css
    /* Branding aliases */
    --color-primary:   var(--color-forest-500);
    --color-primary-dark: var(--color-forest-700);
    --color-primary-surface: var(--color-forest-100);
    --color-accent:    var(--color-sun-500);
    --color-danger:    var(--color-berry-600);
```

**Step 2: Add hero and CTA component styles**

In `public/css/minoo.css` `@layer components`, add:

```css
  /* Hero */
  .hero {
    text-align: center;
    padding-block: var(--space-xl) var(--space-lg);
    max-inline-size: var(--width-prose);
    margin-inline: auto;
  }

  .hero__title {
    font-family: var(--font-heading);
    font-size: var(--text-3xl);
    line-height: var(--leading-tight);
    letter-spacing: var(--tracking-tight);
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

  /* CTA buttons */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding-block: var(--space-xs);
    padding-inline: var(--space-md);
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-base);
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    line-height: var(--leading-tight);
  }

  .btn:hover { text-decoration: none; }
  .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

  .btn--primary {
    background-color: var(--color-primary);
    color: white;
  }
  .btn--primary:hover { background-color: var(--color-primary-dark); color: white; }

  .btn--secondary {
    background-color: transparent;
    color: var(--color-primary);
    border: 2px solid var(--color-primary);
  }
  .btn--secondary:hover { background-color: var(--color-primary-surface); color: var(--color-primary-dark); }

  .btn--accent {
    background-color: var(--color-accent);
    color: var(--color-earth-900);
  }
  .btn--accent:hover { background-color: oklch(from var(--color-accent) calc(l - 0.05) c h); }

  .btn--lg {
    padding-block: var(--space-sm);
    padding-inline: var(--space-lg);
    font-size: var(--text-lg);
  }

  /* Portal section */
  .portal-section {
    padding-block: var(--space-md);
  }

  .portal-section__header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--space-sm);
    flex-wrap: wrap;
    margin-block-end: var(--space-sm);
  }

  /* Content section */
  .content-section {
    max-inline-size: var(--width-prose);
  }

  /* Privacy note */
  .privacy-note {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    padding: var(--space-xs);
    background-color: var(--surface);
    border-radius: var(--radius-sm);
    border-inline-start: 3px solid var(--border);
  }

  .privacy-note a {
    color: var(--text-secondary);
  }

  /* Safety callout */
  .safety-callout {
    padding: var(--space-sm);
    background-color: var(--color-water-100);
    border-radius: var(--radius-md);
    border-inline-start: 4px solid var(--color-water-600);
  }

  /* Skip link */
  .skip-link {
    position: absolute;
    inset-block-start: -100%;
    inset-inline-start: var(--space-sm);
    padding: var(--space-xs) var(--space-sm);
    background-color: var(--accent);
    color: white;
    border-radius: var(--radius-sm);
    z-index: 100;
    text-decoration: none;
  }

  .skip-link:focus {
    inset-block-start: var(--space-xs);
  }
```

**Step 3: Create SVG wordmark**

Create `public/img/minoo-wordmark.svg`:
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 32" role="img" aria-label="Minoo">
  <text x="0" y="26" font-family="Charter, 'Bitstream Charter', Cambria, serif" font-size="28" font-weight="700" fill="oklch(0.25 0.02 70)">Minoo</text>
</svg>
```

**Step 4: Create favicon**

Create `public/favicon.svg`:
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <rect width="32" height="32" rx="6" fill="oklch(0.55 0.10 155)"/>
  <text x="16" y="24" text-anchor="middle" font-family="Charter, serif" font-size="22" font-weight="700" fill="white">M</text>
</svg>
```

**Step 5: Update base.html.twig with favicon, skip link, and footer**

In `templates/base.html.twig`:
- Add `<link rel="icon" href="/favicon.svg" type="image/svg+xml">` in `<head>`
- Add skip link `<a href="#main-content" class="skip-link">Skip to content</a>` as first child of `<body>`
- Add `id="main-content"` to `<main>` tag
- Add footer links for legal pages

**Step 6: Run tests**

```bash
./vendor/bin/phpunit
```

**Step 7: Commit and create PR**

```bash
git checkout -b feature/v0.9-branding
git add public/css/minoo.css public/img/minoo-wordmark.svg public/favicon.svg templates/base.html.twig
git commit -m "feat(#108): branding system with tokens, wordmark, favicon, skip link"
```

---

### Task 2: Homepage Portal Redesign (#103)

**Branch:** `feature/v0.9-homepage`

**Files:**
- Modify: `templates/page.html.twig` (full rewrite of content block)

**Step 1: Rewrite homepage template with hero and dual CTAs**

Replace `templates/page.html.twig` content block with:
- Hero section: title, subtitle, two CTA buttons (Request Help → `/elders`, Volunteer → `/elders/volunteer`)
- Local snapshot section (when location set): nearby communities, upcoming events
- Explore section (when no location): card grid of main sections
- How it works summary: 3 steps in a compact grid

**Step 2: Run tests**

```bash
./vendor/bin/phpunit
```

**Step 3: Commit and create PR**

```bash
git checkout -b feature/v0.9-homepage
git add templates/page.html.twig
git commit -m "feat(#103): homepage portal with hero, CTAs, local snapshot"
```

---

### Task 3: Elders Portal Redesign (#104)

**Branch:** `feature/v0.9-elders`

**Files:**
- Modify: `templates/elders.html.twig` (portal redesign)
- Modify: `templates/elders/request.html.twig` (add representative toggle, consent, privacy note)
- Modify: `src/Controller/ElderSupportController.php:37-84` (handle representative fields)
- Modify: `templates/elders/request-confirmation.html.twig` (show request ID, representative info)
- Test: `tests/Minoo/Unit/Controller/ElderSupportControllerTest.php` (if exists, otherwise create)

**Step 1: Add representative fields to request form**

In `templates/elders/request.html.twig`, after the name field, add:
- Representative toggle: checkbox "I'm requesting on behalf of an Elder"
- When toggled: show elder_name field and consent checkbox
- Privacy note snippet at bottom of form
- Safety link

**Step 2: Update controller to handle representative fields**

In `src/Controller/ElderSupportController.php` `submitRequest()`:
- Read `is_representative`, `elder_name`, `consent` from POST
- Validate: if `is_representative` is set, `elder_name` and `consent` are required
- Store in entity: `is_representative`, `elder_name`, `has_consent`

**Step 3: Redesign elders landing page**

In `templates/elders.html.twig`:
- Add portal intro with clearer heading
- Keep how-it-works steps
- Add safety callout section
- Add phone instructions for non-digital users

**Step 4: Update confirmation to show request ID**

In `templates/elders/request-confirmation.html.twig`:
- Show `entity.uuid()` as reference number
- If representative, show representative info
- Add "What happens next" steps

**Step 5: Write unit test for representative validation**

```php
#[Test]
public function submitRequest_representative_without_consent_shows_error(): void
{
    $request = HttpRequest::create('/elders/request', 'POST', [
        'name' => 'Jane Doe',
        'phone' => '555-0100',
        'type' => 'ride',
        'is_representative' => '1',
        'elder_name' => 'Elder Name',
        // missing consent
    ]);
    // ... assert 422 with consent error
}
```

**Step 6: Run tests, commit, PR**

---

### Task 4: Volunteer Portal Redesign (#105)

**Branch:** `feature/v0.9-volunteer`

**Files:**
- Create: `templates/volunteer.html.twig` (new portal landing)
- Modify: `templates/elders/volunteer.html.twig` (add privacy note)
- Modify: `templates/base.html.twig:24` (add Volunteer nav link)

**Step 1: Create volunteer portal landing page**

Create `templates/volunteer.html.twig`:
- Hero section: "Make a Difference in Your Community"
- Two paths: "Sign Up" CTA → `/elders/volunteer`, "Already volunteering?" → `/login`
- Benefits list (3-4 items)
- How it works steps
- Link to safety page

**Step 2: Add privacy note to volunteer signup form**

In `templates/elders/volunteer.html.twig`, before submit button, add privacy note snippet.

**Step 3: Update nav to add Volunteer link**

In `templates/base.html.twig`, add `/volunteer` link after Elder Support.

**Step 4: Run tests, commit, PR**

---

### Task 5: How It Works & Safety Pages (#107)

**Branch:** `feature/v0.9-info-pages`

**Files:**
- Create: `templates/how-it-works.html.twig`
- Create: `templates/safety.html.twig`

**Step 1: Create how-it-works page**

Create `templates/how-it-works.html.twig`:
- Extends `base.html.twig`
- Step-by-step process for both Elders and Volunteers
- Representative consent language section
- FAQ section (3-4 common questions)

**Step 2: Create safety page**

Create `templates/safety.html.twig`:
- Extends `base.html.twig`
- Safety guidelines checklist
- What to expect from a volunteer
- How to report concerns
- Emergency contacts note

**Step 3: Run tests, commit, PR**

---

### Task 6: Legal Pages (#109)

**Branch:** `feature/v0.9-legal`

**Files:**
- Create: `templates/legal/privacy.html.twig`
- Create: `templates/legal/terms.html.twig`
- Create: `templates/legal/accessibility.html.twig`
- Modify: `templates/base.html.twig:49-53` (footer links)

Note: Framework path routing maps `/legal/privacy` → `legal/privacy.html.twig` via first-segment fallback (`legal.html.twig`). Since we need 3 sub-pages, we need a `legal.html.twig` that acts as a router, OR we can use a controller. Actually, the framework's `tryRenderPathTemplate()` falls back to the first segment — so `/legal/privacy` renders `legal.html.twig` with `path` set to `/legal/privacy`. We can use path conditionals inside `legal.html.twig`.

**Step 1: Create legal.html.twig with path-based content**

Create `templates/legal.html.twig`:
```twig
{% extends "base.html.twig" %}
{% block title %}Legal — Minoo{% endblock %}
{% block content %}
  {% if path == '/legal/privacy' %}
    {# Privacy policy content #}
  {% elseif path == '/legal/terms' %}
    {# Terms content #}
  {% elseif path == '/legal/accessibility' %}
    {# Accessibility statement content #}
  {% else %}
    {# Legal index page listing all three #}
  {% endif %}
{% endblock %}
```

**Step 2: Add footer links**

In `templates/base.html.twig` footer, add links to `/legal/privacy`, `/legal/terms`, `/legal/accessibility`.

**Step 3: Run tests, commit, PR**

---

### Task 7: Communities & People Polish (#106)

**Branch:** `feature/v0.9-communities-people`

**Files:**
- Modify: `templates/communities.html.twig` (portal intro, local snapshot)
- Modify: `templates/people.html.twig` (portal intro, consistent styling)

**Step 1: Add portal intro sections**

Add brief intro text and "What is this?" context to both listing pages. Ensure nearest community highlight is working (already exists from v0.8).

**Step 2: Ensure consistent card styling**

Verify card styles use `.btn` classes for CTAs where appropriate.

**Step 3: Run tests, commit, PR**

---

### Task 8: Template Polish Sweep (#110)

**Branch:** `feature/v0.9-polish`

**Files:**
- Modify: multiple templates for consistency
- Modify: `public/css/minoo.css` (minor fixes)

**Step 1: Audit all templates for consistency**

Check every template for:
- Consistent heading hierarchy (h1 → h2 → h3)
- Uniform `.flow-lg` wrapper on content blocks
- All forms use `.form__actions` for submit buttons
- Empty states have consistent styling
- Success/error messages use consistent classes

**Step 2: Fix inconsistencies**

Apply fixes across templates.

**Step 3: Run tests, commit, PR**

---

### Task 9: Content & Copy Pass (#111)

**Branch:** `feature/v0.9-copy`

**Files:**
- Modify: all portal templates (copy updates)

**Step 1: Review and update copy in all portals**

- Homepage hero: warm, inviting, clear purpose
- Elders portal: empathetic, simple, actionable
- Volunteer portal: motivating, clear benefits
- How-it-works: step-by-step, plain English
- Safety: reassuring, practical

**Step 2: Commit and PR**

---

### Task 10: Playwright Smoke Tests (#112)

**Branch:** `feature/v0.9-playwright`

**Files:**
- Create: `package.json`
- Create: `playwright.config.ts`
- Create: `tests/playwright/homepage.spec.ts`
- Create: `tests/playwright/elders.spec.ts`
- Create: `tests/playwright/volunteer.spec.ts`
- Create: `tests/playwright/legal.spec.ts`
- Create: `tests/playwright/location-bar.spec.ts`

**Step 1: Initialize Playwright**

```bash
npm init -y
npm install -D @playwright/test
npx playwright install chromium
```

**Step 2: Create playwright.config.ts**

```typescript
import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/playwright',
  use: { baseURL: 'http://localhost:8081' },
  webServer: {
    command: 'php -S localhost:8081 -t public',
    port: 8081,
    reuseExistingServer: true,
  },
});
```

**Step 3: Write smoke tests**

Homepage: hero visible, CTAs present and linked correctly, skip link works.
Elders: portal loads, request form accessible, representative toggle works.
Volunteer: portal loads, signup CTA linked.
Legal: all 3 legal pages load, footer links present.
Location bar: present on all pages, toggle works.

**Step 4: Run tests**

```bash
npx playwright test
```

**Step 5: Commit and PR**
