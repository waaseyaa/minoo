# Sprint 3: Production Hardening & Release — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete all Sprint 3 issues (security, accessibility, media copyright, Playwright e2e tests, documentation) to satisfy V1 release gates and governance signoffs.

**Architecture:** Sprint 3 is a hardening sprint — no new features. It adds compliance fields (#195), security headers/config (#198), accessibility fixes (#199), end-to-end test coverage (#136, #137, #138), and documentation (#204). All work happens on `main`.

**Tech Stack:** PHP 8.3, PHPUnit 10.5, Playwright, Twig 3, SQLite, axe-core (accessibility)

---

## Sprint 3 Scope

| Issue | Title | Objective | Signoff Gate |
|-------|-------|-----------|-------------|
| #195 | Media copyright flag and approval workflow | Add `copyright_status` field to 7 media-bearing entities; filter non-approved from public pages | Gate 2 (Media Copyright) |
| #198 | Security review (OWASP top 10) | Audit and harden: session cookies, security headers, rate limiting, dependency audit | Gate 5 (Security) |
| #199 | Accessibility audit (WCAG 2.1 AA) | Fix violations, add skip-link, verify contrast, add axe-core Playwright test | None (quality) |
| #136 | Playwright tests: auth flows | 10 tests covering login, register, logout with form validation | None (CI) |
| #137 | Playwright tests: form submissions | 13 tests covering elder request and volunteer signup submission | None (CI) |
| #138 | Playwright tests: content browsing | Tests for communities, people, search, teachings, events, groups, language pages | None (CI) |
| #204 | Doc: consent field scope | Update entity-model spec to document consent field rationale | None (doc) |

## Branch Strategy

All work on `main`. No worktrees needed — issues are independent and can be committed sequentially. Each issue gets its own commit with `feat(#N):` or `docs(#N):` prefix.

## Parallelization Map

```
Batch 1 (independent, run in parallel):
  ├── #204  Documentation update (docs only, no code)
  ├── #195  Media copyright fields (entity/provider changes)
  └── #198  Security hardening (middleware, config, headers)

Batch 2 (independent, run in parallel, after Batch 1):
  ├── #199  Accessibility audit (template + CSS changes)
  ├── #136  Playwright: auth flows
  ├── #137  Playwright: form submissions
  └── #138  Playwright: content browsing

Batch 3 (sequential, after all):
  └── Final verification + governance checkpoint update
```

**Rationale:** Batch 1 issues modify different files with no overlap. Batch 2 Playwright tests can run in parallel since each touches separate spec files. #199 modifies templates but doesn't conflict with Playwright test creation. Batch 2 depends on Batch 1 because #195 adds `copyright_status` filtering that #138 content browsing tests should observe.

---

## Chunk 1: Documentation + Media Copyright + Security

### Task 1: Documentation — Consent Field Scope (#204)

**Files:**
- Modify: `docs/specs/entity-model.md`

- [ ] **Step 1: Read entity-model.md and locate consent section**

Run:
```bash
grep -n "consent" docs/specs/entity-model.md
```
Find the section discussing consent fields.

- [ ] **Step 2: Add consent field scope documentation**

After the existing consent field documentation, add:

```markdown
### Consent Field Scope

Consent fields (`consent_public`, `consent_ai_training`) apply only to entities representing
individually contributed cultural knowledge:

| Entity Type | Has Consent Fields | Rationale |
|-------------|-------------------|-----------|
| `teaching` | Yes | Individual cultural knowledge contributions |
| `dictionary_entry` | Yes | Individual language contributions |
| `event` | No | Community-level public information (powwows, gatherings) |
| `group` | No | Community-level public information (band councils, organizations) |
| `community` | No | Public registry data sourced from CIRNAC/NorthCloud |
| `cultural_group` | No | Community-level cultural groupings |
| `cultural_collection` | No | Curated collections, not individual contributions |

This distinction is intentional. Events, groups, and communities represent public community
information that does not require individual consent gating. If consent controls are needed
for these types in the future, add the fields to their respective service providers following
the pattern in `TeachingServiceProvider` and `LanguageServiceProvider`.
```

- [ ] **Step 3: Verify the edit**

Run:
```bash
grep -c "Consent Field Scope" docs/specs/entity-model.md
```
Expected: 1

- [ ] **Step 4: Commit**

```bash
git add docs/specs/entity-model.md
git commit -m "docs(#204): Document consent field scope across entity types

Closes #204

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

---

### Task 2: Media Copyright Flag (#195)

**Files:**
- Modify: `src/Provider/TeachingServiceProvider.php`
- Modify: `src/Provider/EventServiceProvider.php`
- Modify: `src/Provider/GroupServiceProvider.php`
- Modify: `src/Provider/CulturalGroupServiceProvider.php`
- Modify: `src/Provider/CulturalCollectionServiceProvider.php`
- Modify: `src/Provider/PeopleServiceProvider.php`
- Modify: `src/Provider/LanguageServiceProvider.php` (speaker entity)
- Modify: `src/Controller/TeachingController.php`
- Modify: `src/Controller/EventController.php`
- Modify: `src/Controller/GroupController.php`
- Create: `tests/Minoo/Unit/MediaCopyright/CopyrightFieldTest.php`

**Context:** 7 entities have `media_id` fields: `teaching`, `event`, `group`, `cultural_group`, `cultural_collection`, `person`, `speaker`. Each needs a `copyright_status` field with values: `community_owned`, `cc_by_nc_sa`, `requires_permission`, `unknown`. Default: `unknown`. Public controllers must exclude entities where `copyright_status` is `requires_permission` or `unknown` AND `media_id` is set.

- [ ] **Step 1: Write failing test for copyright_status field**

Create `tests/Minoo/Unit/MediaCopyright/CopyrightFieldTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\MediaCopyright;

use Minoo\Entity\Teaching;
use Minoo\Entity\Event;
use Minoo\Entity\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teaching::class)]
#[CoversClass(Event::class)]
#[CoversClass(Group::class)]
final class CopyrightFieldTest extends TestCase
{
    #[Test]
    public function teaching_copyright_status_defaults_to_unknown(): void
    {
        $teaching = new Teaching(['title' => 'Test', 'type' => 'culture']);

        self::assertSame('unknown', $teaching->get('copyright_status'));
    }

