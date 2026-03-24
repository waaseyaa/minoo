# Search Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the search results page from a flat list of titles into a visually rich, responsive discovery surface with OG images, highlight snippets, smart badges, and polished layout.

**Architecture:** All changes are in the Minoo application layer — templates (`search.html.twig`, `search-result-card.html.twig`) and CSS (`minoo.css`). No framework or NC changes needed. The search provider already requests highlights and OG images from NC; we just need to render them properly.

**Tech Stack:** Twig 3 templates, vanilla CSS (layered: `@layer components`), no JS required.

**Spec:** `docs/superpowers/specs/2026-03-23-search-experience-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `public/css/minoo.css` | Modify | Define `--accent-surface`, add card-horizontal layout, mark styling, search grid, responsive rules, suggestion, polish |
| `templates/components/search-result-card.html.twig` | Modify | OG image, horizontal layout, hide Page badge, source·date separator, highlight snippet, domain accent class |
| `templates/search.html.twig` | Modify | "Did you mean" slot, search grid class adjustment |

---

### Task 1: Hide "Page" Badge & Source·Date Separator (#510, #511)

**Files:**
- Modify: `templates/components/search-result-card.html.twig`

- [ ] **Step 1: Update badge rendering to hide "Page"**

In `search-result-card.html.twig`, wrap the type badge in a condition that excludes "page":

```twig
{% if badge_class != 'page' %}
  <span class="search-result__badge search-result__badge--{{ badge_class }}">{{ trans(badge_label_key) }}</span>
{% endif %}
```

- [ ] **Step 2: Add dot separator between source and date**

Replace the `card__meta` div:

```twig
<div class="card__meta">
  <span>{{ source_name|replace({'_': ' '}) }}</span>
  {% if crawled_at %}
    <span class="card__meta-sep" aria-hidden="true">·</span>
    <span class="card__date">{{ crawled_at[:10] }}</span>
  {% endif %}
</div>
```

- [ ] **Step 3: Add CSS for the separator**

In `minoo.css` inside `@layer components`, after the `.search-result-card__badges` rule:

```css
.card__meta-sep {
  color: var(--text-muted);
  margin-inline: var(--space-3xs);
}
```

- [ ] **Step 4: Add domain accent class to card**

Update the opening `<article>` tag to include a domain accent modifier:

```twig
<article class="card search-result-card card--{{ badge_class }}">
```

This activates the existing `--card-accent` custom property for the left border color.

- [ ] **Step 5: Visual check**

Run: `php -S localhost:8081 -t public` and visit `/search?q=powow`
Verify: "Page" badges gone, source · date separated, left border colors vary by type.

- [ ] **Step 6: Commit**

```bash
git add templates/components/search-result-card.html.twig public/css/minoo.css
git commit -m "feat(#510,#511): hide Page badge, add source·date separator, domain accent borders"
```

---

### Task 2: OG Images in Cards (#509)

**Files:**
- Modify: `templates/components/search-result-card.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add OG image markup**

After the opening `<article>` tag, wrap the card content in a horizontal layout container and add the image:

```twig
<article class="card search-result-card card--{{ badge_class }}">
  <div class="search-result-card__layout">
    {% if og_image is defined and og_image %}
      <div class="search-result-card__image">
        <img src="{{ og_image }}" alt="" loading="lazy" decoding="async">
      </div>
    {% endif %}
    <div class="search-result-card__content">
      {# ... existing badge row, title, meta, highlight ... #}
    </div>
  </div>
</article>
```

- [ ] **Step 2: Add horizontal card layout CSS**

In `minoo.css` `@layer components`, update search result card styles:

```css
.search-result-card__layout {
  display: flex;
  gap: var(--space-sm);
}

.search-result-card__image {
  flex-shrink: 0;
  width: 120px;
  aspect-ratio: 16 / 9;
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.search-result-card__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.search-result-card__content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3xs);
}

/* Hide OG image on mobile */
@media (max-width: 47.999em) {
  .search-result-card__image {
    display: none;
  }
}
```

- [ ] **Step 3: Visual check**

Visit `/search?q=powow` — cards with OG images show thumbnail on left, text on right. Cards without images show text-only (no gap or placeholder). Resize to mobile — images hidden.

- [ ] **Step 4: Commit**

```bash
git add templates/components/search-result-card.html.twig public/css/minoo.css
git commit -m "feat(#509): show OG images in search result cards with horizontal layout"
```

---

### Task 3: Highlight Snippets (#508)

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Define `--accent-surface` token**

In `minoo.css` `@layer tokens`, inside the `:root` or dark-mode block where the other `--accent`/`--color-*` tokens live, add:

```css
--accent-surface: oklch(from var(--accent) l c h / 0.1);
```

If `oklch(from ...)` isn't supported broadly enough, use a fallback:

```css
--accent-surface: rgba(42, 157, 143, 0.1);
```

- [ ] **Step 2: Style `<mark>` tags in search results**

