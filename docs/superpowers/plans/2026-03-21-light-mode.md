# Light Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a complete light mode theme to Minoo with a toggle, so users can switch between dark and light color schemes.

**Architecture:** The existing token system already has semantic aliases (`--surface`, `--border`, `--accent`, `--link`) that components reference. Light mode overrides these aliases under `[data-theme="light"]` on `<html>`. A toggle button persists choice to `localStorage` and respects `prefers-color-scheme` as default. Hardcoded colors in components get replaced with tokens first.

**Tech Stack:** Vanilla CSS (custom properties, `@layer`), Twig templates, inline JS

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `public/css/minoo.css` | Modify | Add light mode token overrides in `@layer tokens`, fix hardcoded colors in `@layer components` |
| `templates/components/theme-toggle.html.twig` | Create | Theme toggle button partial |
| `templates/base.html.twig` | Modify | Include theme toggle, add theme init script |
| `tests/Playwright/light-mode.spec.ts` | Create | Visual regression tests for both themes |

---

### Task 1: Add light mode token overrides (#419)

**Files:**
- Modify: `public/css/minoo.css` (inside `@layer tokens`, after `:root` block ~line 160)

**Context:** The `:root` block (lines 76-160) defines raw color values AND semantic aliases. The semantic aliases on lines 108-114 are what components actually reference:
```css
--surface:        var(--surface-dark);
--border:         var(--border-dark);
--accent:         var(--color-events);
--link:           var(--color-events);
```

Light mode overrides these aliases to point at the existing light raw values.

- [ ] **Step 1: Add `[data-theme="light"]` override block**

Add this immediately after the `:root` closing brace inside `@layer tokens`:

```css
  [data-theme="light"] {
    /* ── Surface overrides ── */
    --surface:           var(--surface-light);
    --surface-card:      var(--surface-light-card);
    --surface-raised:    #eae5df;

    /* ── Text overrides ── */
    --text-primary:      var(--text-dark);
    --text-secondary:    var(--text-dark-secondary);
    --text-muted:        #888;

    /* ── Border overrides ── */
    --border:            var(--border-light);
    --border-dark:       var(--border-light);
    --border-subtle:     #d4cfc8;

    /* ── Semantic aliases (unchanged but explicit) ── */
    --accent:            var(--color-events);
    --link:              var(--color-events);

    color-scheme: light;
  }
```

- [ ] **Step 2: Add `prefers-color-scheme` auto-detection**

Add this immediately after the `[data-theme="light"]` block:

```css
  @media (prefers-color-scheme: light) {
    :root:not([data-theme="dark"]) {
      --surface:           var(--surface-light);
      --surface-card:      var(--surface-light-card);
      --surface-raised:    #eae5df;
      --text-primary:      var(--text-dark);
      --text-secondary:    var(--text-dark-secondary);
      --text-muted:        #888;
      --border:            var(--border-light);
      --border-dark:       var(--border-light);
      --border-subtle:     #d4cfc8;
      --accent:            var(--color-events);
      --link:              var(--color-events);
      color-scheme: light;
    }
  }
```

This respects OS preference when no explicit `data-theme` is set.

- [ ] **Step 3: Verify dark mode is unchanged**

Open `http://localhost:8081` in browser. Confirm the site still looks identical (no `data-theme` attribute = dark default).

- [ ] **Step 4: Test light mode manually**

In browser devtools, add `data-theme="light"` to `<html>`. Verify:
- Background changes from near-black to warm off-white
- Text changes from light to dark
- Cards get white backgrounds
- Borders lighten

- [ ] **Step 5: Bump CSS version**

In `templates/base.html.twig` line 20, change `?v=10` to `?v=11`.

- [ ] **Step 6: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat(#419): add light mode token overrides in @layer tokens"
```

---

### Task 2: Theme toggle component (#420)

**Files:**
- Create: `templates/components/theme-toggle.html.twig`
- Modify: `templates/base.html.twig` (include toggle + add init script)

- [ ] **Step 1: Create the toggle partial**

Create `templates/components/theme-toggle.html.twig`:

```twig
<button
  class="theme-toggle"
  aria-label="Toggle light/dark theme"
  title="Toggle theme"
  type="button"
>
  <svg class="theme-toggle__icon theme-toggle__icon--dark" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <svg class="theme-toggle__icon theme-toggle__icon--light" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="1.5"/>
    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
  </svg>
</button>
```

- [ ] **Step 2: Add toggle to base template header**

In `templates/base.html.twig`, add the include immediately before the lang-switcher `<li>` (before line 61):

```twig
            <li class="site-nav__utility site-nav__theme">
              {% include "components/theme-toggle.html.twig" %}
            </li>
```

- [ ] **Step 3: Add theme init script**

In `templates/base.html.twig`, add this script immediately after the opening `<body>` tag (line 24), BEFORE any visible content to prevent flash of wrong theme:

```html
<script>
(function() {
  var t = localStorage.getItem('minoo-theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
})();
</script>
```

- [ ] **Step 4: Add toggle behavior script**

In `templates/base.html.twig`, add this inside the existing first `<script>` block (after the lang switcher IIFE, before line 152's closing `</script>`):

```javascript
    // Theme toggle
    (function() {
      var btn = document.querySelector('.theme-toggle');
      if (!btn) return;
      function current() {
        return document.documentElement.getAttribute('data-theme') ||
          (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
      }
      function apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('minoo-theme', theme);
      }
      btn.addEventListener('click', function() {
        apply(current() === 'dark' ? 'light' : 'dark');
      });
    })();
```

- [ ] **Step 5: Add toggle CSS**

In `public/css/minoo.css`, add in `@layer components`:

```css
/* ── Theme toggle ── */
.theme-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-3xs);
  background: none;
  border: 1px solid var(--border-subtle);
  border-radius: 0.375rem;
  color: var(--text-secondary);
  cursor: pointer;
  transition: color 0.2s, border-color 0.2s;
}

