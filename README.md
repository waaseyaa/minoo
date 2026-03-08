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

## Deployment

Minoo is deployed to **minoo.live** via GitHub Actions + [PHP Deployer](https://deployer.org/).

### Automated deployment

Every push to `main` triggers `.github/workflows/deploy.yml`, which:

1. Checks out `waaseyaa/minoo` and `waaseyaa/framework` side-by-side (path repos need both)
2. Runs `composer install --no-dev` to resolve the vendor tree
3. Runs the unit test suite — deploy is blocked on failure
4. Assembles a clean artifact (`.build/`) excluding dev files
5. Deploys via `dep deploy production`, creating a timestamped release at:

```
/home/deployer/minoo/
  releases/        # timestamped releases (last 5 kept)
  shared/          # storage/ and .env persisted across releases
  current -> releases/<latest>
```

### Manual deployment

```bash
dep deploy production           # Full deploy
dep rollback production          # Roll back to previous release
dep deploy:unlock production     # Unlock if deploy was interrupted
```

### Initial server setup

As `jones@northcloud.one`:

```bash
# Create directories
sudo mkdir -p /home/deployer/minoo/{releases,shared/storage}
sudo chown -R deployer:deployer /home/deployer/minoo

# Create shared .env
sudo -u deployer nano /home/deployer/minoo/shared/.env

# Allow PHP-FPM reload without password prompt
echo "deployer ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.3-fpm" \
  | sudo tee /etc/sudoers.d/deployer
```

Add the deploy SSH public key to `/home/deployer/.ssh/authorized_keys`, then store secrets in GitHub:

| Secret | Description |
|--------|-------------|
| `DEPLOY_SSH_KEY` | Private SSH key for `deployer@minoo.live` |
| `WAASEYAA_PAT` | GitHub PAT with `repo` scope to clone `waaseyaa/framework` |

### Nginx + SSL

```bash
sudo cp ops/nginx/minoo.live.conf /etc/nginx/sites-available/minoo.live
sudo ln -s /etc/nginx/sites-available/minoo.live /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d minoo.live -d www.minoo.live
```

### Updating .env in production

```bash
ssh deployer@minoo.live
nano /home/deployer/minoo/shared/.env
# Changes take effect on next request; restart PHP-FPM for OPcache invalidation
```

## License

GPL-2.0-or-later
