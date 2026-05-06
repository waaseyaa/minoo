# Mission Specification: Upgrade Waaseyaa to alpha.171

**Mission Branch**: `upgrade-waaseyaa-alpha-171-01KQTDC2`
**Created**: 2026-05-04
**Status**: Draft
**Input**: Bring Minoo's pinned `waaseyaa/*` packages from the current alpha.142–143 mix up to alpha.171 (latest on Packagist), absorbing 28 alpha releases of framework drift and reconciling Minoo with new architectural invariants (FieldStorage `_data` symmetry, bundle-naming centralization, new schema diagnostics, kernel-subclass test ban, upstream spec-MCP removal).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Clean composer upgrade resolves (Priority: P1)

A maintainer runs `composer update 'waaseyaa/*' --with-dependencies` on `main` and Composer resolves to alpha.171 across every waaseyaa package without conflicts, version pin contradictions, or platform mismatches. The lockfile is committed and `composer validate --strict` passes.

**Why this priority**: Nothing else in this mission is reachable until dependency resolution is clean. This is the gating step.

**Independent Test**: Run `composer update 'waaseyaa/*' --with-dependencies && composer validate --strict && composer install --dry-run`. Success = all three exit 0 with every `waaseyaa/*` package at `0.1.0-alpha.171` (or compatible) in `composer.lock`.

**Acceptance Scenarios**:

1. **Given** the current `composer.json` waaseyaa constraints, **When** `composer update 'waaseyaa/*' --with-dependencies` runs, **Then** all 40 waaseyaa packages resolve to alpha.171 and Composer reports no conflicts.
2. **Given** the resolved lockfile, **When** `composer validate --strict` runs, **Then** it exits 0.
3. **Given** the optional `repositories` path entries for `entity`, `field`, `genealogy`, **When** the upgrade runs, **Then** those overrides remain honored (or are intentionally removed) and the resolution outcome is documented.

---

### User Story 2 — Test suite passes against alpha.171 (Priority: P1)

A maintainer runs `./vendor/bin/phpunit` and the full Minoo suite (currently 914 tests / 2568 assertions) is green against the upgraded framework. Any new failures introduced by alpha.143 → alpha.171 changes are diagnosed and fixed in Minoo, not papered over.

**Why this priority**: Tests are the primary signal that Minoo's code still composes correctly with the framework. Specifically expect pressure on:
- alpha.158 — `MinimalTestKernel` drained; named kernel subclasses forbidden in `tests/**`. Minoo's integration tests boot `HttpKernel` via reflection.
- alpha.165 — `FieldStorage::Data` read/write symmetry, `_data` value coercion in query builder. Minoo's longstanding "fields live in `_data` JSON blob" gotcha may now be enforced.
- alpha.167–171 — `BUNDLE_SUBTABLE_MISSING` and `ORPHAN_BUNDLE_SUBTABLE` diagnostics may surface real schema issues.

**Independent Test**: `./vendor/bin/phpunit` exits 0 with the same or higher test count and no skipped tests beyond the 3 pre-existing ElderSupport JSON-blob skips.

**Acceptance Scenarios**:

1. **Given** the upgraded framework, **When** `./vendor/bin/phpunit --testsuite MinooUnit` runs, **Then** all unit tests pass.
2. **Given** the upgraded framework, **When** `./vendor/bin/phpunit --testsuite MinooIntegration` runs, **Then** the in-memory SQLite kernel boot succeeds and integration tests pass without relying on a named kernel subclass.
3. **Given** any new framework PHPStan baseline drift, **When** `./vendor/bin/phpstan analyse` runs, **Then** the baseline is regenerated and committed.
4. **Given** `bin/waaseyaa schema:check` runs against the dev DB, **When** alpha.171 diagnostics fire, **Then** any reported drift (column-vs-`_data`, bundle subtable missing/orphan) is resolved by migration or documented as accepted.

---

### User Story 3 — Production parity smoke test (Priority: P2)

