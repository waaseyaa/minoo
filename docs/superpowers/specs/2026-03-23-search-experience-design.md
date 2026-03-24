# Search Experience Design

**Date:** 2026-03-23
**Milestone:** Search Experience (#45)
**Issues:** #508–#515

## Problem

The search results page at `/search?q=` is functional but visually flat. Cards lack context (no snippets, no images), the layout wastes space on wide screens, and there's no spelling assistance. The page needs a design pass to become a genuinely useful discovery surface.

## Design

### 1. Search Result Card Redesign

**Current:** Badge row → title link → source + date (single line)

**New layout:**

```
┌──────────────────────────────────────────────────┐
│ ┌────────┐  [Event] lifestyle · education        │
│ │ OG img │  Title Link Here                      │
│ │ 120×68 │  Snippet with <mark>matched</mark>    │
│ │        │  terms highlighted...                  │
│ └────────┘  Nipissing First Nation · 2026-02-24   │
└──────────────────────────────────────────────────┘
```

**Rules:**
- OG image: 120px wide, 16:9 aspect, `object-fit: cover`, `border-radius: var(--radius-sm)`, lazy-loaded via `loading="lazy"`
- When no OG image: card collapses to text-only (no placeholder)
- Highlight snippet: max 2 lines, `var(--text-secondary)`, `<mark>` tags styled with `background: oklch(from var(--accent) l c h / 0.15); color: var(--accent)`
- **Note:** `--accent-surface` is used in existing CSS but never defined. Define it in `@layer tokens` during implementation: `--accent-surface: oklch(from var(--accent) l c h / 0.1)`
- When no highlight: snippet row hidden (no empty space)
- "Page" badge hidden — only show badges for typed content (event, teaching, group, person, business, community)
- Source and date separated by ` · ` (middle dot)

### 2. Two-Column Grid (#513)

**Breakpoints:**
- `< 48em` (mobile): single column
- `≥ 48em` (tablet): 2-column grid
- `≥ 60em` with sidebar: 2-column grid in the results area (sidebar gets its own column via existing `search-layout` grid)

**CSS:** Update `.card-grid` within `.search-results` to use `grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr))`.

### 3. "Did You Mean" Suggestion (#512)

**Template slot** above results:

```html
{% if suggestion %}
  <p class="search-suggestion">
    Did you mean: <a href="/search?q={{ suggestion }}">{{ suggestion }}</a>?
  </p>
{% endif %}
```

**Styling:** `var(--text-secondary)` with the link in `var(--accent)`. Subtle, not intrusive.

**Backend dependency:** Requires `SearchResult` to carry a `suggestion` field from ES suggest API. For now, wire the template slot and CSS. The NC/Waaseyaa search provider will need to pass suggestions when available.

### 4. Responsive Layout (#514)

| Element | Mobile (<48em) | Tablet (48–60em) | Desktop (≥60em) |
|---------|---------------|-------------------|-----------------|
| Search form | Full width, stacked button below on very small screens | Inline, max-width prose | Inline, max-width prose |
| Filters sidebar | Stacked above results | Stacked above results | Left column (16rem) |
| Result cards | Single column, text-only layout (image hidden or above) | 2-column, horizontal card | 2-column, horizontal card |
| Card OG image | Hidden (text-only cards) | Left side, 120px | Left side, 120px |
| Pagination | Full width, larger touch targets (44px min) | Centered, normal size | Centered, normal size |
| Location bar | Full width | Full width | Full width |

### 5. Polish Pass (#515)

After all features land:
- Audit all spacing against token scale (`--space-2xs` through `--space-xl`)
- Ensure consistent `gap` values in card grid, badge row, meta row
- Verify visual hierarchy: h1 > scope badge > results summary > card titles > snippets > meta
- Check card border, shadow, and background consistency with rest of site
- Pagination alignment and visual weight
- Before/after screenshots at 375px, 768px, 1280px

## Implementation Order

1. **#510** Hide "Page" badge + **#511** source/date separator (quick template fixes)
2. **#509** OG images in cards (template + CSS)
3. **#508** Highlight snippets (template + investigate NC)
4. **#513** 2-column grid (CSS)
5. **#512** "Did you mean" slot (template + CSS, backend deferred)
6. **#514** Responsive layout pass (CSS, screenshots)
7. **#515** Final polish (CSS, before/after screenshots)

## Files Modified

- `templates/components/search-result-card.html.twig` — card markup
- `templates/search.html.twig` — suggestion slot, layout structure
- `public/css/minoo.css` — all styling changes
- `src/Search/NorthCloudSearchProvider.php` — investigate highlight/suggestion passthrough

## Out of Scope

- NC Elasticsearch configuration changes (separate repo)
- Facet sidebar population (depends on NC returning facets)
- Search analytics or tracking
