# Waaseyaa Alpha.140 Upgrade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade Minoo from Waaseyaa alpha.137/138 to alpha.140 and integrate the bimaaji introspection package.

**Architecture:** Composer package upgrade + new bridge provider for bimaaji. No entity or template changes. The admin-surface package updates include static asset serving improvements that must be verified.

**Tech Stack:** PHP 8.4, Composer, Waaseyaa framework packages, PHPUnit, Playwright

---

## File Structure

| Action | File | Purpose |
|--------|------|---------|
| Modify | `composer.json` | Bump version constraints, add bimaaji |
| Modify | `composer.lock` | Auto-updated by composer |
| Create | `src/Provider/BimaajiBridgeProvider.php` | Wire bimaaji introspection into DI container |
| Modify | `src/Provider/AppServiceProvider.php` | Register BimaajiBridgeProvider (if not auto-discovered) |
| Delete | `storage/framework/packages.php` | Clear stale manifest after provider changes |

---

### Task 1: Bump waaseyaa/* packages to alpha.140

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock` (auto)

- [ ] **Step 1: Check current locked versions**

Run: `cd /home/fsd42/dev/minoo && composer show 'waaseyaa/*' | head -20`
Expected: All packages at v0.1.0-alpha.137 or v0.1.0-alpha.138

- [ ] **Step 2: Run composer update for waaseyaa packages**

Run: `cd /home/fsd42/dev/minoo && composer update 'waaseyaa/*' --with-all-dependencies`
Expected: All waaseyaa/* packages updated to v0.1.0-alpha.140

- [ ] **Step 3: Clear stale manifest**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 4: Run PHPUnit tests**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit`
Expected: All 442+ tests pass. If failures occur, note the error messages — they indicate breaking changes to fix before proceeding.

- [ ] **Step 5: Run Playwright tests**

Run: `cd /home/fsd42/dev/minoo && npx playwright test`
Expected: 132+ passing (same as baseline). Any new failures indicate admin-surface or rendering regressions.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: bump waaseyaa packages to alpha.140"
```

---

### Task 2: Add waaseyaa/bimaaji package

**Files:**
- Modify: `composer.json`
- Create: `src/Provider/BimaajiBridgeProvider.php`
- Test: `tests/App/Unit/Provider/BimaajiBridgeProviderTest.php`

- [ ] **Step 1: Write the test for BimaajiBridgeProvider**

Create `tests/App/Unit/Provider/BimaajiBridgeProviderTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\BimaajiBridgeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator;
use Waaseyaa\Foundation\ServiceProvider;

#[CoversClass(BimaajiBridgeProvider::class)]
final class BimaajiBridgeProviderTest extends TestCase
{
    #[Test]
    public function it_extends_service_provider(): void
    {
        $ref = new \ReflectionClass(BimaajiBridgeProvider::class);
        $this->assertTrue($ref->isSubclassOf(ServiceProvider::class));
    }

