# Sprint: Page Enrichment & Polishing for Waaseyaa Flagship Launch

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete all 26 issues in milestone #23 in a single sprint, transforming Minoo from a functional platform into a polished, professional flagship site ready for the Waaseyaa launch.

**Architecture:** Five-phase sprint: data substrate → shared scaffolding → page-by-page relational enrichment → visual polish → QA gate. Each phase builds on the previous, with parallelization within phases where files don't overlap.

**Tech Stack:** PHP 8.4 (Waaseyaa framework), Twig 3, vanilla CSS (`@layer components`), PHPUnit 10.5, Playwright

---

## Sprint Overview

| Phase | Days | Issues | Description |
|-------|------|--------|-------------|
| 1. Data Substrate | Day 1 | #300, #301 | Audit entities, populate relationships |
| 2. Global Scaffolding | Day 2 | #282, #283, #284 | Hero sections, breadcrumbs, OG metadata |
| 3. Page Enrichment | Days 3–5 | #285–#299 | Page-by-page relational enrichment |
| 4. Visual Polish | Day 6 | #302, #303, #304 | Card consistency, hero images, mobile |
| 5. QA Gate | Day 7 | #305, #306, #307 | Playwright, Lighthouse, manual checklist |

**Total:** 26 issues across 7 days

---

## Dependency Graph

```
Phase 1: Data Substrate
  #300 audit-entity-quality ─┐
  #301 populate-relationships ┘─→ all subsequent phases depend on clean data

Phase 2: Global Scaffolding (after Phase 1)
  #282 hero-sections ──────┐
  #283 breadcrumbs ────────┤─→ page enrichment uses these shared components
  #284 og-metadata ────────┘

Phase 3: Page Enrichment (after Phase 2, sequential by page type)
  Day 3: #285 homepage-cards ──┐
         #286 homepage-badges ─┘─→ parallel (different CSS sections)
         #287 event-related ───┐
         #288 event-dates ─────┘─→ parallel (controller vs template)

  Day 4: #289 teaching-related ─┐
         #290 teaching-metadata ─┘─→ parallel
         #291 business-map ──────┐
         #292 business-community ─┘─→ parallel (different template sections)
         #293 group-related ──────→ standalone

  Day 5: #294 people-roles ────┐
         #295 people-bio ──────┘─→ parallel (controller vs template)
         #296 community-content ─┐
         #297 community-nearby ──┘─→ parallel
         #298 search-badges ─────┐
         #299 search-suggestions ┘─→ sequential (#299 depends on #298)

Phase 4: Visual Polish (after Phase 3)
  #302 card-consistency ───┐
  #303 hero-images ────────┤─→ parallel (different CSS/template sections)
  #304 mobile-responsive ──┘

Phase 5: QA Gate (after Phase 4)
  #305 playwright-tests ───┐
  #306 lighthouse ─────────┤─→ parallel (independent test suites)
  #307 manual-checklist ───┘─→ after #305 and #306
```

---

## Day-by-Day Execution Plan

### Day 1 — Data Substrate

**Goal:** Ensure every entity has the metadata needed for enrichment.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| Write `bin/audit-entity-quality` | #300 | 2h | None | CLI script, coverage report |
| Run audit, fix critical gaps | #300 | 2h | Audit script | Populated entity fields |
| Write `scripts/populate_relationships.php` | #301 | 2h | None | Relationship population script |
| Run relationship population | #301 | 1h | #300 complete | Cross-entity links in DB |

**Parallelization:** #300 audit script and #301 relationship script can be written in parallel (different files). Running them is sequential (#301 needs #300's fixes first).

**Expected output:**
- `bin/audit-entity-quality` — CLI tool that reports field coverage per entity type
- `scripts/populate_relationships.php` — Populates community_id on events, people, businesses
- Data quality report showing coverage before/after
- All published entities have slugs, titles, and descriptions

**Commit sequence:**
1. `chore: add entity quality audit script`
2. `chore: populate cross-entity relationships`

