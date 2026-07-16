# Changelog

All notable changes to the Minoo project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Navigation links for `/events`, `/groups`, and `/chat` in the sidebar (#920) — all three pages were fully built but unreachable.
- 301 redirects preserving old URLs: `/media/corpus/video/{id}` → `/lessons/media/video/{id}` (stored in pre-2026-07 corpus rows), `/home` → `/`, `/studio` → `/` (#920).

### Changed
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
