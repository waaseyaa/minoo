# Minoo

Indigenous knowledge platform built on Waaseyaa CMS framework.

## Architecture

Minoo is a **thin application** — custom entity types, access policies, service providers, and seeders live in `src/`. All framework code lives in `waaseyaa/framework` (sibling directory, symlinked via Composer path repositories).

```
minoo/
├── src/
│   ├── Entity/        # 12 custom entity classes
│   ├── Provider/      # 6 service providers (one per domain)
│   ├── Access/        # 6 access policy classes
│   └── Seed/          # TaxonomySeeder + ConfigSeeder
├── tests/Minoo/
│   ├── Unit/          # Entity, access, seed tests
│   └── Integration/   # Full kernel boot smoke test
├── config/            # App configuration
├── public/index.php   # Web entry point
└── vendor/            # Symlinks to ../waaseyaa/packages/*
```

## Orchestration

| File pattern | Specialist skill | Cold memory spec |
|---|---|---|
| `src/Entity/*`, `src/Provider/*` | `minoo:entities` | `docs/specs/entity-model.md` |
| `src/Access/*` | `minoo:entities` | `docs/specs/entity-model.md` (access section) |
| `src/Seed/*` | `minoo:entities` | `docs/specs/entity-model.md` (seed section) |
| `tests/Minoo/*` | `minoo:entities` | `docs/specs/entity-model.md` (testing section) |
| `config/*`, `public/*`, `composer.json` | — | See `../waaseyaa/CLAUDE.md` for framework conventions |

For framework-level work (kernel boot, entity storage, access handler internals), use the waaseyaa MCP tools:
- `waaseyaa_get_spec entity-system` — entity types, storage, field definitions
- `waaseyaa_get_spec access-control` — access policies, gate wiring
- `waaseyaa_get_spec infrastructure` — kernel boot, manifest compiler, providers
- `waaseyaa_search_specs <query>` — keyword search across all framework specs

## Entity Domains (4 domains, 12 types)

| Domain | Entities | Provider | Policy |
|--------|----------|----------|--------|
| Events | `event`, `event_type` | `EventServiceProvider` | `EventAccessPolicy` |
| Groups | `group`, `group_type`, `cultural_group` | `GroupServiceProvider`, `CulturalGroupServiceProvider` | `GroupAccessPolicy`, `CulturalGroupAccessPolicy` |
| Teachings | `teaching`, `teaching_type`, `cultural_collection` | `TeachingServiceProvider`, `CulturalCollectionServiceProvider` | `TeachingAccessPolicy`, `CulturalCollectionAccessPolicy` |
| Language | `dictionary_entry`, `example_sentence`, `word_part`, `speaker` | `LanguageServiceProvider` | `LanguageAccessPolicy` |

## Operation Checklists

**Adding a Minoo entity type:**
1. Create entity class in `src/Entity/` extending `EntityBase` — hardcode `entityTypeId` and `entityKeys`
2. Register `EntityType` in existing or new service provider's `register()` method
3. Create or update `AccessPolicy` in `src/Access/` with `#[PolicyAttribute]`
4. Write unit test in `tests/Minoo/Unit/Entity/`
5. Run `./vendor/bin/phpunit` — delete `storage/framework/packages.php` if new provider isn't discovered

**Adding seed data:**
1. Add static method to `TaxonomySeeder` (vocabularies) or `ConfigSeeder` (type configs)
2. Write unit test in `tests/Minoo/Unit/Seed/`
3. Return structured arrays — not persisted entities

## Commands

```bash
composer install                              # Install deps (symlinks to waaseyaa packages)
php -S localhost:8081 -t public               # Dev server (port 8081)
./vendor/bin/phpunit                          # All tests (42 tests, 115 assertions)
./vendor/bin/phpunit --testsuite MinooUnit     # Unit tests only
./vendor/bin/phpunit --testsuite MinooIntegration  # Integration tests (in-memory SQLite)
bin/waaseyaa                                  # CLI
```

## Code Style

- PHP 8.3+, `declare(strict_types=1)` in every file
- Namespace: `Minoo\` for app code, `Minoo\Tests\` for tests
- PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(...)]`, `#[CoversNothing]` for integration
- `final class` by default
- See `../waaseyaa/CLAUDE.md` for full framework conventions

## Gotchas

- **`dirname(__DIR__, 3)`** from `tests/Minoo/Integration/` to reach project root (3 levels up, not 2)
- **Stale manifest cache**: `storage/framework/packages.php` can prevent new providers/policies from being discovered — delete it when adding new providers
- **`PackageManifestCompiler`** reads root `composer.json` for app providers and scans app PSR-4 namespaces for policies — this was a framework fix required for Minoo
- **`LanguageAccessPolicy`** covers all 4 language types via array attribute: `#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'])]`
- **Entity keys** are unique per type (e.g. `eid` for event, `deid` for dictionary_entry, `ccid` for cultural_collection)
- **Integration tests** boot `HttpKernel` with reflection (`boot()` is protected), use `putenv('WAASEYAA_DB=:memory:')` for in-memory SQLite

## Codified Context

- **Tier 1 (Constitution):** This CLAUDE.md — orchestration, checklists, gotchas
- **Tier 2 (Skill):** `skills/minoo/SKILL.md` — domain knowledge for all 4 entity domains
- **Tier 3 (Spec):** `docs/specs/entity-model.md` — full entity model, access patterns, seed data
- **Framework specs:** Use `waaseyaa_*` MCP tools for framework-level context