---

### Day 2 — Global Scaffolding

**Goal:** Establish shared UI components used by all page types.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| Standardize hero sections | #282 | 3h | Phase 1 done | Updated all listing+detail templates |
| Create breadcrumb component | #283 | 2h | None | `templates/components/breadcrumb.html.twig`, CSS |
| Add OG metadata blocks | #284 | 2h | None | `{% block og_meta %}` in base + all templates |

**Parallelization:** All three can run in parallel worktrees — #282 touches template structure, #283 creates a new component, #284 modifies `<head>` blocks. No file conflicts.

**Expected output:**
- `templates/components/breadcrumb.html.twig` — Reusable breadcrumb component
- All listing templates: consistent hero (h1 + subtitle)
- All detail templates: consistent hero (back-link + badge + h1 + meta)
- All templates: `{% block og_meta %}` with entity-specific overrides
- CSS additions in `@layer components` for breadcrumbs
- Translation keys for breadcrumb "Home" label

**Commit sequence:**
1. `feat: standardize hero sections across all page types`
2. `feat: add breadcrumb navigation to detail pages`
3. `feat: add OG metadata to all pages`

---

### Day 3 — Homepage + Event Enrichment

**Goal:** Enrich homepage cards and event pages with relational content.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| Homepage card density + metadata | #285 | 2h | Phase 2 done | Updated homepage-card.html.twig, HomeController |
| Homepage badge colors | #286 | 1h | None | CSS additions |
| Event related content sections | #287 | 3h | Phase 2 done | EventController changes, template sections |
| Event date/venue/status display | #288 | 2h | None | Template + CSS additions |

**Parallelization:**
- #285 + #286: parallel (controller vs CSS)
- #287 + #288: parallel (controller queries vs template formatting)

**Expected output:**
- Homepage cards show images, dates, community names, role tags
- Homepage badges have domain-specific accent colors
- Event detail pages show "Related Teachings", "People Connected", "Host Community"
- Event detail pages show formatted date range, venue, and Happening Now/Upcoming/Past badge
- CSS for event status badges

**Commit sequence:**
1. `feat: enrich homepage cards with metadata and images`
2. `feat: add domain-colored badges to homepage cards`
3. `feat: add related content sections to event detail pages`
4. `feat: add date/venue display and status indicator to events`

---

### Day 4 — Teaching + Business + Group Enrichment

**Goal:** Enrich teaching, business, and group pages with relational content.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| Teaching related content | #289 | 2h | Day 3 done | TeachingController, template |
| Teaching cultural metadata | #290 | 1h | None | Template + CSS |
| Business map + hours | #291 | 3h | None | Template + CSS + Leaflet |
| Business community affiliation | #292 | 1h | None | BusinessController, template |
| Group related content | #293 | 2h | None | GroupController, template |

**Parallelization:**
- #289 + #290: parallel (controller vs template)
- #291 + #292: parallel (different template sections)
- #293: independent, can run alongside #291/#292

**Expected output:**
- Teaching detail pages show related events, knowledge keepers, source links
- Teaching pages show type badge, source attribution, consent status
- Business detail pages show map (if coordinates), hours, services tags
- Business pages show community affiliation
- Group detail pages show related people, events, teachings

**Commit sequence:**
1. `feat: add related content to teaching pages`
2. `feat: add cultural context metadata to teachings`
3. `feat: add map, hours, and services to business pages`
4. `feat: add community affiliation to business pages`
5. `feat: add related content to group pages`

---

### Day 5 — People + Community + Search Enrichment

**Goal:** Enrich people, community, and search pages. Complete all page-level enrichment.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| People roles + connections | #294 | 3h | Day 4 done | PeopleController, template |
| People bio + media | #295 | 1h | None | Template updates |
| Community local content | #296 | 3h | None | CommunityController, template |
| Community nearby section | #297 | 1h | None | CommunityController, template |
| Search badges + metadata | #298 | 2h | None | Search template + CSS |
| Search cross-entity suggestions | #299 | 1h | #298 | Search controller, template |

