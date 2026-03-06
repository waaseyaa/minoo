# Minoo

Indigenous knowledge platform powered by [Waaseyaa CMS](https://github.com/waaseyaa/framework) and [NorthCloud](https://github.com/jonesrussell/north-cloud) ingestion.

Minoo aggregates Indigenous cultural content — language resources, teachings, events, community groups, and cultural collections — into a unified platform with structured data and public-facing pages.

## Entity Types

| Domain | Entity Types |
|--------|-------------|
| Events | `event`, `event_type` |
| Groups | `group`, `group_type`, `cultural_group` |
| Teachings | `teaching`, `teaching_type` |
| Collections | `cultural_collection` |
| Language | `dictionary_entry`, `example_sentence`, `word_part`, `speaker` |

All entity types have JSON:API CRUD endpoints at `/api/{entity_type}` and JSON Schema at `/api/schema/{entity_type}`.

## Development

```bash
composer install                    # Install deps (symlinks to waaseyaa packages)
php -S localhost:8081 -t public     # Dev server
```

## Testing

```bash
./vendor/bin/phpunit                             # All tests (42 tests, 115 assertions)
./vendor/bin/phpunit --testsuite MinooUnit        # Unit tests
./vendor/bin/phpunit --testsuite MinooIntegration # Integration tests (boots full kernel)
```

## Architecture

Minoo is a **thin application** — custom entity types, access policies, service providers, and seeders live in `src/`. All framework code lives in [`waaseyaa/framework`](https://github.com/waaseyaa/framework) (sibling directory, symlinked via Composer path repositories).

```
minoo/
├── src/
│   ├── Entity/        # 12 custom entity classes
│   ├── Provider/      # 6 service providers
│   ├── Access/        # 6 access policy classes
│   └── Seed/          # Taxonomy + config seeders
├── tests/Minoo/
│   ├── Unit/          # Entity, access, seed tests
│   └── Integration/   # Full kernel boot smoke test
├── config/            # App configuration
├── public/index.php   # Web entry point
└── vendor/            # Symlinks to ../waaseyaa/packages/*
```

## License

GPL-2.0-or-later
