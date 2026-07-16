# Anokii parity and the language module

Status: recon complete, decisions locked, nothing built. This is a documentation and
planning artifact. No feature code, routes, providers, entities, or migrations were written
to produce it. Companion to [anishinaabemowin-language-api-tracker.md](anishinaabemowin-language-api-tracker.md),
which owns the language-API vision and the translation-memory / `/api/lang` / dialect / consent
decisions. This doc owns how that work plugs into Anokii.

Constraints that govern every entry here: sovereign stack, no em dashes anywhere, fnprocure / fnpi
and the Pi untouched. Report before building.

## The reframe (read this first)

There is no single canonical Anokii admin that one app runs and another stubs. The facts from the
recon:

1. **Anokii is an opt-in library, not an auto-mounting plugin.** The package
   `vendor/waaseyaa/anokii` has **no `extra.waaseyaa.providers`** block in its `composer.json`, so it
   registers nothing on its own. Each app's own `App\Provider\AnokiiServiceProvider` decides what to
   wire. (minoo has the package at alpha.10, fnpi at alpha.9; both declare `App\Provider\AnokiiServiceProvider`.)
2. **fnprocure (`C:\Users\jones\Local Sites\fnpi-waaseyaa`) is Anokii's fullest consumer, and it
   hand-codes FNPI business modules that minoo must NOT copy.** Its `src/Provider/AnokiiServiceProvider.php`
   hardcodes a ~47-route tree at priority 100 for nine live modules backed by app controllers in
   `src/Controller/*` and app entities in `src/Entity/*`. Most of those are FNPI domain (ventures,
   documents, drive, pages, identity pillars, contact inbox, analytics), not Anokii core.
3. **The package ships a clean module seam that nobody fully uses yet.** Two parts: the brand-neutral
   module catalog `Anokii\Admin\AdminModules` (`vendor/waaseyaa/anokii/src/Admin/AdminModules.php`),
   and the config-gated module-provider pattern in `Anokii\Provider\CoIntelligenceServiceProvider`
   (`vendor/waaseyaa/anokii/src/Provider/CoIntelligenceServiceProvider.php`). fnprocure bypasses both
   and hardcodes; minoo uses neither.
4. **minoo already has a working language corpus pipeline.** Its `/admin/anokii` today is a 5-stage
   pipeline (Ingest, Transcribe, Curate, plus an Overview), reusing only the package shell template via
   the `@anokii` Twig namespace. The feature layer is entirely minoo's.

**The goal:** adopt Anokii's clean module seam (the catalog plus the config-gated module-provider
pattern), and make language the first proper module on it. minoo's existing pipeline becomes the
admin face of that module. We do not clone fnprocure.

## What each side actually is (recon summary)

### The package (`vendor/waaseyaa/anokii`)

| Layer | Path / class | Role |
|---|---|---|
| Module catalog | `src/Admin/AdminModules.php` (`Anokii\Admin\AdminModules`) | 15 brand-neutral modules (dashboard, cointelligence, identity, drive, documents, pages, inbox, venture, ventures, rooms, workspaces, portal, analytics, vault, governance), each with id/label/group/href under `/admin/anokii`/icon/tile. `resolve($liveIds, $overrides, $extra)` flips live vs preview and accepts extra rows; `sharedGraph()` preset; `find($id)`; const `GRAPH_TABLES`. Comment: "the catalog is the single source of truth for paths too." |
| Module-provider seam | `src/Provider/CoIntelligenceServiceProvider.php` | The reference self-contained module: `register()` registers graph entities via `$this->entityType(EntityType::fromClass($class))` and binds `DistributionConfig` plus the LLM provider; `boot()` ensures schema (`new ChatQueryLogSchema($db)->ensure()`); `routes()` mounts `/api/chat` (public) and a lean `/admin/anokii` (admin) gated on `DistributionConfig::moduleEnabled('public-graph-chat')` / `moduleEnabled('anokii-admin')` / `TenancyMode::SharedGraph`. |
| Shell + lean admin | `templates/anokii/admin/_shell.html.twig`, `_base.html.twig`; `src/Controller/AnokiiAdminController.php`; `src/Admin/{AdminShell,AdminData,AdminTemplates}.php` | Shared chrome and the shared-graph-tier admin (graph entity counts plus a no-PII chat-gap log). |
| Auth + config | `src/Dashboard/WorkspaceLoginController.php`, `src/Auth/SetupTokenRepository.php`, `src/Config/DistributionConfig.php`, `src/Config/TenancyMode.php` | Single-admin login / set-password, and the `config/anokii.yaml` distribution switch. |
| Graph entities | `src/Entity/{Community,Place,Organization,Service,Project,Topic,DocChunk}.php` | The Co-Intelligence knowledge graph. Includes a `community` type that collides with minoo's own. |

