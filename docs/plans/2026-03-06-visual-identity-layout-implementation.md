# Visual Identity & Global Layout Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship `minoo.css` with design tokens + base styles, Twig base template with header/footer/nav, and a dictionary entry card component — giving Minoo its visual identity and page shell.

**Architecture:** Single vanilla CSS file using modern features (oklch, clamp, @layer, native nesting, container queries, logical properties). Twig template inheritance: `base.html.twig` defines shell, page templates extend it. No build step.

**Tech Stack:** CSS (no preprocessor), Twig 3, PHP 8.3 (existing SSR framework)

**Issues:** #5 (visual identity), #9 (global navigation and layout)

---

### Task 1: Create `minoo.css` — reset and tokens layers

**Files:**
- Create: `public/css/minoo.css`

**Step 1: Create the CSS file with reset and tokens layers**

```css
@layer reset, tokens, base, layout, components, utilities;

@layer reset {
  *,
  *::before,
  *::after {
    box-sizing: border-box;
  }

  * {
    margin: 0;
  }

  html {
    -webkit-text-size-adjust: none;
    text-size-adjust: none;
  }

  img,
  picture,
  video,
  canvas,
  svg {
    display: block;
    max-inline-size: 100%;
  }

  input,
  button,
  textarea,
  select {
    font: inherit;
  }

  p,
  h1,
  h2,
  h3,
  h4,
  h5,
  h6 {
    overflow-wrap: break-word;
  }

  body {
    min-block-size: 100dvh;
    -webkit-font-smoothing: antialiased;
  }
}

@layer tokens {
  :root {
    /* Color palette — oklch */
    --color-earth-900: oklch(0.25 0.02 70);
    --color-earth-700: oklch(0.40 0.02 70);
    --color-earth-200: oklch(0.88 0.01 70);
    --color-earth-100: oklch(0.94 0.01 70);
    --color-earth-50:  oklch(0.97 0.005 70);

    --color-forest-700: oklch(0.40 0.08 155);
    --color-forest-500: oklch(0.55 0.10 155);
    --color-forest-100: oklch(0.92 0.03 155);

    --color-water-600: oklch(0.50 0.08 230);
    --color-water-100: oklch(0.92 0.03 230);

    --color-sun-500: oklch(0.70 0.12 85);

    --color-berry-600: oklch(0.50 0.12 10);

    /* Semantic aliases */
    --text-primary:   var(--color-earth-900);
    --text-secondary: var(--color-earth-700);
    --surface:        var(--color-earth-50);
    --surface-raised: white;
    --border:         var(--color-earth-200);
    --accent:         var(--color-forest-500);
    --accent-surface: var(--color-forest-100);
    --link:           var(--color-forest-500);
    --error:          var(--color-berry-600);
    --warning:        var(--color-sun-500);
    --info:           var(--color-water-600);

    /* Typography */
    --font-body:    system-ui, -apple-system, sans-serif;
    --font-heading: Charter, 'Bitstream Charter', 'Sitka Text', Cambria, serif;
    --font-mono:    ui-monospace, 'Cascadia Code', 'Source Code Pro', monospace;

    --text-sm:   clamp(0.833rem, 0.816rem + 0.08vw, 0.889rem);
    --text-base: clamp(1rem, 0.964rem + 0.18vw, 1.125rem);
    --text-lg:   clamp(1.2rem, 1.132rem + 0.34vw, 1.406rem);
    --text-xl:   clamp(1.44rem, 1.318rem + 0.61vw, 1.758rem);
    --text-2xl:  clamp(1.728rem, 1.525rem + 1.01vw, 2.197rem);
    --text-3xl:  clamp(2.074rem, 1.753rem + 1.6vw, 2.747rem);

    --leading-tight:  1.2;
    --leading-normal: 1.6;
    --leading-loose:  1.8;

    --tracking-tight:  -0.01em;
    --tracking-normal: 0;
    --tracking-wide:   0.02em;

    /* Spacing — fluid 1.5 ratio */
    --baseline: 0.25rem;

    --space-3xs: clamp(0.25rem, 0.23rem + 0.11vw, 0.313rem);
    --space-2xs: clamp(0.5rem, 0.46rem + 0.23vw, 0.625rem);
    --space-xs:  clamp(0.75rem, 0.68rem + 0.34vw, 0.938rem);
    --space-sm:  clamp(1rem, 0.91rem + 0.45vw, 1.25rem);
    --space-md:  clamp(1.5rem, 1.36rem + 0.68vw, 1.875rem);
    --space-lg:  clamp(2rem, 1.82rem + 0.91vw, 2.5rem);
    --space-xl:  clamp(3rem, 2.73rem + 1.36vw, 3.75rem);
    --space-2xl: clamp(4rem, 3.64rem + 1.82vw, 5rem);

    --gutter: var(--space-sm);

    /* Widths */
    --width-prose:   65ch;
    --width-content: 80rem;
    --width-narrow:  40rem;
    --width-card:    25rem;

    /* Radii */
    --radius-sm:   0.25rem;
    --radius-md:   0.5rem;
    --radius-lg:   1rem;
    --radius-full: 9999px;

    /* Shadows */
    --shadow-sm: 0 1px 2px oklch(0.25 0.02 70 / 0.06);
    --shadow-md: 0 2px 4px oklch(0.25 0.02 70 / 0.08), 0 4px 12px oklch(0.25 0.02 70 / 0.04);
    --shadow-lg: 0 4px 8px oklch(0.25 0.02 70 / 0.08), 0 8px 24px oklch(0.25 0.02 70 / 0.06);
  }
}
```

