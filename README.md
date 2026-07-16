# Minoo

[![CI](https://github.com/waaseyaa/minoo/actions/workflows/ci.yml/badge.svg)](https://github.com/waaseyaa/minoo/actions/workflows/ci.yml)

Indigenous knowledge platform powered by [Waaseyaa CMS](https://github.com/waaseyaa/framework) and [NorthCloud](https://github.com/jonesrussell/north-cloud) ingestion.

Minoo aggregates Indigenous cultural content — language resources, teachings, events, community groups, and cultural collections — into a unified platform with structured data and public-facing pages.

## Entity Types

| Domain | Entity Types |
|--------|-------------|
| Language / corpus | `dictionary_entry`, `example_sentence`, `word_part`, `speaker` |
| Translation memory | `translation_memory`, `tm_gap_log`, `tm_backlog` |
| Ingestion | `ingest_log` |
| Community | `community`, `contributor`, `elder_support_request` |
| Groups / Events | `group`, `event` |
| Feed / social | `post` |
| Games | `game_session`, `daily_challenge`, `crossword_puzzle` |
| Account / Editorial | `saved_word`, `featured_item` |

The authoritative list is the `entityType()` registrations in `src/Provider/Entity/*Provider.php` and `src/Provider/LanguageModuleServiceProvider.php`.

## Development

```bash
composer install                    # Install deps (symlinks to waaseyaa packages)
php -S localhost:8081 -t public     # Dev server
```

## Testing

```bash
./vendor/bin/phpunit                             # All tests — the run's output is the authoritative count
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

## Deployment

Minoo (**minoo.live**) deploys from the **`waaseyaa-infra`** repo (`jonesrussell/waaseyaa-infra`), not from this repo — see its `runbooks/06-minoo-deploy.md`. This repo contains no deploy tooling; the old PHP Deployer path (`deploy.php`, `ops/`, `.github/workflows/deploy.yml`) was retired in the 2026-07 scope cuts.

To ship a new version:

1. In `waaseyaa-infra`, bump `MINOO_REF` in `compose/docker-compose.yml` to a `waaseyaa/minoo` `main` SHA.
2. Push — `.github/workflows/deploy-minoo.yml` rebuilds the `minoo-app` + `minoo-worker` containers on the Raspberry Pi.
3. Serving path: Cloudflare (proxied) → `oiatc-pi` cloudflared tunnel → Caddy (Host routing) → php-fpm.

The deploy workflow runs the idempotent `bin/waaseyaa db:init` — do **not** swap it for `migrate`: the production database predates consistent migration bookkeeping (details in runbook 06, which also covers the database volume, re-seeding, and DNS).

## License

Software: MIT. Community content: [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/). See [LICENSE](LICENSE) for details.