    #[Test]
    public function teaching_can_set_copyright_status(): void
    {
        $teaching = new Teaching([
            'title' => 'Test',
            'type' => 'culture',
            'copyright_status' => 'community_owned',
        ]);

        self::assertSame('community_owned', $teaching->get('copyright_status'));
    }

    #[Test]
    public function event_copyright_status_defaults_to_unknown(): void
    {
        $event = new Event(['title' => 'Test Powwow', 'type' => 'powwow']);

        self::assertSame('unknown', $event->get('copyright_status'));
    }

    #[Test]
    public function group_copyright_status_defaults_to_unknown(): void
    {
        $group = new Group(['name' => 'Test Group', 'type' => 'first_nation']);

        self::assertSame('unknown', $group->get('copyright_status'));
    }

    #[Test]
    public function copyright_status_accepts_all_valid_values(): void
    {
        $validValues = ['community_owned', 'cc_by_nc_sa', 'requires_permission', 'unknown'];

        foreach ($validValues as $value) {
            $teaching = new Teaching([
                'title' => 'Test',
                'type' => 'culture',
                'copyright_status' => $value,
            ]);
            self::assertSame($value, $teaching->get('copyright_status'));
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
./vendor/bin/phpunit tests/Minoo/Unit/MediaCopyright/CopyrightFieldTest.php
```
Expected: FAIL — `copyright_status` field not defined yet.

- [ ] **Step 3: Add copyright_status field to all 7 media-bearing entity providers**

In each provider's `fieldDefinitions` array, add after `media_id`:

```php
'copyright_status' => [
    'type' => 'string',
    'label' => 'Copyright Status',
    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
    'default_value' => 'unknown',
    'weight' => 99,
],
```

Add to these providers:
- `TeachingServiceProvider.php` — in the `teaching` entity type definition
- `EventServiceProvider.php` — in the `event` entity type definition
- `GroupServiceProvider.php` — in the `group` entity type definition
- `CulturalGroupServiceProvider.php` — in the `cultural_group` entity type definition
- `CulturalCollectionServiceProvider.php` — in the `cultural_collection` entity type definition
- `PeopleServiceProvider.php` — in the `person` entity type definition
- `LanguageServiceProvider.php` — in the `speaker` entity type definition

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
./vendor/bin/phpunit tests/Minoo/Unit/MediaCopyright/CopyrightFieldTest.php
```
Expected: OK (5 tests)

- [ ] **Step 5: Update controllers to filter by copyright_status**

For controllers that display media-bearing entities publicly, add a condition to exclude entities where `media_id` is set and `copyright_status` is NOT `community_owned` or `cc_by_nc_sa`.

Since entity queries may not support complex OR/AND conditions easily, use a simpler approach: filter in the controller after loading. In `TeachingController::list()`, `EventController::list()`, and `GroupController::list()`:

After loading entities, filter out those with non-approved media:

```php
$teachings = array_filter($teachings, function ($entity) {
    $mediaId = $entity->get('media_id');
    if ($mediaId === null || $mediaId === '') {
        return true; // No media, no copyright concern
    }
    $status = $entity->get('copyright_status');
    return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
});
$teachings = array_values($teachings);
```

Apply the same pattern to `EventController::list()` and `GroupController::list()`.

For the `show()` methods: check after loading the single entity and return 404 if it has non-approved media.

- [ ] **Step 6: Run full test suite**

Run:
```bash
./vendor/bin/phpunit
```
Expected: All tests pass (283+ tests).

- [ ] **Step 7: Commit**

```bash
git add src/Provider/ src/Controller/EventController.php src/Controller/TeachingController.php src/Controller/GroupController.php tests/Minoo/Unit/MediaCopyright/
git commit -m "feat(#195): Media copyright flag and approval workflow

Add copyright_status field (community_owned, cc_by_nc_sa,
requires_permission, unknown) to all 7 media-bearing entities.
Controllers filter out entities with non-approved media from
public pages. Default is unknown (excluded from public display).

Closes #195

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

- [ ] **Step 8: Delete stale manifest cache**

Run:
```bash
rm -f storage/framework/packages.php
```
Required after modifying service providers.

---

### Task 3: Security Review — OWASP Top 10 (#198)

**Files:**
- Create: `src/Middleware/SecurityHeadersMiddleware.php`
- Create: `src/Middleware/RateLimitMiddleware.php`
- Modify: `src/Provider/AuthServiceProvider.php` (register middleware)
- Modify: `public/index.php` (session cookie config)
- Create: `tests/Minoo/Unit/Security/SecurityHeadersTest.php`
- Create: `tests/Minoo/Unit/Security/RateLimitTest.php`
- Create: `docs/governance/security-checklist.md`

**Context:** OWASP top 10 audit. Some items are already handled (CSRF, SQL injection, XSS via Twig autoescape). Remaining: session cookies, security headers, rate limiting, dependency audit, secrets scan.

- [ ] **Step 1: Audit session cookie configuration**

Read `public/index.php` and check session cookie settings. If not present, add before `session_start()`:

```php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
```

Note: `cookie_secure` requires HTTPS. For local dev, this is fine — PHP falls back gracefully.

- [ ] **Step 2: Write failing test for security headers**

Create `tests/Minoo/Unit/Security/SecurityHeadersTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Security;

use Minoo\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityHeadersMiddleware::class)]
final class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function returns_expected_headers(): void
    {
        $headers = SecurityHeadersMiddleware::headers();

