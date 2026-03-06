# Events & Groups SSR Pages Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship demo listing and detail pages for events and groups, establishing the card + detail patterns for all future SSR pages.

**Architecture:** Path-based template resolution serves `events.html.twig` and `groups.html.twig`. Each template handles both listing (`/events`) and detail (`/events/{slug}`) via path conditional. Hardcoded demo data — no database interaction. Reusable card components.

**Tech Stack:** CSS (@layer components in minoo.css), Twig 3, Playwright MCP for visual verification

**Issues:** Create GitHub issues before starting. Close #6 when done.

---

### Task 0: Create GitHub issues

**Step 1: Create sub-issues for tracking**

```bash
gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" \
  --title "SSR: add event card component and CSS badge/date/detail styles" \
  --body "Card component, badge, date, and detail page CSS additions. Part of #6."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" \
  --title "SSR: add events listing and detail page" \
  --body "events.html.twig with listing grid and detail view. 3 demo events. Part of #6."

gh issue create --repo waaseyaa/minoo --milestone "v0.2 – First Entities + NorthCloud Ingestion" \
  --title "SSR: add group card component and groups listing/detail page" \
  --body "Group card, groups.html.twig with listing grid and detail view. 3 demo groups. Part of #6."
```

Note issue numbers for commit messages.

---

### Task 1: Add CSS badge, date, and detail styles to `minoo.css`

**Files:**
- Modify: `public/css/minoo.css` (append to `@layer components` block, before closing `}`)

**Step 1: Add badge, date, and detail styles**

Insert before the closing `}` of `@layer components`:

```css
  .card__badge {
    display: inline-block;
    font-size: var(--text-sm);
    padding: var(--space-3xs) var(--space-2xs);
    border-radius: var(--radius-full);
    font-weight: 600;
    letter-spacing: var(--tracking-wide);
    text-transform: capitalize;
  }

  .card__badge--event {
    background-color: var(--color-water-100);
    color: var(--color-water-600);
  }

  .card__badge--group {
    background-color: var(--accent-surface);
    color: var(--color-forest-700);
  }

  .card__date {
    font-family: var(--font-mono);
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }

  .detail {
    max-inline-size: var(--width-prose);
  }

  .detail__back {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    text-decoration: none;

    &:hover {
      color: var(--text-primary);
    }

    &::before {
      content: "\2190\00a0";
    }
  }

  .detail__header {
    display: flex;
    flex-direction: column;
    gap: var(--space-2xs);
  }

  .detail__meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-xs);
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }

  .detail__body {
    line-height: var(--leading-loose);
  }
```

**Step 2: Verify CSS is syntactically valid**

Run: `head -1 public/css/minoo.css` — should show `@layer` declaration.

**Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#NN): add badge, date, and detail page styles to minoo.css

Card badge variants for events/groups, date display, detail page layout."
```

---

### Task 2: Create event card component

**Files:**
- Create: `templates/components/event-card.html.twig`

**Step 1: Create the component**

```twig
<article class="card">
  <span class="card__badge card__badge--event">{{ type }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ title }}</a></h3>
  <p class="card__date">{{ date }}</p>
  {% if location is defined and location %}
    <p class="card__meta">{{ location }}</p>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
</article>
```

**Step 2: Commit**

```bash
git add templates/components/event-card.html.twig
git commit -m "feat(#NN): add event card component"
```

---

### Task 3: Create events listing and detail page

**Files:**
- Create: `templates/events.html.twig`

**Step 1: Create the template**

The template handles both `/events` (listing) and `/events/{slug}` (detail) via path conditional. Demo data is hardcoded using Twig `set` to define an array of events, then iterated for the listing or matched by slug for the detail.

```twig
{% extends "base.html.twig" %}

