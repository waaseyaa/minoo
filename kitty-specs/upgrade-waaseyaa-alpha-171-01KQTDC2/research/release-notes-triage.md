# Phase 0 Research: alpha.144 → alpha.171 release-note triage

**Generated**: 2026-05-04
**Source**: `gh api repos/waaseyaa/framework/releases` (full body of each tagged release between alpha.144 and alpha.171)
**Purpose**: Enumerate consequential framework changes against Minoo's known surface area; predict touch points and mitigations before tests run.

## Method

Pulled release notes for every tag in the alpha.144..alpha.171 range (only 10 of the 28 alpha bumps actually shipped tagged releases — the rest were silent splits from the framework monorepo). For each release, identified PRs touching Minoo's hot-path packages (`waaseyaa/entity`, `waaseyaa/foundation`, `waaseyaa/api`, `waaseyaa/routing`, `waaseyaa/ssr`, `waaseyaa/testing`).

## Per-alpha summary (consequential changes only)

### alpha.145
Maintenance only. No predicted Minoo touch points.

### alpha.158
- **`MinimalTestKernel` drained** (PR #1322 refactor; PR #1323 drain).
- **`SqlSchemaHandlerRegistryFallbackTest`** added with mutation-recipe docblock (PR #1324) — informational, demonstrates the new test pattern.
- **Forbids named kernel subclasses in `tests/**`** (PR #1325).

**Minoo touch point**: `tests/App/Integration/*Test.php` boot `HttpKernel` via reflection. Two scenarios:
1. Reflection-based boot is the *thing* being banned — refactor to a sanctioned helper.
2. Only literal `class XKernel extends HttpKernel` definitions are banned — Minoo's reflection pattern likely OK.

**Mitigation hypothesis (WP02)**: Run `grep -RIn -E "class \w+Kernel" tests/` first. If clean, the ban likely doesn't bite. Read `vendor/waaseyaa/testing/` for any new `bootForTesting()` helper or trait.

### alpha.164
- **Spec Kitty adopted upstream** (PR #1348). The waaseyaa monorepo itself migrated to spec-kitty for spec authoring.
- **Spec MCP servers REMOVED** (#1347). The `waaseyaa_*` and `minoo_*` retrieval MCPs go away — spec retrieval moves to spec-kitty's MCP surface.
- Telescope agent-context telemetry, SPA-aligned codified-context JSON (#1339).
- Vocabulary field definitions match test expectations (#1357).
- Attachment uses `iterator_to_array()` for `DBALSelect` Generator result (#1360).

**Minoo touch point**: `.claude/settings.json` registers `minoo` and `bimaaji` MCP servers. The `minoo` server here is Minoo's own MCP (mcp/server.js), not the waaseyaa spec MCP — so it's unaffected. `bimaaji` is also a separate MCP (Anishinaabemowin bridge), unaffected. The spec-MCP removal is upstream-only.

**Mitigation hypothesis (WP06)**: `composer bimaaji-mcp-install` should still work; verify with a live run.

### alpha.165 (Mission #1257 — biggest behavior change in the gap)
- **WP02 (PR #1361)**: architectural remediation umbrella.
- **WP03 (PR #1362)**: bundle-naming centralization.
- **WP04 (PR #1363)**: read/write symmetry for `FieldStorage::Data`.
- **WP05 (PR #1364)**: `_data` value coercion in query builder.
- **WP06 (PR #1365)**: bundle-load drift logging.
- **WP10 (PR #1367)**: declarative `tenancy` slot on `EntityType`.
- **WP11 (PR #1366)**: kernel-path integration lock.
- Missions migrated from `.kittify/missions/` to `kitty-specs/` canonical location (PR #1369).
- Mission #529 §15 ratified (PR #1370).

**Minoo touch point — HIGH CONFIDENCE**: Per CLAUDE.md gotcha, "Entity fields live in `_data` JSON blob: target_type, target_id, user_id, created_at on engagement entities (reaction, comment, follow) are NOT real SQL columns." The `_data` symmetry tightening will likely surface tests that previously passed by accident.

**Predicted failing surfaces**:
- `tests/App/Unit/Entity/EngagementEntityTest.php` (or equivalent) — engagement entities most likely.
- Access policy tests that compare entity fields read via mixed paths.
- Any custom raw-SQL access against entity tables (CLAUDE.md note: "Raw SQL queries against them fail").

**Mitigation hypothesis (WP03)**:
1. Run `--testsuite MinooUnit` first; capture verbatim.
2. For each failure, check whether the entity class declares all `_data`-stored fields in `fieldDefinitions`, and whether tests use `$entity->get('field')` not `$entity->field` or array access.
3. If broad refactoring needed (touching most of 17 entity types), pause and escalate per Complexity Tracking.

**Tenancy slot (WP10 of #1257)**: Minoo's CLAUDE.md says "All 7 entities with `community_id` fields implement `HasCommunityInterface`" — those entities may now need to declare `tenancy` in their EntityType registration. Watch for `EntityType` constructor / fluent setter changes.

### alpha.166–168
PRs not in scope of release notes pulled — likely incremental fixes. No specific predictions.

### alpha.169
PRs not detailed in pulled notes.

### alpha.170
PRs not detailed in pulled notes.

### alpha.171
- **fix(oidc)**: `appendQuery()` for login redirect (#1377). No Minoo touch point unless Minoo uses OIDC — currently does not.
- **feat(diagnostic)**: detect column-vs-`_data` storage drift (#1378).
- **feat(ci)**: `bin/check-symfony-imports` — Path R-narrow boundary linter (#1379). Informational.
- **feat(entity)**: emit `BUNDLE_SUBTABLE_MISSING` at `addBundleFields` registration (#1380).
- **fix(diagnostic)**: portable `ORPHAN_BUNDLE_SUBTABLE` detection (#1381).
- **fix(composer)**: root manifest uses `self.version` for waaseyaa/* siblings (#1383). Touches the framework monorepo's composer manifest — Minoo unaffected unless our `composer.json` references siblings via `@dev`.

**Minoo touch point — HIGH CONFIDENCE**: The three diagnostics (column-vs-`_data`, `BUNDLE_SUBTABLE_MISSING`, `ORPHAN_BUNDLE_SUBTABLE`) will fire against Minoo's existing schema if there's any drift — and there almost certainly is, given the `community_id` columns and other historical column-vs-`_data` patterns.

**Mitigation hypothesis (WP04)**:
1. `bin/waaseyaa schema:check` will produce findings.
2. Generate migrations to either drop redundant columns or backfill `_data`.
3. Migrations MUST use the `_data` CLOB schema (not individual columns).

## Path-repository decision (FR-010)

`composer.json` references three sibling packages via `repositories` path entries:
- `waaseyaa/entity` → `../waaseyaa/packages/entity` (currently at @dev)
- `waaseyaa/field` → `../waaseyaa/packages/field` (currently at @dev)
- `waaseyaa/genealogy` → `../waaseyaa/packages/genealogy` (currently at @dev)

**Decision needed at WP01/T002**:
- Option A: Update sibling tree to alpha.171 commit and tag locally.
- Option B: Remove path overrides for this upgrade; restore later for sibling-repo work.
- Option C: Leave hybrid; document the divergence.

**Recommendation**: Option B (remove for this upgrade). Reasoning:
- The mission's purpose is to land alpha.171 cleanly across all 40 packages.
- Hybrid state (some packages from Packagist, some from path overrides) defeats the upgrade's intent.
- Sibling-repo work for entity/field/genealogy can re-add the overrides as a separate scoped change after the upgrade ships.

WP01/T002 should make this decision explicit and document it in `composer.json` and a research note.

## Predicted Minoo touch-point list

| Source of change | Predicted Minoo file(s) | Predicted WP |
|------------------|--------------------------|--------------|
| alpha.158 kernel-subclass ban | `tests/App/Integration/*Test.php` | WP02 |
| alpha.165 `_data` symmetry | `src/Entity/Reaction.php`, `src/Entity/Comment.php`, `src/Entity/Follow.php`, `src/Entity/Post.php`, plus engagement test files | WP03 |
| alpha.165 `_data` query coercion | Any controller/test doing raw entity queries | WP03 (extension) |
| alpha.165 declarative `tenancy` | Entity types implementing `HasCommunityInterface` (7 types) | WP03 (if needed) |
| alpha.171 column-vs-`_data` diagnostic | Schema state, possibly `community_id` columns | WP04 |
| alpha.171 `BUNDLE_SUBTABLE_MISSING` | New migrations | WP04 |
| alpha.171 `ORPHAN_BUNDLE_SUBTABLE` | Drop migrations for retired bundles | WP04 |
| alpha.164 spec-MCP removal | NONE (Minoo's MCPs are separate) | WP06 (verification only) |
| (PHPStan drift from any of above) | `phpstan-baseline.neon` | WP05 |
| (CLAUDE.md sync line) | `CLAUDE.md` | WP08 |

## Open unknowns (not blocking but worth noting)

1. **Sanctioned kernel-boot helper**: alpha.158 banned named subclasses but I haven't traced the framework's positive guidance. WP02/T008 must read `vendor/waaseyaa/testing/` (or use `waaseyaa_get_spec testing` MCP if still available) before deciding the refactor.
2. **Tenancy slot signature**: alpha.165 added a `tenancy` slot on `EntityType`. Unknown whether existing `HasCommunityInterface` entities need code changes or framework-side reconciliation handles it. WP03 will surface this.
3. **alpha.166–170 silent gap**: 5 alpha bumps without distinct release notes. Either no significant changes, or changes were squashed into the .165/.171 release announcements. WP02–WP04 test runs will surface anything missed here.

## Confidence

- **WP02 (kernel ban)**: Medium — pattern is known, refactor surface depends on whether reflection counts as a named subclass.
- **WP03 (`_data` symmetry)**: Medium — high confidence the change will surface failures, low confidence on volume (could be 2 fixes, could be 20).
- **WP04 (schema diagnostics)**: High — diagnostics will produce findings; remediations are mechanical.
- **WP06 (bimaaji MCP)**: High — should be unaffected, verification is cheap.