        self::assertArrayHasKey('X-Content-Type-Options', $headers);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertArrayHasKey('X-Frame-Options', $headers);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertArrayHasKey('Referrer-Policy', $headers);
        self::assertArrayHasKey('Permissions-Policy', $headers);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run:
```bash
./vendor/bin/phpunit tests/Minoo/Unit/Security/SecurityHeadersTest.php
```
Expected: FAIL — class does not exist.

- [ ] **Step 4: Implement SecurityHeadersMiddleware**

Create `src/Middleware/SecurityHeadersMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;

final class SecurityHeadersMiddleware implements HttpMiddlewareInterface
{
    /** @return array<string, string> */
    public static function headers(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    public function process(Request $request, HttpHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        foreach (self::headers() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
./vendor/bin/phpunit tests/Minoo/Unit/Security/SecurityHeadersTest.php
```
Expected: PASS

- [ ] **Step 6: Write failing test for rate limiting**

Create `tests/Minoo/Unit/Security/RateLimitTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Security;

use Minoo\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = ':memory:';
    }

    #[Test]
    public function allows_requests_under_limit(): void
    {
        $limiter = new RateLimitMiddleware($this->dbPath);

        self::assertTrue($limiter->check('127.0.0.1', '/login', 5, 60));
    }

    #[Test]
    public function blocks_requests_over_limit(): void
    {
        $limiter = new RateLimitMiddleware($this->dbPath);

        for ($i = 0; $i < 5; $i++) {
            $limiter->record('127.0.0.1', '/login');
        }

        self::assertFalse($limiter->check('127.0.0.1', '/login', 5, 60));
    }

    #[Test]
    public function different_ips_have_separate_limits(): void
    {
        $limiter = new RateLimitMiddleware($this->dbPath);

        for ($i = 0; $i < 5; $i++) {
            $limiter->record('127.0.0.1', '/login');
        }

        self::assertFalse($limiter->check('127.0.0.1', '/login', 5, 60));
        self::assertTrue($limiter->check('192.168.1.1', '/login', 5, 60));
    }
}
```

- [ ] **Step 7: Implement RateLimitMiddleware**

Create `src/Middleware/RateLimitMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Middleware;

final class RateLimitMiddleware
{
    private \PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->ensureTable();
    }

    public function check(string $ip, string $path, int $maxAttempts, int $windowSeconds): bool
    {
        $since = time() - $windowSeconds;
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND path = ? AND created_at > ?'
        );
        $stmt->execute([$ip, $path, $since]);

        return (int) $stmt->fetchColumn() < $maxAttempts;
    }

    public function record(string $ip, string $path): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (ip, path, created_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ip, $path, time()]);
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                path TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );
    }
}
```

- [ ] **Step 8: Run rate limit test**

Run:
```bash
./vendor/bin/phpunit tests/Minoo/Unit/Security/RateLimitTest.php
```
Expected: OK (3 tests)

- [ ] **Step 9: Register SecurityHeadersMiddleware in HttpKernel**

Check how CsrfMiddleware is registered and follow the same pattern. The middleware is registered at the framework level in `HttpKernel.php`. Since Minoo can't modify the framework, register it in `public/index.php` or via a service provider that hooks into the kernel's middleware stack.

Read the kernel to determine the registration approach, then add `SecurityHeadersMiddleware` to the middleware pipeline.

- [ ] **Step 10: Apply rate limiting to auth routes**

In `AuthController::submitLogin()` and `AuthController::submitForgotPassword()`, add rate limit checks at the top of the method:

```php
$limiter = new RateLimitMiddleware(
    getenv('WAASEYAA_DB') ?: dirname(__DIR__, 2) . '/waaseyaa.sqlite'
);
$ip = $request->getClientIp() ?? '0.0.0.0';

if (!$limiter->check($ip, '/login', 5, 300)) {
    $html = $this->twig->render('auth/login.html.twig', [
        'errors' => ['email' => 'Too many attempts. Please try again in 5 minutes.'],
        'values' => [],
    ]);
    return new SsrResponse(content: $html, statusCode: 429);
}

$limiter->record($ip, '/login');
```

Apply the same pattern to `submitForgotPassword()` with path `/forgot-password` and limit 3 per 300 seconds.

- [ ] **Step 11: Run composer audit**

Run:
```bash
composer audit
```
Expected: No critical or high vulnerabilities. If any found, update dependencies.

- [ ] **Step 12: Write security checklist document**

Create `docs/governance/security-checklist.md`:

```markdown
# V1 Security Checklist (OWASP Top 10)

**Date:** 2026-03-12
**Reviewer:** Automated + manual code audit

| # | Category | Status | Evidence |
|---|----------|--------|----------|
| A01 | Broken Access Control | PASS | Access policies on all entities, session-based auth, role checks |
| A02 | Cryptographic Failures | PASS | bcrypt password hashing, crypto-random tokens (bin2hex/random_bytes) |
| A03 | Injection (SQLi/XSS) | PASS | PDO prepared statements, Twig autoescape enabled globally |
| A04 | Insecure Design | PASS | Consent metadata, copyright filtering, role-based dashboards |
| A05 | Security Misconfiguration | PASS | Security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy), session cookie flags (HttpOnly, Secure, SameSite=Lax) |
| A06 | Vulnerable Components | VERIFY | `composer audit` — run at deploy time |
| A07 | Auth Failures | PASS | Rate limiting on login/forgot-password, session regeneration, CSRF on all forms |
| A08 | Data Integrity | PASS | No deserialization of user input, CSRF tokens on state-changing requests |
| A09 | Logging Failures | NOTE | Minimal logging in V1 — acceptable for initial release, improve in V1.1 |
| A10 | SSRF | PASS | No user-controlled URL fetching, NorthCloud API URL is config-only |
```

- [ ] **Step 13: Run full test suite**

Run:
```bash
./vendor/bin/phpunit
```
Expected: All tests pass.

- [ ] **Step 14: Commit**

```bash
git add src/Middleware/ tests/Minoo/Unit/Security/ src/Controller/AuthController.php public/index.php docs/governance/security-checklist.md
git commit -m "feat(#198): Security review — OWASP top 10 hardening

Add SecurityHeadersMiddleware (X-Content-Type-Options, X-Frame-Options,
Referrer-Policy, Permissions-Policy). Add RateLimitMiddleware with SQLite
backend for login and forgot-password rate limiting (5 attempts/5 min).
Configure session cookies (HttpOnly, Secure, SameSite=Lax).
Add security checklist document.

Closes #198

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

---

## Chunk 2: Accessibility + Playwright Tests

### Task 4: Accessibility Audit (#199)

**Files:**
- Modify: `templates/base.html.twig` (skip-to-content link)
- Modify: `public/css/minoo.css` (skip-link styles, contrast fixes if needed)
- Modify: templates with images (add alt text where missing)
- Create: `tests/playwright/accessibility.spec.ts`

- [ ] **Step 1: Add skip-to-content link to base template**

Read `templates/base.html.twig`. Add immediately after `<body>`:

```html
<a href="#main-content" class="skip-link">Skip to main content</a>
```

Add `id="main-content"` to the main content wrapper (likely the `<main>` tag or equivalent).

- [ ] **Step 2: Add skip-link CSS**

In `public/css/minoo.css`, add to the `@layer utilities` section:

```css
.skip-link {
  position: absolute;
  inset-inline-start: -9999px;
  background: var(--color-surface);
  color: var(--color-text);
  padding: var(--space-xs) var(--space-sm);
  z-index: 1000;
  text-decoration: none;
}

.skip-link:focus {
  inset-inline-start: var(--space-sm);
  inset-block-start: var(--space-sm);
}
```

- [ ] **Step 3: Verify all images have alt text**

Run:
```bash
grep -rn '<img' templates/ | grep -v 'alt='
```
Expected: No results. If any `<img>` tags lack `alt`, add appropriate alt text.

- [ ] **Step 4: Verify all form inputs have labels**

Run:
```bash
grep -rn '<input' templates/ | grep -v 'type="hidden"' | head -20
```
Cross-reference with `<label for="...">` to ensure all inputs have associated labels.

- [ ] **Step 5: Verify color contrast**

The existing oklch palette should meet 4.5:1. Verify key combinations:
- Text on background
- Secondary text on background
- Links on background
- Error text on background

Use the CSS custom property values to calculate contrast ratios. If any fail, adjust in `minoo.css`.

- [ ] **Step 6: Install axe-core Playwright integration**

Run:
```bash
npm install --save-dev @axe-core/playwright
```

- [ ] **Step 7: Create accessibility Playwright test**

Create `tests/playwright/accessibility.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const publicPages = [
  '/',
  '/teachings',
  '/events',
  '/groups',
  '/language',
  '/communities',
  '/data-sovereignty',
  '/login',
  '/register',
  '/forgot-password',
  '/elder-support',
];

for (const path of publicPages) {
  test(`accessibility: ${path} has no critical or serious violations`, async ({ page }) => {
    await page.goto(path);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const critical = results.violations.filter(v =>
      v.impact === 'critical' || v.impact === 'serious'
    );

    expect(critical).toEqual([]);
  });
}

test('skip-to-content link is present and focusable', async ({ page }) => {
  await page.goto('/');
  const skipLink = page.locator('.skip-link');
  await expect(skipLink).toBeAttached();
  await skipLink.focus();
  await expect(skipLink).toBeFocused();
});

test('all pages have lang attribute on html', async ({ page }) => {
  await page.goto('/');
  const lang = await page.locator('html').getAttribute('lang');
  expect(lang).toBe('en');
});
```

- [ ] **Step 8: Run accessibility tests locally**

Run:
```bash
npx playwright test tests/playwright/accessibility.spec.ts
```
Expected: All pass. If axe-core finds violations, fix them in templates/CSS before proceeding.

- [ ] **Step 9: Run full test suite (PHPUnit + Playwright)**

Run:
```bash
./vendor/bin/phpunit && npx playwright test
```
Expected: All pass.

- [ ] **Step 10: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css tests/playwright/accessibility.spec.ts package.json package-lock.json
git commit -m "feat(#199): Accessibility audit — WCAG 2.1 AA compliance

Add skip-to-content link, verify form labels, image alt text, and
color contrast. Add axe-core Playwright tests for all public pages
checking zero critical/serious WCAG 2a/2aa violations.

Closes #199

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

---

### Task 5: Playwright Tests — Auth Flows (#136)

**Files:**
- Modify: `tests/playwright/auth.spec.ts` (extend existing 67-line file)

**Context:** `auth.spec.ts` already exists with some tests. Read it first, then add missing tests from the issue's list of 10.

- [ ] **Step 1: Read existing auth tests**

Run:
```bash
cat tests/playwright/auth.spec.ts
```
Identify which of the 10 required tests already exist.

- [ ] **Step 2: Add missing auth tests**

Add any missing tests from this list:
1. Login form renders
2. Login validation (empty form)
3. Login failure (invalid credentials)
4. Login success (valid credentials → redirect)
5. Registration form renders
6. Registration validation (empty form)
7. Registration password too short
8. Registration duplicate email
9. Registration success
10. Logout

Tests requiring authenticated state need a seeded test user. Create a test helper or use `test.beforeAll` to seed a user via the CLI or direct SQLite insert:

```typescript
import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Auth flows', () => {
  // Seed test user before tests
  test.beforeAll(() => {
    execSync('php bin/seed-test-user', { cwd: process.cwd() });
  });

