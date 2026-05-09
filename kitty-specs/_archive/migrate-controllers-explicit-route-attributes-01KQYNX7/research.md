# Research: Migrate Controllers to Explicit Route Attributes

**Mission**: `01KQYNX7` · **Date**: 2026-05-06

## Decision 1: Migration mechanism — `token_get_all` + byte-level rewrite

**Decision**: Use a single PHP CLI script that consumes the file via `token_get_all`, walks tokens to locate the splice points, and rewrites the file by string-splicing at exact byte offsets. Do **not** use a full AST-based rewriter (e.g. `nikic/php-parser`).

**Rationale**:
- The change is purely lexical: insert `#[MapRoute] ` or `#[MapQuery] ` immediately before two specific token sequences (`T_ARRAY T_VARIABLE($params)` and `T_ARRAY T_VARIABLE($query)`).
- `token_get_all` is a built-in extension — no Composer dependency, no autoload, no boot. The script can run on a clean checkout with zero setup.
- An AST-based rewriter would round-trip the entire file, risking incidental whitespace/comment changes that bloat the diff and make code review harder.
- Byte-level splicing preserves every byte of the file outside the splice points: comments, alignment, trailing commas, doc blocks, and original whitespace are all retained.

**Alternatives considered**:
- **Blind regex** (`s/array \$params/#[MapRoute] array \$params/`): rejected. The same byte sequence appears inside method bodies (e.g. `function foo(array $params)` vs. `$x = array(); $params = ...;`), inside docblocks, and inside string literals. Even with anchoring, regex cannot reliably distinguish parameter declarations from other usages.
- **`nikic/php-parser`**: rejected. Adds a Composer dev dependency for a one-shot tool, and round-trip serialization is known to drift on edge cases (heredoc indentation, trailing commas in argument lists). The byte-precision splice is simpler and provably non-invasive.
- **`sed -i`**: rejected for the same reason as blind regex; also platform-dependent (BSD sed vs GNU sed), and any multi-line pattern on PHP source is fragile.

## Decision 2: Use-statement insertion — targeted regex (NOT token-walked)

**Decision**: Insert the two `use` statements via a separate, narrow regex pass that operates on the file's `use` block only. Token walking is overkill for this part.

