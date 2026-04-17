# Design System Full Adoption — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the Minoo frontend to match the Minoo Live Design System handoff bundle — new class names, refined component styles, and the app-shell layout pattern.

**Architecture:** The current codebase already has a sidebar layout, dark/light theme, domain-colored cards, and all design tokens. This migration renames shell classes to the design system's BEM convention (`.app`, `.hdr`, `.sbx`, `.ftr`), adds new component patterns (`.hero`, `.sec`, `.badge`, `.chip`, `.detail__layout`, `.keeper`), updates key templates, and removes dead CSS. The design system source is at `/tmp/minoo-design/minoo-live-design-system/`.

**Tech Stack:** Vanilla CSS (single file `public/css/minoo.css`), Twig 3 templates, PHP 8.4 dev server

**Key insight:** The existing `minoo.css` is ~9000 lines with `@layer reset, tokens, base, layout, components, utilities`. Tokens already match the design system. The work is in `@layer components` (add new, update existing) and templates (rename classes).

---

## File Map

| File | Action | Purpose |
|------|--------|---------|
| `public/css/minoo.css` | Modify | Font-face weight ranges, add missing tokens, add new component CSS, remove dead classes |
| `templates/base.html.twig` | Modify | Rename shell classes to `.app` / `.hdr` / `.sbx` / `.ftr` pattern |
| `templates/components/sidebar-nav.html.twig` | Modify | Rename `sidebar-nav__*` to `sbx__*` classes |
| `templates/home.html.twig` | Modify | Adopt `hero` / `sec` / `grid` patterns |
| `templates/components/event-card.html.twig` | Modify | Add `card__eyebrow` pattern, support `card--media` variant |
| `templates/components/teaching-card.html.twig` | Modify | Add `card__eyebrow` pattern |
| `templates/events.html.twig` | Modify | Use `.hero` for listing header, refine detail layout |
| `templates/teachings.html.twig` | Modify | Use `.hero` for listing header, refine detail layout |

---

### Task 1: Font-Face & Token Updates

**Files:**
- Modify: `public/css/minoo.css` (lines 1-8 for font-face, lines 604-790 for tokens)

- [ ] **Step 1: Update font-face weight ranges**

The current font-face declarations have narrow weight ranges. Update to match the design system's full variable font ranges.

Find the three `@font-face` blocks at the top of `minoo.css` (before the `@layer` declaration on line 553). Update them:

```css
@font-face {
  font-family: 'Fraunces';
  src: url('/fonts/fraunces-variable.woff2') format('woff2');
  font-weight: 100 900;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'Fraunces';
  src: url('/fonts/fraunces-italic-variable.woff2') format('woff2');
  font-weight: 100 900;
  font-style: italic;
  font-display: swap;
}

@font-face {
  font-family: 'DM Sans';
  src: url('/fonts/dm-sans-variable.woff2') format('woff2');
  font-weight: 100 1000;
  font-style: normal;
  font-display: swap;
}
```

Key changes: Fraunces normal `400 900` → `100 900`, Fraunces italic `400 900` → `100 900`, DM Sans `400 700` → `100 1000`.

- [ ] **Step 2: Add missing tokens to `:root` in `@layer tokens`**

Inside `@layer tokens { :root { ... } }`, add these missing tokens after the existing `--color-search` line and before the game tokens:

```css
    /* ── Semantic aliases ── */
    --surface: var(--surface-dark);
    --border: var(--border-dark);
    --border-subtle: #2a2a2a;
    --accent: var(--color-events);
    --link: var(--color-events);
    --error: #ff4d5a;
    --warning: var(--color-teachings);
    --info: var(--color-communities);
    --success: var(--color-language);

    /* ── Typography ── */
    --font-body: 'DM Sans', system-ui, -apple-system, sans-serif;
    --font-heading: 'Fraunces', Charter, 'Bitstream Charter', Cambria, serif;
    --font-mono: ui-monospace, 'Cascadia Code', 'Source Code Pro', monospace;

    /* ── Leading & tracking ── */
    --leading-tight: 1.2;
    --leading-normal: 1.6;
    --leading-loose: 1.8;
    --tracking-tight: -0.01em;
    --tracking-wide: 0.02em;
```

Check if `--surface`, `--border`, `--accent`, `--link` already exist elsewhere in the tokens block. If so, skip duplicates. The `--border-subtle` token is already used but may not be defined — verify and add if missing. The `--space-3xs` token should be added to the spacing section:

```css
    --space-3xs: clamp(0.25rem, 0.23rem + 0.11vw, 0.313rem);
```

- [ ] **Step 3: Verify no duplicate tokens**

Run: `grep -n '\-\-surface:' public/css/minoo.css` and similar for `--border:`, `--accent:`, `--link:`, `--font-body:`, `--font-heading:`, `--font-mono:` to check for existing definitions. If any already exist in the tokens block, don't re-add them. Update them in place if the values differ from the design system.

- [ ] **Step 4: Start dev server and visually verify**

Run: `php -S localhost:8080 -t public public/index.php`

Open `http://localhost:8080` — the site should look identical since we only updated token definitions and weight ranges. No visual change expected yet.

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: update font-face ranges and add missing design tokens"
```

---

### Task 2: Add App Shell & Header CSS

**Files:**
- Modify: `public/css/minoo.css` (add to `@layer components`)

- [ ] **Step 1: Add header component CSS**

Add this CSS inside an existing `@layer components` block (or add a new one near the top of the components section, around line 1391):

```css
  /* === Design System: Header === */
  .hdr {
    position: sticky;
    top: 0;
    z-index: 100;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
  }

  .hdr__bar {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-xs) var(--space-lg);
    max-width: var(--width-content);
    margin: 0 auto;
  }

  .hdr__menu {
    background: none;
    border: 0;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px;
    border-radius: var(--radius-md);
  }

  .hdr__menu:hover {
    background: var(--surface-raised);
    color: var(--text-primary);
  }

  .hdr__logo {
    height: 22px;
    display: block;
  }

  [data-theme="dark"] .hdr__logo {
    filter: invert(1) brightness(2);
  }

  .hdr__search {
    flex: 1;
    max-width: 420px;
    background: var(--surface-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-full);
    padding: 6px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
  }

  .hdr__search input {
    background: transparent;
    border: 0;
    outline: 0;
    color: var(--text-primary);
    font-family: var(--font-body);
    font-size: var(--text-sm);
    flex: 1;
    min-width: 0;
  }

  .hdr__search input::placeholder {
    color: var(--text-muted);
  }

  .hdr__locale {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text-secondary);
    border: 1px solid var(--border-subtle);
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    letter-spacing: 0.05em;
  }

  .hdr__locale:hover {
    border-color: var(--accent);
    color: var(--text-primary);
  }

  .hdr__theme {
    background: none;
    border: 1px solid var(--border-subtle);
    color: var(--text-secondary);
    padding: 6px;
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    align-items: center;
  }

  .hdr__theme:hover {
    border-color: var(--accent);
    color: var(--text-primary);
  }

  .hdr__avatar {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    background: var(--color-events);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
  }
```

- [ ] **Step 2: Add sidebar component CSS**

Add below the header CSS:

```css
  /* === Design System: Sidebar === */
  .sbx {
    border-right: 1px solid var(--border);
    padding: var(--space-sm);
    background: var(--surface);
    overflow-y: auto;
  }

  .sbx__group {
    margin-bottom: var(--space-md);
  }

  .sbx__label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    padding: var(--space-sm) var(--space-xs) var(--space-2xs);
    font-weight: 600;
    font-family: var(--font-body);
  }

  .sbx__item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border: 0;
    background: none;
    color: var(--text-secondary);
    font-size: var(--text-sm);
    font-family: var(--font-body);
    border-radius: var(--radius-md);
    cursor: pointer;
    width: 100%;
    text-align: left;
    text-decoration: none;
    transition: background var(--duration-fast) var(--ease-out),
                color var(--duration-fast) var(--ease-out);
  }

  .sbx__item:hover {
    background: var(--surface-raised);
    color: var(--text-primary);
  }

  .sbx__item--active {
    background: var(--surface-raised);
    color: var(--text-primary);
    font-weight: 600;
    border-left: 3px solid var(--accent);
  }
```

- [ ] **Step 3: Add footer component CSS**

Add below the sidebar CSS:

```css
  /* === Design System: Footer === */
  .ftr {
    margin-top: var(--space-2xl);
    border-top: 1px solid var(--border);
  }

  .ftr__inner {
    max-width: var(--width-content);
    margin: 0 auto;
    padding: var(--space-lg);
    display: flex;
    justify-content: space-between;
    gap: var(--space-md);
    flex-wrap: wrap;
    color: var(--text-muted);
    font-size: var(--text-xs);
  }

  .ftr a {
    color: var(--text-secondary);
    text-decoration: none;
    margin-right: var(--space-sm);
  }

  .ftr a:hover {
    color: var(--text-primary);
  }