{% set events = [
  {
    slug: "summer-solstice-powwow",
    title: "Summer Solstice Powwow",
    type: "Powwow",
    date: "June 21 \u2013 22, 2026",
    location: "Manitoulin Island, Ontario",
    excerpt: "A celebration of the longest day with traditional dance, drum circles, and a community feast under the open sky.",
    description: "Join us on Manitoulin Island for the annual Summer Solstice Powwow, a two-day celebration of Indigenous culture, music, and community. The gathering features grand entry ceremonies, traditional and contemporary dance competitions, drum circles, artisan vendors, and a community feast.\n\nAll nations and visitors are welcome. Bring your own chair and tobacco for offerings. Camping is available on-site."
  },
  {
    slug: "community-healing-circle",
    title: "Community Healing Circle",
    type: "Gathering",
    date: "Every Wednesday, 6:30 PM",
    location: "Sudbury Community Centre",
    excerpt: "A safe weekly space for sharing, healing, and reconnection guided by traditional practices.",
    description: "The Community Healing Circle meets weekly to provide a safe, supportive environment for individuals and families seeking healing through traditional practices. Each session is guided by an Elder and includes smudging, sharing circles, and teachings.\n\nNo registration required. Light refreshments provided. Childcare available on request."
  },
  {
    slug: "water-ceremony",
    title: "Spring Water Ceremony",
    type: "Ceremony",
    date: "March 22, 2026",
    location: "Lake Huron shoreline, Sauble Beach",
    excerpt: "Honouring the water on World Water Day with prayer, song, and offerings at the lakeshore.",
    description: "On World Water Day, we gather at the shores of Lake Huron to honour nibi (water) through prayer, song, and offerings. The ceremony is led by Water Walkers and community Elders who carry the tradition of caring for the water.\n\nParticipants are asked to bring a small container of water from their home to add to the collective offering. Dress warmly and wear footwear suitable for the shoreline."
  }
] %}

{% set slug = path|replace({'/events/': '', '/events': ''})|trim('/') %}
{% set current_event = null %}
{% for e in events %}
  {% if e.slug == slug %}
    {% set current_event = e %}
  {% endif %}
{% endfor %}

{% if path == '/events' %}

  {% block title %}Events — Minoo{% endblock %}

  {% block content %}
    <div class="flow-lg">
      <h1>Events</h1>
      <p>Community gatherings, ceremonies, and celebrations.</p>

      <div class="card-grid">
        {% for event in events %}
          {% include "components/event-card.html.twig" with {
            title: event.title,
            type: event.type,
            date: event.date,
            location: event.location,
            excerpt: event.excerpt,
            url: "/events/" ~ event.slug
          } %}
        {% endfor %}
      </div>
    </div>
  {% endblock %}

{% elseif current_event %}

  {% block title %}{{ current_event.title }} — Minoo{% endblock %}

  {% block content %}
    <div class="flow-lg detail">
      <a href="/events" class="detail__back">Events</a>
      <div class="detail__header">
        <span class="card__badge card__badge--event">{{ current_event.type }}</span>
        <h1>{{ current_event.title }}</h1>
        <div class="detail__meta">
          <span class="card__date">{{ current_event.date }}</span>
          <span>{{ current_event.location }}</span>
        </div>
      </div>
      <div class="detail__body flow">
        {% for paragraph in current_event.description|split("\n\n") %}
          <p>{{ paragraph }}</p>
        {% endfor %}
      </div>
    </div>
  {% endblock %}

{% else %}

  {% block title %}Event Not Found — Minoo{% endblock %}

  {% block content %}
    <div class="flow-lg">
      <h1>Event Not Found</h1>
      <p>The event at <code>{{ path }}</code> could not be found.</p>
      <p><a href="/events">Browse all events</a></p>
    </div>
  {% endblock %}

{% endif %}
```

**IMPORTANT NOTE:** Twig does not allow multiple `{% block %}` definitions in conditional branches at the top level of a child template. The approach above will fail. Instead, the blocks must wrap the entire conditional:

```twig
{% extends "base.html.twig" %}

