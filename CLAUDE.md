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

## Entity Types (12)

| Domain | Entities | Provider | Policy |
|--------|----------|----------|--------|
| Events | `event`, `event_type` | `EventServiceProvider` | `EventAccessPolicy` |
| Groups | `group`, `group_type` | `GroupServiceProvider` | `GroupAccessPolicy` |
| Cultural Groups | `cultural_group` | `CulturalGroupServiceProvider` | `CulturalGroupAccessPolicy` |
| Teachings | `teaching`, `teaching_type` | `TeachingServiceProvider` | `TeachingAccessPolicy` |
| Collections | `cultural_collection` | `CulturalCollectionServiceProvider` | `CulturalCollectionAccessPolicy` |
| Language | `dictionary_entry`, `example_sentence`, `word_part`, `speaker` | `LanguageServiceProvider` | `LanguageAccessPolicy` |

All entities extend `EntityBase` with hardcoded `entityTypeId` and `entityKeys`. Each entity class constructor takes `(array $values)`.

## Access Policy Pattern

All policies follow the same pattern:
- Authenticated users with `'administer content'` permission: full access
- Anonymous users: view published only (`status == 1`), no create/update/delete
- Uses `#[PolicyAttribute(entityType: '...')]` for auto-discovery

`LanguageAccessPolicy` covers all 4 language entity types via `#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker'])]`.

## Seed Data

- **TaxonomySeeder**: `galleryVocabulary()` (6 terms), `teachingTagsVocabulary()` (6 terms)
- **ConfigSeeder**: `eventTypes()` (3), `groupTypes()` (3), `teachingTypes()` (3)

Static methods returning structured arrays — not persisted entities. Used by install/seed commands.

## Commands

```bash
composer install                              # Install deps (creates symlinks to waaseyaa packages)
php -S localhost:8081 -t public               # Dev server (port 8081)
./vendor/bin/phpunit                          # All tests
./vendor/bin/phpunit --testsuite MinooUnit     # Unit tests only
./vendor/bin/phpunit --testsuite MinooIntegration  # Integration tests (boots kernel with in-memory SQLite)
bin/waaseyaa                                  # CLI
```

## API Endpoints

All entity types get automatic JSON:API endpoints:
- `GET /api/{type}` — list (paginated)
- `GET /api/{type}/{id}` — read
- `POST /api/{type}` — create (requires authentication)
- `PATCH /api/{type}/{id}` — update
- `DELETE /api/{type}/{id}` — delete
- `GET /api/schema/{type}` — JSON Schema with field definitions

## Code Style

- PHP 8.3+, `declare(strict_types=1)` in every file
- Namespace: `Minoo\` for app code, `Minoo\Tests\` for tests
- PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(...)]`, `#[CoversNothing]` for integration
- `final class` by default

## Framework Relationship

- Framework packages are in `../waaseyaa/packages/` (path repositories in composer.json)
- Pre-Packagist: uses `@dev` version constraints with symlinks
- Post-Packagist: switch to `^0.1` constraints, remove path repositories
- See `../waaseyaa/CLAUDE.md` for full framework conventions and gotchas

## Key Patterns

- Custom entities: extend `EntityBase`, register via `EntityTypeManager` in a service provider's `register()` method
- Access policies: implement `AccessPolicyInterface`, use `#[PolicyAttribute]` for discovery
- Service providers: extend `ServiceProvider`, register in `extra.waaseyaa.providers` in composer.json
- Entity keys: each entity has a unique primary key name (e.g. `eid` for event, `deid` for dictionary_entry)
- Integration tests: boot `HttpKernel` with reflection (`boot()` is protected), use `putenv('WAASEYAA_DB=:memory:')` for in-memory SQLite, delete stale `storage/framework/packages.php` before boot

## Gotchas

- `dirname(__DIR__, 3)` from `tests/Minoo/Integration/` to reach project root (3 levels up, not 2)
- `PackageManifestCompiler` reads root `composer.json` for app providers and scans app PSR-4 namespaces for policies — this was a framework fix required for Minoo
- Stale `storage/framework/packages.php` cache can prevent new providers/policies from being discovered — delete it when adding new providers
