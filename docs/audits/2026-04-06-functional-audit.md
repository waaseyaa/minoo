# Functional Audit — minoo.live — 2026-04-06

**Context:** Run after the alpha.75 → alpha.107 incident-recovery, before the polish sprint and Twitch launch. Production verified live on release 282. This audit walked ~35 public routes via curl + HTML inspection. No JS execution, no Playwright screenshots (those are a follow-up pass).

**Method:** For each route — fetch body, check HTTP status + content-length, extract `<title>` and first `<h1>`, grep for placeholder text and dummy content, note unrendered template strings.

---

## Summary

| Bucket | Count |
|---|---|
| Routes checked | 35 |
| Healthy (200, real H1, real title) | 27 |
| Missing `<h1>` | 4 |
| 404 (feature not built) | 2 |
| 403 (auth required, expected) | 2 |
| Critical bugs | 2 |
| Polish items | 6 |
| Data quality items | 2 |

---

## Per-route findings

### Working cleanly

| Route | Status | Size | Title | H1 |
|---|---|---|---|---|
| `/about` | 200 | 24 KB | About Minoo — Minoo | About Minoo |
| `/events` | 200 | 33 KB | Events — Minoo | Events |
| `/teachings` | 200 | 45 KB | Teachings — Minoo | Teachings |
| `/businesses` | 200 | 30 KB | Indigenous Businesses — Minoo | Indigenous Businesses |
| `/oral-histories` | 200 | 22 KB | Our Stories — Minoo | Our Stories |
| `/data-sovereignty` | 200 | 26 KB | Data Sovereignty — Minoo | Data Sovereignty |
| `/legal` | 200 | 22 KB | Legal — Minoo | Legal |
| `/legal/privacy` | 200 | 23 KB | Privacy Policy — Minoo | Privacy Policy |
| `/legal/terms` | 200 | 23 KB | Terms of Use — Minoo | Terms of Use |
| `/legal/accessibility` | 200 | 23 KB | Accessibility Statement — Minoo | Accessibility Statement |
| `/language` | 200 | 45 KB | Language — Minoo | Language |
| `/communities` | 200 | 162 KB | All Communities — Minoo | All Communities |
| `/games` | 200 | 29 KB | Games — Minoo | Games |
| `/games/shkoda` | 200 | 26 KB | Shkoda — Word Game | Shkoda |
| `/games/crossword` | 200 | 25 KB | Crossword — Minoo Games | Crossword |
| `/games/agim` | 200 | 24 KB | Agim — Minoo | Agim |
| `/elders` | 200 | 26 KB | Elder Support Program — Minoo | Elder Support Program |
| `/elders/request` | 200 | 26 KB | Request Elder Support — Minoo | Request Elder Support |
| `/elders/volunteer` | 200 | 26 KB | Volunteer to Help Elders — Minoo | Volunteer to Help Elders |
| `/login` | 200 | 18 KB | Sign In — Minoo | Sign In |
| `/register` | 200 | 18 KB | Create Account — Minoo | Create Account |
| `/search?q=makwa` | 200 | 22 KB | Search — Minoo | Search |
| `/people` | 200 | 42 KB | People — Minoo | People (13 article cards) |
| `/safety` | 200 | 24 KB | Safety Guidelines — Minoo | Safety Guidelines |
| `/how-it-works` | 200 | 25 KB | How Minoo Works — Minoo | How Minoo Works |
| `/account` | 200 | 22 KB | Welcome back — Minoo | Welcome back |
| `/dashboard/coordinator` | 403 | — | (auth required, expected) | — |
| `/dashboard/volunteer` | 403 | — | (auth required, expected) | — |

### Issues

#### CRITICAL — homepage and feed have no `<h1>`

Both `/` and `/feed` return real content (79 KB, 42 KB) with the title `A Living Map of Community — Minoo`, but neither emits an `<h1>` element. This is an accessibility violation (WCAG 1.3.1, 2.4.6) and a meaningful SEO penalty for the most-visited URL on the site. Both routes appear to share the same template (same title), so likely a one-template fix.