```

- [ ] **Step 4: Add/update app shell CSS**

The existing `app-layout` grid likely already works. Add aliases so both old and new class names work during migration:

```css
  /* === Design System: App Shell === */
  .app {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .app__body {
    display: grid;
    grid-template-columns: 240px 1fr;
    flex: 1;
    min-height: 0;
  }

  .app__main {
    min-width: 0;
    padding: var(--space-md) var(--space-lg);
    max-width: var(--width-content);
    margin: 0 auto;
    width: 100%;
  }
```

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add design system shell CSS (header, sidebar, footer, app shell)"
```

---

### Task 3: Update base.html.twig to Design System Shell

**Files:**
- Modify: `templates/base.html.twig`

- [ ] **Step 1: Read the full base.html.twig**

Read `templates/base.html.twig` to understand the current structure. Key areas to change:
- The `<body>` wrapper should become `<div class="app">`
- The header section should use `.hdr` classes
- The sidebar/main grid should use `.app__body` + `.sbx` + `.app__main`
- The footer should use `.ftr` classes

- [ ] **Step 2: Update the body structure**

The current structure is approximately:
```html
<body>
  <!-- skip link -->
  <header>...</header>
  <div class="ribbon"></div>
  <div class="app-layout">
    <aside class="app-sidebar">...</aside>
    <main class="app-main">...</main>
  </div>
  <div class="ribbon"></div>
  <footer>...</footer>
</body>
```

Change to:
```html
<body>
  <!-- skip link -->
  <div class="app">
    <header class="hdr">
      <div class="hdr__bar">
        <button class="hdr__menu" id="sidebar-toggle" aria-label="Menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <a href="{{ lang_url('/') }}">
          <img class="hdr__logo" src="/img/minoo-wordmark.svg" alt="Minoo">
        </a>
        <div class="hdr__search">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input placeholder="Search Teachings, Events, communities…" name="q">
        </div>
        {% include "components/language-switcher.html.twig" %}
        {% include "components/theme-toggle.html.twig" %}
        {% if account is defined and account.isAuthenticated() %}
          <div class="hdr__avatar" title="{{ account.get('display_name')|default('A') }}">{{ account.get('display_name')|default('A')|first|upper }}</div>
        {% else %}
          <a href="{{ lang_url('/auth/login') }}" class="btn btn--ghost">Sign in</a>
        {% endif %}
      </div>
      <div class="ribbon" aria-hidden="true"></div>
    </header>

    <div class="app__body{% if hide_sidebar is defined and hide_sidebar %} app__body--no-sidebar{% endif %}">
      {% if hide_sidebar is not defined or not hide_sidebar %}
        <aside class="sbx" id="app-sidebar">
          {% include "components/sidebar-nav.html.twig" %}
        </aside>
      {% endif %}
      <main class="app__main" id="main-content">
        {% include "components/location-bar.html.twig" %}
        {% include "components/flash-messages.html.twig" %}
        {% block content %}{% endblock %}
      </main>
    </div>

    <footer class="ftr">
      <div class="ribbon" aria-hidden="true"></div>
      <div class="ftr__inner">
        <div>
          <img src="/img/minoo-wordmark.svg" style="height: 18px; display: block; margin-bottom: 8px; filter: var(--wordmark-filter, invert(1) brightness(2));" alt="Minoo">
          <div>&copy; 2026 Minoo Live. All our relations.</div>
          <div style="margin-top: 4px;">Built on Anishinaabe territory.</div>
        </div>
        <div>
          <a href="{{ lang_url('/about') }}">About</a>
          <a href="{{ lang_url('/contact') }}">Contact</a>
          <a href="{{ lang_url('/safety') }}">Protocols</a>
          <a href="{{ lang_url('/legal/privacy') }}">Privacy</a>
          <a href="{{ lang_url('/language') }}">Anishinaabemowin</a>
        </div>
      </div>
    </footer>
  </div>
</body>
```

**Important:** Preserve the existing `{% block head %}`, skip link, analytics script, sidebar toggle JS, and other functional elements. This step changes class names and structure, not behavior.

- [ ] **Step 3: Add no-sidebar variant CSS**

Add to the app shell CSS section:

```css
  .app__body--no-sidebar {
    grid-template-columns: 1fr;
  }
```

- [ ] **Step 4: Update the sidebar toggle JS**

The existing sidebar toggle JS references `app-sidebar` and `app-sidebar__overlay`. Update the selectors in the `<script>` block at the bottom of `base.html.twig` to reference the new `#app-sidebar` ID (which we preserved) and add/remove a class for mobile visibility. Keep the existing toggle logic but update class references.

- [ ] **Step 5: Visual verification**

Start dev server, check:
1. Header renders with logo, search bar, theme toggle
2. Sidebar appears on left with nav links
3. Main content fills remaining width
4. Footer appears at bottom with ribbon above
5. Ribbon appears below header
6. Both light and dark themes work

- [ ] **Step 6: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css
git commit -m "feat: adopt design system app shell in base template

Renames app-layout/app-sidebar/app-main to app/app__body/sbx/app__main.
Adds .hdr header with search bar and .ftr footer with ribbon."
```

---

### Task 4: Update Sidebar Navigation

**Files:**
- Modify: `templates/components/sidebar-nav.html.twig`

- [ ] **Step 1: Read the current sidebar-nav.html.twig**

The current file uses classes: `sidebar-nav`, `sidebar-nav__group`, `sidebar-nav__heading`, `sidebar-nav__item`, `sidebar-nav__item--active`, `sidebar-nav__icon`. These need to become `sbx__group`, `sbx__label`, `sbx__item`, `sbx__item--active`.

- [ ] **Step 2: Rename classes in the template**

Replace all class references:
- `sidebar-nav` → remove (the `<nav>` wrapper is now `<aside class="sbx">` in base.html.twig)
- `sidebar-nav__group` → `sbx__group`
- `sidebar-nav__heading` → `sbx__label`
- `sidebar-nav__item` → `sbx__item`
- `sidebar-nav__item--active` → `sbx__item--active`
- `sidebar-nav__icon` → keep inline SVGs but remove the icon class (icons are inline in the `sbx__item` flex container)

The `<nav>` wrapper's class `sidebar-nav` can be removed since the parent `<aside class="sbx">` now handles styling. Keep the `aria-label` on the `<nav>`.

Structure each nav group as:
```html
<div class="sbx__group">
  <div class="sbx__label">Explore</div>
  <a href="..." class="sbx__item{% if active %} sbx__item--active{% endif %}">
    <svg ...>...</svg>
    <span>Home</span>
  </a>
  ...
</div>
```

- [ ] **Step 3: Add "You" group**

The design system has two groups: "Explore" and "You". Map the current nav items:

**Explore:** Home, Feed (if authenticated), Communities, Events, Teachings, People, Businesses, Oral Histories
**Programs:** Elder Program, Request Help, Volunteer (keep existing)
**You:** Messages, Games (Shkoda, Crosswords, Matcher)

Keep the existing groups but rename the headings to match design system convention.

- [ ] **Step 4: Visual verification**

Check sidebar renders with proper grouping, active states highlight correctly, icons align with labels.

- [ ] **Step 5: Commit**

```bash
git add templates/components/sidebar-nav.html.twig
git commit -m "feat: rename sidebar-nav classes to sbx design system pattern"
```

---

### Task 5: Add Hero, Section, Badge, and Chip CSS

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add hero component CSS**

Add to `@layer components`:

```css
  /* === Design System: Hero === */
  .hero {
    padding: var(--space-xl) 0 var(--space-lg);
    max-width: 65ch;
  }

  .hero__eyebrow {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    font-weight: 700;
    margin: 0 0 var(--space-sm);
  }

  .hero h1 {
    margin: 0 0 var(--space-sm);
    font-size: var(--text-3xl);
    line-height: 1.1;
    letter-spacing: -0.02em;
  }

  .hero h1 em {
    font-style: italic;
    color: var(--color-teachings);
    font-weight: 500;
  }

  .hero p {
    font-size: var(--text-lg);
    line-height: 1.5;
    color: var(--text-secondary);
    margin: 0 0 var(--space-md);
  }

  .hero__ctas {
    display: flex;
    gap: var(--space-xs);
    flex-wrap: wrap;
  }
```

- [ ] **Step 2: Add section component CSS**

```css
  /* === Design System: Section === */
  .sec {
    margin-top: var(--space-xl);
  }

  .sec__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: var(--space-sm);
    border-bottom: 1px solid var(--border);
    padding-bottom: var(--space-2xs);
  }

  .sec__head h2 {
    margin: 0;
    font-size: var(--text-xl);
    letter-spacing: -0.01em;
  }

  .sec__head a {
    color: var(--text-secondary);
    font-size: var(--text-sm);
    text-decoration: none;
    font-family: var(--font-body);
  }

  .sec__head a:hover {
    color: var(--accent);
  }
