# Social Feed v1 Closeout Sprint

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the remaining 4 Social Feed v1 issues (#453, #454, #469, #399) and ship the milestone.

**Architecture:** Bug fixes and test consolidation — no new features. #453 adds a safety-net catch in EngagementController. #454 merges duplicate test files. #469 fixes Playwright CI failures. #399 verifies social feed e2e coverage.

**Tech Stack:** PHP 8.4, PHPUnit 10.5, Playwright, GitHub Actions CI

---

### Task 1: #453 — EngagementController catches InvalidArgumentException (#453)

**Context:** `SqlEntityStorage::create()` calls entity constructors via `new $class(values: $values)`, so constructor validation runs. But if a constructor throws `InvalidArgumentException`, it propagates as 500. The controller should catch it and return 422.

**Files:**
- Modify: `src/Controller/EngagementController.php`
- Modify: `tests/Minoo/Unit/Controller/EngagementControllerTest.php`

- [ ] **Step 1: Write failing test — react() catches InvalidArgumentException**

Add to `tests/Minoo/Unit/Controller/EngagementControllerTest.php`:

```php
#[Test]
public function react_catches_constructor_exception(): void
{
    $controller = $this->makeController();
    // Valid input that passes controller validation but would fail entity constructor
    // We need to mock EntityTypeManager to return a storage that throws
    // This requires refactoring makeController — see step 3
    $this->assertTrue(true); // placeholder
}
```

Actually — the Unit test creates a real `EngagementController` with a mocked `EntityTypeManager`. To test the catch, we need the mock storage's `create()` to throw. The Integration test already has `mockStorage()` helper for this. We'll add this test AFTER Task 2 merges the test files.

**Skip to Step 2.**

- [ ] **Step 2: Add try/catch around all `$storage->create()` calls**

In `src/Controller/EngagementController.php`, wrap each `create()`+`save()` pair. There are 4 methods: `react()`, `comment()`, `follow()`, `createPost()`.

Pattern for each:

```php
try {
    $entity = $storage->create([...]);
    $storage->save($entity);
} catch (\InvalidArgumentException $e) {
    return $this->json(['error' => $e->getMessage()], 422);
}
```

Apply to:
- `react()` (lines 42-49)
- `comment()` (lines 90-97)
- `follow()` (lines 169-175)
- `createPost()` (lines 212-218)

- [ ] **Step 3: Run PHPUnit to verify no regressions**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All tests pass (existing validation tests unaffected)

- [ ] **Step 4: Document in CLAUDE.md gotchas**

Add to CLAUDE.md gotchas section:

```
- **EntityStorage::create() calls constructors**: `SqlEntityStorage::instantiateEntity()` uses `new $class(values: $values)` — constructor validation IS invoked. EngagementController wraps create()+save() in try/catch for `InvalidArgumentException` as a safety net returning 422.
```

- [ ] **Step 5: Commit**

```bash
git add src/Controller/EngagementController.php CLAUDE.md
git commit -m "fix(#453): catch InvalidArgumentException from entity constructors in EngagementController"
```

---

### Task 2: #454 — Consolidate EngagementControllerTest into Unit/ (#454)

**Context:** Two test files exist:
- `tests/Minoo/Unit/Controller/EngagementControllerTest.php` (188 lines) — validation-only tests, simpler helpers
- `tests/Minoo/Integration/Controller/EngagementControllerTest.php` (354 lines) — validation + happy-path tests (create 201, delete 200/403/404), richer mock helpers (`mockStorage`, `mockEntity`)

Both use mocks (no real DB), so the Integration test is misplaced. Merge the Integration test's additional coverage into Unit, adopt its better mock helpers, delete the Integration copy.

**Files:**
- Modify: `tests/Minoo/Unit/Controller/EngagementControllerTest.php`
- Delete: `tests/Minoo/Integration/Controller/EngagementControllerTest.php`

- [ ] **Step 1: Replace Unit test with merged version**

The merged file should:
1. Keep namespace `Minoo\Tests\Unit\Controller`
2. Use the Integration test's `setUp()` + `mockAccount()` + `mockEntity()` + `mockStorage()` helpers (they're more capable)
3. Include ALL tests from both files (no duplicates — prefer the more thorough version when tests overlap)
4. Add the `InvalidArgumentException` catch test from Task 1

Tests to include (merged, deduplicated):
- `react_requires_valid_input` / `react_rejects_missing_fields` → keep the more descriptive one
- `react_rejects_invalid_target_type` (both have this — keep one)
- `react_rejects_invalid_reaction_type` (both have this — Integration version mocks storage)
- `comment_requires_valid_input` / `comment_rejects_missing_fields` → keep one
- `comment_rejects_invalid_target_type` (both have)
- `comment_rejects_body_too_long` / `comment_rejects_oversized_body` → keep one
- `follow_requires_valid_input` / `follow_rejects_invalid_target_type` (both have target_type)
- `follow_rejects_invalid_target_type` (both)
- `create_post_requires_valid_input` / `createPost_rejects_empty_body` → keep both (different scenarios)
- `createPost_rejects_oversized_body` / `create_post_rejects_body_too_long` → keep one
- `getComments_rejects_invalid_target_type` (Unit only — keep)
- **Happy path (Integration only — add all):**
  - `react_creates_reaction_and_returns_201`
  - `comment_creates_comment_and_returns_201`
  - `follow_creates_follow_and_returns_201`
  - `create_post_returns_201`