**Step 2: Verify the file exists and is valid CSS**

Run: `ls -la public/css/minoo.css && head -5 public/css/minoo.css`
Expected: File exists, starts with `@layer`

**Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#5): add minoo.css with reset and design tokens

oklch color palette, fluid type/spacing scales, @layer structure."
```

---

### Task 2: Add base and layout layers to `minoo.css`

**Files:**
- Modify: `public/css/minoo.css` (append after tokens layer)

**Step 1: Append the base layer**

Add after the closing `}` of `@layer tokens`:

```css
@layer base {
  body {
    font-family: var(--font-body);
    font-size: var(--text-base);
    line-height: var(--leading-normal);
    color: var(--text-primary);
    background-color: var(--surface);
  }

  h1, h2, h3, h4 {
    font-family: var(--font-heading);
    line-height: var(--leading-tight);
    letter-spacing: var(--tracking-tight);
    text-wrap: balance;
  }

  h1 { font-size: var(--text-3xl); }
  h2 { font-size: var(--text-2xl); }
  h3 { font-size: var(--text-xl); }
  h4 { font-size: var(--text-lg); }

  a {
    color: var(--link);
    text-decoration-thickness: 1px;
    text-underline-offset: 0.15em;

    &:hover {
      text-decoration-thickness: 2px;
    }
  }

  :focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
  }

  code {
    font-family: var(--font-mono);
    font-size: 0.9em;
  }
}
```

**Step 2: Append the layout layer**

```css
@layer layout {
  .site {
    display: grid;
    grid-template-rows: auto 1fr auto;
    min-block-size: 100dvh;
  }

  .site-header {
    padding-block: var(--space-xs);
    padding-inline: var(--gutter);
    border-block-end: 1px solid var(--border);
  }

  .site-header__inner {
    max-inline-size: var(--width-content);
    margin-inline: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-md);
  }

  .site-name {
    font-family: var(--font-heading);
    font-size: var(--text-xl);
    color: var(--text-primary);
    text-decoration: none;
    letter-spacing: var(--tracking-tight);
  }

  .site-nav {
    display: flex;
    gap: var(--space-xs);
    list-style: none;
    padding: 0;
  }

  .site-nav a {
    padding: var(--space-3xs) var(--space-2xs);
    border-radius: var(--radius-sm);
    text-decoration: none;
    font-size: var(--text-sm);
    color: var(--text-secondary);

    &:hover {
      color: var(--text-primary);
      background-color: var(--accent-surface);
    }

    &[aria-current="page"] {
      color: var(--accent);
      background-color: var(--accent-surface);
    }
  }

  .site-main {
    padding-block: var(--space-lg);
    padding-inline: var(--gutter);
  }

  .site-main__inner {
    max-inline-size: var(--width-content);
    margin-inline: auto;
  }

  .site-footer {
    padding-block: var(--space-md);
    padding-inline: var(--gutter);
    border-block-start: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: var(--text-sm);
  }

  .site-footer__inner {
    max-inline-size: var(--width-content);
    margin-inline: auto;
  }

  /* Mobile nav toggle — hidden above bp-md */
  .nav-toggle {
    display: none;
  }

  @media (max-width: 60em) {
    .site-nav {
      display: none;

      &.is-open {
        display: flex;
        flex-direction: column;
        position: absolute;
        inset-block-start: 100%;
        inset-inline: 0;
        background-color: var(--surface-raised);
        padding: var(--space-sm);
        border-block-end: 1px solid var(--border);
        box-shadow: var(--shadow-md);
      }
    }

    .nav-toggle {
      display: block;
      background: none;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: var(--space-3xs) var(--space-2xs);
      cursor: pointer;
      font-size: var(--text-sm);
      color: var(--text-secondary);
    }

    .site-header__inner {
      position: relative;
    }
  }
}
```

**Step 3: Append components and utilities layers (empty + minimal)**

```css
@layer components {
  /* Components added in subsequent tasks */
}