A maintainer boots the upgraded app locally (`php -S 0.0.0.0:8080 -t public public/index.php`) and the public surface renders end-to-end with no WSOD, no zero-byte 200s, and no unhandled exceptions in the error log.

**Why this priority**: Past framework jumps (alpha.75 → alpha.107) silently broke `Response::send()` emission and produced zero-byte 200s in production. This mission must not repeat that failure mode.

**Independent Test**: For each canonical route, `curl -sS -o /tmp/page -w "%{http_code}/%{size_download}\n" http://localhost:8080{path}` returns a 200 with body size > 1KB and `<title>` present.

**Acceptance Scenarios**:

1. **Given** the upgraded app is running locally, **When** an anonymous visitor loads `/`, **Then** the public homepage renders with the design system applied and a non-empty body.
2. **Given** an authenticated session, **When** the user loads `/feed`, **Then** the social feed renders with posts and engagement controls.
3. **Given** the games hub, **When** the user loads `/games`, `/games/shkoda`, `/games/crossword`, and `/games/agim`, **Then** each renders without 500s.
4. **Given** the communities surface, **When** a community detail page loads, **Then** the NorthCloud client adapter resolves leadership/band-office data without fatal errors.
5. **Given** Elder Support, **When** the request form loads, **Then** the volunteer signup and coordinator dashboard remain reachable.

---

### User Story 4 — Bimaaji MCP server still ships (Priority: P2)

After upgrading, `composer bimaaji-mcp-install` succeeds and `.claude/settings.json` continues to register both the `minoo` and `bimaaji` MCP servers. Claude Code in this repo can still call `mcp__bimaaji__*` tools.

