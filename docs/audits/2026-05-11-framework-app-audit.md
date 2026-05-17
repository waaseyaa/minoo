# Framework / App Audit — Minoo + Waaseyaa — 2026-05-11

**Context:** A deliberate hostile-Laravel-dev review of Minoo (the flagship Waaseyaa app) and Waaseyaa (the framework underneath it), produced as a stress test on the framework's reason to exist. The exercise: assume the reader is a snooty Laravel veteran skeptical of any custom framework, surface every defensible criticism, then strip the rhetoric and keep only what's actionable. This document is the stripped version.

**Method:** Reread the Minoo CLAUDE.md gotcha list, the orchestration table, the open issue references (#520, #618, #493, #44, #48, #749, #750, #751), the framework alpha sync line (alpha.107 → alpha.175), and the directory layout. Cross-reference against what Laravel + a thin domain package would have given us "for free." Group findings by theme. For each: severity, evidence, repo of record, and what a fix would actually entail.

**Audience:** Russell, as keeper of Waaseyaa. Findings phrased so each can become an issue, a mission brief, or a deliberate "no, we keep it" decision.

---

## Summary

| Bucket | Count |
|---|---|
| Findings — high severity | 5 |
| Findings — medium severity | 7 |
| Findings — low severity / smell | 3 |
| Things to preserve | 4 |
| Mission candidates | 10 |

Repos:
- **W** = `waaseyaa/framework` (the platform)
- **M** = `waaseyaa/minoo` (the app)
- **W+M** = work spans both

---

## Findings

### F1 — Entity fields live in a `_data` JSON blob, not real columns  **[high · W+M]**

Every content entity stores its field values as a JSON string in a `_data` CLOB column. `target_type`, `target_id`, `user_id`, `created_at` on engagement entities are not SQL columns. Raw SQL `WHERE` against them fails; entity queries work because `SqlEntityStorage` extracts from the blob in PHP. Indexes, foreign keys, and query-planner statistics are forfeit by construction. Production user lookup is documented as `WHERE _data LIKE '%field_value%'`.

This is the load-bearing decision in the framework. Downstream symptoms: the `schema:check` drift detector, the migration generator, the integration tests that can't use raw `WHERE`, the engagement-query gymnastics, the perceived need for a custom storage layer at all. Issue #520 tracks adding real columns for engagement entities; no scope beyond engagement is in flight.

**Fix scope:** Framework support for column-backed fields (FieldDefinition → migration generator → SqlEntityStorage column-aware reads), then per-entity migrations in Minoo. This is the single highest-leverage change in the audit.

### F2 — SQLite in production, single-writer, no replication path  **[high · M, strategic]**

Production runs against `storage/waaseyaa.sqlite` on one box (`/home/deployer/minoo/current`). One writer process. No replication, no read-replica story, no documented migration path to Postgres/MySQL. Fine for alpha and for a low-traffic community site; a liability the day two coordinators hit Elder Support concurrently, or the day a backup restore is needed under pressure.

This finding interacts with F1: if the answer to "what database in 12 months" is Postgres, then the `_data` blob fix should target a schema that exists in Postgres, not a SQLite-shaped one. Sequence matters.

**Fix scope:** A written decision (`docs/decisions/`-style) — when do we migrate, to what, what triggers the migration, what tests prove it. Not the migration itself; the decision.

### F3 — Framework alpha churn taxes the consumer  **[high · W, organizational]**

Sync line history: alpha.75 → alpha.107 → alpha.155 → alpha.173 → alpha.175 inside a few months. Each bump has carried at least one breaking change requiring app-side migration:

- alpha.106 — controllers return `Symfony\Component\HttpFoundation\Response`; every controller touched.
- alpha.107 — community-scoped tenancy + `SovereigntyProfile` + `AppControllerRouter`.
- alpha.173 — `HasCommandsInterface` opt-in marker; without it commands silently disappear from the CLI.
- alpha.175 — `symfony/console` hard-cut, `HasCommandsInterface` → `HasNativeCommandsInterface`, command classes split into Handler + `CommandDefinition`.

Each of these was the right framework call. Cumulatively they cost Minoo sprints that didn't ship user-visible value. The framework has no published API-stability contract, no deprecation window, and no notion of "beta." Today Minoo is the only consumer, so the tax is hidden. The day there's a second Waaseyaa app the tax triples and good framework decisions start getting refused on migration cost.

**Fix scope:** A stability charter — define beta criteria, name the stable surface, commit to a deprecation window (e.g. "shim ships in N, removal in N+2"), publish an upgrade-guide template.

### F4 — Silent-failure modes in the boot/registration path  **[high · W]**

Several places where forgetting one line gives you no error, just wrong behavior:

- `HasCommandsInterface` (alpha.173+): provider without the marker → commands silently absent at CLI. No warning, no log.
- `SsrPageHandler::resolveControllerInstance()` uses a hardcoded `$serviceMap` plus a singleton-resolver fallback. A service not registered as a singleton in a provider → not injected, with whatever runtime symptom that produces downstream.
- `PackageManifestCompiler` caches to `storage/framework/packages.php`; stale cache prevents new providers/policies from being discovered. The documented fix is `rm storage/framework/packages.php`.
- `ComposerProviderParityTest` exists in CI specifically because the `composer.json` provider list and the compiled manifest drift in practice.

Each one is defensible in isolation. Together they say: the framework's registration model trusts the developer to remember rituals, and the cost of forgetting is invisible.

**Fix scope:** Either make the framework loud (boot-time assertions, `composer dump-autoload` integration, registration-failure logs at notice level), or make registration declarative enough that forgetting isn't possible. The parity test is a good smoke but a bad UX — it tells you after CI runs, not at the moment of error.

### F5 — Admin surface non-functional pending session middleware  **[high · M]**

Tracked in #597 (contract mismatch) and #618 (session auth). `/admin/surface/session` returns 200 instead of 401 when unauthenticated. The deploy health check was disabled to work around it. Admin panel does not function. This is a V1 (#19) blocker.

**Fix scope:** Session middleware fix → re-enable health check → admin smoke test in CI.

**RESOLVED (2026-05-17, alpha.180 upgrade):** Both diagnoses were wrong. (1) The real path is `/admin/_surface/session` (underscore prefix added upstream in `b5afb62c6`); the audit/deploy probe pointed at the no-underscore URL, which hit the SPA catch-all instead of the session handler. (2) The framework's session contract is intentionally envelope-based — HTTP 200 with `{"ok":false,"error":{"status":401}}` when anonymous (commit `9c3f39c64 fix(admin-surface): use requireSession on session endpoint, not requireAuthentication`), so the rationale lives in the SPA needing to distinguish "logged out" from "endpoint missing". The actual blocker was an off-by-one in `AdminRouteProvider`'s `dirname(__DIR__, 2)` (should be `, 3`) that made the SPA catch-all return "Admin interface not available." for every `/admin/*` path. Catch-all fixed; deploy health check re-enabled in `deploy.php` with the correct URL and envelope-shape body assertion (`"ok":false` + `"status":401`). #618 closed.

### F6 — Console kernel broken on production  **[medium · W]**

Issue #493. Missing `SqliteEmbeddingStorage` class crashes all CLI commands invoked via `ConsoleKernel`. The current workaround is documented in CLAUDE.md: boot `HttpKernel` via reflection in one-liner scripts (see `scripts/populate_featured.php`). This is a production-grade workaround for a framework-grade bug, and it dilutes the contract that `bin/waaseyaa <command>` is the way to run things.

**Fix scope:** Resolve #493 in framework; verify all `bin/waaseyaa` commands run on production; remove the reflection workaround from CLAUDE.md gotchas.

### F7 — Boot/emit contract is fragile and undocumented  **[medium · W]**

The WSOD incident (commit 6c4e755, alpha.75 → alpha.107 jump) came from gating `$response->send()` on `PHP_SAPI === 'cli-server'` in `public/index.php`. Under Caddy + PHP-FPM the body never emitted; every route returned 200 with zero content-length. Headers were sent normally so HTTP-status checks passed.

Two structural problems: (a) the right shape of `public/index.php` is tribal knowledge, not enforced by the framework, and (b) the canonical health check (HTTP 200) cannot detect this failure mode. CLAUDE.md now warns to verify with body size, but that's a procedural fix for a contract gap.

**Fix scope:** Framework ships a canonical `public/index.php` template (or a one-line bootstrap helper). CI smoke-tests body size against a known route. Add a "zero-byte 200" assertion to the deploy health check.

### F8 — Mocking surface requires deep framework knowledge  **[medium · W, DX]**

Documented gotchas: mock `ContentEntityBase` not `EntityInterface` (because `get()`/`set()` are on the concrete base, not the interface); `set()` must `return $mock;` from callbacks because it returns `static`; DI resolves `EntityTypeManager` concrete, not `EntityTypeManagerInterface`. Individually trivial; collectively they say the public testing surface is the concrete class hierarchy, not the interfaces.

**Fix scope:** Either expose `get`/`set` on `EntityInterface` (or a `FieldableInterface` that the framework's own test helpers reference), publish entity test doubles (`Waaseyaa\Testing\FakeContentEntity`), or both. Cuts the gotcha count and lets tests target the contract.

### F9 — Implicit-array dispatcher migration debt is logged but unowned  **[medium · W+M]**

The post-#1390 dispatcher shim emits notices on every controller method invocation with an implicit-array signature. Minoo lowered `log_level` to `notice` to make them visible. CLAUDE.md publishes the grep recipe to extract the backlog: `grep -F 'dispatcher.deprecation' <log> | sed -E '...' | sort -u`. As of this audit no one owns working that list down.

**Fix scope:** Run the grep against a recent production log, dump unique findings into a Minoo issue, work through them in batches. Then turn off the framework's tolerance for the implicit form and remove the shim.

### F10 — CLAUDE.md gotcha count is the framework health metric  **[medium · M, meta]**

~80 bullets in Minoo's CLAUDE.md "Gotchas" section. Each one is, in the strictest reading, a leaked framework abstraction or an app-side workaround for a framework limitation. Examples that aren't really domain gotchas: `dirname(__DIR__, 3)` paths, `EntityStorage::delete()` takes an array, `count()->execute()` returns `[N]`, `trans()` is a Twig function, `final` blocks PHPUnit mocking, autoloader corruption in worktrees.

The list is honestly maintained and load-bearing — it's why the project ships at all. But its size and trajectory are the most informative single signal about the framework's maturity.

**Fix scope:** Triage pass — for each gotcha, classify as **keep** (real domain knowledge), **fix-at-framework** (file a framework issue), **fix-at-app** (file a Minoo issue), or **stale** (delete). Track gotcha count between framework releases. Net growth = abstraction is leaking faster than it's sealing.

### F11 — Worktree workflow has known sharp edges  **[medium · M]**

Documented worktree gotchas: autoloader corruption requires `composer dump-autoload` post-merge; vendor symlinks don't survive cleanup; pre-push hook skips PHPUnit silently when `vendor/` is missing; the `/simplify` skill leaks edits into the main repo; cross-PR entity field conflicts surface after merge; squash-merge silently drops one side of a conflict on overlapping files.

This is a different shape of problem from F4 — it's the spec-kitty / parallel-agent workflow rubbing against composer's assumptions, not strictly a framework issue. But it costs sprints on merge-fix passes.

**Fix scope:** Worktree-aware pre-push hook (fail loudly if vendor missing, not skip). Post-merge `composer dump-autoload` in main repo as a documented step. Squash-merge banned for overlapping-file PRs; rebase-merge instead.

### F12 — Crossword + games gaps left as gotchas, not issues  **[medium · M]**

Documented: only easy-tier crossword puzzles exist; medium/hard returns 500. Themed puzzle packs tab is blank with no empty state. These are real product gaps captured in CLAUDE.md "Gotchas" alongside framework foot-guns. Wrong location — gotchas are for knowledge, issues are for tracking. See feedback_document_as_issues memory.

**Fix scope:** Move these to GitHub issues, remove from gotchas, link from games milestone (#47).

### F13 — DI registration parity test is a smell  **[low · W+M]**

`ComposerProviderParityTest` asserts the five providers in `composer.json` match the compiled package manifest. The test exists because, in practice, those drift. A parity test is the right safety net; the underlying need for it suggests the registration story is more brittle than it looks. Folds into F4.

### F14 — Reflection used to boot the kernel in tests and scripts  **[low · W]**

Integration tests boot `HttpKernel` with reflection because `boot()` is protected. Production scripts (`populate_featured.php`) do the same as workaround for F6. Reflection-as-API works but signals that the kernel's public surface doesn't quite match its real consumer set.

**Fix scope:** Decide whether the kernel should expose a public `boot()` for test/script use, or whether the framework ships test helpers (`Waaseyaa\Testing\KernelHarness`) that own the reflection internally.

### F15 — `EntityTypeManager` interface vs concrete confusion  **[low · W]**

Providers must `resolve(EntityTypeManager::class)`, not `EntityTypeManagerInterface::class`, because the kernel registers the concrete only. Documented gotcha. Either the interface should be the registered binding, or the interface shouldn't exist. Currently it's both: published, but inert.

---

## Things to preserve

These are not findings — they're the parts of the system that justify the custom framework existing at all. The audit explicitly keeps them off the change list.

### P1 — The domain model

Elder, Knowledge Keeper, Teachings, dialect-aware content, community-scoped tenancy, sovereignty profiles, access policies as first-class composable units with `#[PolicyAttribute]`. This is the part Laravel's Eloquent + Policy stack cannot give you without fighting its grain. The domain modeling is thoughtful, and it's the reason Waaseyaa exists.

### P2 — Frontend / SSR approach

Single hand-written `public/css/minoo.css` with `@layer`, oklch, container queries, native nesting, fluid `clamp()`. No build step. Twig 3 inheritance, components folder, path-based template routing. Taste, restraint, and a working dev loop with zero tooling. Don't touch this.

### P3 — Test discipline

914 tests / 2568 assertions, PHPStan level 5, ShipMonk dead-code pass with custom usage providers, lefthook pre-commit + pre-push, integration kernel-boot smoke. Most Laravel codebases I've reviewed have less than half of this. Keep raising it.

### P4 — Codified-context system

CLAUDE.md as constitution, skills as specialist tier, specs retrieved via MCP, drift detector at SessionStart, Spec Kitty as execution layer. This is the most interesting *engineering process* artifact in the repo and it has nothing to do with Waaseyaa-the-framework specifically. It's also a publishable pattern (see M9 below).

Caveat acknowledged in F10: the codified-context system is partly load-bearing scaffolding for an alpha framework. That doesn't make it less valuable — it makes it more reusable, because every alpha framework needs one and almost none have one.

---

## Mission candidates

Named, briefed, with dependency arrows. Each is a Spec Kitty mission candidate, not a mission yet. Repo of record listed; missions filed in whichever repo owns the work.

| # | Name | Repo | Brief | Depends on |
|---|---|---|---|---|
| M1 | **Framework stability charter** | W | Define beta criteria, public stable surface, deprecation window, upgrade-guide template. Output: `docs/specs/stability-charter.md` + a tagged beta release plan. | — |
| M2 | **Production data layer decision** | M | Written decision: when do we leave SQLite, for what, under what trigger. Output: `docs/decisions/2026-XX-data-layer.md`. | — |
| M3 | **Column-backed fields** | W+M | Framework support for `FieldDefinition::column()` → SqlEntityStorage column-aware reads + writes → migration generator. Per-entity migrations in Minoo follow. Closes #520 and the long tail. | M1, M2 |
| M4 | **Boot/registration loudness** | W | Make silent-failure modes loud: `HasCommandsInterface` warning when provider returns commands without the marker; `SsrPageHandler` injection-failure log; manifest cache invalidation tied to `composer dump-autoload`. Retire `ComposerProviderParityTest` as a side effect. | M1 |
| M5 | **Console kernel rehabilitation** | W | Fix #493 (missing `SqliteEmbeddingStorage`); restore `bin/waaseyaa` on production; remove reflection-as-API workaround from Minoo scripts. | M1 |
| M6 | **Boot/emit contract** | W | Canonical `public/index.php` template shipped by framework. Body-size assertion in deploy health check. Documented response lifecycle. | M1 |
| M7 | **Testing surface cleanup** | W | Publish `Waaseyaa\Testing\FakeContentEntity` + entity test doubles. Move `get`/`set` to `FieldableInterface` (or equivalent) so tests can target contracts. Cuts ~6 gotchas. | M1 |
| M8 | **Gotcha decimation** | M | Triage all ~80 CLAUDE.md gotchas: keep / framework-issue / app-issue / stale. Net target: <30. Track gotcha count between framework releases as a health metric going forward. | runs alongside M3–M7 |
| M9 | **Implicit-array migration backlog** | M | Run `dispatcher.deprecation` grep against production logs, file backlog as a Minoo issue, work through. Framework removes the shim once Minoo is clean. | — |
| M10 | **Admin surface session fix + V1 unblock** | M | ~~Resolve #618 (session middleware), re-enable deploy health check for admin, smoke-test admin panel in CI.~~ **DONE 2026-05-17.** Actual root cause was an off-by-one in `AdminRouteProvider`'s project-root resolver, not session middleware. Deploy health check re-enabled with corrected URL (`/admin/_surface/session`) and envelope-shape body assertion. #618 closed. | — |

### Dependency sketch

```
M1 (stability charter) ──┬─→ M3 (columns)        ┐
                         ├─→ M4 (loud boot)      │
                         ├─→ M5 (console)        ├─→ M8 (gotcha decimation)
                         ├─→ M6 (emit contract)  │
                         └─→ M7 (testing surface)┘

M2 (data layer) ─────────→ M3 (columns)

M9 (implicit-array)  — independent, ongoing
M10 (admin session)  — independent, V1 critical path
```

### Sequencing notes

- **M1 first.** Everything framework-side depends on a published stability story. Without it, M3–M7 ship as more alpha churn — exactly the problem F3 describes.
- **M2 before M3.** No point migrating engagement fields to SQLite columns if the 12-month target is Postgres. Decide the destination, then move.
- **M10 in parallel.** V1 blocker, doesn't depend on framework work.
- **M9 in parallel.** Long-tail cleanup, can interleave with anything.
- **M8 last but continuous.** Gotcha decimation can't precede the fixes that retire each gotcha; it's the closing pass.

### Out-of-scope for this audit

Two related artifacts came up in the review but didn't make the mission list because they're publication artifacts, not engineering missions:

- **Domain model articulation.** A written steelman of why Waaseyaa's domain primitives don't fit Eloquent's grain. Useful for hiring, for outside readers, and for keeping yourself honest about which framework decisions are earning their keep. Suggested as a `docs/` essay, not a mission.
- **Codified-context portability writeup.** Extract the Spec Kitty + MCP + skills + drift-detector pattern as a reusable methodology. Publishable independently of Waaseyaa. Suggested as a blog post or a small standalone repo.

---

## Reading order for whoever picks this up next

1. F1, F2, F3 — the three load-bearing critiques. Everything else folds into these.
2. P1–P4 — what we keep, so the missions don't accidentally regress them.
3. M1 — the charter mission. Drives everything framework-side.
4. M10 — V1 unblock, in parallel.

Everything else is detail.