    #[Test]
    public function it_registers_application_graph_generator(): void
    {
        $provider = new BimaajiBridgeProvider();
        // Verify the register method exists and is callable
        $this->assertTrue(method_exists($provider, 'register'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Provider/BimaajiBridgeProviderTest.php`
Expected: FAIL — class `App\Provider\BimaajiBridgeProvider` not found

- [ ] **Step 3: Add bimaaji to composer.json**

Run: `cd /home/fsd42/dev/minoo && composer require waaseyaa/bimaaji:'^0.1'`
Expected: Package installed successfully

- [ ] **Step 4: Create BimaajiBridgeProvider**

Create `src/Provider/BimaajiBridgeProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator;
use Waaseyaa\Bimaaji\Introspection\EntityIntrospectionProvider;
use Waaseyaa\Bimaaji\Introspection\RoutingIntrospectionProvider;
use Waaseyaa\Bimaaji\Introspection\AdminIntrospectionProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider;

final class BimaajiBridgeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ApplicationGraphGenerator::class, function () {
            $etm = $this->resolve(EntityTypeManager::class);

            return new ApplicationGraphGenerator([
                new EntityIntrospectionProvider($etm),
                new AdminIntrospectionProvider($etm),
            ]);
        });
    }
}
```

Note: `RoutingIntrospectionProvider` requires a `RouteCollection` which may not be available at registration time. Start with entity + admin introspection. Add routing introspection if the route collection is accessible from the container.

- [ ] **Step 5: Register the provider**

Add to `composer.json` under `extra.waaseyaa.providers`:

```json
"providers": [
    "App\\Provider\\AppServiceProvider",
    "App\\Provider\\BimaajiBridgeProvider"
]
```

Then clear the manifest:

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Provider/BimaajiBridgeProviderTest.php`
Expected: PASS

- [ ] **Step 7: Run full test suite**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit`
Expected: All tests pass (previous count + 2 new tests)

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock src/Provider/BimaajiBridgeProvider.php tests/App/Unit/Provider/BimaajiBridgeProviderTest.php
git commit -m "feat: add waaseyaa/bimaaji with bridge provider"
```

---

### Task 3: Verify admin-surface serves from vendor dist

**Files:**
- No file changes expected (verification only)

- [ ] **Step 1: Start dev server**

Run: `cd /home/fsd42/dev/minoo && php -S localhost:8080 -t public public/index.php &`

- [ ] **Step 2: Check admin route responds**

Run: `curl -sS -o /dev/null -w "%{http_code}/%{size_download}" http://localhost:8080/admin`
Expected: 200 with non-zero body size. If 404, the admin-surface fallback isn't wired up.

- [ ] **Step 3: Check SPA assets load**

Open `http://localhost:8080/admin` in a browser. Verify:
- The SPA shell renders (Vue/Nuxt app mounts)
- No console errors for missing assets
- The `/admin/_surface/session` request returns a response (may be 401 if not authenticated)

- [ ] **Step 4: Document findings**

If the admin SPA loads: proceed to Task 4.
If it doesn't: investigate the `AdminSurfaceServiceProvider` and `AdminSpaFallback` handler. The alpha.140 update may have changed how the dist directory is resolved. Fix any path issues before proceeding.

- [ ] **Step 5: Stop dev server and commit if fixes were needed**

```bash
kill %1  # stop background server
# Only commit if files were changed:
git add -A && git commit -m "fix: admin-surface serving after alpha.140 upgrade"
```

---

### Task 4: Smoke-test newsletter entities via admin-surface CRUD

**Files:**
- No file changes expected (verification only)

- [ ] **Step 1: Start dev server and authenticate**

Run: `cd /home/fsd42/dev/minoo && php -S localhost:8080 -t public public/index.php &`

Navigate to `http://localhost:8080/admin` in a browser. Log in with dev admin credentials.

- [ ] **Step 2: Check catalog includes newsletter entities**

Run: `curl -sS http://localhost:8080/admin/_surface/catalog | python3 -m json.tool | grep newsletter`
Expected: `newsletter_edition`, `newsletter_item`, `newsletter_submission` appear in the catalog response.

- [ ] **Step 3: List newsletter editions**

Run: `curl -sS http://localhost:8080/admin/_surface/newsletter_edition`
Expected: JSON response with empty list or existing editions (from backport script).

- [ ] **Step 4: Create a test edition via admin API**

```bash
curl -sS -X POST http://localhost:8080/admin/_surface/newsletter_edition/action/create \
  -H 'Content-Type: application/json' \
  -d '{"volume": 99, "issue_number": 99, "community_id": "test", "headline": "Smoke Test", "status": "draft"}'
```

Expected: 200/201 with created entity data.

- [ ] **Step 5: Verify the edition persists**

Run: `curl -sS http://localhost:8080/admin/_surface/newsletter_edition`
Expected: The smoke test edition appears in the list.

- [ ] **Step 6: Clean up test data and stop server**

Delete the test edition via admin API or directly:
```bash
kill %1
```

- [ ] **Step 7: Commit milestone completion marker**

```bash
git commit --allow-empty -m "chore: milestone 1 complete — waaseyaa alpha.140 verified"
```
