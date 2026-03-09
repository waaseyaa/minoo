# Standardized CI Workflows Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add consistent CI workflows to all three Waaseyaa ecosystem repos so PRs are gated on tests and linting.

**Architecture:** Each repo gets a self-contained `ci.yml` workflow triggered on pushes to main and PRs to main. PHP 8.4 across all repos (matches Minoo deploy, fixes Waaseyaa Symfony 8 lockfile issue). Minoo and MyClaudia check out waaseyaa/framework alongside for Composer path repos.

**Tech Stack:** GitHub Actions, PHP 8.4, PHPUnit 10.5, Playwright (Minoo only), Composer, Node.js 22 (Minoo only)

---

### Task 1: Fix Waaseyaa Framework CI (framework#278)

**Files:**
- Modify: `/home/fsd42/dev/waaseyaa/.github/workflows/ci.yml`

**Branch:** `fix/ci-php84` (from current `feat/app-directory-convention` or main)

**Step 1: Create branch from main**

```bash
cd /home/fsd42/dev/waaseyaa
git checkout main
git checkout -b fix/ci-php84
```

**Step 2: Update ci.yml**

Changes:
1. Bump PHP from 8.3 → 8.4 in ALL jobs (test, security-defaults)
2. Add `extensions: pdo_sqlite, sqlite3, mbstring, xml` to PHP setup
3. Fix schema validation: add `--strict=false` to ajv compile (x-waaseyaa extension keywords are rejected in strict mode)
4. Add a `lint` job: `composer validate --strict` + PHP syntax check

Final `ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Validate composer.json
        run: composer validate --strict

      - name: Check PHP syntax
        run: find packages/*/src -name '*.php' -print0 | xargs -0 -n1 php -l

  test:
    name: PHPUnit
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_sqlite, sqlite3, mbstring, xml
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run unit tests
        run: ./vendor/bin/phpunit --testsuite Unit

      - name: Run integration tests
        run: ./vendor/bin/phpunit --testsuite Integration

  manifest-conformance:
    name: Manifest conformance
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Validate defaults manifests have project_versioning
        run: |
          fail=0
          for f in defaults/*.yaml; do
            if ! grep -q 'project_versioning' "$f"; then
              echo "FAIL: $f is missing project_versioning block"
              fail=1
            fi
          done
          exit $fail

      - name: Validate defaults JSON Schemas are well-formed
        run: |
          fail=0
          for f in defaults/*.schema.json; do
            if ! python3 -c "import json,sys; json.load(open('$f'))" 2>/dev/null; then
              echo "FAIL: $f is not valid JSON"
              fail=1
            fi
          done
          exit $fail

      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      - name: Install ajv-cli
        run: npm install -g ajv-cli

      - name: Validate defaults JSON Schemas are valid draft-07
        run: |
          fail=0
          for f in defaults/*.schema.json; do
            if ! ajv compile --spec=draft7 --strict=false -s "$f" > /dev/null 2>&1; then
              echo "FAIL: $f is not a valid JSON Schema draft-07"
              fail=1
            fi
          done
          exit $fail

  ingestion-defaults:
    name: Ingestion defaults
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Validate ingestion schema metadata and compatibility rules
        run: bash bin/check-ingestion-defaults

  security-defaults:
    name: Security defaults
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Check no secrets in defaults/
        run: bash bin/check-no-secrets

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_sqlite, sqlite3, mbstring, xml
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run structural secrets scan
        run: ./vendor/bin/phpunit --filter Phase22
```

**Step 3: Commit and push**

```bash
git add .github/workflows/ci.yml
git commit -m "fix(#278): bump CI to PHP 8.4, fix ajv strict mode, add lint job"
git push -u origin fix/ci-php84
```

**Step 4: Create PR and verify CI passes**

```bash
gh pr create --title "fix(#278): bump CI to PHP 8.4, fix schema validation" \
  --body "Closes #278"
```

Wait for CI to go green.

---

### Task 2: Add Minoo CI Workflow (minoo#121)

**Files:**
- Create: `/home/fsd42/dev/minoo/.github/workflows/ci.yml`

**Branch:** `ci/add-workflow` (from main)

**Step 1: Create branch from main**

```bash
cd /home/fsd42/dev/minoo
git checkout main
git checkout -b ci/add-workflow
```

**Step 2: Create ci.yml**