#### CRITICAL — `/communities` listing renders unrendered Alpine template strings

The agent's earlier inspection found `href="'/communities/' + c.slug"` literally in the SSR HTML output. The community listing is client-rendered via Alpine/Vue, which means:
- Crawlers see no real `<a>` links to community detail pages
- First paint shows raw template syntax until JS hydrates
- 162 KB page weight is heavy for what should be a server-rendered list

For an Indigenous knowledge platform whose value proposition includes 637 First Nations community profiles, this is the page that needs to be discoverable and fast. Server-render it.

#### POLISH — `/messages` has no `<h1>` and title is just "Messages"

Inconsistent with every other page (which uses `Page Name — Minoo`). Also missing H1.

#### POLISH — `/admin` returns a 1 KB shell with title "Waaseyaa" and no H1

This is the admin SPA mount point. Known broken until #618 (admin session auth) lands. Worth a placeholder page that at least says "Admin — coming soon" or redirects to `/login` for unauthenticated visitors so it doesn't look broken to a curious crawler.

#### POLISH — `/notifications` returns 404

Notifications feature isn't built yet (Notifications milestone #43 has 8 open issues). Either:
- Add a graceful "Notifications coming soon" page, or
- Hide the link from the nav until it ships

#### POLISH — `/dashboard` returns 404 but role-specific dashboards exist

`/dashboard/coordinator` and `/dashboard/volunteer` both return 403 (auth-gated, working). But `/dashboard` itself 404s. Should either:
- Return a role-router page that redirects logged-in users to their appropriate dashboard
- Return 404 cleanly (it does, but the link discovery suggests users will type it)

#### POLISH — Original audit scope mentioned `/privacy`, `/terms`, `/accessibility`

These 404 because the real paths are `/legal/privacy` etc. Not a site bug, but worth confirming nothing else (old emails, social posts, external backlinks) points at the bare paths. Add a redirect from `/privacy` → `/legal/privacy` so any old links work.

#### POLISH — Audit didn't reach community detail pages

The agent ran out of turns before fetching a single community detail like `/communities/sagamok-anishnawbek`. The slugs aren't easy to discover from the listing because the listing uses Alpine template strings (see CRITICAL above). Community detail page is a known important surface — needs a separate manual check or a fixed listing page first.

#### DATA — 637 communities seeded from CIRNAC, unknown completeness

How many have real names + leadership + coordinates + websites? CLAUDE.md notes "ISC profiles have no email field" and "Website leadership scraping is unreliable (~80% false positive)". A data-quality report on the community registry would tell us how much real data is in there vs placeholder seeds.

#### DATA — Dictionary entries with JSON-wrapped `definition` field

Known gotcha (`cleanDefinition()` pattern in controllers). Audit didn't sample dictionary detail pages — worth confirming none leak raw `["bear"]` strings to users.

---

## Proposed GitHub issues

Suggested milestone: **Polish Sprint** (create a new one targeting one week out).

### CRITICAL

```
gh issue create --title "Homepage and /feed are missing <h1> element" \
  --body "Both / and /feed serve real content (79KB and 42KB) with title 'A Living Map of Community — Minoo', but neither emits an <h1>. WCAG 1.3.1 / 2.4.6 violation, and SEO penalty on the most-visited route. Likely one-template fix since both routes share the same title. See docs/audits/2026-04-06-functional-audit.md." \
  --milestone "Polish Sprint" --label bug
```

```
gh issue create --title "/communities listing renders unrendered Alpine template strings (Vue href bindings in SSR)" \
  --body "The All Communities page outputs literal href=\"'/communities/' + c.slug\" in its SSR HTML. The page is client-rendered, so crawlers see no real links to community detail pages, first paint shows raw template syntax until JS hydrates, and the 162KB page weight is heavy for what should be a server-rendered list. For an Indigenous knowledge platform whose value prop includes 637 community profiles, this needs SSR. See docs/audits/2026-04-06-functional-audit.md." \
  --milestone "Polish Sprint" --label bug
```

### POLISH

