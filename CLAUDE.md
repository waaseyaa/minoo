# Minoo

Indigenous knowledge platform built on Waaseyaa CMS framework.

## Architecture

Minoo is a **thin application** — custom entity types, access policies, service providers, and seeders live in `src/`. All framework code lives in `waaseyaa/framework` (sibling directory, symlinked via Composer path repositories).

```
minoo/
├── src/
│   ├── Access/        # 10 access policy classes
│   ├── Controller/    # 10 HTTP controllers
│   ├── Domain/        # Bounded contexts (Geo/)
│   ├── Entity/        # 17 custom entity classes
│   ├── Ingestion/     # Inbound data pipelines (mappers, materializer)
│   ├── Provider/      # 13 service providers
│   ├── Search/        # Search providers, autocomplete
│   ├── Seed/          # TaxonomySeeder, ConfigSeeder, etc.
│   └── Support/       # Cross-cutting utilities (GeoDistance, SlugGenerator)
├── tests/Minoo/
│   ├── Unit/          # Entity, access, seed tests
│   └── Integration/   # Full kernel boot smoke test
├── templates/
│   ├── base.html.twig           # Page shell (header, nav, footer)
│   ├── page.html.twig           # Default page (extends base)
│   ├── 404.html.twig            # Not found page (extends base)
│   ├── events.html.twig         # Events listing + detail (extends base)
│   ├── groups.html.twig         # Groups listing + detail (extends base)
│   ├── teachings.html.twig      # Teachings listing + detail (extends base)
│   ├── language.html.twig       # Language demo page (extends base)
│   └── components/              # Reusable Twig partials
│       ├── dictionary-entry-card.html.twig
│       ├── event-card.html.twig
│       ├── group-card.html.twig
│       └── teaching-card.html.twig
├── public/
│   ├── index.php                # Web entry point
│   └── css/minoo.css            # Design system (tokens, layout, components)
├── config/            # App configuration
└── vendor/            # Symlinks to ../waaseyaa/packages/*
```

## Orchestration

| File pattern | Specialist skill | Cold memory spec |
|---|---|---|
| `src/Entity/*`, `src/Provider/*` | `minoo:entities` | `docs/specs/entity-model.md` |
| `src/Access/*` | `minoo:entities` | `docs/specs/entity-model.md` (access section) |
| `src/Seed/*` | `minoo:entities` | `docs/specs/entity-model.md` (seed section) |
| `tests/Minoo/*` | `minoo:entities` | `docs/specs/entity-model.md` (testing section) |
| `src/Ingestion/*` | `minoo:ingestion` | `docs/specs/ingestion-pipeline.md` |
| `src/Search/*`, `src/Provider/SearchServiceProvider.php` | `minoo:search` | `docs/specs/search.md` |
| `src/Controller/*`, route definitions in `src/Provider/` | `minoo:controllers` | `docs/specs/entity-model.md`, `docs/specs/frontend-ssr.md` |
| `templates/*`, `public/css/*` | `minoo:frontend-ssr` | `docs/specs/frontend-ssr.md` |
| `src/Domain/Geo/*` | — | Geo bounded context: CommunityFinder, LocationService, VolunteerRanker, value objects |
| `src/Support/*` | — | Cross-cutting: GeoDistance, SlugGenerator, NorthCloudClient, MailService, Flash, CommunityLookup |
| `config/*`, `composer.json` | — | See `../waaseyaa/CLAUDE.md` for framework conventions |
| `src/Entity/*`, `src/Provider/*`, `src/Access/*` | `waaseyaa-app-development` | `docs/specs/entity-model.md` |
| `src/Controller/*`, `src/Routing/*` | `waaseyaa-app-development` | — |
| GitHub issues, milestones, new features, roadmap | — | `docs/specs/workflow.md` |

For Minoo-level specs, use the Minoo MCP tools:
- `minoo_list_specs` — list all available specs
- `minoo_get_spec <name>` — full spec content (e.g. `entity-model`, `ingestion-pipeline`, `search`, `frontend-ssr`)
- `minoo_search_specs <query>` — keyword search across all Minoo specs

For framework-level work (kernel boot, entity storage, access handler internals), use the waaseyaa MCP tools:
- `waaseyaa_get_spec entity-system` — entity types, storage, field definitions
- `waaseyaa_get_spec access-control` — access policies, gate wiring
- `waaseyaa_get_spec infrastructure` — kernel boot, manifest compiler, providers
- `waaseyaa_search_specs <query>` — keyword search across all framework specs

## Entity Domains (6 domains, 14 types)