- **Delete/ownership (Integration only — add all):**
  - `delete_reaction_returns_200_for_owner`
  - `delete_reaction_returns_403_for_non_owner`
  - `delete_reaction_allowed_for_admin`
  - `delete_reaction_returns_404_when_not_found`
  - `delete_comment_returns_403_for_non_owner`
  - `delete_post_returns_403_for_non_owner`
- **New (from Task 1):**
  - `react_catches_constructor_invalid_argument_exception`

- [ ] **Step 2: Delete the Integration copy**

```bash
rm tests/Minoo/Integration/Controller/EngagementControllerTest.php
```

If the `tests/Minoo/Integration/Controller/` directory is now empty, remove it too.

- [ ] **Step 3: Run PHPUnit to verify**

Run: `./vendor/bin/phpunit --filter EngagementControllerTest`
Expected: All tests pass, test count increases (was ~12 in Unit, now ~22 merged)

- [ ] **Step 4: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass, total count decreases by the number of deleted Integration duplicates

- [ ] **Step 5: Commit**

```bash
git add tests/Minoo/Unit/Controller/EngagementControllerTest.php
git rm tests/Minoo/Integration/Controller/EngagementControllerTest.php
git commit -m "fix(#454): consolidate EngagementControllerTest into Unit/ with full coverage"
```

---

### Task 3: #469 — Fix Playwright CI failures (#469)

**Context:** 20+ Playwright tests fail in CI. The issue body lists failures in: `accessibility.spec.ts`, `homepage.spec.ts`, `legal.spec.ts`, `light-mode.spec.ts`, `location-bar.spec.ts`, `social-feed.spec.ts`. PHPUnit, PHPStan, Lint, Security all pass — only Playwright blocks deploy.

**Files:**
- Modify: `tests/playwright/accessibility.spec.ts`
- Modify: `tests/playwright/homepage.spec.ts`
- Modify: `tests/playwright/legal.spec.ts`
- Modify: `tests/playwright/light-mode.spec.ts`
- Modify: `tests/playwright/location-bar.spec.ts`
- Modify: `tests/playwright/social-feed.spec.ts`
- Possibly: `templates/base.html.twig`, `templates/feed.html.twig`, `public/css/minoo.css`

- [ ] **Step 1: Run Playwright locally to reproduce failures**

```bash
npx playwright test --reporter=list 2>&1 | tail -40
```

Identify which tests fail and WHY (missing selectors, wrong text, timing issues).

- [ ] **Step 2: Categorize failures**

Group failures by root cause:
- **Selector mismatch**: Test expects a selector that doesn't exist in current templates
- **Content mismatch**: Test expects text that changed
- **Timing/rendering**: Test doesn't wait for content
- **Feature not rendered in CI**: PHP dev server doesn't serve certain content

- [ ] **Step 3: Fix each category**

For selector mismatches: Update test selectors to match current template output.
For content mismatches: Update expected text to match current content.
For timing: Add `waitForSelector` or `waitForLoadState`.
For CI rendering: If templates require seed data not present in CI, either add test fixtures or mark tests as requiring a running app.

- [ ] **Step 4: Run Playwright again to verify all pass**

```bash
npx playwright test --reporter=list
```

Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add tests/playwright/
git commit -m "fix(#469): fix Playwright CI failures — update selectors and expectations for Social Feed v1"
```

If template/CSS changes were needed:
```bash
git add tests/playwright/ templates/ public/css/
git commit -m "fix(#469): fix Playwright CI failures — align templates and tests for Social Feed v1"
```

---

### Task 4: #399 — Verify Playwright e2e tests for social feed (#399)

**Context:** `tests/playwright/social-feed.spec.ts` already exists with basic coverage (layout, filter chips, responsive). After Task 3 fixes CI, verify this test covers the acceptance criteria and close the issue.

**Files:**
- Review: `tests/playwright/social-feed.spec.ts`

- [ ] **Step 1: Review existing social-feed.spec.ts coverage**

Check that these scenarios are covered:
- Three-column layout renders
- Feed cards have content
- Filter chips visible and clickable
- Responsive: sidebars hide at breakpoints
- Left sidebar navigation works

- [ ] **Step 2: Run social-feed tests specifically**

```bash
npx playwright test social-feed --reporter=list
```

Expected: All pass (after Task 3 fixes)

- [ ] **Step 3: Close the issue**

If coverage is sufficient and tests pass:
```bash
gh issue close 399 -c "Social feed e2e tests passing — covers layout, filter chips, responsive breakpoints, sidebar nav."
```

If additional tests are needed, add them before closing.

- [ ] **Step 4: Final commit if tests were added**

```bash
git add tests/playwright/social-feed.spec.ts
git commit -m "test(#399): verify social feed Playwright e2e coverage"
```

---

## Completion

After all 4 tasks, close remaining issues and the milestone:

```bash
gh issue close 453 -c "EngagementController now catches InvalidArgumentException from entity constructors, returns 422."
gh issue close 454 -c "Consolidated into Unit/ with full coverage — validation, happy path, delete ownership, admin override."
gh issue close 469 -c "All Playwright tests passing in CI."
gh issue close 399 -c "Social feed e2e tests verified and passing."
gh issue close 417 -c "Server-side SSH key config — resolved separately." # if applicable
```

Then verify milestone can be closed:
```bash
gh api repos/{owner}/{repo}/milestones/40 --jq '{title, open_issues, closed_issues}'
```