**Parallelization:**
- #294 + #295: parallel (controller vs template)
- #296 + #297: parallel (different sections of same controller/template)
- #298 + #299: sequential (#299 extends #298)
- People, Community, and Search groups are independent — can run as 3 parallel worktrees

**Expected output:**
- People pages show role badges, offering tags, linked businesses, community, related events
- People pages show bio with paragraph formatting, profile photo
- Community pages show events, teachings, businesses, people, groups from that community
- Community pages show 6 nearest communities with distance
- Search results have entity-type badges with domain colors
- Search results show cross-entity suggestions

**Commit sequence:**
1. `feat: enrich people pages with roles and connections`
2. `feat: add bio and media to people pages`
3. `feat: add local content sections to community pages`
4. `feat: add nearby communities to community pages`
5. `feat: improve search results with badges and metadata`
6. `feat: add cross-entity suggestions to search`

---

### Day 6 — Visual Polish

**Goal:** Tighten visual consistency across the entire site.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| Card style consistency audit | #302 | 3h | Phase 3 done | CSS normalization |
| Hero images on detail pages | #303 | 2h | Phase 3 done | Controller + template + CSS |
| Mobile responsiveness audit | #304 | 3h | Phase 3 done | CSS fixes, viewport testing |

**Parallelization:** All three can run in parallel — #302 touches card CSS, #303 adds hero image sections, #304 fixes responsive breakpoints. Minimal overlap.

**Expected output:**
- All card types normalized: same border-radius, padding, shadow, hover states
- Detail pages show hero images when media available (with copyright filtering)
- Responsive hero images with aspect ratio
- All pages render correctly at 320/375/414px
- No horizontal overflow, 44px touch targets, single-column grids on mobile

**Commit sequence:**
1. `feat: normalize card styles for consistency`
2. `feat: add hero images to detail pages`
3. `feat: fix mobile responsiveness across all pages`

---

### Day 7 — QA Gate

**Goal:** Verify everything works, meets accessibility standards, and is launch-ready.

| Task | Issue | Est. | Dependencies | Output |
|------|-------|------|--------------|--------|
| Playwright smoke tests | #305 | 3h | Phase 4 done | 15-20 new test cases |
| Lighthouse audit | #306 | 2h | Phase 4 done | Score report, critical fixes |
| Manual verification checklist | #307 | 3h | #305, #306 | Checklist doc with pass/fail |

**Parallelization:** #305 and #306 can run in parallel (different test tools). #307 runs after both complete.

**Expected output:**
- Playwright tests for all enriched page sections
- Lighthouse scores: Performance 80+, Accessibility 90+, Best Practices 90+, SEO 90+
- `docs/launch-checklist.md` with pass/fail for every page type
- All critical issues resolved before marking sprint complete

**Commit sequence:**
1. `test: add Playwright smoke tests for enriched pages`
2. `chore: Lighthouse audit — resolve critical issues`
3. `docs: flagship launch verification checklist`

---

## Parallelization Summary

| Day | Parallel Groups | Max Concurrent Agents |
|-----|----------------|----------------------|
| 1 | Write audit + relationship scripts | 2 |
| 2 | Hero + Breadcrumbs + OG metadata | 3 |
| 3 | Homepage (2) + Events (2) | 4 |
| 4 | Teachings (2) + Businesses (2) + Groups (1) | 3 |
| 5 | People (2) + Community (2) + Search (2) | 3 |
| 6 | Cards + Hero images + Mobile | 3 |
| 7 | Playwright + Lighthouse, then Manual | 2 |

---