@layer utilities {
  .flow > * + * {
    margin-block-start: var(--space-sm);
  }

  .flow-lg > * + * {
    margin-block-start: var(--space-md);
  }

  .visually-hidden {
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    block-size: 1px;
    inline-size: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
  }

  .compact {
    --space-3xs: calc(clamp(0.25rem, 0.23rem + 0.11vw, 0.313rem) * 0.6);
    --space-2xs: calc(clamp(0.5rem, 0.46rem + 0.23vw, 0.625rem) * 0.6);
    --space-xs:  calc(clamp(0.75rem, 0.68rem + 0.34vw, 0.938rem) * 0.6);
    --space-sm:  calc(clamp(1rem, 0.91rem + 0.45vw, 1.25rem) * 0.6);
    --space-md:  calc(clamp(1.5rem, 1.36rem + 0.68vw, 1.875rem) * 0.6);
  }

  .prose {
    max-inline-size: var(--width-prose);
  }
}
```

**Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#5): add base, layout, and utility layers to minoo.css

Element defaults, page shell grid, responsive nav, flow utilities."
```

---

### Task 3: Create base Twig template

**Files:**
- Create: `templates/base.html.twig`
- Modify: `templates/page.html.twig`
- Modify: `templates/404.html.twig`

**Step 1: Create `templates/base.html.twig`**

```twig
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{% block title %}Minoo{% endblock %}</title>
  <link rel="stylesheet" href="/css/minoo.css">
  {% block head %}{% endblock %}
</head>
<body>
  <div class="site">
    <header class="site-header">
      <div class="site-header__inner">
        <a href="/" class="site-name">Minoo</a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="main-nav">Menu</button>
        <nav id="main-nav" aria-label="Main">
          <ul class="site-nav">
            <li><a href="/events">Events</a></li>
            <li><a href="/groups">Groups</a></li>
            <li><a href="/teachings">Teachings</a></li>
            <li><a href="/language">Language</a></li>
          </ul>
        </nav>
      </div>
    </header>

    <main class="site-main">
      <div class="site-main__inner">
        {% block content %}{% endblock %}
      </div>
    </main>

    <footer class="site-footer">
      <div class="site-footer__inner">
        <p>&copy; {{ "now"|date("Y") }} Minoo</p>
      </div>
    </footer>
  </div>
  <script>
    document.querySelector('.nav-toggle')?.addEventListener('click', function() {
      const nav = document.getElementById('main-nav')?.querySelector('.site-nav');
      const open = nav?.classList.toggle('is-open');
      this.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  </script>
</body>
</html>
```

**Step 2: Rewrite `templates/page.html.twig` to extend base**