| Domain | Entities | Provider | Policy |
|--------|----------|----------|--------|
| Events | `event`, `event_type` | `EventServiceProvider` | `EventAccessPolicy` |
| Groups | `group`, `group_type`, `cultural_group` | `GroupServiceProvider`, `CulturalGroupServiceProvider` | `GroupAccessPolicy`, `CulturalGroupAccessPolicy` |
| Teachings | `teaching`, `teaching_type`, `cultural_collection` | `TeachingServiceProvider`, `CulturalCollectionServiceProvider` | `TeachingAccessPolicy`, `CulturalCollectionAccessPolicy` |
| Language | `dictionary_entry`, `example_sentence`, `word_part`, `speaker` | `LanguageServiceProvider` | `LanguageAccessPolicy` |
| Ingestion | `ingest_log` | `IngestServiceProvider` | `IngestAccessPolicy` |
| Editorial | `featured_item` | `FeaturedItemServiceProvider` | `FeaturedItemAccessPolicy` |

## Frontend / SSR

- **CSS:** Single vanilla file `public/css/minoo.css` — no build step, no preprocessor
- **CSS architecture:** `@layer reset, tokens, base, layout, components, utilities` — oklch colors, fluid `clamp()` type/spacing, native nesting, container queries, logical properties
- **Templates:** Twig 3 with inheritance — `base.html.twig` defines shell, pages extend it
- **Path routing:** Framework `RenderController::tryRenderPathTemplate()` maps `/language` → `language.html.twig` (framework#189). Paths without a matching template or path alias get 404.
- **Design doc:** `docs/plans/2026-03-06-visual-identity-layout-design.md` — color palette, type scale, spacing scale, component patterns

**Adding a public page:**
1. Create `templates/{path-segment}.html.twig` extending `base.html.twig`
2. Define `{% block title %}` and `{% block content %}`
3. The framework serves it at `/{path-segment}` automatically

**Adding a Twig component:**
1. Create `templates/components/{name}.html.twig`
2. Use `{% include "components/{name}.html.twig" with { ... } %}` from pages
3. Add corresponding CSS in `@layer components` in `minoo.css`

## Operation Checklists

**Adding a Minoo entity type:**
1. Create entity class in `src/Entity/` extending `ContentEntityBase` or `ConfigEntityBase` — hardcode `entityTypeId` and `entityKeys`
2. Register `EntityType` in existing or new service provider's `register()` method
3. Create or update `AccessPolicy` in `src/Access/` with `#[PolicyAttribute]`
4. Write unit test in `tests/Minoo/Unit/Entity/`
5. Run `./vendor/bin/phpunit` — delete `storage/framework/packages.php` if new provider isn't discovered

**Adding seed data:**
1. Add static method to `TaxonomySeeder` (vocabularies) or `ConfigSeeder` (type configs)
2. Write unit test in `tests/Minoo/Unit/Seed/`
3. Return structured arrays — not persisted entities

**Adding a featured item:**
1. Run `php scripts/populate_featured.php` or create via `$storage->create([...])` with entity_type, entity_id, headline, subheadline, weight, starts_at, ends_at, status
2. Featured items appear on homepage when `starts_at <= now <= ends_at` and `status = 1`
3. Higher weight = more prominent positioning

## Commands

```bash
composer install                              # Install deps (symlinks to waaseyaa packages)
php -S localhost:8081 -t public               # Dev server (port 8081)
./vendor/bin/phpunit                          # All tests (208 tests, 501 assertions)
./vendor/bin/phpunit --testsuite MinooUnit     # Unit tests only
./vendor/bin/phpunit --testsuite MinooIntegration  # Integration tests (in-memory SQLite)
bin/waaseyaa                                  # CLI
bin/waaseyaa migrate                          # Run pending migrations
bin/waaseyaa migrate:status                   # Show migration status
bin/waaseyaa migrate:rollback                 # Rollback last batch
bin/waaseyaa make:migration <name>            # Generate a new migration file
bin/waaseyaa schema:check                     # Detect schema drift (missing columns)
```

## Content Tone

All user-facing copy follows `docs/content-tone-guide.md`:
- **Voice:** First-person plural ("we," "our") or second-person ("you"). Never corporate third-person.
- **Terminology:** Elder, Knowledge Keeper, Teachings (capitalized). "Community leaders and Knowledge Keepers" not "resource people."
- **Philosophy:** Every page SHOWs real content, TELLs why it matters, INVITEs action.

## Code Style

- PHP 8.4+, `declare(strict_types=1)` in every file
- Namespace: `Minoo\` for app code, `Minoo\Tests\` for tests
- PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(...)]`, `#[CoversNothing]` for integration
- `final class` by default
- See `../waaseyaa/CLAUDE.md` for full framework conventions

## Gotchas

- **`dirname(__DIR__, 3)`** from `tests/Minoo/Integration/` to reach project root (3 levels up, not 2)
- **Stale manifest cache**: `storage/framework/packages.php` can prevent new providers/policies from being discovered — delete it when adding new providers
- **`PackageManifestCompiler`** reads root `composer.json` for app providers and scans app PSR-4 namespaces for policies — this was a framework fix required for Minoo
- **`LanguageAccessPolicy`** covers all 4 language types via array attribute: `#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'])]`
- **Entity keys** are unique per type (e.g. `eid` for event, `deid` for dictionary_entry, `ccid` for cultural_collection, `ilid` for ingest_log)
- **Schema drift**: Adding a field to `fieldDefinitions` does not ALTER existing SQLite tables. Run `bin/waaseyaa schema:check` to detect drift, then create a migration with `bin/waaseyaa make:migration add_<column>_to_<table>` and run `bin/waaseyaa migrate`. Migration files live in `migrations/` as PHP files returning `Migration` instances (see existing migrations for pattern)
- **Integration tests** boot `HttpKernel` with reflection (`boot()` is protected), use `putenv('WAASEYAA_DB=:memory:')` for in-memory SQLite
- **CSS conventions**: logical properties only (`margin-block`, `padding-inline`, never `left`/`right`), `gap` for spacing, native nesting (no BEM), container queries on components, media queries only for page shell
- **Path-based templates** require framework#189 — `tryRenderPathTemplate()` matches single segments exactly and also falls back to the first segment for multi-segment paths (e.g. `/events/slug` renders `events.html.twig` with `path` set to `/events/slug`)
- **Listing+detail templates** use path conditionals inside `{% block content %}` — `{% set %}` must be inside the block, and only one `{% block %}` per name (use conditionals inside the block, not multiple blocks in conditionals)
- **Database path**: Minoo config resolves to `{projectRoot}/storage/waaseyaa.sqlite`. Override with `WAASEYAA_DB` env var. When copying production DB locally, place it in `storage/`.
- **`@layer utilities` fixed (#273)**: Was caused by a premature `}` closing `@layer components` early, leaving subsequent component styles unlayered (which outranks all layers in the cascade). Fixed by removing the extra brace.
- **Homepage nearby fallback**: `buildNearbyMixed()` returns empty when community entities lack `status=1` (most imported communities have NULL status). `buildRecentMixed()` provides a non-proximity fallback so the "Nearby" tab always has content.
- **ConfigEntityBase vs ContentEntityBase**: Config entities are seed-based (in-memory), NOT database-persisted. Use `ContentEntityBase` with `'uuid' => 'uuid'` in entityKeys for any entity that needs SQL storage. Config entities (like `EventType`, `GroupType`) come from seeders only.
- **Entity creation**: Use `$storage->create([...])` then `$storage->save($entity)` — NOT `new Entity([...])`. Direct instantiation won't persist correctly through the storage layer.
- **Homepage card routing**: Homepage `loadGroups()` filters `type != business`. Card URLs in `page.html.twig` check `is_business` to route to `/businesses/` vs `/groups/`. `loadFeaturedItems()` resolves correct URLs per entity type.

## GitHub Workflow

All work in this repo follows a GitHub-first workflow. See `docs/specs/workflow.md` (via `minoo_get_spec workflow`) for the full governance model including the versioning strategy and current milestone structure.

**The 5 rules — enforced at every session start via `bin/check-milestones`:**

1. **All work begins with an issue.** Ask for the issue number before writing code. If none exists, create one and assign it to a milestone first.
2. **Every issue belongs to a milestone.** Unassigned issues are incomplete triage — prompt assignment if missing.
3. **Milestones define the roadmap.** Check the active milestone before proposing work. Do not invent new milestones without explicit discussion.
4. **PRs must reference issues.** PR title format: `feat(#N): description`. Use `.github/pull_request_template.md`.
5. **Read the drift report.** `bin/check-milestones` runs at session start. Flag any warnings before beginning work.

## Codified Context

- **Tier 1 (Constitution):** This CLAUDE.md — orchestration, checklists, gotchas
- **Tier 2 (Skills):**
  - `skills/minoo/SKILL.md` — entity types, access policies, service providers, seed data
  - `skills/minoo-ingestion/SKILL.md` — ingestion pipeline, mappers, materializer
  - `skills/minoo-search/SKILL.md` — NorthCloud search, autocomplete
  - `skills/minoo-controllers/SKILL.md` — HTTP controllers, routing, request handling
  - `skills/minoo-frontend-ssr/SKILL.md` — templates, CSS design system, SSR rendering
- **Tier 3 (Specs):** Retrieved via `minoo_*` MCP tools:
  - `docs/specs/workflow.md` — GitHub workflow governance, versioning model, milestone structure
  - `docs/specs/entity-model.md` — entity types, access, seeds (318 lines)
  - `docs/specs/ingestion-pipeline.md` — NorthCloud ingest, mappers, materialization
  - `docs/specs/search.md` — search provider, config, template
  - `docs/specs/frontend-ssr.md` — templates, CSS design system, components
- **Framework specs:** Use `waaseyaa_*` MCP tools for framework-level context
