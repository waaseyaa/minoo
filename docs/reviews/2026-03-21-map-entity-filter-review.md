# Code Review: Map Entity Type Filter (#340)

**PRs reviewed:** #442 (CSS), #443 (Playwright), #444 (Controller + Template), #445 (JS refactor)
**Reviewer:** Claude Code (Senior Code Reviewer)
**Date:** 2026-03-21
**Plan:** `docs/superpowers/plans/2026-03-21-map-entity-filter.md`
**Spec:** `docs/superpowers/specs/2026-03-21-map-entity-filter-design.md`

---

## What Was Done Well

- Clean separation of concerns across 4 PRs (CSS / JS / PHP / tests)
- `toggleFilter()` in PR #445 uses a lookup table instead of nested if/else -- cleaner than the plan's version
- Playwright tests (PR #443) add graceful `test.skip()` guards for environments without seeded data
- CSS follows the project's `@layer` architecture with proper light-mode overrides and `prefers-color-scheme` fallback
- Template uses `JSON_HEX_TAG | JSON_HEX_AMP` flags for script-tag JSON injection prevention
- Accessibility: `role="group"`, `aria-label`, `aria-pressed`, native `<button>` elements

---

## Critical Issues (Must Fix)

### 1. Missing `businessCount` state in PR #445

**Files:** `public/js/atlas-discovery.js` (PR #445), `templates/communities/list.html.twig` (PR #444)

PR #444's template uses `x-show="businessCount > 0"` on the Businesses filter pill. However, PR #445's simplify pass removed `businessCount` from the Alpine data. The JS diff adds `allBusinesses: []` but never declares `businessCount` or sets it.

**Impact:** The Businesses pill will always be hidden because Alpine evaluates `businessCount` as `undefined`, which is falsy.

**Fix in PR #445 -- add to Alpine data block:**
```js
allBusinesses: [],
businessCount: 0,   // <-- add this line
```
**And in `init()` after loading `allBusinesses`:**
```js
this.allBusinesses = window.__atlas_businesses || [];
this.businessCount = this.allBusinesses.length;  // <-- add this line
```

### 2. Controller queries wrong field name (PR #444)

**File:** `src/Controller/CommunityController.php`

PR #444 uses `->condition('type', 'business')`. The plan specifies `->condition('group_type', 'business')`.

Looking at the entity definition: `Group` entity has `'bundle' => 'type'` in its keys, and `fieldDefinitions` includes a `'type'` field. However, this is the bundle key mapped to the `type` column -- it stores the bundle discriminator (e.g., `'community'`, `'business'`).

The plan's `->condition('group_type', 'business')` references a separate `group_type` config entity (id `group_type`, line 169 in GroupServiceProvider). That is a different entity type entirely, not a field on the `group` table.

**Verdict:** PR #444's `->condition('type', 'business')` is actually **correct** -- it queries the bundle column. The plan was wrong. However, verify this works against real data. The plan also had `->condition('status', 1)` first, while PR #444 has `->condition('type', 'business')` first -- order does not matter for query building, so this is fine.

**Action:** No code change needed, but update the plan document to match the implementation.

---

## Important Issues (Should Fix)

### 3. XSS in marker popups (PR #445)

**File:** `public/js/atlas-discovery.js`

Business and community names are interpolated directly into `.bindPopup()` HTML strings without escaping:
```js
.bindPopup('<strong>' + b.name + '</strong>' +
  (b.community_name ? '<br>' + b.community_name : ''));
```

If any business name contains `<script>` or HTML, it executes in the popup. The data comes from the controller's `json_encode()` which only escapes for script-tag context (`JSON_HEX_TAG`), not for HTML attribute/content context.

**Risk:** Low in practice (admin-controlled data), but a defense-in-depth gap.

**Recommendation:** Use Leaflet's text-safe API or add a simple escape helper:
```js
function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
```

### 4. Playwright tests only cover Communities pill (PR #443)

The tests assert on `.atlas-filter--communities` but never test the Businesses pill toggle. The spec says to "toggle businesses off/on, verify marker count changes." At minimum, add a test that checks `.atlas-filter--businesses` visibility when businesses exist.

---

## Suggestions (Nice to Have)

### 5. CSS version bump collision risk

PR #442 bumps `?v=11` to `?v=12` in `base.html.twig`. If any other PR also touches this line, merging will conflict. Consider bumping only in the last-merged PR, or using a content hash.

### 6. Template uses `String()` vs `.toString()` for aria-pressed

PR #444 uses `:aria-pressed="String(filters.communities)"` while the design spec uses `.toString()`. Both work, but `String()` is the safer choice (handles `undefined`). This is fine as-is -- just noting the divergence.

---

## Cross-PR Compatibility Summary

| PR pair | Compatible? | Notes |
|---------|-------------|-------|
| #444 + #445 | **NO** | `businessCount` missing from JS (Critical #1) |
| #444 + #442 | Yes | CSS classes match template classes |
| #444 + #443 | Yes | Playwright locators match template selectors |
| #445 + #442 | Yes | No direct interaction |
| #445 + #443 | Yes | Alpine behavior matches test assertions |
| #442 + #443 | Yes | No direct interaction |

---

## Merge Order Recommendation

1. **PR #442** (CSS) -- no dependencies
2. **PR #445** (JS) -- fix `businessCount` first, then merge
3. **PR #444** (Controller + Template) -- depends on #445 for Alpine state
4. **PR #443** (Playwright) -- merge last, tests validate the full stack

---

## Plan Deviation Summary

| Deviation | Beneficial or Problematic? |
|-----------|---------------------------|
| `->condition('type', ...)` vs plan's `group_type` | Beneficial -- plan was wrong, PR is correct |
| `businessCount` omitted from JS | Problematic -- breaks `x-show` binding |
| `toggleFilter` uses lookup table | Beneficial -- cleaner than plan's if/else |
| Playwright adds `test.skip` guards | Beneficial -- more robust in CI |
| `String()` vs `.toString()` | Neutral -- both correct |
