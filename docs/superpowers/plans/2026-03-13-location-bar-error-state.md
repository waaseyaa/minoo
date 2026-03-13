# Location Bar Error State Fix — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the infinite "Detecting location…" state when geolocation is denied, times out, or unavailable — replacing it with a clickable "Set your location" prompt.

**Architecture:** Progressive enhancement. The template renders a safe default ("Set your location"), JS upgrades to "Detecting location…" only during active geolocation, and the error handler falls back to the safe default. A `<noscript>` tag ensures no-JS users see a usable state.

**Tech Stack:** Twig templates, vanilla JS, CSS, Playwright

**Issue:** [#133](https://github.com/waaseyaa/minoo/issues/133)
**Branch:** `fix/133-location-bar-error-state` (from `release/v1`)

---

## Housekeeping: Close already-implemented v0.14 issues

Before starting, close issues #139, #140, #141 which are already implemented:

```bash
gh issue close 139 --comment "Already implemented — hero copy, CTAs, and Explore Minoo descriptions all match acceptance criteria."
gh issue close 140 --comment "Already implemented — all 5 listing pages have subtitles aligned with narrative pillars."
gh issue close 141 --comment "Already implemented — Elder Support opens with values statement; Volunteer 'Why Volunteer?' section acknowledges what volunteers receive."
```

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `templates/components/location-bar.html.twig` | Change default text, add noscript fallback |
| Modify | `templates/base.html.twig` | Fix JS error handler, add "Detecting…" state during geolocation |
| Modify | `tests/playwright/location-bar.spec.ts` | Add error state and no-cookie default tests |

No new files. No CSS changes needed — the existing `.location-bar__text` and `.location-bar__toggle` styles already handle both states correctly via the `render()` function.

---

## Task 1: Fix default template text and add noscript fallback

**Files:**
- Modify: `templates/components/location-bar.html.twig`

The current template hardcodes "Detecting location…" as the default text. This is wrong because:
- If JS is disabled, the user sees "Detecting…" forever
- Even with JS, the text flashes "Detecting…" before `render(readCookie())` executes

The fix: default to "Set your location" (the safe fallback). JS will upgrade this to "Detecting…" only when geolocation is actively running.

- [ ] **Step 1: Change default text from "Detecting location…" to "Set your location"**

In `templates/components/location-bar.html.twig`, change line 6:

```html
<!-- Before -->
<span class="location-bar__text" id="location-text">Detecting location&hellip;</span>

<!-- After -->
<span class="location-bar__text" id="location-text">Set your location</span>
```

- [ ] **Step 2: Hide the "Change" button by default**

The "Change" button only makes sense when a location is already set. Default it to empty so it doesn't show alongside "Set your location":

```html
<!-- Before -->
<button class="location-bar__toggle" id="location-toggle" type="button" aria-expanded="false" aria-label="Change location">Change</button>

<!-- After -->
<button class="location-bar__toggle" id="location-toggle" type="button" aria-expanded="false" aria-label="Change location"></button>
```

- [ ] **Step 3: Add noscript fallback**

After the closing `</div>` of `location-bar__inner` (line 8), before the dropdown div, add:

```html
<noscript>
  <p class="location-bar__noscript">Location features require JavaScript.</p>
</noscript>
```

- [ ] **Step 4: Commit**

```bash
git add templates/components/location-bar.html.twig
git commit -m "fix(#133): Default location bar to 'Set your location' with noscript fallback"
```

---

## Task 2: Fix JavaScript geolocation error handling

**Files:**
- Modify: `templates/base.html.twig` (lines 193-207)

The current JS has two problems:
1. The geolocation error callback is `() => {}` — a silent no-op
2. There's no "Detecting…" state shown during the geolocation request

- [ ] **Step 1: Add "Detecting location…" state before geolocation request**

In `templates/base.html.twig`, find the geolocation block (line 194-206). Change from:

```javascript
    if ((!loc || loc.source === 'ip' || loc.source === 'none') && navigator.geolocation && !sessionStorage.getItem('minoo_geo_asked')) {
      sessionStorage.setItem('minoo_geo_asked', '1');
      navigator.geolocation.getCurrentPosition(async (pos) => {
```

To:

```javascript
    if ((!loc || loc.source === 'ip' || loc.source === 'none') && navigator.geolocation && !sessionStorage.getItem('minoo_geo_asked')) {
      sessionStorage.setItem('minoo_geo_asked', '1');
      textEl.textContent = 'Detecting location\u2026';
      navigator.geolocation.getCurrentPosition(async (pos) => {
```

This shows "Detecting location…" ONLY while geolocation is in progress — not as the static default.

- [ ] **Step 2: Fix the error callback to show "Set your location"**

Change the error callback from:

```javascript
      }, () => {}, {timeout: 10000, maximumAge: 300000});
```

To:

```javascript
      }, () => { render(null); }, {timeout: 10000, maximumAge: 300000});
```

`render(null)` already handles this correctly — it sets text to "Set your location" and clears the toggle button.

- [ ] **Step 3: Handle the case where geolocation succeeds but no nearby community is found**

The success handler already handles this — if `data.success` is false, `render()` is never called and the text stays at "Detecting…". Add a fallback:

Change from:

```javascript
          if (data.success) render({communityName: data.communityName, source: 'browser'});
        } catch {}
```

To:

```javascript
          if (data.success) {
            render({communityName: data.communityName, source: 'browser'});
          } else {
            render(null);
          }
        } catch { render(null); }
```

- [ ] **Step 4: Verify cookie-first rendering still works**

Line 130 (`render(readCookie())`) runs immediately on page load. Since the template now defaults to "Set your location", this will:
- If cookie exists: immediately render "Near CommunityName" — no flash of "Detecting…"
- If no cookie: keep "Set your location" (correct default)

No change needed — just verify this logic is intact.

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig
git commit -m "fix(#133): Handle geolocation denial/timeout with 'Set your location' fallback"
```

---

## Task 3: Add Playwright tests for error states

**Files:**
- Modify: `tests/playwright/location-bar.spec.ts`

- [ ] **Step 1: Add test for default state without geolocation**

Playwright runs in Chromium which supports geolocation but doesn't grant it by default. Without explicit `context.grantPermissions(['geolocation'])`, the browser denies geolocation — which is exactly our error case.

Add to the existing `test.describe('Location Bar', ...)` block:

```typescript
  test('shows "Set your location" when no cookie and geolocation unavailable', async ({ page }) => {
    // Clear any location cookie
    await page.context().clearCookies();
    await page.goto('/');

    // Wait for JS to execute and geolocation to be denied/timeout
    const locationText = page.locator('#location-text');
    // Should eventually show "Set your location" (not stuck on "Detecting…")
    await expect(locationText).toHaveText('Set your location', { timeout: 15000 });
  });
```

- [ ] **Step 2: Add test for cookie-based immediate render**

```typescript
  test('renders community name immediately from cookie', async ({ page }) => {
    // Set a location cookie before navigating
    await page.context().addCookies([{
      name: 'minoo_location',
      value: encodeURIComponent(JSON.stringify({
        communityName: 'Thunder Bay',
        communityId: 'test-id',
        latitude: 48.38,
        longitude: -89.25,
        source: 'manual'
      })),
      domain: 'localhost',
      path: '/'
    }]);
    await page.goto('/');

    const locationText = page.locator('#location-text');
    await expect(locationText).toHaveText('Near Thunder Bay');
  });
```

- [ ] **Step 3: Add test for "Set your location" click opens dropdown**

```typescript
  test('"Set your location" text opens dropdown when clicked', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/');

    const locationText = page.locator('#location-text');
    await expect(locationText).toHaveText('Set your location', { timeout: 15000 });

    await locationText.click();
    await expect(page.locator('#location-dropdown')).toBeVisible();
    await expect(page.locator('#location-search')).toBeFocused();
  });