  // ... tests
});
```

If `bin/seed-test-user` doesn't exist, create it as a simple PHP script that inserts a test user with known credentials into the SQLite database.

- [ ] **Step 3: Run auth Playwright tests**

Run:
```bash
npx playwright test tests/playwright/auth.spec.ts
```
Expected: All 10 tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/playwright/auth.spec.ts bin/seed-test-user
git commit -m "test(#136): Playwright tests for auth flows

Add 10 end-to-end tests covering login form rendering, validation,
failure, success; registration form rendering, validation, password
rules, duplicate email, success; and logout flow.

Closes #136

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

---

### Task 6: Playwright Tests — Form Submissions (#137)

**Files:**
- Modify: `tests/playwright/elders.spec.ts` (extend existing 77-line file)
- Modify: `tests/playwright/volunteer.spec.ts` (extend existing 58-line file)

**Context:** Both spec files exist with some tests. Read them first, then add the missing submission and validation tests.

- [ ] **Step 1: Read existing elder and volunteer tests**

Run:
```bash
cat tests/playwright/elders.spec.ts
cat tests/playwright/volunteer.spec.ts
```
Identify which of the 13 required tests already exist.

- [ ] **Step 2: Add missing elder request submission tests**

Add to `elders.spec.ts`:
1. Submit valid request → confirmation page with UUID
2. Validation: missing name
3. Validation: missing phone
4. Validation: missing type
5. Representative toggle → fields appear
6. Representative validation
7. Representative consent required
8. Confirmation page details

- [ ] **Step 3: Add missing volunteer signup submission tests**

Add to `volunteer.spec.ts`:
9. Submit valid signup → confirmation
10. Validation: missing name
11. Validation: missing phone
12. Skills selection → confirmation shows skills
13. Confirmation page details

- [ ] **Step 4: Run form submission tests**

Run:
```bash
npx playwright test tests/playwright/elders.spec.ts tests/playwright/volunteer.spec.ts
```
Expected: All 13 new tests pass.

- [ ] **Step 5: Commit**

```bash
git add tests/playwright/elders.spec.ts tests/playwright/volunteer.spec.ts
git commit -m "test(#137): Playwright tests for elder request and volunteer signup

