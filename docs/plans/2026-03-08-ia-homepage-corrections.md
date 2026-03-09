# v0.10 — IA & Homepage Corrections Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix IA/UX drift so Minoo presents as a multi-pillar community platform, not an Elder Support app.

**Architecture:** Template and CSS changes only. Restructure nav in `base.html.twig` with a Programs dropdown, redesign homepage sections in `page.html.twig`, expand `/elders` portal in `elders.html.twig`, and update Playwright tests. No PHP, no schema changes, no new controllers.

**Tech Stack:** Twig 3 templates, vanilla CSS (`@layer`), Playwright tests

---

### Task 1: Correct global navigation IA (#114)

**Files:**
- Modify: `templates/base.html.twig` (lines 18-39, nav structure + JS at line 64-68)
- Modify: `public/css/minoo.css` (lines 216-320, nav styles)
- Modify: `tests/playwright/homepage.spec.ts` (add nav test)

**Step 1: Create feature branch**

```bash
git checkout -b feature/v0.10-nav
```

**Step 2: Update nav structure in `base.html.twig`**

Replace lines 18-39 (the `<nav>` block) with hierarchical nav. Key changes:
- Reorder: Communities, People, Teachings, Events as primary items
- Remove: Groups, Language as standalone top-level items (they remain accessible via their URLs)
- Add: Programs dropdown containing Elder Support and Volunteer
- Move: Search and Login into a `site-nav__utility` group
- Add `aria-current` support for dropdown children

Replace the nav block with:

```twig
<nav id="main-nav" aria-label="Main">
  <ul class="site-nav">
    <li><a href="/communities"{% if path is defined and path starts with '/communities' %} aria-current="page"{% endif %}>Communities</a></li>
    <li><a href="/people"{% if path is defined and path starts with '/people' %} aria-current="page"{% endif %}>People</a></li>
    <li><a href="/teachings"{% if path is defined and path starts with '/teachings' %} aria-current="page"{% endif %}>Teachings</a></li>
    <li><a href="/events"{% if path is defined and path starts with '/events' %} aria-current="page"{% endif %}>Events</a></li>
    <li class="site-nav__dropdown">
      <button class="site-nav__dropdown-toggle" aria-expanded="false"{% if path is defined and (path starts with '/elders' or path starts with '/volunteer') %} aria-current="true"{% endif %}>Programs</button>
      <ul class="site-nav__dropdown-menu">
        <li><a href="/elders"{% if path is defined and path starts with '/elders' %} aria-current="page"{% endif %}>Elder Support</a></li>
        <li><a href="/volunteer"{% if path is defined and path starts with '/volunteer' %} aria-current="page"{% endif %}>Volunteer</a></li>
      </ul>
    </li>
    <li class="site-nav__utility"><a href="/search"{% if path is defined and path starts with '/search' %} aria-current="page"{% endif %}>Search</a></li>
    {% if account is defined and account.isAuthenticated() %}
      {% if 'elder_coordinator' in account.getRoles() %}
        <li class="site-nav__utility"><a href="/dashboard/coordinator"{% if path is defined and path starts with '/dashboard' %} aria-current="page"{% endif %}>Dashboard</a></li>
      {% elseif 'volunteer' in account.getRoles() %}
        <li class="site-nav__utility"><a href="/dashboard/volunteer"{% if path is defined and path starts with '/dashboard' %} aria-current="page"{% endif %}>My Dashboard</a></li>
      {% endif %}
      <li class="site-nav__utility"><a href="/logout">Logout</a></li>
    {% else %}
      <li class="site-nav__utility"><a href="/login"{% if path is defined and path == '/login' %} aria-current="page"{% endif %}>Login</a></li>
    {% endif %}
  </ul>
</nav>
```

**Step 3: Update nav toggle JS in `base.html.twig`**

Replace the nav toggle script (line 63-68) with updated JS that handles both the hamburger menu AND the Programs dropdown:

```html
<script>
  document.querySelector('.nav-toggle')?.addEventListener('click', function() {
    const nav = document.getElementById('main-nav')?.querySelector('.site-nav');
    const open = nav?.classList.toggle('is-open');
    this.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.querySelectorAll('.site-nav__dropdown-toggle').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const menu = this.nextElementSibling;
      const open = menu.hidden === undefined ? !menu.classList.contains('is-open') : menu.hidden;
      this.setAttribute('aria-expanded', open ? 'true' : 'false');
      menu.classList.toggle('is-open', open);
      if (!open) menu.classList.remove('is-open');
    });
  });
  document.addEventListener('click', function() {
    document.querySelectorAll('.site-nav__dropdown-menu.is-open').forEach(function(m) {
      m.classList.remove('is-open');
      m.previousElementSibling.setAttribute('aria-expanded', 'false');
    });
  });
</script>
```

**Step 4: Add dropdown CSS to `minoo.css`**

Add after the existing `.site-nav a` block (after line 239), within `@layer layout`:

```css
/* Nav dropdown */
.site-nav__dropdown {
  position: relative;
}

.site-nav__dropdown-toggle {
  padding: var(--space-3xs) var(--space-2xs);
  border-radius: var(--radius-sm);
  text-decoration: none;
  font-size: var(--text-sm);
  color: var(--text-secondary);
  background: none;
  border: none;
  cursor: pointer;
  font-family: inherit;
}

.site-nav__dropdown-toggle:hover {
  color: var(--text-primary);
  background-color: var(--accent-surface);
}

.site-nav__dropdown-toggle[aria-current="true"] {
  color: var(--accent);
  background-color: var(--accent-surface);
}

.site-nav__dropdown-toggle::after {
  content: " \25BE";
  font-size: 0.75em;
}

.site-nav__dropdown-menu {
  display: none;
  position: absolute;
  inset-block-start: 100%;
  inset-inline-start: 0;
  min-inline-size: 10rem;
  background-color: var(--surface-raised);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-md);
  padding: var(--space-3xs) 0;
  list-style: none;
  z-index: 10;
}

.site-nav__dropdown-menu.is-open {
  display: block;
}

.site-nav__dropdown-menu a {
  display: block;
  padding: var(--space-3xs) var(--space-sm);
  white-space: nowrap;
}

/* Utility nav items (Search, Login) — slightly muted */
.site-nav__utility {
  margin-inline-start: auto;
}

.site-nav__utility + .site-nav__utility {
  margin-inline-start: 0;
}
```

Add mobile override inside the `@media (max-width: 60em)` block (around line 289):

```css
.site-nav__dropdown-menu {
  position: static;
  box-shadow: none;
  border: none;
  padding-inline-start: var(--space-sm);
  background: transparent;
}

.site-nav__dropdown-menu.is-open {
  display: block;
}

.site-nav__utility {
  margin-inline-start: 0;
}
```

**Step 5: Update Playwright homepage test**

In `tests/playwright/homepage.spec.ts`, update the hero CTA test since the primary CTA will change (done in Task 2), and add a nav structure test:

```typescript
test('navigation has Programs dropdown', async ({ page }) => {
  await page.goto('/');
  const programsBtn = page.locator('.site-nav__dropdown-toggle');
  await expect(programsBtn).toHaveText(/Programs/);
  await programsBtn.click();
  await expect(page.locator('.site-nav__dropdown-menu')).toBeVisible();
  await expect(page.locator('.site-nav__dropdown-menu a[href="/elders"]')).toBeVisible();
  await expect(page.locator('.site-nav__dropdown-menu a[href="/volunteer"]')).toBeVisible();
});
```

**Step 6: Run tests**

```bash
./vendor/bin/phpunit
npx playwright test
```

Expected: All pass. PHPUnit tests don't touch nav. Playwright tests may need updates (done in step 5).