In `@layer components`, add:

```css
.search-result-card mark {
  background: oklch(from var(--accent) l c h / 0.15);
  color: var(--color-language);
  padding-inline: 0.15em;
  border-radius: 2px;
}
```

- [ ] **Step 3: Limit snippet to 2 lines**

Add to the `.card__body` rule inside search context (or create a new rule):

```css
.search-result-card .card__body {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
```

- [ ] **Step 4: Visual check**

Search for a term that NC returns highlights for. Verify `<mark>` tags render with accent background. If no highlights come back from NC, verify the snippet area simply doesn't render (existing `{% if highlight %}` guard).

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#508): style highlight snippets with accent mark tags and line clamping"
```

---

### Task 4: Two-Column Grid (#513)

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Override card-grid for search results**

In `@layer components`, add:

```css
.search-results .card-grid {
  grid-template-columns: 1fr;
}

@media (min-width: 48em) {
  .search-results .card-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
```

- [ ] **Step 2: Remove max-inline-size constraint on search cards**

The existing `.search-result-card { max-inline-size: unset; }` should remain. Verify it's still present.

- [ ] **Step 3: Visual check**

Desktop: 2-column grid. Mobile: single column. Cards fill their column evenly.

- [ ] **Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#513): two-column search result grid on tablet and desktop"
```

---

### Task 5: "Did You Mean" Suggestion Slot (#512)

**Files:**
- Modify: `templates/search.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add suggestion template slot**

In `search.html.twig`, after the `search-active-filters` block and before `<div class="search-layout">`, add:

```twig
{% if results.suggestion is defined and results.suggestion %}
  <p class="search-suggestion">
    {{ trans('search.did_you_mean') }}
    <a href="{{ lang_url('/search') }}?q={{ results.suggestion|url_encode }}">{{ results.suggestion }}</a>?
  </p>
{% endif %}
```

- [ ] **Step 2: Add suggestion CSS**

```css
.search-suggestion {
  font-size: var(--text-sm);
  color: var(--text-secondary);
  font-style: italic;
}

.search-suggestion a {
  color: var(--color-language);
  font-style: normal;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 2px;
}
```

- [ ] **Step 3: Commit**

This is a template-only change. The backend doesn't return suggestions yet, so this slot is inert until NC/Waaseyaa wire it up.

```bash
git add templates/search.html.twig public/css/minoo.css
git commit -m "feat(#512): add 'did you mean' suggestion slot (template + CSS, backend pending)"
```

---

### Task 6: Responsive Layout Pass (#514)

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Mobile search form**

Ensure the search form fills width on mobile:

```css
@media (max-width: 47.999em) {
  .search-form {
    max-inline-size: none;
  }
}
```

- [ ] **Step 2: Mobile pagination touch targets**

```css
@media (max-width: 47.999em) {
  .pagination__link {
    min-height: 44px;
    min-width: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
}
```

- [ ] **Step 3: Sidebar filter stacking**

The existing `@media (min-width: 60em)` rule for `.search-layout` already handles this — sidebar is only a column at ≥60em, stacked below that. Verify this works correctly.

- [ ] **Step 4: Take responsive screenshots**

Use Playwright MCP to screenshot at 3 breakpoints:
- 375px wide (mobile)
- 768px wide (tablet)
- 1280px wide (desktop)

Save as `search-375.png`, `search-768.png`, `search-1280.png`.

- [ ] **Step 5: Fix any overflow or spacing issues found in screenshots**

- [ ] **Step 6: Commit**

```bash
git add public/css/minoo.css
git commit -m "fix(#514): responsive layout improvements for search page"
```

---

### Task 7: Final Polish Pass (#515)

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Audit spacing**

Check all search-related CSS uses design tokens consistently:
- Card padding: `var(--space-sm)`
- Card grid gap: `var(--space-sm)`
- Badge gaps: `var(--space-3xs)`
- Section gaps: `var(--space-md)` or `var(--space-lg)`
- Fix any hardcoded pixel values or undefined shorthand tokens (`--space-s`, `--space-m` etc.)

- [ ] **Step 2: Visual hierarchy check**

Verify heading sizes descend: h1 (Search) > scope badge > results summary > h3 card titles > snippet text > meta text. Adjust `font-size`, `font-weight`, or `color` if anything feels out of order.

- [ ] **Step 3: Pagination polish**

Center pagination, ensure consistent gap and border-radius:

```css
.pagination {
  justify-content: center;
  margin-block-start: var(--space-lg);
}
```

- [ ] **Step 4: Take before/after screenshots**

Screenshot the final state at 375px, 768px, 1280px. Compare visually with the screenshots from Task 6.

- [ ] **Step 5: Commit**

```bash
git add public/css/minoo.css
git commit -m "fix(#515): search page spacing and visual polish pass"
```

---

## Completion

After all 7 tasks:
- Close issues #508–#515 via PR or individually
- Take final screenshots and attach to the PR
- Update milestone #45 progress