```

- [ ] **Step 4: Run Playwright tests**

```bash
npx playwright test tests/playwright/location-bar.spec.ts --reporter=list
```

Expected: All 7 tests pass (4 existing + 3 new).

- [ ] **Step 5: Commit**

```bash
git add tests/playwright/location-bar.spec.ts
git commit -m "test(#133): Playwright tests for location bar error state and cookie rendering"
```

---

## Task 4: Full verification and PR

- [ ] **Step 1: Run PHPUnit**

```bash
./vendor/bin/phpunit
```

Expected: 302+ tests pass, 0 failures. (No PHP changes, but verify no regressions.)

- [ ] **Step 2: Run full Playwright suite**

```bash
npx playwright test --reporter=list
```

Expected: All tests pass.

- [ ] **Step 3: Visual verification with Playwright MCP**

Use Playwright MCP to:
1. Navigate to homepage with cookies cleared — verify "Set your location" shows (not "Detecting…")
2. Navigate to homepage with a location cookie set — verify "Near CommunityName" shows immediately

- [ ] **Step 4: Create PR**

```bash
gh pr create --base release/v1 --title "fix(#133): Location bar error state when geolocation fails" --body "$(cat <<'EOF'
## Summary
- Default location bar text changed from "Detecting location…" to "Set your location"
- Geolocation error/timeout now falls back to "Set your location" instead of hanging
- Cookie-based location renders immediately without waiting for geolocation
- Added `<noscript>` fallback for no-JS users
- "Detecting location…" now shown only during active geolocation request

## Acceptance Criteria
- [x] Geolocation denial/timeout shows "Set your location" prompt
- [x] No infinite "Detecting…" state under any condition
- [x] Cookie-based location renders immediately without waiting for geolocation
- [x] `<noscript>` fallback for no-JS users
- [x] Playwright test: verify location bar renders without geolocation

Closes #133

## Test Plan
- [ ] PHPUnit: all tests pass
- [ ] Playwright: location-bar.spec.ts (7 tests) all pass
- [ ] Visual: homepage with cleared cookies shows "Set your location"
- [ ] Visual: homepage with location cookie shows "Near CommunityName"

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
