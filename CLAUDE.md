# Minoo

Indigenous knowledge platform built on Waaseyaa CMS framework.

## Architecture

Minoo is a **thin application** — custom entity types, access policies, service providers, and seeders live in `src/`. All framework code lives in `waaseyaa/framework` (sibling directory, symlinked via Composer path repositories).

```
minoo/
├── src/Entity/        # Custom entity classes
├── src/Provider/      # Service providers
├── src/Access/        # Access policies
├── src/Seed/          # Data seeders
├── tests/Minoo/       # App-specific tests
├── config/            # App configuration
├── public/index.php   # Web entry point
└── vendor/            # Symlinks to ../waaseyaa/packages/*
```

## Commands

```bash
composer install                              # Install deps (creates symlinks to waaseyaa packages)
php -S localhost:8081 -t public               # Dev server
./vendor/bin/phpunit --testsuite MinooUnit     # Unit tests
./vendor/bin/phpunit --testsuite MinooIntegration  # Integration tests
bin/waaseyaa                                  # CLI
```

## Code Style

- PHP 8.3+, `declare(strict_types=1)` in every file
- Namespace: `Minoo\` for app code, `Minoo\Tests\` for tests
- PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(...)]`
- `final class` by default

## Framework Relationship

- Framework packages are in `../waaseyaa/packages/` (path repositories in composer.json)
- Pre-Packagist: uses `@dev` version constraints with symlinks
- Post-Packagist: switch to `^0.1` constraints, remove path repositories

## Key Patterns

- Custom entities: extend `EntityBase`, register via `EntityTypeManager` in a service provider
- Access policies: implement `AccessPolicyInterface` (+ `FieldAccessPolicyInterface`), use `#[PolicyAttribute]`
- Service providers: extend `ServiceProvider`, register in `extra.waaseyaa.providers` in composer.json
- See `../waaseyaa/CLAUDE.md` for full framework conventions and gotchas