**Step 7: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css tests/playwright/homepage.spec.ts
git commit -m "feat(#114): correct global navigation IA with Programs dropdown"
```

---

### Task 2: Homepage hero and sections redesign (#115)

**Files:**
- Modify: `templates/page.html.twig` (full rewrite of content block)
- Modify: `tests/playwright/homepage.spec.ts` (update all hero/section tests)

**Step 1: Rewrite `page.html.twig`**

Replace the entire content block with:

```twig
{% extends "base.html.twig" %}

{% block title %}Minoo — A Living Map of Community{% endblock %}

{% block content %}
  <div class="flow-lg">
    <section class="hero">
      <h1 class="hero__title">Minoo: A Living Map of Community</h1>
      <p class="hero__subtitle">Discover communities, people, teachings, and programs that connect and support where you live.</p>
      <div class="hero__actions">
        <a href="/communities" class="btn btn--primary btn--lg">Explore Communities</a>
        <a href="/people" class="btn btn--secondary btn--lg">Browse Community Resources</a>
      </div>
    </section>

    {% if location is defined and location.hasLocation() %}
      <section class="portal-section flow">
        <div class="portal-section__header">
          <h2>Near {{ location.communityName }}</h2>
        </div>

        {% if nearby_communities is defined and nearby_communities|length > 0 %}
          <h3>Nearby Communities</h3>
          <div class="card-grid">
            {% for item in nearby_communities %}
              <a href="/communities/{{ item.community.get('slug') }}" class="card card--community">
                <div class="card__body">
                  <h4 class="card__title">{{ item.community.get('name') }}</h4>
                  <p class="card__meta">{{ item.distanceKm|round }} km away</p>
                </div>
              </a>
            {% endfor %}
          </div>
        {% endif %}

        {% if events is defined and events|length > 0 %}
          <h3>Upcoming Events</h3>
          <div class="card-grid">
            {% for event in events %}
              {% include "components/event-card.html.twig" with {event: event} %}
            {% endfor %}
          </div>
        {% endif %}

        <p><a href="/communities">View all communities</a> · <a href="/events">View all events</a></p>
      </section>
    {% endif %}

    <section class="portal-section">
      <h2>Explore Minoo</h2>
      <div class="card-grid">
        <a href="/communities" class="card">
          <div class="card__body">
            <h3 class="card__title">Communities</h3>
            <p class="card__meta">Find First Nations and municipalities across the region</p>
          </div>
        </a>
        <a href="/people" class="card">
          <div class="card__body">
            <h3 class="card__title">People</h3>
            <p class="card__meta">Community resource people, Elders, and knowledge keepers</p>
          </div>
        </a>
        <a href="/teachings" class="card">
          <div class="card__body">
            <h3 class="card__title">Teachings</h3>
            <p class="card__meta">Traditional knowledge and cultural resources</p>
          </div>
        </a>
        <a href="/events" class="card">
          <div class="card__body">
            <h3 class="card__title">Events</h3>
            <p class="card__meta">Community gatherings and cultural events</p>
          </div>
        </a>
        <a href="/elders" class="card">
          <div class="card__body">
            <h3 class="card__title">Elder Support Program</h3>
            <p class="card__meta">Request help for an Elder or volunteer your time</p>
          </div>
        </a>
      </div>
    </section>
  </div>
{% endblock %}
```

Key changes:
- Hero: "Minoo: A Living Map of Community" with platform subhead
- Primary CTAs: Explore Communities → /communities, Browse Community Resources → /people
- Removed "How It Works" section entirely (moved to /elders in Task 3)
- "Explore" section renamed to "Explore Minoo", now includes 5 cards (added Elder Support Program card)
- Location section unchanged

**Step 2: Rewrite `tests/playwright/homepage.spec.ts`**

```typescript
import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('shows hero with platform title and CTAs', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero__title')).toContainText('Minoo');
    await expect(page.locator('.hero__actions .btn--primary')).toHaveAttribute('href', '/communities');
    await expect(page.locator('.hero__actions .btn--secondary')).toHaveAttribute('href', '/people');
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
  });

  test('has Explore Minoo section with 5 cards', async ({ page }) => {
    await page.goto('/');
    const heading = page.getByRole('heading', { name: 'Explore Minoo' });
    await expect(heading).toBeVisible();
    const section = heading.locator('..');
    await expect(section.locator('.card-grid .card')).toHaveCount(5);
  });

  test('Explore section includes Elder Support card', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.card-grid a[href="/elders"] .card__title')).toContainText('Elder Support');
  });

  test('navigation has Programs dropdown', async ({ page }) => {
    await page.goto('/');
    const programsBtn = page.locator('.site-nav__dropdown-toggle');
    await expect(programsBtn).toHaveText(/Programs/);
    await programsBtn.click();
    await expect(page.locator('.site-nav__dropdown-menu')).toBeVisible();
    await expect(page.locator('.site-nav__dropdown-menu a[href="/elders"]')).toBeVisible();
    await expect(page.locator('.site-nav__dropdown-menu a[href="/volunteer"]')).toBeVisible();
  });
});
```

**Step 3: Run tests**

```bash
./vendor/bin/phpunit
npx playwright test
```

**Step 4: Commit**

```bash
git add templates/page.html.twig tests/playwright/homepage.spec.ts
git commit -m "feat(#115): homepage hero and sections redesign for Minoo-wide identity"
```

---

### Task 3: Move Elder Support "How It Works" to /elders (#116)

**Files:**
- Modify: `templates/elders.html.twig` (expand with How It Works from homepage)
- Modify: `tests/playwright/elders.spec.ts` (add How It Works test)

**Step 1: Rewrite `elders.html.twig`**

The /elders page already has a two-column how-it-works section. Add the 3-step summary (from the old homepage) as a prominent intro before the detailed for-elders/for-volunteers sections:

```twig
{% extends "base.html.twig" %}

