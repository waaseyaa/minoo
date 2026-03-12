# Minoo V1.0 Release Notes

**Release Date:** April 22, 2026
**Platform:** [minoo.community](https://minoo.community) (placeholder URL)

---

## What is Minoo?

Minoo is an Indigenous knowledge platform built by a First Nations developer in northern Ontario. It connects communities, preserves language and teachings, and supports Elder care through volunteer matching — all governed by community values, not corporate ones.

---

## What V1 Delivers

### Elder Support Program
Submit requests for Elder assistance — rides, groceries, visits, companionship. Volunteers sign up and are matched by proximity and skills. Coordinators manage assignments through a dashboard with a 6-state workflow (open, assigned, in progress, completed, confirmed, cancelled).

### Community Registry
637 First Nations communities seeded from CIRNAC open data. Community detail pages display leadership and band office information sourced from NorthCloud. Location-aware search finds communities near you.

### Language Preservation
Anishinaabemowin dictionary with entries, example sentences, word parts, and speaker information. All dictionary content sourced from The Ojibwe People's Dictionary is displayed with proper attribution.

### Teachings & Events
Browse cultural teachings by category and type. Find community events. All content rendered from real entity data with card-based layouts.

### Search
Full-text search across all content types with Indigenous content prioritization and community-based filtering.

---

## Content Attribution

Dictionary content on this platform is **copyrighted by The Ojibwe People's Dictionary** ([ojibwe.lib.umn.edu](http://ojibwe.lib.umn.edu)) and is used under the [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-nc-sa/4.0/) (CC BY-NC-SA 4.0).

All community-contributed content shared on Minoo is licensed under CC BY-NC-SA 4.0. This means:
- You may share and adapt the content for non-commercial purposes
- You must give appropriate credit
- You must share adaptations under the same license
- You may not use the content for commercial purposes

---

## Data Sovereignty

Minoo is built with Indigenous data sovereignty as a core principle:

- **No data mining.** Community data is not analyzed for patterns beyond platform functionality.
- **No selling data.** Community information is never sold, licensed, or monetized.
- **No AI training.** Teachings, language entries, and community knowledge are never used to train AI models. Content is marked with `consent_ai_training` metadata (default: no).
- **No surveillance.** We do not track or profile community members.
- **No unauthorized export.** Community data cannot be exported without governance approval.

Read our full [Data Sovereignty Statement](/data-sovereignty) for details.

---

## Security & Accessibility

- Security review completed against OWASP Top 10
- WCAG 2.1 AA accessibility audit with zero critical violations
- All forms use CSRF protection
- Session cookies are HttpOnly, Secure, SameSite
- Rate limiting on authentication and form endpoints
- Content Security Policy and security headers enforced

---

## Technical Details

- **Framework:** Waaseyaa CMS (custom PHP 8.3+ framework)
- **Frontend:** Server-side rendered Twig templates, vanilla CSS with design tokens
- **Data:** SQLite with NorthCloud API integration (community data)
- **Tests:** 252+ PHPUnit tests, Playwright e2e suite
- **Deployment:** Deployer with instant rollback capability

---

## Known Limitations

- Content authoring is CLI/admin only — no public content submission yet
- Grandparent Program (structured elder profiles, onboarding) is planned for a future release
- Multi-community coordinator scoping is planned for a future release
- Leadership data requires manual or semi-automated scraping

---

## Acknowledgments

Minoo is built on the traditional territories of the Anishinaabe people. We acknowledge the Knowledge Keepers and Elders whose teachings this platform is designed to honor and preserve.

Dictionary content is made possible by [The Ojibwe People's Dictionary](http://ojibwe.lib.umn.edu), a collaborative project of the University of Minnesota's Department of American Indian Studies and the university's College of Liberal Arts.

Community data sourced from Crown-Indigenous Relations and Northern Affairs Canada (CIRNAC) open data and NorthCloud content platform.

---

*Built by and for Indigenous communities. Miigwech.*