**Rationale**:
- `use` statements are top-level, file-scoped, and never appear inside method bodies. There's no risk of false matches.
- Insertion order: alphabetical among `Waaseyaa\` uses. The pattern is `^use Waaseyaa\\.*;$` — find the last match, insert the new ones after it. If no `Waaseyaa\` uses exist (rare for these controllers), insert before the first `^use ` statement.
- Idempotency: skip the insert if a `use Waaseyaa\SSR\Attribute\MapRoute;` (or `MapQuery`) line already exists.

**Alternatives considered**:
- **Token-walk the use block**: works but adds complexity. The regex pass is ~10 lines and unambiguous because `use` statements have a strict, file-level grammar.
- **Always append at the bottom of the use block**: rejected. Mixed alphabetization is harder to review than a strict ordering. Project convention (sampled across `src/Controller/AuthController.php`, `src/Controller/EngagementController.php`, etc.) is alphabetical-by-FQCN.

## Decision 3: WP isolation — spec-kitty default worktrees

**Decision**: Each WP runs in its own worktree under `.worktrees/migrate-controllers-explicit-route-attributes-01KQYNX7-lane-{a..f}/`, branched fresh from `main`. PRs squash-merge to `main` independently. No stacked PRs.

**Rationale**:
- The 6 WP clusters are file-disjoint (no two WPs touch the same controller).
- Squash-merge to `main` per WP gives a clean linear history (`migrate(#753): auth+account cluster → MapRoute/MapQuery`).
- Spec-Kitty's default worktree-per-lane model already enforces the isolation; no special setup needed.
- No PR depends on another's intermediate state, so any WP can land first or be reverted without ripple.

**Alternatives considered**:
- **Single feature branch with stacked commits**: rejected. Six commits in one PR is harder to review than six tight PRs, and rollback granularity is per-WP.
- **Single-PR squash of all six clusters**: rejected. 37-file, 173-method change in one PR is a recipe for review fatigue and merge-conflict pain.

## Decision 4: Verification — three-pronged per WP

**Decision**: Each WP must pass all three before the PR can squash-merge:

1. **Unit/integration tests**: `./vendor/bin/phpunit` returns 0 with the baseline `OK (914 tests, 2568 assertions)` (or higher if new tests have been added on `main` since mission creation).
2. **Cold-boot smoke**: start `php -S 0.0.0.0:8080 -t public public/index.php` from a clean process, hit one representative HTTP method per migrated controller, expect HTTP 200 (or documented non-200 like 401/302 for auth-protected routes) and non-zero `Content-Length`. Smoke-route table is in `quickstart.md`.
3. **Cold-boot log scan**: with `WAASEYAA_LOG_LEVEL=notice` (project default), tail the server log during smoke and `grep -F 'dispatcher.deprecation'`. Expect zero entries naming any controller migrated by this WP. (Entries naming controllers in *other* WPs are fine until those WPs land.)

**Rationale**:
- PHPUnit covers the binding semantics — if `array $params` no longer works after attribution, tests that hit those controllers will fail.
- Smoke-routing covers the routing layer — phpunit boots a partial kernel; only a real HTTP request exercises the full dispatcher path.
- Log scan is the only direct evidence that the shim is no longer being invoked. Without it, the test suite could be silent on the deprecation channel even if the shim is firing.

**Alternatives considered**:
- **Just phpunit**: rejected — would miss WSOD-class regressions that don't touch test-covered methods.
- **Just smoke**: rejected — easy to forget controllers, brittle to environment.
- **`bin/waaseyaa schema:check` or similar**: not applicable; this is a routing/dispatcher change, not a schema change.

## Decision 5: Drift handling — WP06 reconciliation

**Decision**: WP06 runs the extractor against the full repo as a final step. If the count > 0 (i.e., a controller was added between #753 filing on 2026-05-06 and WP06 execution), include those controllers in WP06's diff.

**Rationale**:
- The #753 inventory is a snapshot. The repo will continue to evolve during the migration window.
- Catching drift in WP06 (rather than letting it ship a broken extractor that immediately fails CI / lefthook) is the natural fence-posting.
- The extractor itself is the canonical drift detector — using it as the WP06 gate is symmetric and self-checking.

**Alternatives considered**:
- **Run extractor at WP01 and freeze controller additions**: rejected. Cannot block other contributors for a multi-week migration.
- **Add a follow-up issue for drift**: rejected. The whole point of WP06 is "the migration is complete." Drift remediation is part of completion, not a follow-up.

## Decision 6: Migration script lifecycle — transient

**Decision**: `scripts/migrate-controller-attributes.php` is created in WP01, used by WPs 01–06, and **removed** in WP06 before merge. It is never on `main`.

**Rationale**:
- The script's only purpose is the migration. After WP06 lands, there is nothing left to migrate.
- The extractor (`scripts/check-implicit-array-params.php`) is the long-lived guard — it stays on `main`. Keeping the migration script around invites confusion ("which one do I run?") and bit-rot.
- If a future controller introduces an implicit-array param (the extractor catches it), the fix is one-off and can be done by hand or by re-deriving the trivial migration logic.

**Alternatives considered**:
- **Commit migration script too**: rejected. The script is a working tool, not part of the codebase's contract.
- **Move to `scripts/archive/`**: rejected. Same as above — extra files add maintenance overhead with no benefit.

## Decision 7: Smoke-route selection

**Decision**: One representative route per controller, prioritizing routes that exercise the `array $params`/`array $query` binding the migration touches. Prefer GET routes for idempotent smoke; for write-only controllers (e.g. `IngestionApiController`) use the route that doesn't require a fresh CSRF token (typically the GET `/status` endpoint, if available, or a HEAD probe of POST endpoints).

**Rationale**:
- We're verifying that attribute binding still resolves the right values into the right parameters. Hitting the cold-boot dispatcher with a real request proves the binding contract.
- Idempotent smoke means the test is rerunnable across WPs without state contamination.

**Alternatives considered**:
- **All routes per controller**: rejected. ~5x effort for marginal extra confidence.
- **No smoke, only phpunit**: rejected per Decision 4 rationale.

## Open questions (none)

All decisions are locked. No `[NEEDS CLARIFICATION]` markers remain. The plan is ready for `/spec-kitty.tasks`.