{% block title %}Elder Support Program — Minoo{% endblock %}

{% block content %}
<section class="content-section flow-lg">
  <div class="portal-section">
    <h1>Elder Support Program</h1>
    <p class="text-secondary">A Minoo program connecting Elders with community volunteers for everyday help — rides, groceries, yard work, and friendly visits.</p>
  </div>

  <div class="portal-section flow">
    <h2>How It Works</h2>
    <div class="how-it-works">
      <div class="how-it-works__step flow">
        <h3>1. Request Help</h3>
        <p>An Elder or someone on their behalf submits a request — a ride, help with groceries, yard work, or a friendly visit.</p>
      </div>
      <div class="how-it-works__step flow">
        <h3>2. Get Matched</h3>
        <p>A community coordinator reviews the request and connects the Elder with a nearby volunteer.</p>
      </div>
      <div class="how-it-works__step flow">
        <h3>3. Receive Support</h3>
        <p>The volunteer reaches out, arranges the details, and provides the help. The coordinator follows up.</p>
      </div>
    </div>
  </div>

  <div class="how-it-works">
    <div class="how-it-works__step">
      <h2>For Elders</h2>
      <p>Need a ride to an appointment? Help with groceries or yard work? Or would you just like someone to visit? You don't have to do everything on your own — our volunteers are happy to help.</p>
      <ol class="how-it-works__list">
        <li>Submit a request describing what you need</li>
        <li>A coordinator reviews your request and finds a volunteer</li>
        <li>The volunteer reaches out to arrange the details</li>
        <li>After the visit, the coordinator follows up to make sure everything went well</li>
      </ol>
      <a href="/elders/request" class="btn btn--primary">Request Help</a>
    </div>

    <div class="how-it-works__step">
      <h2>For Volunteers</h2>
      <p>Have some time to share? Elders in your community could use a hand. Sign up and a coordinator will match you with requests that fit your schedule.</p>
      <ol class="how-it-works__list">
        <li>Sign up with your availability and skills</li>
        <li>A coordinator assigns you to a request</li>
        <li>Connect with the Elder and provide support</li>
        <li>Mark the request complete when you're done</li>
      </ol>
      <a href="/elders/volunteer" class="btn btn--secondary">Volunteer</a>
    </div>
  </div>

  <div class="safety-callout">
    <h2>Your Safety Matters</h2>
    <p>All volunteers are connected through community coordinators who know your area. You're never obligated to accept help, and you can cancel a request at any time. <a href="/safety">Read our safety guidelines</a>.</p>
  </div>

  <div class="portal-section">
    <h2>Prefer to Call?</h2>
    <p>Contact your local coordinator at your community phone number. They can submit a request on your behalf.</p>
  </div>
