# Elder Support Launch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the Elder Support landing page into site navigation, add OG meta for Facebook sharing, generate an OG image, and draft a Facebook coordinator call-out post.

**Architecture:** All changes are additive: nav template edits, Twig block overrides, i18n key additions, one new image file. No controllers, routes, CSS, or entities change.

**Tech Stack:** Twig templates, PHP i18n arrays, HTML/CSS OG image template + Playwright screenshot

---

### Task 1: Add Elder Support Program link to sidebar nav

**Files:**
- Modify: `templates/components/sidebar-nav.html.twig:44-54`

- [ ] **Step 1: Add the `/elders` landing page link and fix active states**

Replace the entire Programs group (lines 44-54) with three links. The new `/elders` link goes first, followed by the relabeled request and volunteer links. Each link gets a precise active state check.

In `templates/components/sidebar-nav.html.twig`, replace:

```twig
  <div class="sidebar-nav__group">
    <h4 class="sidebar-nav__heading">{{ trans('sidebar.programs') }}</h4>
    <a href="{{ lang_url('/elders/request') }}" class="sidebar-nav__item{% if current_path is defined and current_path starts with '/elders' %} sidebar-nav__item--active{% endif %}">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 00-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>
      {{ trans('nav.elder_support') }}
    </a>
    <a href="{{ lang_url('/elders/volunteer') }}" class="sidebar-nav__item{% if current_path is defined and current_path starts with '/volunteer' %} sidebar-nav__item--active{% endif %}">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      {{ trans('nav.volunteer') }}
    </a>
  </div>
```

With:

```twig
  <div class="sidebar-nav__group">
    <h4 class="sidebar-nav__heading">{{ trans('sidebar.programs') }}</h4>
    <a href="{{ lang_url('/elders') }}" class="sidebar-nav__item{% if current_path is defined and current_path == '/elders' %} sidebar-nav__item--active{% endif %}">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      {{ trans('nav.elder_program') }}
    </a>
    <a href="{{ lang_url('/elders/request') }}" class="sidebar-nav__item{% if current_path is defined and current_path starts with '/elders/request' %} sidebar-nav__item--active{% endif %}">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 00-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>
      {{ trans('nav.request_help') }}
    </a>
    <a href="{{ lang_url('/elders/volunteer') }}" class="sidebar-nav__item{% if current_path is defined and current_path starts with '/elders/volunteer' %} sidebar-nav__item--active{% endif %}">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      {{ trans('nav.volunteer') }}
    </a>
  </div>
```

Key changes:
- New `/elders` link with community/users icon at top of group
- `/elders` active state uses exact match (`== '/elders'`) not prefix match
- `/elders/request` active state changed from `starts with '/elders'` to `starts with '/elders/request'`
- `/elders/volunteer` active state changed from `starts with '/volunteer'` to `starts with '/elders/volunteer'`
- Request link uses new `nav.request_help` trans key instead of `nav.elder_support`

- [ ] **Step 2: Verify the template renders**

Run: `php -S localhost:8081 -t public &`

Visit `http://localhost:8081/elders` in browser. Confirm:
- Three links appear under "Programs"
- "Elder Support Program" links to `/elders`
- "Request Help" links to `/elders/request`
- "Volunteer" links to `/elders/volunteer`

Kill the server after verification.

- [ ] **Step 3: Commit**

```bash
git add templates/components/sidebar-nav.html.twig
git commit -m "feat: add Elder Support Program link to sidebar nav

Add /elders landing page link above request/volunteer shortcuts.
Fix active state bugs on elder/volunteer nav items."
```

---

### Task 2: Update feed sidebar and i18n keys

**Files:**
- Modify: `templates/components/feed-sidebar-left.html.twig:27-30`
- Modify: `resources/lang/en.php:17`
- Modify: `resources/lang/oj.php:69`

- [ ] **Step 1: Update feed sidebar elder support link**

In `templates/components/feed-sidebar-left.html.twig`, replace:

```twig
    <a href="{{ lang_url('/elders') }}" class="sidebar-nav__item sidebar-nav__item--elders">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 00-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>
      {{ trans('nav.elder_support') }}
    </a>
```

With:

```twig
    <a href="{{ lang_url('/elders') }}" class="sidebar-nav__item sidebar-nav__item--elders">
      <svg class="sidebar-nav__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.24 12.24a6 6 0 00-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>
      {{ trans('nav.elder_program') }}
    </a>
```

This feed sidebar already links to `/elders` (the landing page), so we just update the trans key to match the new `nav.elder_program`.

- [ ] **Step 2: Update English i18n keys**

In `resources/lang/en.php`, find line 17:

```php
    'nav.elder_support' => 'Elder Support',
```

Replace with:

```php
    'nav.elder_program' => 'Elder Support Program',
    'nav.request_help' => 'Request Help',
```

Keep `'nav.volunteer' => 'Volunteer',` on the next line unchanged.

- [ ] **Step 3: Update Ojibwe i18n keys**

In `resources/lang/oj.php`, find line 69:

```php
    'nav.elder_support' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "an elder" + wiidookaagewin: "help, assistance"
```

Replace with:

```php
    'nav.elder_program' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "an elder" + wiidookaagewin: "help, assistance"
    'nav.request_help' => '', // TODO: Ojibwe translation needed
```

- [ ] **Step 4: Verify no remaining references to old key**

Run: `grep -r "nav\.elder_support" resources/ templates/`

Expected: no matches. If any remain, update them to `nav.elder_program` or `nav.request_help` as appropriate.

- [ ] **Step 5: Commit**

```bash
git add templates/components/feed-sidebar-left.html.twig resources/lang/en.php resources/lang/oj.php
git commit -m "feat: update i18n keys for Elder Support nav rename

Rename nav.elder_support to nav.elder_program (landing page)
and nav.request_help (request form). Update feed sidebar reference."
```

---

### Task 3: Add OG meta overrides to Elder Support landing page

**Files:**
- Modify: `templates/elders.html.twig:1-3`

- [ ] **Step 1: Add OG block overrides**

In `templates/elders.html.twig`, replace the opening lines:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('elders.title') }} — Minoo{% endblock %}
```

With:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('elders.title') }} — Minoo{% endblock %}
{% block og_title %}Elder Support Program — Minoo{% endblock %}
{% block og_description %}Rides, groceries, yard work, visits. Our Elders shouldn't have to ask twice. Request help or volunteer your time.{% endblock %}
{% block og_image %}https://minoo.live/img/og-elder-support.png{% endblock %}
```

- [ ] **Step 2: Verify OG tags render**

Run: `php -S localhost:8081 -t public &`

Run: `curl -s http://localhost:8081/elders | grep 'og:'`

Expected output should include:
```html
<meta property="og:title" content="Elder Support Program — Minoo">
<meta property="og:description" content="Rides, groceries, yard work, visits. Our Elders shouldn't have to ask twice. Request help or volunteer your time.">
<meta property="og:image" content="https://minoo.live/img/og-elder-support.png">
```

Kill the server after verification.

- [ ] **Step 3: Commit**

```bash
git add templates/elders.html.twig
git commit -m "feat: add OG meta overrides for Elder Support landing page

Custom title, description, and image for Facebook/social sharing."
```

---

### Task 4: Generate Elder Support OG image

**Files:**
- Create: `public/img/og-elder-support.png`

- [ ] **Step 1: Create OG image HTML template**

Create a temporary file `scripts/og-elder-support.html` with this content:

```html
<!DOCTYPE html>
<html>
<head>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    width: 1200px;
    height: 630px;
    background: #1a1a2e;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 80px;
    position: relative;
    overflow: hidden;
  }
  .logo {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 3px;
    color: #4ecdc4;
    margin-bottom: 16px;
  }
  .dots {
    display: flex;
    gap: 8px;
    margin-bottom: 40px;
  }
  .dots span {
    width: 12px;
    height: 12px;
    border-radius: 50%;
  }
  .dots span:nth-child(1) { background: #e74c6f; }
  .dots span:nth-child(2) { background: #f4a261; }
  .dots span:nth-child(3) { background: #4ecdc4; }
  .dots span:nth-child(4) { background: #7c6fe0; }
  .dots span:nth-child(5) { background: #4ecdc4; }
  h1 {
    font-size: 64px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.15;
    margin-bottom: 24px;
  }
  .tagline {
    font-size: 24px;
    color: #a0a0b8;
    line-height: 1.5;
  }
  .url {
    font-size: 18px;
    color: #4ecdc4;
    margin-top: 32px;
  }
  .arc {
    position: absolute;
    right: -80px;
    bottom: -80px;
    width: 400px;
    height: 400px;
    border: 2px solid rgba(78, 205, 196, 0.15);
    border-radius: 50%;
  }
</style>
</head>
<body>
  <div class="logo">MINOO</div>
  <div class="dots">
    <span></span><span></span><span></span><span></span><span></span>
  </div>
  <h1>Elder Support<br>Program</h1>
  <p class="tagline">Caring for our Elders, together</p>
  <p class="url">minoo.live/elders</p>
  <div class="arc"></div>
</body>
</html>
```

- [ ] **Step 2: Screenshot with Playwright**

Run:

```bash
npx playwright screenshot --viewport-size="1200,630" scripts/og-elder-support.html public/img/og-elder-support.png
```

If `npx playwright` is not available, install it first:

```bash
npx playwright install chromium
```

Then re-run the screenshot command.

- [ ] **Step 3: Verify the image**

Open `public/img/og-elder-support.png` and confirm:
- 1200x630 dimensions
- Dark background matching `og-default.png` style
- "MINOO" logo in teal
- Colored dots accent
- "Elder Support Program" headline in white
- "Caring for our Elders, together" tagline in gray
- "minoo.live/elders" URL in teal
- Subtle arc decoration

- [ ] **Step 4: Clean up and commit**

```bash
rm scripts/og-elder-support.html
git add public/img/og-elder-support.png
git commit -m "feat: add Elder Support OG image for social sharing

1200x630 PNG matching Minoo brand style for Facebook/social cards."
```

---

### Task 5: Draft Facebook coordinator call-out post

**Files:**
- Create: `docs/launch/2026-04-05-elder-support-facebook-post.md`

- [ ] **Step 1: Write the post draft**

Create `docs/launch/2026-04-05-elder-support-facebook-post.md`:

```markdown
# Elder Support Program — Facebook Post

**Status:** Draft — awaiting Russell's approval before posting
**Post from:** Russell's personal Facebook account
**Link:** https://minoo.live/elders

---

I've been building something for a while now and it's ready.

Minoo has an Elder Support program. If an Elder in our community needs a ride to an appointment, help with groceries, yard work, or just a visit, they (or a family member) can submit a request. Volunteers sign up with what they can offer and when they're available. A coordinator matches them up and follows through.

The forms are live. The workflow is built. What I need now is a coordinator. One person who knows our community, cares about our Elders, and wants to actually put these tools to use. You don't need to be technical. You just need to be willing to check in, match people up, and follow through.

If that's you, or you know who it should be, reach out. Or just go look at what's there:

https://minoo.live/elders
```

- [ ] **Step 2: Commit**

```bash
mkdir -p docs/launch
git add docs/launch/2026-04-05-elder-support-facebook-post.md
git commit -m "docs: draft Facebook post for Elder Support coordinator call-out

Draft for Russell's review before posting to personal Facebook."
```

---

### Task 6: Run tests and final verification

**Files:** None (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `./vendor/bin/phpunit`

Expected: All tests pass (no test changes in this plan, but verify nothing broke).

- [ ] **Step 2: Bump CSS cache version**

No CSS changes in this plan, so no cache bust needed. Skip this step.

- [ ] **Step 3: Final commit (if any fixups needed)**

If any tests failed or fixups were needed, commit them:

```bash
git add -A
git commit -m "fix: address test/lint issues from Elder Support launch changes"
```

If all clean, this step is a no-op.