Key considerations:
- Must checkout waaseyaa/framework alongside (Composer path repos use `../waaseyaa/packages/*`)
- PHPUnit has two suites: MinooUnit, MinooIntegration
- Playwright needs Node.js 22, Chromium, and a PHP dev server
- Use WAASEYAA_PAT secret for framework checkout (same as deploy.yml)

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - name: Checkout minoo
        uses: actions/checkout@v4
        with:
          path: minoo

      - name: Checkout waaseyaa framework
        uses: actions/checkout@v4
        with:
          repository: waaseyaa/framework
          path: waaseyaa
          token: ${{ secrets.WAASEYAA_PAT }}

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Validate composer.json
        working-directory: minoo
        run: composer validate --strict

      - name: Check PHP syntax
        working-directory: minoo
        run: find src -name '*.php' -print0 | xargs -0 -n1 php -l

  test:
    name: PHPUnit
    runs-on: ubuntu-latest
    steps:
      - name: Checkout minoo
        uses: actions/checkout@v4
        with:
          path: minoo

      - name: Checkout waaseyaa framework
        uses: actions/checkout@v4
        with:
          repository: waaseyaa/framework
          path: waaseyaa
          token: ${{ secrets.WAASEYAA_PAT }}

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_sqlite, sqlite3, mbstring, xml
          coverage: none

      - name: Install dependencies
        working-directory: minoo
        run: composer install --no-interaction --prefer-dist

      - name: Run unit tests
        working-directory: minoo
        run: ./vendor/bin/phpunit --testsuite MinooUnit

      - name: Run integration tests
        working-directory: minoo
        run: ./vendor/bin/phpunit --testsuite MinooIntegration

  playwright:
    name: Playwright
    runs-on: ubuntu-latest
    steps:
      - name: Checkout minoo
        uses: actions/checkout@v4
        with:
          path: minoo

      - name: Checkout waaseyaa framework
        uses: actions/checkout@v4
        with:
          repository: waaseyaa/framework
          path: waaseyaa
          token: ${{ secrets.WAASEYAA_PAT }}

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_sqlite, sqlite3, mbstring, xml
          coverage: none

      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'
          cache-dependency-path: minoo/package-lock.json

      - name: Install PHP dependencies
        working-directory: minoo
        run: composer install --no-interaction --prefer-dist

      - name: Install Node dependencies
        working-directory: minoo
        run: npm ci

      - name: Install Playwright browsers
        working-directory: minoo
        run: npx playwright install --with-deps chromium

      - name: Run Playwright smoke tests
        working-directory: minoo
        run: npx playwright test
```

**Step 3: Commit and push**

```bash
cd /home/fsd42/dev/minoo
git add .github/workflows/ci.yml
git commit -m "ci(#121): add CI workflow with PHPUnit, Playwright, and lint"
git push -u origin ci/add-workflow
```

**Step 4: Create PR and verify CI passes**

```bash
gh pr create --title "ci(#121): add CI workflow for PRs" --body "Closes #121"
```

---

### Task 3: Add MyClaudia CI Workflow (myclaudia#19)

**Files:**
- Create: `/home/fsd42/dev/myclaudia/.github/workflows/ci.yml`

**Branch:** `ci/add-workflow` (from main)

**Step 1: Create branch from main**

```bash
cd /home/fsd42/dev/myclaudia
git checkout main
git checkout -b ci/add-workflow
```

**Step 2: Create ci.yml**

Key considerations:
- Must checkout waaseyaa/framework alongside (Composer path repos use `/home/jones/dev/waaseyaa/packages/*` locally but `../waaseyaa/packages/*` in CI)
- composer.json path repos point to `/home/jones/dev/waaseyaa/packages/*` — CI needs to override this. Use `composer config repositories.0 path ../waaseyaa/packages/*` before install, OR the checkout puts framework at the right relative path.
- Actually, the CI checkout puts repos side-by-side as `myclaudia/` and `waaseyaa/` — but composer.json references `/home/jones/dev/waaseyaa/packages/*` (absolute). Need to rewrite to relative `../waaseyaa/packages/*` in CI.
- PHPUnit has one suite: Unit

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - name: Checkout myclaudia
        uses: actions/checkout@v4
        with:
          path: myclaudia

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Validate composer.json
        working-directory: myclaudia
        run: composer validate --no-check-publish

      - name: Check PHP syntax
        working-directory: myclaudia
        run: find src -name '*.php' -print0 | xargs -0 -n1 php -l

  test:
    name: PHPUnit
    runs-on: ubuntu-latest
    steps:
      - name: Checkout myclaudia
        uses: actions/checkout@v4
        with:
          path: myclaudia

      - name: Checkout waaseyaa framework
        uses: actions/checkout@v4
        with:
          repository: waaseyaa/framework
          path: waaseyaa
          token: ${{ secrets.WAASEYAA_PAT }}

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_sqlite, sqlite3, mbstring, xml
          coverage: none

      - name: Rewrite Composer path repositories for CI
        working-directory: myclaudia
        run: |
          php -r "
            \$c = json_decode(file_get_contents('composer.json'), true);
            \$c['repositories'] = [['type' => 'path', 'url' => '../waaseyaa/packages/*']];
            file_put_contents('composer.json', json_encode(\$c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . \"\\n\");
          "

      - name: Install dependencies
        working-directory: myclaudia
        run: composer install --no-interaction --prefer-dist

      - name: Run unit tests
        working-directory: myclaudia
        run: ./vendor/bin/phpunit
```

**Step 3: Commit and push**

```bash
cd /home/fsd42/dev/myclaudia
git add .github/workflows/ci.yml
git commit -m "ci(#19): add CI workflow with PHPUnit and lint"
git push -u origin ci/add-workflow
```

**Step 4: Create PR and verify CI passes**

```bash
gh pr create --title "ci(#19): add CI workflow for PRs" --body "Closes #19"
```

---

## Execution Order

1. **Task 1 (Waaseyaa)** first — fix the existing CI so it passes on main
2. **Task 2 (Minoo)** and **Task 3 (MyClaudia)** in parallel — independent repos

## Verification

After all three PRs are created, watch CI with:
```bash
cd /home/fsd42/dev/waaseyaa && gh run watch
cd /home/fsd42/dev/minoo && gh run watch
cd /home/fsd42/dev/myclaudia && gh run watch
```
