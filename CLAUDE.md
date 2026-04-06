# Minoo

Indigenous knowledge platform built on Waaseyaa CMS framework.

Last framework sync: Waaseyaa alpha.104 (community-scoped tenancy #1093–#1094, domain router architecture #571)

## Architecture

Minoo is a **thin application** — custom entity types, access policies, service providers, and seeders live in `src/`. All framework code lives in `waaseyaa/framework` (sibling directory). Framework packages are installed from versioned tags via `composer install`.

```
minoo/
├── src/
│   ├── Access/        # 10 access policy classes
│   ├── Controller/    # 11 HTTP controllers (incl. CrosswordController)
│   ├── Domain/        # Bounded contexts (Geo/)
│   ├── Entity/        # 17 custom entity classes
│   ├── Ingestion/     # Inbound data pipelines (mappers, materializer)
│   ├── Provider/      # 13 service providers
│   ├── Search/        # Search providers, autocomplete
│   ├── Seed/          # TaxonomySeeder, ConfigSeeder, etc.
│   └── Support/       # Cross-cutting utilities (GeoDistance, SlugGenerator, CrosswordEngine)
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
| `src/Domain/Geo/*`, `src/Support/GeoDistance.php`, `src/Support/CommunityLookup.php` | — | `docs/specs/geo-domain.md` |
| `src/Support/NorthCloudClient.php`, `src/Support/NorthCloudCache.php` | — | `docs/specs/geo-domain.md` (NC client section) |
| `src/Support/*` (other) | — | Cross-cutting: SlugGenerator, MailService, Flash, FixtureResolver, ElderIdentity |
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

## Entity Domains (6 domains, 15 types)

| Domain | Entities | Provider | Policy |
|--------|----------|----------|--------|
| Events | `event`, `event_type` | `EventServiceProvider` | `EventAccessPolicy` |
| Groups | `group`, `group_type`, `cultural_group` | `GroupServiceProvider`, `CulturalGroupServiceProvider` | `GroupAccessPolicy`, `CulturalGroupAccessPolicy` |
| Teachings | `teaching`, `teaching_type`, `cultural_collection` | `TeachingServiceProvider`, `CulturalCollectionServiceProvider` | `TeachingAccessPolicy`, `CulturalCollectionAccessPolicy` |
| Language | `dictionary_entry`, `example_sentence`, `word_part`, `speaker`, `dialect_region` | `LanguageServiceProvider`, `DialectRegionServiceProvider` | `LanguageAccessPolicy` |
| Games | `game_session`, `crossword_puzzle` | `GameServiceProvider` | — |
| Ingestion | `ingest_log` | `IngestServiceProvider` | `IngestAccessPolicy` |
| Editorial | `featured_item` | `FeaturedItemServiceProvider` | `FeaturedItemAccessPolicy` |

**Note:** `post` has its own `PostAccessPolicy` (public-read, auth-create, author+coordinator delete), separate from `EngagementAccessPolicy` which covers `reaction`, `comment`, `follow`.

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
./vendor/bin/phpunit                          # All tests (829 tests, 2395 assertions)
./vendor/bin/phpunit --testsuite MinooUnit     # Unit tests only
./vendor/bin/phpunit --testsuite MinooIntegration  # Integration tests (in-memory SQLite)
bin/waaseyaa                                  # CLI
bin/waaseyaa migrate                          # Run pending migrations
bin/waaseyaa migrate:status                   # Show migration status
bin/waaseyaa migrate:rollback                 # Rollback last batch
bin/waaseyaa make:migration <name>            # Generate a new migration file
bin/waaseyaa schema:check                     # Detect schema drift (missing columns)
bin/waaseyaa ingest:nc-sync --limit=10        # Pull NC indigenous content as teachings/events
bin/waaseyaa ingest:nc-sync --dry-run         # Preview what would sync without persisting
php scripts/populate_engagement.php           # Seed feed with users, posts, reactions, comments
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

- **Entity fields live in `_data` JSON blob**: `target_type`, `target_id`, `user_id`, `created_at` on engagement entities (reaction, comment, follow) are NOT real SQL columns. Raw SQL queries against them fail. Use entity queries (`getQuery()->condition()`) which handle `_data` extraction. See #520 for adding real columns.
- **`HttpKernel` has no `resolve()` method**: Use `$kernel->getEntityTypeManager()` for ETM access in scripts. The `resolve()` method is on `ServiceProvider`, not the kernel.
- **DI resolves `EntityTypeManager` (concrete), not the interface**: Providers must use `$this->resolve(EntityTypeManager::class)`, not `EntityTypeManagerInterface::class`. The kernel registers the concrete class only.
- **Parallel worktree agents diverge on APIs**: When spawning leaf + integration worktree agents, the integration agent writes its own class versions with potentially different signatures. Always reconcile the integration PR after leaf PRs merge.
- **`dirname(__DIR__, 3)`** from `tests/Minoo/Integration/` to reach project root (3 levels up, not 2)
- **Stale manifest cache**: `storage/framework/packages.php` can prevent new providers/policies from being discovered — delete it when adding new providers
- **`PackageManifestCompiler`** reads root `composer.json` for app providers and scans app PSR-4 namespaces for policies — this was a framework fix required for Minoo
- **`LanguageAccessPolicy`** covers all 4 language types via array attribute: `#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'])]`
- **Entity keys** are unique per type (e.g. `eid` for event, `deid` for dictionary_entry, `ccid` for cultural_collection, `ilid` for ingest_log)
- **Schema drift**: Adding a field to `fieldDefinitions` does not ALTER existing SQLite tables. Run `bin/waaseyaa schema:check` to detect drift, then create a migration with `bin/waaseyaa make:migration add_<column>_to_<table>` and run `bin/waaseyaa migrate`. Migration files live in `migrations/` as PHP files returning `Migration` instances (see existing migrations for pattern)
- **Integration tests** boot `HttpKernel` with reflection (`boot()` is protected), use `putenv('WAASEYAA_DB=:memory:')` for in-memory SQLite
- **Database path**: Minoo config resolves to `{projectRoot}/storage/waaseyaa.sqlite`. Override with `WAASEYAA_DB` env var. When copying production DB locally, place it in `storage/`.
- **Don't use `final` on services that need mocking**: PHPUnit cannot mock final classes. Use `final` for value objects and entities, but not for services injected via DI that tests need to mock (e.g. `EntityLoaderService`).
- **Controllers return `Symfony\Component\HttpFoundation\Response`** (migration #631/#632, alpha.106). Use `new Response($html)` for HTML, `new RedirectResponse($url)` for redirects, `new Response($json, 200, ['Content-Type' => 'application/json'])` or a `$this->json()` helper for JSON. Cookies: `$response->headers->setCookie(Cookie::create(...))` — not the legacy SsrResponse `Set-Cookie` header string. Tests use `$response->getStatusCode()` / `getContent()` / `headers->get()`, not readonly properties.
- **EntityStorageInterface namespace**: `Waaseyaa\Entity\Storage\EntityStorageInterface` (not `Waaseyaa\Entity\EntityStorageInterface`)
- **Worktree pre-push hook**: `.husky/pre-push` runs `vendor/bin/phpunit` but worktrees don't have `vendor/` linked — push with `--no-verify` from worktrees, or run tests from main repo first.
- **Worktree autoloader corruption**: After parallel worktree agents complete, run `composer dump-autoload` before running tests or pushing. Worktrees can leave stale paths in `vendor/composer/autoload_classmap.php`.
- **Squash merge conflict loss**: When squash-merging PRs with overlapping files, the conflict resolution may silently drop one side's changes. Always verify the merged result contains both PRs' intended changes, especially for template/CSS files.
- **Cross-PR entity field conflicts**: When parallel PRs add required fields to entities (e.g. `community_id` on Post) and other PRs write tests against those entities, the tests will fail after merge. Budget a merge-fix pass when running parallel worktree sprints.
- **Reaction field rename**: `emoji` was renamed to `reaction_type` with migration `20260322_120000`. Allowed values: `like`, `interested`, `recommend`, `miigwech`, `connect`. All API endpoints, JS, and tests use `reaction_type`.
- **Game sessions must set `game_type`**: Both `ShkodaController` and `CrosswordController` must include `'game_type' => 'shkoda'`/`'crossword'` when creating game sessions. `GameStatsCalculator::build()` filters by `game_type` — missing it causes stats to silently return zero for authenticated users.
- **Game controllers must inject `GateInterface`**: All game API endpoints that mutate session state (`check`, `complete`, `hint`, `abandon`, `guess`) must call `$this->gate->denies('update', $session, $account)` for session ownership validation.
- **AuthMailer requires `isConfigured()` guard**: All `AuthMailer` methods check `MailService::isConfigured()` before sending. Without a valid `SENDGRID_API_KEY`, SendGrid returns HTTP 401 and throws — crashing registration and password reset flows. CI and local dev typically have no API key.
- **PHPStan baseline drift**: After adding new files that call `EntityInterface::get()`, regenerate the baseline with `./vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`. The baseline won't auto-update when new files are added.
- **Controller DI**: `SsrPageHandler::resolveControllerInstance()` auto-injects constructor params. It checks a hardcoded `$serviceMap` (EntityTypeManager, Twig, HttpRequest, AccountInterface), then falls back to `serviceResolver` for any type registered as a singleton in a service provider. Register new services in providers and they'll be injected automatically.
- **Production deploy path**: `/home/deployer/minoo/current` (symlink to `releases/N`). DB at `storage/waaseyaa.sqlite`. User table is `user` (not `users`), fields stored in `_data` JSON blob. Query by field: `WHERE _data LIKE '%field_value%'`.
- **EntityStorage::create() calls constructors**: `SqlEntityStorage::instantiateEntity()` uses `new $class(values: $values)` — constructor validation IS invoked. EngagementController wraps create()+save() in try/catch for `InvalidArgumentException` as a safety net returning 422.
- **Mock `ContentEntityBase`, not `EntityInterface`**: `get()`/`set()` are on `FieldableInterface`/`ContentEntityBase`, not `EntityInterface`. PHPUnit cannot configure methods that don't exist on the mocked type. Use `$this->createMock(ContentEntityBase::class)` for entities that need `get()`/`set()` in tests.
- **Mock `set()` must return self**: `ContentEntityBase::set()` returns `static` (fluent). Mock callbacks using `willReturnCallback` must `return $mock;` — a void callback causes `TypeError` at runtime.
- **Playwright tests coupled to i18n strings**: Playwright assertions like `getByRole('heading', { name: '...' })` break when translation strings change. Update `tests/playwright/*.spec.ts` whenever `resources/lang/en.php` heading/title strings change.
- **`SqlEntityStorage::delete()` takes an array**: `$storage->delete([$entity])` not `$storage->delete($entity)`. Single entity causes TypeError.
- **`count()->execute()` returns `[N]`**: A single-element array with the count as value. Use `$result[0]` not `count($result)` to get the actual count.
- **NorthCloudClient timeout**: Default 5s is too tight for full-text search (ES queries take 5+ seconds). Use `search.timeout` config (15s) when constructing client for search operations.
- **NC Search API param**: Uses `size` for pagination, not `page_size` (that's the communities endpoint only).
- **ConsoleKernel broken on production** (#493): Missing `SqliteEmbeddingStorage` class crashes all CLI commands. Workaround: boot `HttpKernel` via reflection in one-liner scripts (same pattern as `scripts/populate_featured.php`).
- **`trans()` is a Twig function, not PHP**: Controllers cannot call `trans()`. Use hardcoded English strings for `Flash::success()`/`Flash::error()` — this matches all existing controllers (AuthController, ElderSupportWorkflowController, etc.).
- **App-specific identity fields belong in Minoo, not framework**: Use `ElderIdentity::isElder($user)` / `ElderIdentity::setElder($user, bool)` from `src/Support/ElderIdentity.php`. Never add Minoo domain concepts to the framework `User` class.
- **Validate Referer before redirecting**: `$request->headers->get('Referer')` can be an external URL. Use `RoleManagementController::safeReferrer()` pattern — reject anything that doesn't start with `/` or starts with `//`.
- **Vendor packages are versioned, not symlinked**: `vendor/waaseyaa/*` is installed from version tags (e.g. `^0.1.0-alpha.70`), not path repositories. Framework changes require: tag new release in waaseyaa → `composer update 'waaseyaa/*'` in Minoo. Editing `vendor/` directly is lost on next update. Run `composer install` locally — do NOT use a vendor symlink (breaks after monorepo package extraction).
- **`ServiceProvider::boot()` takes no parameters**: Cannot inject via `boot()` signature. Use `$this->resolve(EventDispatcherInterface::class)` inside `boot()` body.
- **Feed scoring config**: All ranking constants (decay half-life, affinity signals, engagement weights, diversity thresholds) are in `config/feed_scoring.php`. Tunable without code changes.
- **Conditional grid columns**: Use `:has()` when grid layouts have optional children (e.g. `.search-layout:has(.search-filters)`). Without it, empty grid columns waste space.
- **Worktree agent simplify leakage**: The `/simplify` skill dispatched inside a worktree agent may modify files in the main repo's working directory instead of the worktree. Stash main repo changes before merging worktree branches.
- **"Did you mean" suggestion slot**: Template and CSS exist in `search.html.twig` but `SearchResult` has no `suggestion` field yet. See #519 for backend wiring.
- **Migration tables must use `_data` CLOB schema**: Content entities use `{id} INTEGER PRIMARY KEY AUTOINCREMENT, uuid CLOB, bundle CLOB, {label} CLOB, langcode CLOB, _data CLOB`. Config entities use `{id} TEXT PRIMARY KEY, bundle CLOB, langcode CLOB, _data CLOB`. All field values are stored in the `_data` JSON blob — do NOT create individual columns for fields. `SqlEntityStorage` will error with "no column named _data" if the schema is wrong.
- **Dictionary `definition` field is JSON-wrapped**: Values like `["bear"]` need `json_decode()` before display. Use `cleanDefinition()` pattern in controllers.
- **New providers must be registered in `composer.json`**: Add to `extra.waaseyaa.providers[]` then run `php bin/waaseyaa optimize:manifest`. Without this, routes and entity types are not discovered.
- **Worktree vendor corruption**: Worktrees don't share the main repo's `vendor/`. After worktree cleanup, run `composer install` in the main repo to restore dependencies.
- **CSS cache bust is manual**: Bump `?v=N` in `base.html.twig` after CSS changes. Stale CSS on production is the #1 cause of "it looks broken after deploy."
- **Crossword puzzle tiers**: Only easy-tier puzzles exist in the database. Practice mode for medium/hard returns 500. Generate puzzles via CLI before testing those tiers. See #558, #560.
- **Crossword themes tab**: No themed puzzle packs exist yet. The tab renders blank with no empty state message. See #559.
- **CSS/template gotchas**: Moved to `minoo:frontend-ssr` skill (Common Mistakes section)
- **Entity creation gotchas**: Moved to `minoo:entities` skill (Common Mistakes section)
- **`NORTHCLOUD_BASE_URL` on production**: Must be `http://localhost:8050` (source-manager), NOT `https://northcloud.one` (that's Minoo itself). The `NorthCloudClient` uses this for people/band-office lookups on community detail pages.
- **`api.northcloud.one` is the public read-only proxy**: Caddy reverse proxy to source-manager port 8050. GET-only, explicit path allowlist. Deployed via `northcloud-ansible` role `north-cloud`.
- **ISC sub-pages have direct URLs**: `FNGovernance.aspx?BAND_NUMBER={band}&lang=eng` (chief/council + election dates), `FNReserves.aspx?BAND_NUMBER={band}&lang=eng` (reserve names + hectares), `FNRegPopulation.aspx?BAND_NUMBER={band}&lang=eng` (registered population breakdown). No ASP.NET postback needed.
- **ISC profiles have no email field**: No sub-page contains band office email. Email must come from community website scraping.
- **Website leadership scraping is unreliable**: NC Go scraper's `ExtractLeaders` has ~80% false positive rate — role context bleeds across entire pages, matching nav links and headings as names. Use ISC Governance sub-page for authoritative leadership data instead. See indigenous-harvesters#2.
- **SSH to razor-crest rate-limits**: fail2ban blocks rapid SSH connections. Add 10-15s delays between SSH commands. Use SSH tunnels (`-f -N -L`) for sustained API access.

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
  - `docs/specs/geo-domain.md` — Geo bounded context, LocationService, NorthCloudClient, volunteer ranking
- **Framework specs:** Use `waaseyaa_*` MCP tools for framework-level context

## Architectural Boundaries

Minoo is the **application layer**. It owns entity types, map-driven UX, dialect-aware content, access policies, templates, and CSS.

**Minoo does NOT own:**
- Content classification logic (that's North Cloud)
- Crawl scheduling or source fetching (that's North Cloud)
- Entity system internals, storage engine, or ingestion envelope contract (that's Waaseyaa)

**Import rules:**
- Minoo imports from Waaseyaa (framework) — never the reverse
- Minoo consumes the shared taxonomy contract (`jonesrussell/indigenous-taxonomy`) for category/region/dialect constants
- Minoo may call North Cloud APIs (via NorthCloudClient) but must not import NC Go packages
- North Cloud must not contain Minoo-specific entity types or templates

**Shared contracts:**
- `jonesrussell/indigenous-taxonomy` — categories, regions, dialect codes (PHP package)
- Waaseyaa ingestion envelope schema — used by Python harvesters to feed Minoo directly
- NC source-manager API — used by harvesters to register sources