.theme-toggle:hover {
  color: var(--text-primary);
  border-color: var(--text-muted);
}

/* Show moon in dark mode, sun in light mode */
.theme-toggle__icon--light { display: none; }
[data-theme="light"] .theme-toggle__icon--dark { display: none; }
[data-theme="light"] .theme-toggle__icon--light { display: block; }

@media (prefers-color-scheme: light) {
  :root:not([data-theme="dark"]) .theme-toggle__icon--dark { display: none; }
  :root:not([data-theme="dark"]) .theme-toggle__icon--light { display: block; }
}
```

- [ ] **Step 6: Verify toggle works**

Open `http://localhost:8081`. Click the toggle button. Verify:
- Theme switches between dark and light
- Moon icon shows in dark mode, sun icon in light mode
- Refreshing the page preserves the chosen theme
- With no stored preference, OS `prefers-color-scheme` is respected

- [ ] **Step 7: Commit**

```bash
git add templates/components/theme-toggle.html.twig templates/base.html.twig public/css/minoo.css
git commit -m "feat(#420): add theme toggle component with localStorage persistence"
```

---

### Task 3: Light mode component variants — feed cards, sidebars, widgets (#421)

**Files:**
- Modify: `public/css/minoo.css` (`@layer components`)

**Context:** Components that need attention (from audit):
- `.card` — hardcoded `oklch(0.2 0 0)` border
- `.card:hover` — hardcoded `oklch(0.4 0 0)` border
- `.feed-pill`, `.community-pill` — `rgba()` backgrounds tuned for dark
- `.feed-shimmer` — gradient uses dark surface colors
- `.content-well .tag` — hardcoded `#e8e3dc`
- `.lang-switcher__toggle` — hardcoded `oklch(0.6 0 0)` border
- `.lang-switcher__item--active` — hardcoded `white`
- Flash messages — `rgba()` backgrounds tuned for dark

- [ ] **Step 1: Replace hardcoded card borders with tokens**

Find in `public/css/minoo.css`:
```css
/* .card border — replace oklch(0.2 0 0) with var(--border-subtle) */
/* .card:hover border — replace oklch(0.4 0 0) with var(--text-muted) */
```

Search for the `.card` rule in `@layer components`. Replace the hardcoded oklch border values with token references.

- [ ] **Step 2: Replace hardcoded lang-switcher colors**

Find `.lang-switcher__toggle` and replace `oklch(0.6 0 0)` border with `var(--border-subtle)`.
Find `.lang-switcher__item--active` and replace `white` with `var(--text-primary)`.

- [ ] **Step 3: Replace hardcoded tag background**

Find `.content-well .tag` and replace `#e8e3dc` with `var(--border-light)`.

- [ ] **Step 4: Add light mode overrides for pill components**

Add to `@layer components`:

```css
[data-theme="light"] .feed-pill {
  background: rgba(106, 156, 175, 0.08);
}

[data-theme="light"] .community-pill {
  background: rgba(106, 156, 175, 0.1);
}

[data-theme="light"] .feed-shimmer {
  background: linear-gradient(90deg, var(--surface-card) 0%, #f0ece6 50%, var(--surface-card) 100%);
}
```

- [ ] **Step 5: Add light mode overrides for flash messages**

Add to `@layer components`:

```css
[data-theme="light"] .flash-message--success { background: rgba(42, 157, 143, 0.08); }
[data-theme="light"] .flash-message--error { background: rgba(255, 77, 90, 0.08); }
[data-theme="light"] .flash-message--info { background: rgba(106, 156, 175, 0.08); }
[data-theme="light"] .flash-message--warning { background: rgba(244, 162, 97, 0.08); }
```

- [ ] **Step 6: Add prefers-color-scheme equivalents**

Duplicate each `[data-theme="light"]` override inside a `@media (prefers-color-scheme: light)` block scoped to `:root:not([data-theme="dark"])` so OS-preference users also get the fixes.

- [ ] **Step 7: Verify all components in light mode**

Switch to light mode. Check these pages visually:
- Homepage (feed cards, pills, shimmer)
- `/events` (event cards)
- `/teachings` (teaching cards, detail page content well)
- `/communities` (community pills)
- `/search` (search result cards)

Confirm no hardcoded dark colors remain visible.

