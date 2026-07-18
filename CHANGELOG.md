# Changelog

All notable changes to the Minoo project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Feed engagement UI (#817): `public/js/engagement.js` wires the inert feed-card buttons to the social spine API — optimistic reaction toggle, inline comment view/add, share, and delete-own-post — plus an authenticated-only Feed entry in the sidebar navigation.
- Navigation links for `/events`, `/groups`, and `/chat` in the sidebar (#920) — all three pages were fully built but unreachable.
- 301 redirects preserving old URLs: `/media/corpus/video/{id}` → `/lessons/media/video/{id}` (stored in pre-2026-07 corpus rows), `/home` → `/`, `/studio` → `/` (#920).

### Changed
- Upgraded the Waaseyaa framework `v0.1.0-alpha.249 → v0.1.0-alpha.267` (58 packages; adds `waaseyaa/groups`, drops `psr/simple-cache` + `lcobucci/clock`; `waaseyaa/bimaaji` exact pin moved in lockstep) (#920). App adaptations ride the same branch:
  - C-22 repository migration: every `getStorage()` call site (src, scripts, tests, migrations) moved to the repository API; storage-chain test mocks rewritten.
  - Machine-JSON fields retyped `text_long` → `text` (game_session `guesses`/`grid_state`/`found_objects`, crossword_puzzle `words`/`clues`, post `images`): alpha.255+ sanitizes `text_long` as HTML at every read boundary and would mangle JSON served over generic surfaces. Storage shape (`_data` blob) unchanged — no migration.
  - Deleted the `Waaseyaa\Mcp\Bridge` adapter layer (interfaces removed upstream between alpha.249 and alpha.267).
  - Test harness: a PHPUnit extension resets `SsrServiceProvider`'s static Twig cache between test classes — at alpha.267 the previous kernel's already-initialized Twig environment leaks into the next kernel boot in the same process and crashes `SeoServiceProvider::boot()` (framework finding; production single-boot processes unaffected).
- Renamed the `group` entity type to `community_group` across the app — registration, entity class, access policy, controllers, feed domain, engagement target types, templates, dynamic i18n keys, and the runtime-generated `feed-card--` CSS selector — with a shape-aware migration that renames the SQL table, folds legacy columns/subtable rows into `_data`, and drops `group__business`/`group_type` (#923). The `/groups` URL surface, `groups.*` route names and i18n keys, `?filter=group` param, and search badge token are unchanged.
- Retired Spec Kitty as the execution layer; Minoo now follows the design-first + anchor-issue workflow (`docs/specs/workflow.md`). `.kittify/` removed; `kitty-specs/` kept as read-only history (#920).
- De-registered four vestigial config entity types (`group_type`, `event_type`, `dialect_region`, `cultural_group`) — bundle validity lives in `ConfigSeeder` static arrays; no data migration needed (#920).
- New corpus imports write lesson-route video URLs (`/lessons/media/video/{id}`) (#920).

### Removed
- Legacy PHP Deployer stack (`deploy.php`, `ops/`, `deploy/`, `deploy.yml` workflow) and broken ops bins (`bin/migrate`, `bin/cache-clear`, `bin/validate-release`, golden-index/audit-site tooling) — production deploys live in `waaseyaa-infra` (#920).
- Newsletter era: 37MB admin SPA, built bundle + gitignore carve-out, render script, assets, CSS print block (#920).
- Messaging/crisis-era remnants: orphaned `public/js/msg/` bundle, crisis config/images/lang keys, dead `MERCURE_*` env keys (#920).
- Zero-caller public routes: per-game stats endpoints (×5), `crossword.abandon`, `corpus.context` (#920).
- Dead code: unrouted controller methods, unregistered `SecurityHeadersMiddleware`, stale engagement target types (#920).

### Fixed
- Sidebar nav active-state now fires: a render-time `current_path()` Twig function (language prefix stripped) feeds the template's `current_path` comparisons, which no layer previously provided; `/feed` highlights Home (#922).
- `composer phpstan:dead-code` gate runs again — 45 stale baseline entries removed, route-string concat controllers now recognized (#920).

## [1.0.3] — 2026-03-14

### Changed
- Pinned Waaseyaa framework dependency to v0.1.0-alpha.1 (from @dev)
- Updated minimum-stability to alpha
- Added explicit version options to path repositories for version resolution

## [1.0.2] — 2026-03-14

### Added
- Leadership pipeline integration with NorthCloud (#189, #190, #191)
  - Community source linking via NC API
  - Leadership scrape job creation
  - Leader entity type with ingestion pipeline
- Dictionary source switched to NorthCloud API (#203)
  - Paginated sync with dry-run mode
  - Dual-format mapper (OPD + NC API)
  - Attribution tracking
- Consent fields (`consent_public`, `consent_ai_training`) on all content entities (#202 Gate 3)
- Copyright filtering on HomeController and PeopleController (#202 Gate 2)
- Export governance: `bin/export-communities` requires `--confirm` flag (#202 Gate 4)
- Migration runner with transaction wrapping and deploy integration
- Initial schema migration (001) for community tables
- Dry-run flag for `bin/backfill-nc-ids` (#186)

### Changed
- `composer.json` license corrected from GPL-2.0-or-later to MIT (#202 Gate 1)

### Fixed
- Migration runner: transaction wrapping to prevent partial applies
- Migration runner: source `shared/.env` before running `bin/migrate` in deploy
- Migration 001: removed community_type backfill that caused issues on fresh installs

## [1.0.1] — 2026-03-13

### Added
- Community registry sync with NorthCloud (#135, #177)

### Changed
- Added `.superpowers/` to `.gitignore`

## [1.0.0] — 2026-03-12

### Added
- Initial V1 release of Minoo Indigenous knowledge platform
- Elder Support Program with 6-state volunteer matching workflow
- Community Registry with 637 First Nations communities from CIRNAC open data
- Anishinaabemowin dictionary with entries, example sentences, word parts, and speaker data
- Teachings and Events browsing with card-based layouts
- Full-text search across all content types
- Server-side rendered Twig templates with vanilla CSS design system
- 252+ PHPUnit tests and Playwright e2e suite
- Deployer-based deployment with instant rollback
- AI chat feature (disabled by default)
