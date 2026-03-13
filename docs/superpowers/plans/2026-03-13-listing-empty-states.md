# Listing Page Empty States — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace bare "Check back soon" paragraphs on all listing pages with warm, mission-aligned empty states that follow the Show → Tell → Invite philosophy.

**Architecture:** Reusable `.empty-state` CSS component + per-page Twig markup with contextual copy and action links. No backend changes.

**Tech Stack:** Twig templates, CSS (design tokens), Playwright

**Issue:** [#211](https://github.com/waaseyaa/minoo/issues/211)
**Branch:** `feat/211-listing-empty-states` (from `release/v1`)

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `public/css/minoo.css` | Add `.empty-state` component in `@layer components` |
| Create | `templates/components/empty-state.html.twig` | Reusable empty-state partial |
| Modify | `templates/events.html.twig` | Replace bare `<p>` with empty-state component |
| Modify | `templates/groups.html.twig` | Replace bare `<p>` with empty-state component |
| Modify | `templates/teachings.html.twig` | Replace bare `<p>` with empty-state component |
| Modify | `templates/language.html.twig` | Replace bare `<p>` with empty-state component |
| Modify | `templates/people.html.twig` | Replace bare `<p>` with empty-state component |
| Create | `tests/playwright/empty-states.spec.ts` | Verify empty-state rendering |

---

## Task 1: Create the `.empty-state` CSS component

**Files:**
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add `.empty-state` styles in `@layer components`**

Add after the `.safety-callout` block (around line 1902), before `/* Skip link */`:

```css
  /* Empty state — shown when a listing has no items */
  .empty-state {
    padding: var(--space-md) var(--space-lg);
    background-color: var(--color-earth-50);
    border-radius: var(--radius-md);
    border-inline-start: 4px solid var(--color-earth-200);
    text-align: start;
  }

  .empty-state__heading {
    font-size: var(--step-1);
    font-weight: 600;
    color: var(--text-primary);
    margin-block-end: var(--space-2xs);
  }

  .empty-state__body {
    color: var(--text-secondary);
    margin-block-end: var(--space-sm);
  }

  .empty-state__action {
    display: inline-block;
    font-weight: 500;
    color: var(--link);
  }
```

- [ ] **Step 2: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#211): Add .empty-state CSS component"
```

---

## Task 2: Create reusable empty-state Twig component

**Files:**
- Create: `templates/components/empty-state.html.twig`

- [ ] **Step 1: Create the component**

```twig
<div class="empty-state">
  <p class="empty-state__heading">{{ heading }}</p>
  <p class="empty-state__body">{{ body }}</p>
  {% if action_url is defined and action_label is defined %}
    <a href="{{ action_url }}" class="empty-state__action">{{ action_label }} →</a>
  {% endif %}
</div>
```

- [ ] **Step 2: Commit**

```bash
git add templates/components/empty-state.html.twig
git commit -m "feat(#211): Add reusable empty-state Twig component"
```

---

## Task 3: Update Events empty state

**Files:**
- Modify: `templates/events.html.twig` (line ~32-34)

- [ ] **Step 1: Replace the bare `<p>` in the else branch**

Change from:

```twig
      {% else %}
        <p>No events scheduled yet. Check back soon.</p>
      {% endif %}
```

To:

```twig
      {% else %}
        {% include "components/empty-state.html.twig" with {
          heading: "No events scheduled yet",
          body: "Powwows, gatherings, ceremonies, and community events will appear here as they're added. In the meantime, explore what's happening in your community.",
          action_url: "/communities",
          action_label: "Explore communities"
        } %}
      {% endif %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/events.html.twig
git commit -m "feat(#211): Improve events listing empty state"
```

---

## Task 4: Update Groups empty state

**Files:**
- Modify: `templates/groups.html.twig` (line ~31-33)

- [ ] **Step 1: Replace the bare `<p>` in the else branch**

Change from:

```twig
      {% else %}
        <p>No groups listed yet. Check back soon.</p>
      {% endif %}
```

To:

```twig
      {% else %}
        {% include "components/empty-state.html.twig" with {
          heading: "No groups listed yet",
          body: "Community groups, cultural organizations, and youth programs will appear here as they're registered. Explore other ways to connect in the meantime.",
          action_url: "/communities",
          action_label: "Explore communities"
        } %}
      {% endif %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/groups.html.twig
git commit -m "feat(#211): Improve groups listing empty state"
```

---

## Task 5: Update Teachings empty state

**Files:**
- Modify: `templates/teachings.html.twig` (line ~32-34)

- [ ] **Step 1: Replace the bare `<p>` in the else branch**

Change from:

```twig
      {% else %}
        <p>No teachings available yet. Check back soon.</p>
      {% endif %}
```

To:

```twig
      {% else %}
        {% include "components/empty-state.html.twig" with {
          heading: "No Teachings shared yet",
          body: "Living knowledge — culture, history, and language — will be shared here by Knowledge Keepers and community members. This space is growing.",
          action_url: "/language",
          action_label: "Explore the language collection"
        } %}
      {% endif %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/teachings.html.twig
git commit -m "feat(#211): Improve teachings listing empty state"
```

---

## Task 6: Update Language empty state

**Files:**
- Modify: `templates/language.html.twig` (line ~32-34)

- [ ] **Step 1: Replace the bare `<p>` in the else branch**

Change from:

```twig
      {% else %}
        <p>No dictionary entries available yet. Check back soon.</p>
      {% endif %}
```

To:

```twig
      {% else %}
        {% include "components/empty-state.html.twig" with {
          heading: "No dictionary entries yet",
          body: "Anishinaabemowin words, meanings, and example sentences will appear here as speakers and language learners contribute. Language carries a worldview that translation alone cannot.",
          action_url: "/teachings",
          action_label: "Explore Teachings"
        } %}
      {% endif %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/language.html.twig
git commit -m "feat(#211): Improve language listing empty state"
```

---

## Task 7: Update People empty state

**Files:**
- Modify: `templates/people.html.twig` (line ~75-77)

- [ ] **Step 1: Replace the bare `<p>` in the else branch**

Change from:

```twig
      {% else %}
        <p>No community members found.</p>
      {% endif %}
```

To:

```twig
      {% else %}
        {% include "components/empty-state.html.twig" with {
          heading: "No community members listed yet",
          body: "Elders, Knowledge Keepers, language speakers, makers, and community leaders will appear here as people create profiles. The people who carry our communities forward deserve to be seen.",
          action_url: "/elders/volunteer",
          action_label: "Volunteer to support an Elder"
        } %}
      {% endif %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/people.html.twig
git commit -m "feat(#211): Improve people listing empty state"
```

---

## Task 8: Playwright tests for empty states

**Files:**
- Create: `tests/playwright/empty-states.spec.ts`

The listing pages currently have seeded data, so empty states won't appear in normal browsing. However, we can verify the empty-state component renders correctly by testing a page where the listing is guaranteed empty — or we can test the component markup exists in the template and renders when included.

Since the test database has seeded communities but may not have events/groups/teachings/language entries, some pages may already show empty states. We test whichever pages are empty, and add a structural test for the component.

- [ ] **Step 1: Create the test file**

```typescript
import { test, expect } from '@playwright/test';

test.describe('Empty state component', () => {
  // Test structural rendering on pages that may have no data.
  // In the test environment, not all entity types have seeded data.

  test('events page renders empty state or card grid', async ({ page }) => {
    await page.goto('/events');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    // One or the other must be present
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__body')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('groups page renders empty state or card grid', async ({ page }) => {
    await page.goto('/groups');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('teachings page renders empty state or card grid', async ({ page }) => {
    await page.goto('/teachings');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('language page renders empty state or card grid', async ({ page }) => {
    await page.goto('/language');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('people page renders empty state or card grid', async ({ page }) => {
    await page.goto('/people');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('empty state action links are valid', async ({ page }) => {
    // Check any page that shows an empty state has working action links
    await page.goto('/events');
    const emptyState = page.locator('.empty-state');

    if (await emptyState.count() > 0) {
      const actionLink = emptyState.locator('.empty-state__action');
      const href = await actionLink.getAttribute('href');
      expect(href).toBeTruthy();
      // Verify the link target loads (not a 404)
      const response = await page.goto(href!);
      expect(response?.status()).toBeLessThan(400);
    }
  });
});
```

- [ ] **Step 2: Run tests**

```bash
npx playwright test tests/playwright/empty-states.spec.ts --reporter=list
```

Expected: All 6 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/playwright/empty-states.spec.ts
git commit -m "test(#211): Playwright tests for listing page empty states"
```

---

## Task 9: Full verification and PR

- [ ] **Step 1: Run PHPUnit**

```bash
./vendor/bin/phpunit
```

Expected: 316+ tests pass (no PHP changes, but verify no regressions).

- [ ] **Step 2: Run full Playwright suite**

```bash
npx playwright test --reporter=list
```

Expected: All tests pass.

- [ ] **Step 3: Create PR**

```bash
gh pr create --base release/v1 --title "feat(#211): Improve empty-state messaging on all listing pages" --body "$(cat <<'EOF'
## Summary
- Created reusable `.empty-state` CSS component (warm earth tones, border accent, action link)
- Created `empty-state.html.twig` partial with heading, body, and action link
- Updated 5 listing pages with mission-aligned empty-state copy:
  - Events: invites exploration of communities
  - Groups: invites exploration of communities
  - Teachings: links to language collection
  - Language: links to Teachings
  - People: links to Elder volunteer signup
- Copy follows Show → Tell → Invite philosophy and content tone guide

Closes #211

## Test Plan
- [ ] PHPUnit: all tests pass (no PHP changes)
- [ ] Playwright: empty-states.spec.ts — 6 tests pass
- [ ] Visual: listing pages show styled empty states when no data present

**Plan:** `docs/superpowers/plans/2026-03-13-listing-empty-states.md`

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