### fnprocure (`fnpi-waaseyaa`), the fullest consumer

`src/Provider/AnokiiServiceProvider.php` hardcodes routes at priority 100 (to beat the framework admin
SPA catch-all at `/admin/{path}`, priority 0) for nine live modules:

| Module | Controller (`src/Controller/`) | Entity (`src/Entity/`, type id) |
|---|---|---|
| dashboard / settings | `AnokiiController` | (none) |
| identity | `IdentityController` | `Pillar` (`identity_pillar`) |
| pages | `PagesController` | `Page` (`page`) |
| cointelligence | `CoIntelligenceController` | `DocChunk` (package) |
| drive | `DriveController` | `DriveFile` (`drive_asset`) |
| documents | `DocumentsController` | `Document`, `DocumentNote` |
| ventures | `VenturesController` | `VentureLane`, `VentureSnapshot`, `GatingFact` |
| venture | `VentureController` | `VentureThread`, `VentureItem` |
| analytics | `AnokiiAnalyticsController` | (SQLite analytics) |
| inbox | `ContactInboxController` | `ContactSubmission` |

It reuses from the package: `WorkspaceLoginController` plus `SetupTokenRepository` (auth), the
`_shell.html.twig` base (extended by app `templates/anokii/_fnpi_base.html.twig` via an `@anokiipkg`
namespace), the `DocChunk` entity, and the CoIntelligence engine. It has no `config/anokii.yaml`
(sovereign default), so the package providers mount none of their own routes; fnprocure's
cointelligence UI is its own `CoIntelligenceController`. **None of the ventures / documents / drive /
pages / identity / inbox / analytics surfaces are Anokii core; they are FNPI business domain and must
not be copied into minoo.**

### minoo today

- `src/Provider/AnokiiServiceProvider.php`: embeds only the Anokii shell (the `@anokii` Twig namespace
  plus theme tokens such as `anokii_brand_title`, `anokii_theme_css`); it deliberately does NOT register
  `Anokii\Provider\CoIntelligenceServiceProvider`, to keep the package `community` graph entity from
  colliding with minoo's own `community` type.
- `src/Provider/Routing/AnokiiRouteProvider.php`: declares the `/admin/anokii` route tree at priority
  100/110, gated to minoo's own staff roles (`admin`, `elder_coordinator`) via `STAFF_ROLES` /
  `ROUTE_PRIORITY`.
- Controllers `src/Http/Controller/Anokii/`: `AnokiiAdminController::index` (Overview, funnel via
  `src/Anokii/Pipeline/PipelineCounts.php`), `IngestController`, `TranscribeController`,
  `CurateController`, `AnokiiMediaController`.
- `src/Http/View/AnokiiShellContext.php` (sidebar nav, user chip); `src/Anokii/Pipeline/{PipelineStage,PipelineStageResolver,PipelineCounts}.php` (the ingested to published state machine).
- Templates `templates/pages/anokii/{index,ingest,transcribe,curate}.html.twig`, all
  `{% extends "@anokii/_shell.html.twig" %}`.
- Language entities (in `src/Provider/Entity/EntityFoundationProvider.php`): `example_sentence` (with
  `pipeline_status`, `lesson_slug`), `dictionary_entry`, `word_part`, `speaker`, `dialect_region`,
  `contributor`, plus dormant `ingest_log`.
- No `DistributionConfig` / `TenancyMode` / `config/anokii.yaml` (zero references in `src/`).

"In name only" in code means: zero references to `Anokii\Admin\*` or the package `Anokii\Controller\AnokiiAdminController`
anywhere in `minoo/src`; no catalog; no graph entities; no config gating. Only the shell chrome is shared.