```twig
{% extends "base.html.twig" %}

{% block title %}{{ title|default('Minoo') }}{% endblock %}

{% block content %}
  <h1>{{ title|default('Minoo') }}</h1>
  <p>Path: {{ path }}</p>
{% endblock %}
```

**Step 3: Rewrite `templates/404.html.twig` to extend base**

```twig
{% extends "base.html.twig" %}

{% block title %}404 Not Found — Minoo{% endblock %}

{% block content %}
  <h1>Not Found</h1>
  <p>The page <code>{{ path }}</code> could not be found.</p>
{% endblock %}
```

**Step 4: Start dev server and verify in browser**

Run: `php -S localhost:8081 -t public &`
Then open `http://localhost:8081` — should see styled page with header, nav, footer.

**Step 5: Verify with Playwright — take screenshot of homepage**

Use Playwright MCP: navigate to `http://localhost:8081`, take a screenshot. Verify:
- Earth-toned background
- Serif "Minoo" site name
- Horizontal nav with 4 links
- Footer with copyright

**Step 6: Verify responsive — take screenshot at mobile width**

Use Playwright MCP: resize to 375x812, take screenshot. Verify:
- "Menu" button visible
- Nav links hidden

**Step 7: Commit**

```bash
git add templates/base.html.twig templates/page.html.twig templates/404.html.twig
git commit -m "feat(#9): add base template with header, nav, and footer

Twig template inheritance. Responsive nav with mobile toggle.
page.html.twig and 404.html.twig extend base."
```

---

### Task 4: Add dictionary entry card component

**Files:**
- Modify: `public/css/minoo.css` (add to `@layer components`)
- Create: `templates/components/dictionary-entry-card.html.twig`
- Create: `templates/language.html.twig` (demo page for visual verification)

**Step 1: Add card styles to the components layer in `minoo.css`**

Replace the empty `@layer components` block with:

```css
@layer components {
  .card {
    container-type: inline-size;
    background-color: var(--surface-raised);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    box-shadow: var(--shadow-sm);
    max-inline-size: var(--width-card);
  }

  .card__title {
    font-family: var(--font-heading);
    font-size: var(--text-xl);
    line-height: var(--leading-tight);
    letter-spacing: var(--tracking-tight);
  }

  .card__meta {
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }

  .card__body {
    font-size: var(--text-base);
    line-height: var(--leading-normal);
  }

  .card__tag {
    display: inline-block;
    font-size: var(--text-sm);
    padding: var(--space-3xs) var(--space-2xs);
    background-color: var(--accent-surface);
    color: var(--color-forest-700);
    border-radius: var(--radius-full);
  }

  .card > * + * {
    margin-block-start: var(--space-2xs);
  }

  @container (min-width: 20rem) {
    .card {
      padding: var(--space-md);
    }
  }

  .card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, var(--width-card)), 1fr));
    gap: var(--space-md);
  }
}
```

**Step 2: Create dictionary entry card template**

Create `templates/components/dictionary-entry-card.html.twig`:

```twig
<article class="card">
  <h3 class="card__title">{{ word }}</h3>
  {% if part_of_speech is defined and part_of_speech %}
    <p class="card__meta">{{ part_of_speech }}</p>
  {% endif %}
  {% if definition is defined and definition %}
    <p class="card__body">{{ definition }}</p>
  {% endif %}
  {% if example is defined and example %}
    <p class="card__body"><em>{{ example }}</em></p>
  {% endif %}
  {% if tags is defined and tags %}
    <div>
      {% for tag in tags %}
        <span class="card__tag">{{ tag }}</span>
      {% endfor %}
    </div>
  {% endif %}
</article>
```

**Step 3: Create language demo page**