Add 13 end-to-end tests covering elder request submission, validation,
representative toggle, and confirmation; plus volunteer signup
submission, validation, skills selection, and confirmation.

Closes #137

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

---

### Task 7: Playwright Tests — Content Browsing (#138)

**Files:**
- Modify: `tests/playwright/communities.spec.ts` (extend existing 21-line file)
- Create: `tests/playwright/content-pages.spec.ts`
- Create: `tests/playwright/search.spec.ts`

**Context:** `communities.spec.ts` exists (21 lines). People page tests may need `people.spec.ts`. Read existing community tests first.

- [ ] **Step 1: Read existing content browsing tests**

Run:
```bash
cat tests/playwright/communities.spec.ts
```
Identify what's already covered.

- [ ] **Step 2: Add missing community tests**

From the issue:
1. Listing page loads
2. Type filter (if implemented)
3. Community detail
4. 404 for invalid slug

- [ ] **Step 3: Create content-pages.spec.ts**

Test all content listing + detail pages:

```typescript
import { test, expect } from '@playwright/test';

const contentPages = [
  { path: '/teachings', heading: /teachings/i },
  { path: '/events', heading: /events/i },
  { path: '/groups', heading: /groups/i },
  { path: '/language', heading: /language|dictionary/i },
];

for (const { path, heading } of contentPages) {
  test(`${path} listing page loads`, async ({ page }) => {
    await page.goto(path);
    await expect(page.locator('h1')).toContainText(heading);
  });
}

test('404 for invalid teaching slug', async ({ page }) => {
  const response = await page.goto('/teachings/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

test('404 for invalid event slug', async ({ page }) => {
  const response = await page.goto('/events/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

test('404 for invalid group slug', async ({ page }) => {
  const response = await page.goto('/groups/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

test('404 for invalid language slug', async ({ page }) => {
  const response = await page.goto('/language/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});
```

