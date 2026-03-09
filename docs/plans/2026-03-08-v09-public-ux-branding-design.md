# v0.9 — Public UX, Branding & Front-Facing Redesign

**Date:** 2026-03-08
**Status:** Approved
**Milestone:** v0.9

## Goal

Transform Minoo's public site into a polished, trustworthy, launch-ready product. Every top-level page becomes a portal that is local by default and global by design. Deliver production-ready templates, branding tokens, copy, accessibility, legal pages, and Playwright coverage.

## Design Principles

1. **Portal Principle**: Each page answers in 5 seconds: What is this? Who is it for? What can I do next?
2. **Dual Audience**: Primary local user path + secondary remote helper path on every portal.
3. **Location Integration**: Use existing LocationService (v0.8) for prefill and proximity.
4. **Accessibility**: WCAG AA minimum — semantic HTML, ARIA, keyboard focus, skip links.
5. **Mobile First**: All templates responsive.
6. **Copy Tone**: Warm, community-rooted, plain English.

## Existing Infrastructure (from v0.7/v0.8)

- Location bar with autocomplete and browser geolocation
- Elder support request/volunteer workflow (forms, dashboards, coordinator assignment)
- Communities page with proximity sorting and nearest highlight
- People page with client-side filtering
- CSS design system: OKLCH colors, fluid spacing, container queries, native nesting
- 8 controllers, 13+ entity types, 236 tests passing

## Issues (10)

### 1. Homepage Portal Redesign
Hero section with two CTAs (Request Help, Volunteer), location context, local snapshot card.

### 2. Elders Portal Redesign
Full portal at `/elders` with compact request form, representative toggle, consent, safety link.

### 3. Volunteer Portal Redesign
`/volunteer` landing with signup CTA, dashboard link, profile edit CTA.

### 4. Communities and People Landing Pages
Redesigned with local snapshot, search, filters, nearest community highlight.

### 5. How It Works and Safety Pages
Canonical `/how-it-works` and `/safety` with clear steps and safety checklist.

### 6. Branding System Implementation
CSS tokens alignment, typography, button/card styles, SVG wordmark, favicon.

### 7. Legal Pages
`/legal/privacy`, `/legal/terms`, `/legal/accessibility`, privacy summary for forms.

### 8. Template Polish Sweep
Unified headings, spacing, form layouts, empty states, messages across site.

### 9. Content and Copy Pass
Copy deck for portals, FAQs, hero microcopy.

### 10. Playwright Smoke Test Expansion
Tests covering portals, CTAs, representative flows, legal pages, location bar.

## Branding Tokens

Adapt to existing OKLCH system. Core semantic tokens:
- Primary: forest green (existing `--color-forest-500`)
- Accent: sun/warm (existing `--color-sun-500`)
- Surface/text: earth tones (existing palette)
- Add explicit `--color-primary`, `--color-accent` aliases
- Standardize button, card, and form component tokens

## Architecture Decisions

1. **No new CSS file** — extend existing `public/css/minoo.css` with branding aliases in `@layer tokens`
2. **No build step** — maintain vanilla CSS, inline JS approach
3. **Path-based templates** — new pages (`/how-it-works`, `/safety`, `/legal/*`) use framework path routing
4. **Playwright setup** — add `package.json` with Playwright dev dependency, `tests/playwright/` directory
5. **No feature flags** — changes are additive and non-breaking; deploy directly