## Locked decisions (each with the exact code seam it touches)

### D1. Scope: full model parity first, then the language module

Adopt the catalog-driven shell, config gating, and the module-provider seam. Re-home the existing
corpus pipeline under the catalog, THEN build the language module on top.
- Catalog-driven shell: `Anokii\Admin\AdminModules` (`vendor/waaseyaa/anokii/src/Admin/AdminModules.php`)
  plus the package `templates/anokii/admin/_shell.html.twig` (minoo already extends `@anokii/_shell.html.twig`).
- Config gating: introduce a minimal `config/anokii.yaml` consumed by
  `Anokii\Config\DistributionConfig` (`vendor/waaseyaa/anokii/src/Config/DistributionConfig.php`) and
  `Anokii\Config\TenancyMode` (`.../TenancyMode.php`). Neither minoo nor fnpi has this file today.
- Module-provider seam: see D2.
- Re-home target: the current `src/Provider/Routing/AnokiiRouteProvider.php` tree and
  `src/Http/Controller/Anokii/*` become the admin face of the language module (D7).

### D2. Seam: module-as-ServiceProvider, config-gated

Follow the `CoIntelligenceServiceProvider` pattern, NOT fnprocure's hardcoded routes:
- Register entities via `$this->entityType(EntityType::fromClass(...))`.
- Bind services as singletons in `register()`.
- Ensure schema in `boot()` (mirrors `new ChatQueryLogSchema($db)->ensure()`).
- Mount routes in `routes(WaaseyaaRouter $router)` gated on
  `DistributionConfig::moduleEnabled('language')`.
- Reference seam: `vendor/waaseyaa/anokii/src/Provider/CoIntelligenceServiceProvider.php` (whole class).
- The new provider would be `App\Provider\LanguageModuleServiceProvider` (in-app now, see D6 for the
  later package path). It joins minoo's composer-facing provider list the way the existing providers do
  (parity asserted by `ComposerProviderParityTest`).

### D3. community collision sidestepped

Do NOT adopt the Anokii CoIntelligence graph entities (`community`, `place`, `organization`, `service`,
`project`, `topic`, `doc_chunk`) in minoo. Keep minoo's own `community`.
- Seam to keep OFF: do not register `Anokii\Provider\CoIntelligenceServiceProvider`, and do not call
  `EntityType::fromClass(\Anokii\Entity\Community::class)` or its siblings
  (`vendor/waaseyaa/anokii/src/Entity/*`). minoo's `AnokiiServiceProvider` already excludes this on
  purpose; that exclusion stays.
- Co-Intelligence over the language graph is explicitly out of scope. Revisit only if ever wanted, and
  only after a rename or namespace decision for `community`.

### D4. Auth: keep minoo's role model

Keep `admin` and `elder_coordinator` gating. Do not adopt the package single-admin
`WorkspaceLoginController`.
- Seam: minoo's `src/Provider/Routing/AnokiiRouteProvider.php` `STAFF_ROLES` / `requireRole(...)` stays
  authoritative.
- Do NOT wire `vendor/waaseyaa/anokii/src/Dashboard/WorkspaceLoginController.php` or
  `src/Auth/SetupTokenRepository.php`.
- Divergence to document: the package shell (`_shell.html.twig`) assumes a single-admin user chip and a
  `/admin/anokii/login` flow. minoo renders the same shell but binds a role-gated account context (via
  `src/Http/View/AnokiiShellContext.php`). When adopting the catalog-driven shell, keep feeding it
  minoo's account context, not the package login.

### D5. API surface: per-module /api/*

`/api/lang` mounts as a peer to the package's `/api/chat`. No single API gateway.
- Reference: `CoIntelligenceServiceProvider::routes()` mounts `/api/chat` directly via `RouteBuilder`.
- The language module mounts `/api/lang/*` the same way (public, `allowAll()`, consent enforced at the
  policy layer), alongside its admin routes under `/admin/anokii/language`.
- Consistent with the tracker's `/api/lang` decision (see
  [tracker section A.1](anishinaabemowin-language-api-tracker.md)).

### D6. Catalog ownership: local now, upstream later