```
gh issue create --title "/messages page: missing <h1>, title doesn't follow 'Page — Minoo' convention" \
  --body "Title is just 'Messages' instead of 'Messages — Minoo'. No <h1>. Inconsistent with every other page on the site." \
  --milestone "Polish Sprint" --label polish
```

```
gh issue create --title "/admin returns 1KB shell with no H1, looks broken to crawlers and humans" \
  --body "Admin SPA mount point. Known non-functional until #618 lands. Replace with a placeholder that says 'Admin — coming soon' or redirect unauthenticated visitors to /login. Right now it returns a blank-looking page that shows up in any external scan." \
  --milestone "Polish Sprint" --label polish
```

```
gh issue create --title "/notifications returns 404 but link is in the nav" \
  --body "Notifications feature is in milestone #43 (8 open issues, not started). Either add a 'Coming soon' placeholder page, or hide the nav link until it ships. Currently anyone clicking the link hits a hard 404." \
  --milestone "Polish Sprint" --label polish
```

```
gh issue create --title "/dashboard 404s but /dashboard/coordinator and /dashboard/volunteer exist" \
  --body "Add a /dashboard route that detects the logged-in user's role and redirects to the appropriate dashboard. Currently bare /dashboard 404s, which is confusing for users who type it manually." \
  --milestone "Polish Sprint" --label polish
```

```
gh issue create --title "Add /privacy, /terms, /accessibility redirects to /legal/* paths" \
  --body "Old/external links to bare /privacy etc. currently 404. Real pages live at /legal/privacy, /legal/terms, /legal/accessibility (all working). Add 301 redirects so historical links from social posts, emails, and search engines don't break." \
  --milestone "Polish Sprint" --label polish
```

### DATA

```
gh issue create --title "Audit community registry data completeness (637 First Nations from CIRNAC)" \
  --body "We have 637 communities seeded but unknown completeness on real names, leadership, coordinates, websites. Run a script to count how many have each field populated, output a CSV. This is the page that crawlers and visitors will most want, so we need to know what's there before deciding what to enrich first. See CLAUDE.md notes on ISC profile gaps and website leadership scraping unreliability." \
  --milestone "Content Enrichment Pipeline" --label data
```

```
gh issue create --title "Verify no dictionary detail pages leak raw JSON definition values" \
  --body "Known gotcha: dictionary 'definition' field is JSON-wrapped (e.g. [\"bear\"]) and needs cleanDefinition() in controllers before display. Sample a few /language/{entry} pages to confirm none leak raw [\"bear\"] strings to users." \
  --milestone "Polish Sprint" --label data
```

### Follow-up audit pass needed

```
gh issue create --title "Manual audit pass for /communities/{slug} detail pages" \
  --body "Initial functional audit (docs/audits/2026-04-06-functional-audit.md) didn't reach a community detail page because the listing page uses unrendered Alpine template strings, making slugs hard to extract programmatically. Once the SSR fix lands for the listing page, do a manual audit of 5-10 community detail pages: real data vs placeholder, leadership rendering, geo coordinates, website links, image references." \
  --milestone "Polish Sprint" --label audit
```

---

## Not bugs (audit clarifications)

- Homepage and games pages had high `placeholder` keyword hits in the initial scan — all turned out to be HTML form `placeholder="..."` attributes and harmless JS, not visible placeholder text.
- No "Lorem ipsum", "TODO", or "coming soon" content found anywhere.
- Performance not measured (no `time_total` checks beyond the initial pages). Add to follow-up.
- Mobile / responsive checks not done (would require Playwright).
- Light mode parity not checked (would require Playwright theme toggle).

---

## Stream notes

Of these issues, the one most worth fixing on stream is the **homepage H1**. It's:
- A real bug
- Easy to demo (open template, add `<h1>`, watch curl confirm)
- Fast (likely a 5-line template change)
- Visible (the homepage IS the audience landing page)
- Has a strong narrative: "I just shipped alpha.107 and now I'm fixing my homepage H1 because I want my Indigenous knowledge platform to actually pass accessibility checks. Here, watch."

Second-best is the `/communities` SSR fix because the story has substance: "We have 637 First Nations communities and right now Google can't index any of them. Let me fix that."