{# Data and slug extraction go here, outside blocks #}

{% block title %}
  {% if path == '/events' %}Events — Minoo
  {% elseif current_event is defined and current_event %}{{ current_event.title }} — Minoo
  {% else %}Event Not Found — Minoo{% endif %}
{% endblock %}

{% block content %}
  {% if path == '/events' %}
    {# listing #}
  {% elseif current_event is defined and current_event %}
    {# detail #}
  {% else %}
    {# not found #}
  {% endif %}
{% endblock %}
```

The `{% set %}` for demo data must go inside the content block (Twig doesn't allow `set` at the top level of a child template outside of blocks, but set inside a block is scoped to that block). So the data definition must be duplicated or placed in both blocks.

**Simplest correct approach:** Define demo data inside `{% block content %}` and use a single conditional there. For the title block, use a simpler path check without needing the events data.

**Step 2: Start dev server and verify**

Run: `php -S localhost:8081 -t public &`
Navigate to `http://localhost:8081/events` — verify card grid with 3 events.
Navigate to `http://localhost:8081/events/summer-solstice-powwow` — verify detail page.
Navigate to `http://localhost:8081/events/nonexistent` — verify not-found message.
Take Playwright screenshots at desktop (1280x800) and mobile (375x812).

**Step 3: Commit**

```bash
git add templates/events.html.twig
git commit -m "feat(#NN): add events listing and detail page

3 demo events (powwow, gathering, ceremony). Path conditional for
listing vs detail. Card grid on listing, prose layout on detail."
```

---

### Task 4: Create group card component and groups page

**Files:**
- Create: `templates/components/group-card.html.twig`
- Create: `templates/groups.html.twig`

**Step 1: Create group card component**

```twig
<article class="card">
  <span class="card__badge card__badge--group">{{ type }}</span>
  <h3 class="card__title"><a href="{{ url }}">{{ name }}</a></h3>
  {% if region is defined and region %}
    <p class="card__meta">{{ region }}</p>
  {% endif %}
  {% if excerpt is defined and excerpt %}
    <p class="card__body">{{ excerpt }}</p>
  {% endif %}
</article>
```

**Step 2: Create groups template**

Same pattern as events — data inside content block, path conditional, listing grid + detail page + not-found fallback.

Demo groups:
1. **Anishinaabe Language Circle** (Online Community) — "A virtual gathering space for Ojibwe language learners at all levels, meeting twice weekly for conversation practice, vocabulary building, and storytelling."
2. **N'Swakamok Indigenous Friendship Centre** (Local Community) — Northern Ontario — "A welcoming community space offering cultural programming, family support services, Elder counselling, and youth mentorship in the Sudbury region."
3. **Great Lakes Water Protectors** (Advocacy Organization) — Great Lakes Region — "A grassroots alliance of Indigenous communities and allies working to protect the Great Lakes watershed through legal advocacy, water monitoring, and public education."

**Step 3: Verify with Playwright**

Navigate to `http://localhost:8081/groups` — card grid with 3 groups.
Navigate to `http://localhost:8081/groups/anishinaabe-language-circle` — detail page.
Take screenshots.

**Step 4: Commit**

```bash
git add templates/components/group-card.html.twig templates/groups.html.twig
git commit -m "feat(#NN): add group card component and groups listing/detail page

3 demo groups (online, local, advocacy). Same listing/detail pattern
as events."
```

---

### Task 5: Final verification and cleanup

**Step 1: Full Playwright pass**

All pages at 1280x800:
1. `http://localhost:8081/` — homepage
2. `http://localhost:8081/events` — events listing
3. `http://localhost:8081/events/summer-solstice-powwow` — event detail
4. `http://localhost:8081/groups` — groups listing
5. `http://localhost:8081/groups/anishinaabe-language-circle` — group detail
6. `http://localhost:8081/language` — language page (regression check)
7. `http://localhost:8081/nonexistent` — 404 (regression check)

Repeat at 375x812 for key pages (events listing, event detail).

**Step 2: Verify nav active states**

Events pages should show "Events" nav link with `aria-current="page"`.
Groups pages should show "Groups" nav link with `aria-current="page"`.

**Step 3: Run tests (regression check)**

Run: `./vendor/bin/phpunit` — all 109 tests should pass.

**Step 4: Update CLAUDE.md**

Add `events.html.twig`, `groups.html.twig` to the architecture tree.

**Step 5: Commit and close issues**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md with events and groups pages"

gh issue close NN -c "Shipped: event card, events listing/detail"
gh issue close NN -c "Shipped: group card, groups listing/detail"
gh issue close NN -c "Shipped: CSS badge, date, detail styles"
gh issue close 6 -c "Shipped: SSR public pages for events and groups with demo data"
```

---

## Execution Notes

- **Task 3 is the tricky one** — Twig template inheritance + conditionals. Watch for block scoping rules. Test the template renders before writing the full demo data.
- **Demo data is intentionally realistic** — culturally appropriate content for an Indigenous knowledge platform.
- **Path conditional is a shim** — document it as temporary. When entity rendering + path aliases land, these templates get replaced.
- **Card components are permanent** — `event-card.html.twig` and `group-card.html.twig` will be reused when real entity data replaces the demo content.