## Risk Areas & Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Sparse entity data after audit | Enrichment sections appear empty | Manually populate 5-10 entities per type as minimum viable content |
| Leaflet map integration (#291) | Heavier than expected | Fall back to static Google Maps embed or address-only display |
| Cross-entity queries slow on production | Homepage latency | Cache featured items query; lazy-load related sections |
| Template conflicts from parallel agents | Merge failures | Each agent touches different templates; use worktree isolation |
| Lighthouse performance targets | CSS/image weight | Defer non-critical images with `loading="lazy"`; audit CSS bundle size |
| Missing copyright-cleared images | Hero images section renders empty | Graceful fallback (no broken layout); populate community_owned images |

---

## Blockers to Watch For

1. **Production database path** — Framework resolves to `{projectRoot}/waaseyaa.sqlite` locally but `storage/waaseyaa.sqlite` on production. Population scripts must handle both.
2. **Schema drift** — New fields added during enrichment (e.g., `services` on groups) require `ALTER TABLE` on production SQLite.
3. **Stale manifest cache** — Always `rm -f storage/framework/packages.php` after adding providers or fields.
4. **ConfigEntityBase trap** — Any new entity must use `ContentEntityBase` with `'uuid' => 'uuid'` for DB persistence. Use `$storage->create()` not `new Entity()`.
5. **Copyright filtering** — Hero images must check `copyright_status` in `['community_owned', 'cc_by_nc_sa']`. Missing status = no image shown.
6. **Playwright pre-existing failures** — 5 tests (accessibility, auth, location-bar, volunteer) fail pre-sprint. Don't block on these.

---

## Definition of Done

The sprint is complete when ALL of the following are true:

- [ ] All 26 issues (#282–#307) are closed
- [ ] PHPUnit: all tests pass (current 442 + new tests from enrichment)
- [ ] Playwright: all new smoke tests pass; no regressions in existing passing tests
- [ ] Lighthouse: Performance 80+, Accessibility 90+, Best Practices 90+, SEO 90+ on all page types
- [ ] Every detail page type shows at least one related content section when data exists
- [ ] Every detail page has breadcrumbs, proper OG metadata, and consistent hero
- [ ] Homepage Featured section renders correctly with active items
- [ ] Homepage cards show domain-colored badges and richer metadata
- [ ] All pages render correctly at 320px viewport width
- [ ] No raw translation key strings visible on any page
- [ ] Manual verification checklist completed with all items passing
- [ ] All changes deployed to production (minoo.live)
- [ ] Production population scripts run (relationships, metadata)

---

## Post-Sprint Follow-Up

Items explicitly deferred from this sprint:

| Item | Reason | Tracking |
|------|--------|----------|
| Location bar fix (#280) | Depends on NorthCloud API | #280 open |
| Admin UI for featured items | No multiple editors yet | Future milestone |
| Image upload/management UI | CLI-based for now | Future milestone |
| Homepage search → /businesses routing | Currently routes to /groups | Part of search overhaul |
| Community autocomplete in location bar | NorthCloud dependency | #280 |
| Regional featured item targeting | YAGNI until regional content exists | Spec documented |
| Event sub-events (per arena/day) | LNHL future enhancement | Not tracked yet |
| Automated image optimization pipeline | Manual for now | Future milestone |

---

## Execution Notes for Agentic Workers

- **Parallel worktrees** are safe for tasks listed as parallelizable — they touch different files
- **Day boundaries** are approximate — a fast session may complete Days 1–3 in one sitting
- **Controller pattern**: Constructor injects `EntityTypeManager` + `Twig\Environment`. Lazy-instantiate services. No custom DI.
- **Card components**: Reuse existing `homepage-card.html.twig`, `event-card.html.twig`, etc. — don't create new card types unless the existing ones can't express the data
- **CSS**: All new styles go in `@layer components`. Use existing design tokens (`--space-*`, `--text-*`, `--color-*`). Logical properties only.
- **Translation keys**: Add to both `resources/lang/en.php` and `resources/lang/oj.php`
- **Test pattern**: PHPUnit for controllers (mock EntityTypeManager, ArrayLoader for Twig). Playwright for template rendering.