</section>
{% endblock %}
```

Key changes:
- Title: "Elder Support Program" (not "Grandparent Connection") — frames as a Minoo program
- Subtitle mentions "A Minoo program" explicitly
- Added "How It Works" 3-step summary section (moved from homepage)
- Kept existing For Elders / For Volunteers detailed sections
- Kept safety callout and call option

**Step 2: Update `tests/playwright/elders.spec.ts`**

Add a test for the How It Works section on /elders:

```typescript
test('has How It Works section', async ({ page }) => {
  await page.goto('/elders');
  await expect(page.getByRole('heading', { name: 'How It Works' })).toBeVisible();
  await expect(page.getByText('Request Help')).toBeTruthy();
  await expect(page.getByText('Get Matched')).toBeTruthy();
  await expect(page.getByText('Receive Support')).toBeTruthy();
});
```

Also update the existing title test if it checks for "Grandparent Connection" — it should now check for "Elder Support Program".

**Step 3: Run tests**

```bash
./vendor/bin/phpunit
npx playwright test
```

**Step 4: Commit**

```bash
git add templates/elders.html.twig tests/playwright/elders.spec.ts
git commit -m "feat(#116): move How It Works to /elders, reframe as Elder Support Program"
```

---

### Task 4: Copy and visual alignment (#117)

**Files:**
- Modify: `templates/volunteer.html.twig` (update framing)
- Modify: `templates/how-it-works.html.twig` (update framing)
- Modify: `tests/playwright/volunteer.spec.ts` (update if title checks change)
- Modify: `tests/playwright/info-pages.spec.ts` (update if title checks change)

**Step 1: Update `volunteer.html.twig`**

Minor copy change: ensure it frames volunteering as part of the Elder Support Program under Minoo:
- Update subtitle to mention "Minoo's Elder Support Program"
- Keep all CTAs and structure the same

**Step 2: Update `how-it-works.html.twig`**

Minor copy change:
- Ensure the page mentions "Minoo's Elder Support Program" not just generic elder support
- Keep all FAQ and sections

**Step 3: Visual pass**

Check that:
- Nav dropdown uses correct design tokens (no magic values)
- 5-card grid on homepage doesn't break layout
- `/elders` How It Works section flows well before the two-column detail
- No broken links or missing `aria-current` states

**Step 4: Run full test suite**

```bash
./vendor/bin/phpunit
npx playwright test
```

**Step 5: Commit**

```bash
git add templates/volunteer.html.twig templates/how-it-works.html.twig tests/playwright/volunteer.spec.ts tests/playwright/info-pages.spec.ts
git commit -m "feat(#117): copy and visual alignment — Minoo platform framing"
```

---

## Merge Strategy

All 4 tasks are on a single branch. After all tasks pass:

```bash
# Squash or merge to main
git checkout main
git merge feature/v0.10-nav
git push
```

Then close issues #114-#117 and close milestone v0.10.

## Notes

- Groups and Language pages still exist at their URLs — they're just not in the primary nav. Users can still navigate to `/groups` and `/language` via search or direct links.
- No PHP changes needed — all work is templates, CSS, and Playwright tests.
- PHPUnit tests (238 tests) should all pass unchanged since no PHP code is modified.
