# V1 Deployment Runbook

This runbook covers deploying Minoo V1.0.2 to staging and production.

---

## Prerequisites

- PHP 8.4+ with extensions: `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`
- Composer 2.x
- [PHP Deployer](https://deployer.org/) installed (`composer global require deployer/deployer`)
- SSH access to target server (`minoo.live`)
- GitHub CLI (`gh`) for workflow dispatch

---

## Pre-deploy Checklist

1. Verify CI is green on `main`:
   ```bash
   gh run list --branch main --limit 5
   ```

2. Run smoke tests locally:
   ```bash
   bin/smoke-test
   ```

3. Run release validation:
   ```bash
   bin/validate-release
   ```

4. Verify production `.env` has all required NorthCloud variables:
   ```
   NC_API_URL=https://api.northcloud.one
   NC_API_TOKEN=<token>
   ```

5. Verify production `.env` has database and app configuration:
   ```
   APP_ENV=production
   APP_DEBUG=false
   WAASEYAA_DB=/path/to/production.sqlite
   ```

---

## Deploy to Staging

```bash
dep deploy staging
```

### Staging Verification

1. Check health endpoint responds:
   ```bash
   curl -s https://staging.minoo.live/health
   ```

2. Verify dictionary entries load on `/language` page

3. Verify community pages show leadership data

4. Verify consent fields are present on content entities

5. Run smoke tests against staging:
   ```bash
   MINOO_BASE_URL=https://staging.minoo.live bin/smoke-test
   ```

---

## Deploy to Production

### Option 1: GitHub Actions (Recommended)

```bash
gh workflow run deploy-production.yml -f confirm=deploy-v1
```

Monitor the run:
```bash
gh run watch
```

### Option 2: Manual Deploy

```bash
dep deploy production --no-interaction
```

### What the Deploy Does

1. Checks out `minoo` and `waaseyaa/framework` repositories
2. Installs production dependencies (`composer install --no-dev`)
3. Assembles build artifact (excludes tests, docs, dev files)
4. Deploys via PHP Deployer (rsync to server, symlink swap)
5. Runs migrations (`bin/migrate`)

---

## Post-deploy Verification

Run these checks immediately after deployment:

1. **Health endpoint:**
   ```bash
   curl -s https://minoo.live/health
   ```

2. **Dictionary entries load:**
   Visit `https://minoo.live/language` -- verify entries display with OPD attribution.

3. **Community pages show leadership:**
   Visit any community detail page -- verify leadership section renders.

4. **Robots.txt blocks sensitive paths:**
   ```bash
   curl -s https://minoo.live/robots.txt
   ```
   Verify `/api/` and `/dashboard/` are disallowed.

5. **Footer attribution:**
   Check page footer displays CC BY-NC-SA 4.0 notice and OPD attribution link.

6. **Consent fields active:**
   Verify content with `consent_public = false` does not appear on public pages.

7. **Export governance:**
   ```bash
   ssh deployer@minoo.live "cd /opt/minoo/current && bin/export-communities"
   ```
   Should fail without `--confirm` flag.

---

## Rollback

If issues are discovered post-deploy, rollback immediately:

```bash
dep rollback production
```

This performs an instant symlink swap to the previous release. No data loss occurs because:
- All schema migrations are additive (no destructive changes)
- SQLite database lives outside the release directory
- Environment files are in `shared/` (not part of the release)

### Rollback Verification

After rollback, repeat the post-deploy verification steps above to confirm the previous release is serving correctly.

---

## Troubleshooting

### Migration fails during deploy

Migrations run in transactions. If one fails, it rolls back cleanly. Check logs:
```bash
ssh deployer@minoo.live "cat /opt/minoo/current/storage/logs/*.log | tail -50"
```

### NorthCloud API connection fails

Verify environment variables:
```bash
ssh deployer@minoo.live "cd /opt/minoo/current && source shared/.env && echo \$NC_API_URL"
```

Check NC API is reachable from the server:
```bash
ssh deployer@minoo.live "curl -s -o /dev/null -w '%{http_code}' https://api.northcloud.one/health"
```

### Dictionary entries missing after deploy

The dictionary sync runs as a scheduled job, not during deploy. Trigger manually if needed:
```bash
ssh deployer@minoo.live "cd /opt/minoo/current && bin/waaseyaa sync:dictionary"
```

### Deployer permission errors

Ensure the `deployer` user has write access to `/opt/minoo/` and the SSH key is authorized:
```bash
ssh deployer@minoo.live "ls -la /opt/minoo/"
```
