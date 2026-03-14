# Changelog

All notable changes to the Minoo project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