- [ ] **Step 4: Create search.spec.ts**

```typescript
import { test, expect } from '@playwright/test';

test('search page loads', async ({ page }) => {
  await page.goto('/search');
  await expect(page.locator('h1')).toContainText(/search/i);
});

test('search with empty query shows prompt', async ({ page }) => {
  await page.goto('/search');
  await expect(page.getByText(/enter.*search|search.*term/i)).toBeVisible();
});

test('search with query returns results or no-results message', async ({ page }) => {
  await page.goto('/search?q=community');
  const hasResults = await page.locator('[class*="result"], [class*="card"]').count();
  const hasNoResults = await page.getByText(/no results/i).count();
  expect(hasResults + hasNoResults).toBeGreaterThan(0);
});
```

- [ ] **Step 5: Run content browsing tests**

Run:
```bash
npx playwright test tests/playwright/communities.spec.ts tests/playwright/content-pages.spec.ts tests/playwright/search.spec.ts
```
Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add tests/playwright/communities.spec.ts tests/playwright/content-pages.spec.ts tests/playwright/search.spec.ts
git commit -m "test(#138): Playwright tests for content browsing pages

Add end-to-end tests for content listing pages (teachings, events,
groups, language), 404 handling for invalid slugs, community browsing,
and search page functionality.

Closes #138

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>"
```

---

## Chunk 3: Final Verification

### Task 8: Final Verification and Governance Update

- [ ] **Step 1: Run full PHPUnit suite**

Run:
```bash
./vendor/bin/phpunit
```
Expected: All tests pass (288+ tests).

- [ ] **Step 2: Run full Playwright suite**

Run:
```bash
npx playwright test
```
Expected: All tests pass.

- [ ] **Step 3: Run composer audit**

Run:
```bash
composer audit
```
Expected: No critical/high vulnerabilities.

- [ ] **Step 4: Verify all Sprint 3 issues are closed**

Run:
```bash
for issue in 136 137 138 195 198 199 204; do
  echo -n "#$issue: "
  gh issue view $issue --json state -q '.state'