- [ ] **Step 8: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#421): light mode component variants — cards, pills, flash messages"
```

---

### Task 4: Light mode variants — forms, auth, dashboard (#422)

**Files:**
- Modify: `public/css/minoo.css` (`@layer components`)

**Context:** Interior pages (login, register, account, coordinator dashboard, elder support forms) likely use form elements, input fields, and dashboard-specific components that may have dark-tuned styles.

- [ ] **Step 1: Audit form and dashboard components**

Search `public/css/minoo.css` for form-related selectors: `input`, `textarea`, `select`, `.form-`, `.auth-`, `.dashboard-`, `.coordinator-`, `.elder-`, `.volunteer-`. Note any hardcoded dark colors.

- [ ] **Step 2: Add light mode form input overrides**

Add appropriate `[data-theme="light"]` rules for:
- Text inputs, textareas, selects (background, border, text color, placeholder color)
- Focus states (outline/ring color)
- Buttons (background, text, border — ensure contrast)

- [ ] **Step 3: Add light mode dashboard overrides**

Add rules for coordinator dashboard, volunteer dashboard, and account pages. Focus on:
- Dashboard cards/panels
- Status badges and workflow state indicators
- Table/list backgrounds and borders

- [ ] **Step 4: Add light mode auth page overrides**

Check login/register forms. Add overrides for:
- Auth container backgrounds
- Link colors on light backgrounds
- Error/validation message styling

- [ ] **Step 5: Add light mode overrides for chat widget**

The chat widget (`.chat-widget`, `.chat-widget__panel`, `.chat-widget__message`) needs light mode variants for panel background, message bubbles, and input field.

- [ ] **Step 6: Add prefers-color-scheme equivalents for all new rules**

Same pattern as Task 3 — duplicate inside `@media (prefers-color-scheme: light)`.

- [ ] **Step 7: Verify all interior pages**

Switch to light mode. Check:
- `/login` and `/register`
- `/account`
- `/dashboard/coordinator`
- `/dashboard/volunteer`
- `/elders/request`
- Chat widget (open it in light mode)

- [ ] **Step 8: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#422): light mode variants — forms, auth, dashboard, chat widget"
```

---

### Task 5: Visual regression tests (#423)

**Files:**
- Create: `tests/Playwright/light-mode.spec.ts`

**Context:** Minoo already has Playwright tests. This task adds screenshot-based regression tests that toggle between themes and capture key pages.

- [ ] **Step 1: Check existing Playwright config**

Look at existing Playwright test files and config to match conventions (test directory, base URL, project setup).

- [ ] **Step 2: Write the test file**

Create `tests/Playwright/light-mode.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';

const pages = [
  { name: 'homepage', path: '/' },
  { name: 'events', path: '/events' },
  { name: 'teachings', path: '/teachings' },
  { name: 'communities', path: '/communities' },
  { name: 'language', path: '/language' },
  { name: 'search', path: '/search' },
];

for (const { name, path } of pages) {
  test(`${name} — dark mode`, async ({ page }) => {
    await page.goto(path);
    await page.evaluate(() => {
      document.documentElement.setAttribute('data-theme', 'dark');
    });
    await page.waitForTimeout(300);
    await expect(page).toHaveScreenshot(`${name}-dark.png`, { fullPage: true });
  });

  test(`${name} — light mode`, async ({ page }) => {
    await page.goto(path);
    await page.evaluate(() => {
      document.documentElement.setAttribute('data-theme', 'light');
    });
    await page.waitForTimeout(300);
    await expect(page).toHaveScreenshot(`${name}-light.png`, { fullPage: true });
  });
}

test('theme toggle persists across navigation', async ({ page }) => {
  await page.goto('/');
  await page.click('.theme-toggle');
  const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
  expect(theme).toBe('light');

  await page.goto('/events');
  const persisted = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
  expect(persisted).toBe('light');
});

test('theme toggle switches icons', async ({ page }) => {
  await page.goto('/');
  // Dark mode default — moon icon visible
  await expect(page.locator('.theme-toggle__icon--dark')).toBeVisible();
  await expect(page.locator('.theme-toggle__icon--light')).toBeHidden();

  await page.click('.theme-toggle');
  // Light mode — sun icon visible
  await expect(page.locator('.theme-toggle__icon--light')).toBeVisible();
  await expect(page.locator('.theme-toggle__icon--dark')).toBeHidden();
});
```

- [ ] **Step 3: Run tests to generate baseline screenshots**

```bash
npx playwright test tests/Playwright/light-mode.spec.ts --update-snapshots
```

- [ ] **Step 4: Run tests to verify they pass against baselines**

```bash
npx playwright test tests/Playwright/light-mode.spec.ts
```

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Playwright/light-mode.spec.ts tests/Playwright/light-mode.spec.ts-snapshots/
git commit -m "test(#423): Playwright visual regression tests for light/dark mode"
```

---

## Completion

After all 5 tasks are committed, bump the CSS version one final time if not already at v11, and verify the full test suite still passes:

```bash
./vendor/bin/phpunit
npx playwright test
```