Create `templates/language.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}Language — Minoo{% endblock %}

{% block content %}
  <h1>Language</h1>
  <p class="flow">Ojibwe dictionary entries.</p>

  <div class="card-grid" style="margin-block-start: var(--space-md);">
    {% include "components/dictionary-entry-card.html.twig" with {
      word: "Miigwech",
      part_of_speech: "interjection",
      definition: "Thank you. An expression of gratitude.",
      example: "Miigwech for the wild rice.",
      tags: ["greeting", "common"]
    } %}

    {% include "components/dictionary-entry-card.html.twig" with {
      word: "Anishinaabe",
      part_of_speech: "noun (animate)",
      definition: "A human being; an Indigenous person. The original people.",
      tags: ["identity", "common"]
    } %}

    {% include "components/dictionary-entry-card.html.twig" with {
      word: "Aki",
      part_of_speech: "noun (inanimate)",
      definition: "The earth, land, ground. The physical world.",
      example: "Gakina gegoo onjibaa akiing.",
      tags: ["nature"]
    } %}
  </div>
{% endblock %}
```

**Step 4: Verify with Playwright — screenshot of language page**

Use Playwright MCP: navigate to `http://localhost:8081/language`, take screenshot. Verify:
- Page has header/nav/footer from base
- Card grid with 3 dictionary entry cards
- Cards have earth-toned borders, serif titles, tag pills
- Cards flow responsively

**Step 5: Verify mobile — screenshot at 375px**

Use Playwright MCP: resize to 375x812, screenshot. Verify cards stack single-column.

**Step 6: Commit**

```bash
git add public/css/minoo.css templates/components/dictionary-entry-card.html.twig templates/language.html.twig
git commit -m "feat(#5): add dictionary entry card component and language demo page

Card with container queries, card-grid layout, demo with 3 Ojibwe words."
```

---

### Task 5: Wire language route and final verification

**Files:**
- Check: framework routing — does the SSR controller already serve `/language`?

The `RenderController::renderPath()` renders `page.html.twig` for all paths. To serve `language.html.twig` for `/language`, the Twig template chain needs to resolve it. Two options:

**Option A (simple):** Modify `page.html.twig` to detect path and delegate:
```twig
{% if path == '/language' %}
  {% include "language.html.twig" %}
{% else %}
  {# default content #}
{% endif %}
```

**Option B (correct):** Check if the framework's `RenderController` supports path-based template resolution (e.g., trying `language.html.twig` before `page.html.twig`). Read `RenderController::renderPath()` — it only tries `page.html.twig`. This is a framework enhancement.

**Step 1: Check framework template resolution**

Read `RenderController::renderPath()` — currently tries `page.html.twig` and `ssr/page.html.twig` only. Enhance it to try path-based templates first.

If framework change is out of scope, use Option A as a temporary shim in `page.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ title|default('Minoo') }}{% endblock %}

{% block content %}
  {% if path == '/language' %}
    {% include "language-content.html.twig" %}
  {% else %}
    <h1>{{ title|default('Minoo') }}</h1>
    <p>Path: {{ path }}</p>
  {% endif %}
{% endblock %}
```

(Rename `language.html.twig` to `language-content.html.twig` since it would be included, not standalone.)

**Better approach:** Modify `RenderController::renderPath()` in the framework to try `{path-segment}.html.twig` first. This is a small, clean framework change that benefits all Waaseyaa apps. Create a framework issue for this.

**Step 2: Choose approach and implement**

Recommend: framework enhancement (small diff, correct solution). If deferred, use the shim.

**Step 3: Full Playwright verification pass**

Navigate and screenshot:
1. `http://localhost:8081/` — homepage with shell
2. `http://localhost:8081/language` — card grid
3. `http://localhost:8081/nonexistent` — 404 page with shell
4. Resize to 375px and re-check all 3

**Step 4: Commit and close issues**

```bash
git commit -m "feat(#9): wire path-based template resolution

Language page served at /language with card grid."
```

Then:
```bash
gh issue close 5 -c "Visual identity shipped: oklch tokens, fluid type/spacing, earth-tone palette in minoo.css"
gh issue close 9 -c "Global layout shipped: base template, header/nav/footer, responsive mobile nav"
```

---

## Execution Notes

- **Task 3 is the integration point** — that's where we first see the design in the browser. Most debugging will happen there.
- **Task 5 has a framework decision** — path-based template resolution. Decide shim vs framework PR before executing.
- **Playwright verification** in Tasks 3, 4, 5 catches visual regressions immediately.
- All CSS is in one file, all tokens in one `:root` block — easy to audit and iterate.
