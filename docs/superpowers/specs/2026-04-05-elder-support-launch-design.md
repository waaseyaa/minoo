# Elder Support Program Launch

**Date:** 2026-04-05
**Status:** Draft
**Scope:** Nav wiring, OG meta, OG image, Facebook coordinator call-out

## Context

The Elder Support program is built and functional:
- Landing page at `/elders` with two-path layout (request help vs. volunteer)
- Request form at `/elders/request` with validation, representative flow, privacy notice
- Volunteer signup at `/elders/volunteer` with skills, availability, travel distance
- Confirmation pages for both paths
- Controller (`ElderSupportController`) handles form submission, entity creation, flash messages
- Workflow controller (`ElderSupportWorkflowController`) handles coordinator-side state management
- Full i18n strings in `resources/lang/en.php` (Ojibwe translations mostly empty)

What's missing for launch: the landing page isn't linked from the sidebar nav, there's no Elder-specific OG image for Facebook sharing, and the Facebook post calling for a coordinator hasn't been written.

## Deliverables

### 1. Sidebar Navigation Update

**File:** `templates/components/sidebar-nav.html.twig`

Add `/elders` landing page link at the top of the "Programs" group, above the existing request and volunteer links:

```
Programs
  Elder Support Program    → /elders           (new)
  Request Help             → /elders/request   (existing, relabeled)
  Volunteer                → /elders/volunteer (existing)
```

**Changes:**
- Add new `sidebar-nav__item` linking to `/elders` with an appropriate icon (e.g. users/community icon)
- Relabel `nav.elder_support` → `nav.request_help` for the request link (to distinguish from the program overview)
- Add new trans key `nav.elder_program` for the landing page link
- Fix active state: currently `/elders/request` uses `starts with '/elders'` which would match all Elder pages. Each link should highlight only on its own path:
  - `/elders` link: active when `current_path == '/elders'`
  - `/elders/request` link: active when `current_path starts with '/elders/request'`
  - `/elders/volunteer` link: active when `current_path starts with '/elders/volunteer'` (currently checks `/volunteer`, which is wrong)

**i18n keys to add/update:**
- `nav.elder_program` → "Elder Support Program" (en), TBD (oj)
- `nav.request_help` → "Request Help" (en), TBD (oj)
- `nav.volunteer` stays as-is

### 2. OG Meta Overrides

**File:** `templates/elders.html.twig`

Add Twig block overrides for Facebook/social sharing:

```twig
{% block og_title %}Elder Support Program — Minoo{% endblock %}
{% block og_description %}Rides, groceries, yard work, visits. Our Elders shouldn't have to ask twice. Request help or volunteer your time.{% endblock %}
{% block og_image %}https://minoo.live/img/og-elder-support.png{% endblock %}
```

No controller changes needed. `base.html.twig` already has overridable `og_title`, `og_description`, `og_image` blocks.

### 3. OG Image

**File:** `public/img/og-elder-support.png` (new, 1200x630)

Generate using the `generating-og-images` skill (HTML template → Playwright screenshot).

Design:
- Minoo brand colors (oklch palette from `minoo.css`)
- "Elder Support Program" as headline text
- Short tagline: "Caring for our Elders, together"
- Clean, minimal layout consistent with `og-default.png` style

### 4. Facebook Post (Coordinator Call-Out)

Draft copy for Russell to post from his personal Facebook account. Tone: blend of humble builder, proud announcement, and community call to action.

**Target URL:** `https://minoo.live/elders`

**Beats to hit:**
1. The thing exists — I built an Elder Support program into Minoo
2. What it does — Elders (or family) can request help with rides, groceries, yard work, visits. Volunteers sign up. A coordinator matches them.
3. The ask — The tools are ready, but the program needs a coordinator. One person who cares about Elders and wants to put this to use.

**Draft:**

> I've been building something for a while now and it's ready.
>
> Minoo has an Elder Support program. If an Elder in our community needs a ride to an appointment, help with groceries, yard work, or just a visit, they (or a family member) can submit a request. Volunteers sign up with what they can offer and when they're available. A coordinator matches them up and follows through.
>
> The forms are live. The workflow is built. What I need now is a coordinator. One person who knows our community, cares about our Elders, and wants to actually put these tools to use. You don't need to be technical. You just need to be willing to check in, match people up, and follow through.
>
> If that's you, or you know who it should be, reach out. Or just go look at what's there:
>
> https://minoo.live/elders

**Constraints:**
- No hashtags
- No "excited to announce" language
- Russell's personal voice, not Minoo's community voice
- Final copy subject to Russell's approval before posting

## Files Changed

| File | Change |
|------|--------|
| `templates/components/sidebar-nav.html.twig` | Add `/elders` link, relabel request link, fix active states |
| `templates/components/feed-sidebar-left.html.twig` | Update `nav.elder_support` → `nav.request_help` reference |
| `templates/elders.html.twig` | Add OG meta block overrides |
| `resources/lang/en.php` | Add `nav.elder_program`, rename `nav.elder_support` → `nav.request_help` |
| `resources/lang/oj.php` | Add `nav.elder_program`, rename `nav.elder_support` → `nav.request_help` |
| `public/img/og-elder-support.png` | New OG image (1200x630) |

## Out of Scope

- Landing page content/copy changes (existing copy is solid)
- CSS changes (existing styles cover the nav and landing page)
- New controllers or routes
- Ojibwe translations for landing page strings (tracked separately in Anishinaabemowin Localization milestone)
- Coordinator dashboard polish (separate milestone: Admin Surface)