Add the language module via `AdminModules::resolve($liveIds, $overrides, $extra)` in-app now, passing a
`language` row in `$extra`. Upstream into the canonical package catalog plus a future
`waaseyaa/anokii-language` package only when a second install wants language tooling.
- Seam: `Anokii\Admin\AdminModules::resolve(array $liveIds, array $overrides = [], array $extra = [])`
  (`vendor/waaseyaa/anokii/src/Admin/AdminModules.php`, lines 68 onward). `$extra` rows are appended to
  the catalog without forking it.
- This mirrors the dialect-package deferral already locked in the tracker (canonical now behind a seam,
  publish the package only when federation demands it). See tracker section A.3.

### D7. One language module, not two

The existing corpus pipeline (Ingest / Transcribe / Curate) becomes the admin face of the language
module, with its admin tile at `/admin/anokii/language`. The tracker's translation memory, `/api/lang`,
gap-log, and gated ASR hookup are that same module's data model, public API, and services. Do not
create a separate corpus module.
- Seam: `src/Http/Controller/Anokii/{IngestController,TranscribeController,CurateController}.php` plus
  `src/Anokii/Pipeline/*` are re-homed under the `language` catalog entry, not a second `corpus` entry.
- Data model added by the same module: `translation_memory` and `tm_gap_log` (tracker section C).
- If a reason to split into two modules emerges during build, flag it rather than splitting silently.
  Current judgment: one module, because the pipeline and the TM share the same entities
  (`example_sentence`, `dictionary_entry`, `word_part`), the same consent gate, and the same dialect
  contract.

### D8. ASR stays consent-gated

The language module resolves an `AsrClient` binding to the separate Python/GPU worker (the Pi is
inference-only). No public ASR surface until the Phase 0 consent agreement with Steven Bennett /
Sagamok exists.
- Seam: an `AsrClient` interface bound as a singleton in `LanguageModuleServiceProvider::register()`,
  resolved by the module's services. No route is mounted for ASR in Phase 1.
- Gate: tracker Phase 0 (see [tracker governance gate](anishinaabemowin-language-api-tracker.md)).

## Build sequence (proposed milestone and ordered issues, titles only)

These are proposed issue titles, not created issues, and not code. minoo follows the design-first +
anchor-issue flow (Spec Kitty retired 2026-07-16, see CLAUDE.md Workflow), so each item becomes a
GitHub issue under one milestone. Ordered by dependency.

**Proposed milestone:** `Anokii parity and the language module`

1. Introduce `config/anokii.yaml` and wire `DistributionConfig` / `TenancyMode` into minoo (minimal,
   sovereign tenancy, `language` module flag off by default).
2. Adopt the `AdminModules` catalog-driven `/admin/anokii` shell (dashboard and nav from the catalog,
   keeping minoo's role-gated account context, not the package single-admin login).
3. Re-home the corpus pipeline (Ingest / Transcribe / Curate / Overview) as the language module admin
   tile at `/admin/anokii/language`, registered via `AdminModules::resolve(..., $extra)`.
4. Add `LanguageModuleServiceProvider` (config-gated module-provider seam) with `translation_memory`
   and `tm_gap_log` entities and `LanguageAccessPolicy` coverage.
5. Add the `DialectCodeProvider` seam (canonical codes from `ConfigSeeder::dialectRegions()` /
   `dialect_region.code`, swappable to a package later).
6. Add `/api/lang` lookup endpoints (exact, then fuzzy, then gap-log write) with the `dialect`
   parameter, a `confidence` score, and a `needs_speaker_review` flag.
7. Add the `AsrClient` binding stub, consent-gated (no public surface until Phase 0).

Independent and parallelizable: the standalone English seed crawler (language-API tracker mission 2)
has no Anokii or dialect dependency and can proceed at any time alongside the above.

## Open questions carried forward

1. Intended module-packaging pattern: the package offers both the config-gated provider seam and the
   catalog, but the reference consumer (fnprocure) hardcodes. We are choosing the provider seam (D2);
   confirm that is the intended Anokii direction before extracting a `waaseyaa/anokii-language` package.
2. `community` rename or namespace, only if Co-Intelligence over the language graph is ever wanted (D3).
3. `config/anokii.yaml` shape: minimal fields needed for `moduleEnabled('language')` plus tenancy, to be
   pinned in issue 1.