**Why this priority**: alpha.164 removed *spec* MCP servers upstream. Confirm `waaseyaa/bimaaji` (Minoo's local MCP) is unaffected and the install script still resolves a valid Node entry point.

**Independent Test**: `composer bimaaji-mcp-install` exits 0; `node vendor/waaseyaa/bimaaji/mcp/server.js --help` (or equivalent) returns without throwing.

**Acceptance Scenarios**:

1. **Given** the upgraded framework, **When** `composer bimaaji-mcp-install` runs, **Then** Node deps install cleanly under `vendor/waaseyaa/bimaaji/mcp/`.
2. **Given** Claude Code restarts in this repo, **When** MCP servers register, **Then** `mcp__bimaaji__*` tools appear in the palette.

---

### User Story 5 — Schema diagnostics gate future drift (Priority: P3)

The new alpha.171 diagnostics (`BUNDLE_SUBTABLE_MISSING`, `ORPHAN_BUNDLE_SUBTABLE`, column-vs-`_data` drift) are wired into Minoo's CI surface so schema drift cannot silently land on `main`.

**Why this priority**: Lower priority because not strictly required to ship the upgrade, but failing to wire the new gates means we'll re-discover the same drift on the next upgrade.

**Independent Test**: A test or CI step invokes the diagnostic command and fails the build when it reports drift.

**Acceptance Scenarios**:

1. **Given** a hypothetical entity with a `field_definitions` field that has no corresponding `_data` storage path, **When** CI runs, **Then** the build fails with `BUNDLE_SUBTABLE_MISSING`.
2. **Given** a leftover bundle subtable with no corresponding entity bundle definition, **When** CI runs, **Then** the build fails with `ORPHAN_BUNDLE_SUBTABLE`.

---

### Edge Cases

- **Path repositories for `entity`, `field`, `genealogy`**: These are pinned to `@dev` against sibling worktrees (`../waaseyaa/packages/*`). If those siblings are not at alpha.171, Composer will resolve to whatever is on disk, defeating the upgrade. Either (a) update the sibling tree and tag, (b) temporarily remove the path overrides, or (c) document the hybrid state. Decide explicitly.
- **`HttpKernel` reflection in integration tests**: alpha.158 forbids named kernel subclasses in `tests/**`. Minoo currently boots via `(new ReflectionMethod(HttpKernel::class, 'boot'))->setAccessible(true)`. Verify this pattern is still allowed; if not, find the new sanctioned boot path.
- **Production deploy**: Auto-deploys on push to `main` (per project memory). Do NOT push the upgrade branch to `main` until smoke tests pass locally, and verify the production deploy with body-size checks (not just 200 status).
- **Vendor cache corruption in worktrees**: If the upgrade branch is worked in a worktree, run `composer dump-autoload` in the main repo before merging back.
- **`AuthMailer.isConfigured()` guard**: Verify the registration / password-reset paths still no-op cleanly without a SendGrid key in dev.
- **NC client adapter**: Confirm `Waaseyaa\NorthCloud\Client\NorthCloudClient` constructor signature hasn't changed in alpha.143 → alpha.171; Minoo's adapter passes a config-driven timeout.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: All `waaseyaa/*` constraints in `composer.json` MUST resolve to `^0.1.0-alpha.171` (or equivalent semver-compatible expression that yields alpha.171).
- **FR-002**: `composer.lock` MUST contain alpha.171 for every `waaseyaa/*` package and MUST be committed.
- **FR-003**: `./vendor/bin/phpunit` MUST exit 0 with no new test failures introduced by the upgrade.
- **FR-004**: `bin/waaseyaa schema:check` MUST report no drift after the upgrade. Any drift surfaced by new alpha.171 diagnostics MUST be resolved via migration or explicitly accepted with a documented rationale.
- **FR-005**: The `bimaaji` MCP server MUST remain registered in `.claude/settings.json` and bootable after `composer bimaaji-mcp-install`.
- **FR-006**: The 5 composer providers in `extra.waaseyaa.providers` MUST still match the trailing segment of the compiled package manifest (asserted by `ComposerProviderParityTest`).
- **FR-007**: Local smoke tests against the routes in User Story 3 MUST pass with body sizes > 1KB and `<title>` present in the response.
- **FR-008**: PHPStan baseline MUST be regenerated if new files or framework changes introduce drift; the regenerated baseline MUST be committed.
- **FR-009**: The `CLAUDE.md` "Last framework sync" line MUST be updated to alpha.171, with a brief note on which alphas introduced consequential change.
- **FR-010**: Decision regarding `repositories` path overrides for `entity`, `field`, `genealogy` MUST be made explicit in the implementation plan (keep, remove, or update sibling tree).

### Key Entities

- **`composer.json` / `composer.lock`**: The version pins and resolved tree. Source of truth for installed framework state.
- **`vendor/waaseyaa/*`**: Installed framework code. Tests run against this.
- **`storage/waaseyaa.sqlite`**: Dev DB. Schema diagnostics run against this.
- **`bin/waaseyaa schema:check`**: Existing CLI surface for detecting schema drift; will fire new diagnostics after upgrade.
- **`.claude/settings.json`**: MCP server registration. Must continue to wire `bimaaji`.
- **`CLAUDE.md`**: Tier-1 constitution. Carries the "Last framework sync" line.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `composer show 'waaseyaa/*'` reports `0.1.0-alpha.171` for every waaseyaa package after the upgrade.
- **SC-002**: `./vendor/bin/phpunit` exit code 0; test count >= 914; assertion count >= 2568; skipped count <= 3 (pre-existing ElderSupport).
- **SC-003**: `bin/waaseyaa schema:check` exits 0 with zero drift entries.
- **SC-004**: All 5 smoke-test routes (`/`, `/feed`, `/games`, a community detail page, `/elder-support`) return HTTP 200 with body size > 1KB locally.
- **SC-005**: After deploy to production, the same 5 routes return HTTP 200 with body size > 1KB measured via `curl -sS -o /tmp/page -w "%{http_code}/%{size_download}"`.
- **SC-006**: Time from upgrade-branch creation to merge-to-`main` is recorded for future reference (target: under 4 hours of focused work, contingent on test-fix volume).