```

- [ ] **Step 3: Add grid component CSS**

Check if `.grid` already exists. If not, add:

```css
  /* === Design System: Grid === */
  .ds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-md);
  }
```

Note: Using `.ds-grid` to avoid conflicts with any existing `.grid` utility class. If no conflict exists, use `.grid` instead.

- [ ] **Step 4: Add badge component CSS**

```css
  /* === Design System: Badge === */
  .badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 8px;
    border-radius: var(--radius-sm);
    background: var(--badge-bg, rgba(230, 57, 70, 0.15));
    color: var(--badge-fg, var(--color-events));
  }

  .badge--events     { --badge-bg: rgba(230, 57, 70, 0.15);  --badge-fg: #e63946; }
  .badge--teachings  { --badge-bg: rgba(244, 162, 97, 0.15); --badge-fg: #f4a261; }
  .badge--language   { --badge-bg: rgba(42, 157, 143, 0.15); --badge-fg: #2a9d8f; }
  .badge--communities { --badge-bg: rgba(106, 156, 175, 0.15); --badge-fg: #6a9caf; }
  .badge--people     { --badge-bg: rgba(131, 56, 236, 0.15); --badge-fg: #8338ec; }
  .badge--programs   { --badge-bg: rgba(199, 125, 255, 0.15); --badge-fg: #c77dff; }
```

- [ ] **Step 5: Add chip/filter CSS**

```css
  /* === Design System: Filters / Chips === */
  .filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: var(--space-md);
  }

  .chip {
    border: 1px solid var(--border-subtle);
    background: transparent;
    color: var(--text-secondary);
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    cursor: pointer;
    font-family: var(--font-body);
    font-weight: 500;
    letter-spacing: 0.02em;
    transition: all var(--duration-fast) var(--ease-out);
  }

  .chip:hover {
    color: var(--text-primary);
    border-color: var(--text-muted);
  }

  .chip--active {
    background: var(--color-events);
    border-color: var(--color-events);
    color: #fff;
  }
```

- [ ] **Step 6: Add card refinements**

Add the `card--media` variant and `card__eyebrow` to the existing card styles. Find the existing `.card` block (around line 1392) and add after it:

```css
  .card__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--card-accent, var(--color-events));
  }

  .card--media {
    padding: 0;
    overflow: hidden;
  }

  .card--media .card__thumb {
    width: 100%;
    aspect-ratio: 16 / 10;
    background: var(--surface-raised);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
  }

  .card--media .card__content {
    padding: var(--space-sm);
  }

  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }
```

Check if `fadeInUp` keyframes already exist before adding.

- [ ] **Step 7: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add hero, section, badge, chip, and card media CSS components"
```

---

### Task 6: Update Homepage Template

**Files:**
- Modify: `templates/home.html.twig`

- [ ] **Step 1: Read current home.html.twig**

The current template uses `home-hero`, `home-hero__title`, `home-hero__subtitle`, `home-hero__actions`, `home-section`, `home-section__heading`, `home-section__header`, `card-grid`.

- [ ] **Step 2: Rewrite homepage to design system pattern**

Replace the content block with:

```twig
{% block content %}
  <div class="flow-lg">

    {# === Hero === #}
    <section class="hero">
      <p class="hero__eyebrow">Aanii &middot; Welcome</p>
      <h1>A place where Indigenous Knowledge, culture, and <em>community</em> come together.</h1>
      <p>Explore Teachings from Knowledge Keepers, find Events near you, and connect with communities across Turtle Island.</p>
      <div class="hero__ctas">
        <a href="{{ lang_url('/events') }}" class="btn btn--primary btn--lg">Find Events near you</a>
        <a href="{{ lang_url('/teachings') }}" class="btn btn--secondary btn--lg">Explore Teachings</a>
      </div>
    </section>

    {# === Featured Items === #}
    {% if featured is defined and featured|length > 0 %}
      <section class="sec">
        <div class="sec__head">
          <h2>Featured</h2>
        </div>
        <div class="ds-grid">
          {% for item in featured %}
            <a href="{{ item.url }}" class="card card--{{ item.entity_type }}">
              <span class="card__eyebrow">{{ item.entity_type|capitalize }}</span>
              <h3 class="card__title">{{ item.headline }}</h3>
              {% if item.subheadline is defined and item.subheadline %}
                <p class="card__meta">{{ item.subheadline }}</p>
              {% endif %}
            </a>
          {% endfor %}
        </div>
      </section>
    {% endif %}

    {# === Upcoming Events === #}
    {% if events is defined and events|length > 0 %}
      <section class="sec">
        <div class="sec__head">
          <h2>Upcoming Events</h2>
          <a href="{{ lang_url('/events') }}">View all &rarr;</a>
        </div>
        <div class="ds-grid">
          {% for event in events|slice(0, 4) %}
            {% include "components/event-card.html.twig" with {
              title: event.get('title'),
              type: event.get('type')|default(''),
              starts_at: event.get('starts_at'),
              community_name: communities[event.get('community_id')].get('name')|default(''),
              slug: event.get('slug'),
              photo_url: event.get('photo_url')|default('')
            } %}
          {% endfor %}
        </div>
      </section>
    {% endif %}

    {# === Recent Teachings === #}
    {% if teachings is defined and teachings|length > 0 %}
      <section class="sec">
        <div class="sec__head">
          <h2>Recent Teachings</h2>
          <a href="{{ lang_url('/teachings') }}">View all &rarr;</a>
        </div>
        <div class="ds-grid">
          {% for t in teachings|slice(0, 4) %}
            {% include "components/teaching-card.html.twig" with {
              title: t.get('title'),
              type: t.get('type')|default('Teaching'),
              community_name: communities[t.get('community_id')].get('name')|default(''),
              slug: t.get('slug')
            } %}
          {% endfor %}
        </div>
      </section>
    {% endif %}

    {# === CTA === #}
    {% if account is not defined or not account.isAuthenticated() %}
      <section class="sec" style="text-align: center; padding: var(--space-xl) 0;">
        <h2>Join the Conversation</h2>
        <p style="color: var(--text-secondary); max-width: 50ch; margin: var(--space-sm) auto var(--space-md);">Create an account to share your stories, follow communities, and stay up to date with Events and Teachings that matter to you.</p>
        <a href="{{ lang_url('/auth/register') }}" class="btn btn--primary btn--lg">Create an Account</a>
      </section>
    {% endif %}
  </div>
{% endblock %}
```

**Important:** Check what variables the `HomeController` passes to the template (`featured`, `events`, `teachings`, `communities`). If the variable names differ, adjust the template accordingly. The existing template's variable names should be preserved.

- [ ] **Step 3: Visual verification**

Check:
1. Hero section with eyebrow, italic "community", CTAs
2. Featured section with cards (if featured items exist)
3. Upcoming Events grid
4. Recent Teachings grid
5. CTA section for anonymous users

- [ ] **Step 4: Commit**

```bash
git add templates/home.html.twig
git commit -m "feat: adopt design system hero/section pattern on homepage"
```

---

### Task 7: Add Detail Page CSS

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add detail layout CSS**

Check if `.detail` styles already exist (they do, around line ~2960+). Verify what exists and add any missing pieces. The design system needs:

```css
  /* === Design System: Detail Page === */
  .detail {
    padding-top: var(--space-md);
  }

  .detail__hero {
    width: 100%;
    aspect-ratio: 21 / 9;
    background: var(--surface-raised);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-md);
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-family: var(--font-mono);
    font-size: var(--text-xs);
  }

  .detail h1 {
    font-size: var(--text-3xl);
    margin: 0 0 var(--space-2xs);
    letter-spacing: -0.02em;
  }

  .detail__meta {
    color: var(--text-secondary);
    font-size: var(--text-sm);
    margin-bottom: var(--space-md);
    display: flex;
    gap: var(--space-sm);
    flex-wrap: wrap;
    align-items: center;
  }

  .detail__meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .detail__layout {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: var(--space-lg);
    margin-top: var(--space-md);
  }

  .detail__body p {
    font-size: var(--text-base);
    line-height: 1.7;
    color: var(--text-primary);
    margin: 0 0 var(--space-sm);
  }

  .detail__aside {
    position: sticky;
    top: 80px;
    align-self: start;
  }
```

If `.detail` styles already exist in the file, merge these properties into the existing rules rather than duplicating. Existing detail styles may have different properties — keep both where they don't conflict.

- [ ] **Step 2: Add aside-card and keeper CSS**

```css
  /* === Design System: Aside Card === */
  .aside-card {
    background: var(--surface-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    margin-bottom: var(--space-sm);
  }

  .aside-card h4 {
    margin: 0 0 var(--space-2xs);
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    font-family: var(--font-body);
    font-weight: 600;
  }

  /* === Design System: Keeper Attribution === */
  .keeper {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .keeper__avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
  }

  .keeper__name {
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: var(--text-sm);
  }

  .keeper__role {
    font-size: var(--text-xs);
    color: var(--text-muted);
  }
```

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat: add detail page layout, aside-card, and keeper CSS"
```

---

### Task 8: Update Events Template

**Files:**
- Modify: `templates/events.html.twig`

- [ ] **Step 1: Read current events.html.twig**

Understand the current listing/detail split. The template likely has a conditional: listing mode vs. single event detail mode.

- [ ] **Step 2: Update listing section hero**

Find the listing section header (likely a `<h1>` or `.listing-hero`). Replace with:

```twig
{# Inside the listing branch #}
<section class="hero" style="padding-bottom: var(--space-sm);">
  <p class="hero__eyebrow">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
    {{ trans('events.near_location') }} &middot; <a href="#" style="color: var(--text-muted);">change</a>
  </p>
  <h1>{{ trans('events.title') }}</h1>
  <p>{{ trans('events.subtitle')|default('Gatherings, ceremonies, and community events across Turtle Island.') }}</p>
</section>
```

- [ ] **Step 3: Update event filters to chip pattern**

If the events template has filter buttons, update them from the current pattern to:

```twig
<div class="filters">
  {% for filter_type in event_types|default(['All']) %}
    <button class="chip{% if current_filter == filter_type %} chip--active{% endif %}"
            onclick="window.location.href='{{ lang_url('/events') }}?type={{ filter_type }}'">
      {{ filter_type }}
    </button>
  {% endfor %}
</div>
```

Adapt this to the actual filter mechanism (the template may use `event-filters.html.twig` component). If so, update that component instead.

- [ ] **Step 4: Update event detail section**

In the single event detail branch, update to use the design system's two-column layout:

```twig
{# Inside the detail branch (event is defined) #}
<div class="detail">
  <a href="{{ lang_url('/events') }}" class="btn btn--ghost" style="margin-bottom: var(--space-sm); padding: 4px 0;">
    &larr; {{ trans('events.back') }}
  </a>

  {% if event.get('photo_url') %}
    <div class="detail__hero">
      <img src="{{ event.get('photo_url') }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
    </div>
  {% endif %}

  <span class="badge badge--events">{{ event.get('type')|default('')|capitalize }}</span>
  <h1>{{ event.get('title') }}</h1>

  <div class="detail__meta">
    {% if event.get('starts_at') %}
      <span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        {{ event.get('starts_at')|date('F j, Y') }}
      </span>
    {% endif %}
    {% if community_name is defined and community_name %}
      <span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        {{ community_name }}
      </span>
    {% endif %}
  </div>

  <div class="detail__layout">
    <div class="detail__body">
      {{ event.get('content')|raw }}
    </div>
    <aside class="detail__aside">
      {% if community_name is defined and community_name %}
        <div class="aside-card">
          <h4>{{ trans('events.host_community') }}</h4>
          <div class="keeper">
            <div class="keeper__avatar" style="background: var(--color-communities);">{{ community_name|first|upper }}</div>
            <div>
              <div class="keeper__name">{{ community_name }}</div>
            </div>
          </div>
        </div>
      {% endif %}
    </aside>
  </div>
</div>
```

Adapt variable names to match what `EventController` actually passes. The existing template's variable access patterns (e.g., `event.get('title')`) should be preserved.

- [ ] **Step 5: Visual verification**

Check:
1. Events listing with hero and filter chips
2. Event detail with two-column layout
3. Aside card with community info
4. Back button works

- [ ] **Step 6: Commit**

```bash
git add templates/events.html.twig
git commit -m "feat: adopt design system hero, chips, and detail layout on events page"
```

---

### Task 9: Update Teachings Template

**Files:**
- Modify: `templates/teachings.html.twig`

- [ ] **Step 1: Read current teachings.html.twig**

Similar pattern to events — listing mode vs. detail mode.

- [ ] **Step 2: Update listing hero**

Replace `listing-hero` with design system hero:

```twig
<section class="hero">
  <p class="hero__eyebrow">{{ trans('teachings.eyebrow')|default('Oral teachings from Knowledge Keepers') }}</p>
  <h1>{{ trans('teachings.title') }}</h1>
  <p>{{ trans('teachings.subtitle') }}</p>
</section>
```

- [ ] **Step 3: Update listing grid**

Replace `card-grid` with `ds-grid` (or `grid` if no conflict).

- [ ] **Step 4: Update teaching detail**

Apply the two-column detail layout similar to events. The teaching detail should include Knowledge Keeper attribution in the aside:

```twig
<div class="detail">
  <a href="{{ lang_url('/teachings') }}" class="btn btn--ghost" style="margin-bottom: var(--space-sm); padding: 4px 0;">
    &larr; Back
  </a>

  <span class="badge badge--teachings">{{ teaching.get('type')|default('Teaching')|capitalize }}</span>
  <h1>{{ teaching.get('title') }}</h1>

  <div class="detail__meta">
    {% if teaching.get('duration') %}
      <span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        {{ teaching.get('duration') }}
      </span>
    {% endif %}
    <span>&middot;</span>
    <span>English &middot; Anishinaabemowin</span>
  </div>

  <div class="detail__layout">
    <div class="detail__body">
      {{ teaching.get('content')|raw }}
    </div>
    <aside class="detail__aside">
      {% if community_name is defined and community_name %}
        <div class="aside-card">
          <h4>Knowledge Keeper</h4>
          <div class="keeper">
            <div class="keeper__avatar" style="background: var(--color-teachings);">{{ community_name|first|upper }}</div>
            <div>
              <div class="keeper__name">{{ community_name }}</div>
            </div>
          </div>
        </div>
      {% endif %}
    </aside>
  </div>
</div>
```

Adapt to actual variable names from the controller.

- [ ] **Step 5: Visual verification and commit**

```bash
git add templates/teachings.html.twig
git commit -m "feat: adopt design system hero and detail layout on teachings page"
```

---

### Task 10: CSS Cleanup & Cache Bust

**Files:**
- Modify: `public/css/minoo.css`
- Modify: `templates/base.html.twig`

- [ ] **Step 1: Identify dead CSS classes**

Search for classes that were replaced and are no longer used in any template:

```bash
# Check if old classes are still referenced anywhere
grep -r 'home-hero\|home-section\|listing-hero\|app-layout\|app-sidebar\b\|app-main\b\|sidebar-nav__' templates/
```

For each class that returns no results, it's safe to remove from `minoo.css`.

- [ ] **Step 2: Remove dead CSS**

Remove CSS blocks for classes confirmed unused:
- `.home-hero`, `.home-hero__title`, `.home-hero__subtitle`, `.home-hero__actions` (if no template references remain)
- `.home-section`, `.home-section__header`, `.home-section__heading`, `.home-section__link`, `.home-section__intro`
- `.listing-hero`, `.listing-hero__subtitle`
- Old `sidebar-nav__*` styles (if fully migrated to `sbx__*`)
- Old `app-layout`, `app-sidebar`, `app-main` styles (if fully migrated)

**Be conservative:** only remove if `grep -r` confirms zero references across all templates.

- [ ] **Step 3: Bump CSS cache version**

In `templates/base.html.twig`, find the stylesheet link and bump the version:

```html
<!-- Find this line -->
<link rel="stylesheet" href="/css/minoo.css?v=57">
<!-- Change to -->
<link rel="stylesheet" href="/css/minoo.css?v=58">
```

- [ ] **Step 4: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All 442 tests pass. CSS/template changes should not break PHP tests.

- [ ] **Step 5: Visual smoke test all pages**

Check in browser:
1. `/` — homepage with hero, sections, cards
2. `/events` — listing with hero, filters
3. `/events/{slug}` — detail with two-column layout
4. `/teachings` — listing with hero
5. `/teachings/{slug}` — detail with two-column layout
6. `/communities` — should still work (not modified)
7. `/language` — should still work
8. Toggle light/dark theme on each page
9. Check sidebar active state on each page

- [ ] **Step 6: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "chore: remove dead CSS classes and bump cache version"
```

---

## Post-Implementation Notes

- **Mobile sidebar:** The sidebar needs JS toggle behavior for mobile. The existing toggle mechanism should continue to work if IDs were preserved. If not, add a follow-up task.
- **Other pages:** Pages like `/communities`, `/language`, `/games/*`, `/feed`, `/elders` were not modified. They should still work because the app shell changes only rename wrapper classes, and the old CSS was not removed until confirmed unused.
- **Playwright tests:** Some tests may reference old class names in selectors. Run `npx playwright test` after the migration and fix any selector-based failures.