done
```
Expected: All CLOSED.

- [ ] **Step 5: Update Epic #201 Sprint 3 checkboxes**

Run:
```bash
gh issue view 201 --json body -q '.body' > /tmp/epic-body.md
```
Edit to check off Sprint 3 items, then:
```bash
gh issue edit 201 --body "$(cat /tmp/epic-body-updated.md)"
```

- [ ] **Step 6: Comment on #202 with Sprint 3 governance update**

Run:
```bash
gh issue comment 202 --body "## Sprint 3 Governance Update — [date]

All Sprint 3 issues complete. Governance gate readiness:

- Gate 2 (Media Copyright): READY — copyright_status field on all media-bearing entities, non-approved filtered from public pages
- Gate 3 (Data Sovereignty): READY (since Sprint 2)
- Gate 4 (Community Governance): READY (since Sprint 2)
- Gate 5 (Security): READY — OWASP checklist complete, security headers, rate limiting, session hardening, dependency audit

All 5 gates ready for human signoff on staging."
```

- [ ] **Step 7: Push to remote**

Run:
```bash
git push origin main
```

---

## Exit Criteria

- [ ] All 7 Sprint 3 issues (#136, #137, #138, #195, #198, #199, #204) implemented and closed
- [ ] PHPUnit: all tests passing (288+ tests)
- [ ] Playwright: all tests passing (existing + new auth, form, content, accessibility)
- [ ] `composer audit`: zero critical/high vulnerabilities
- [ ] All 5 governance gates ready for human signoff
- [ ] Epic #201 Sprint 3 checkboxes checked
- [ ] Comment posted on #202 with final gate status
- [ ] Ready for `superpowers:requesting-code-review`
