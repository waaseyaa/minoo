# Location Cookie Resilience — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent server 500 errors when a user has a stale or corrupted `minoo_location` cookie by adding input validation to `LocationContext::fromArray()` and defensive guards in `LocationService::fromRequest()`.

**Architecture:** Validate at the boundary (cookie/session parsing) and fix the `hasLocation()` logic bug. Invalid data returns `LocationContext::none()` instead of silently casting garbage values that crash downstream.

**Tech Stack:** PHP 8.3, PHPUnit

**Issue:** [#209](https://github.com/waaseyaa/minoo/issues/209)
**Branch:** `fix/209-location-cookie-resilience` (from `release/v1`)

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `src/Domain/Geo/ValueObject/LocationContext.php` | Validate `fromArray()` input, fix `hasLocation()` |
| Modify | `src/Domain/Geo/Service/LocationService.php` | Defensive cookie parsing, clear bad cookies |
| Create | `tests/Minoo/Unit/Domain/Geo/LocationContextTest.php` | Unit tests for validation |
| Create | `tests/Minoo/Unit/Domain/Geo/LocationServiceTest.php` | Unit tests for cookie resilience |

---

## Task 1: Fix LocationContext validation and hasLocation()

**Files:**
- Modify: `src/Domain/Geo/ValueObject/LocationContext.php`

- [ ] **Step 1: Write the failing test for fromArray() with non-numeric communityId**

Create `tests/Minoo/Unit/Domain/Geo/LocationContextTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Domain\Geo;

use Minoo\Domain\Geo\ValueObject\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocationContext::class)]
final class LocationContextTest extends TestCase
{
    #[Test]
    public function fromArrayWithValidDataCreatesContext(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(42, $ctx->communityId);
        self::assertSame('Thunder Bay', $ctx->communityName);
        self::assertSame(48.38, $ctx->latitude);
        self::assertSame(-89.25, $ctx->longitude);
    }

    #[Test]
    public function fromArrayWithNonNumericCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 'test-id',
            'communityName' => 'Fake',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithZeroCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 0,
            'communityName' => 'Invalid',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithNonNumericLatitudeReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 'invalid',
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithOutOfRangeLatitudeReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 91.0,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithOutOfRangeLongitudeReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => 181.0,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithMissingCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithStringNumericCommunityIdCastsCorrectly(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => '42',
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'cookie',
        ]);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(42, $ctx->communityId);
    }

    #[Test]
    public function noneHasNoLocation(): void
    {
        $ctx = LocationContext::none();

        self::assertFalse($ctx->hasLocation());
        self::assertNull($ctx->communityId);
    }

    #[Test]
    public function toArrayRoundTrips(): void
    {
        $original = new LocationContext(
            communityId: 42,
            communityName: 'Thunder Bay',
            latitude: 48.38,
            longitude: -89.25,
            source: 'manual',
        );

        $restored = LocationContext::fromArray($original->toArray());

        self::assertSame($original->communityId, $restored->communityId);
        self::assertSame($original->communityName, $restored->communityName);
        self::assertSame($original->latitude, $restored->latitude);
        self::assertSame($original->longitude, $restored->longitude);
        self::assertSame($original->source, $restored->source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Domain/Geo/LocationContextTest.php
```

Expected: Several tests FAIL (non-numeric ID returns a context with `communityId=0` instead of none).

- [ ] **Step 3: Fix fromArray() with input validation**

In `src/Domain/Geo/ValueObject/LocationContext.php`, replace the `fromArray()` method:

```php
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['communityId'])) {
            return self::none();
        }

        // Validate communityId is numeric and positive.
        if (!is_numeric($data['communityId']) || (int) $data['communityId'] <= 0) {
            return self::none();
        }

        // Validate latitude if present.
        if (isset($data['latitude'])) {
            if (!is_numeric($data['latitude'])) {
                return self::none();
            }
            $lat = (float) $data['latitude'];
            if ($lat < -90.0 || $lat > 90.0) {
                return self::none();
            }
        }

        // Validate longitude if present.
        if (isset($data['longitude'])) {
            if (!is_numeric($data['longitude'])) {
                return self::none();
            }
            $lon = (float) $data['longitude'];
            if ($lon < -180.0 || $lon > 180.0) {
                return self::none();
            }
        }

        return new self(
            communityId: (int) $data['communityId'],
            communityName: isset($data['communityName']) && is_string($data['communityName']) ? $data['communityName'] : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            source: isset($data['source']) && is_string($data['source']) ? $data['source'] : 'none',
        );
    }
```

- [ ] **Step 4: Fix hasLocation() to check communityId > 0**

In the same file, change `hasLocation()`:

```php
    public function hasLocation(): bool
    {
        return $this->communityId !== null && $this->communityId > 0;
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Domain/Geo/LocationContextTest.php
```

Expected: All 10 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Domain/Geo/ValueObject/LocationContext.php tests/Minoo/Unit/Domain/Geo/LocationContextTest.php
git commit -m "fix(#209): Validate LocationContext::fromArray() input and fix hasLocation()"
```

---

## Task 2: Add defensive cookie parsing in LocationService

**Files:**
- Modify: `src/Domain/Geo/Service/LocationService.php`

The `fromRequest()` method should catch any exceptions from cookie parsing and return `LocationContext::none()` if the cookie is bad. It should also clear the bad cookie so the user isn't stuck.

- [ ] **Step 1: Write the test**

Create `tests/Minoo/Unit/Domain/Geo/LocationServiceCookieTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Domain\Geo;

use Minoo\Domain\Geo\ValueObject\LocationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LocationContext::fromArray() resilience when called
 * with data that simulates corrupted cookie/session input.
 *
 * LocationService::fromRequest() depends on Symfony Request and EntityTypeManager,
 * so we test the validation boundary at LocationContext::fromArray() directly.
 */
final class LocationServiceCookieTest extends TestCase
{
    #[Test]
    public function corruptedJsonParsesToInvalidContext(): void
    {
        // Simulates: json_decode('{"communityId":"not_a_number","latitude":"bad"}', true)
        $data = [
            'communityId' => 'not_a_number',
            'latitude' => 'bad',
            'longitude' => 'worse',
            'source' => 'cookie',
        ];

        $ctx = LocationContext::fromArray($data);

        self::assertFalse($ctx->hasLocation());
        self::assertNull($ctx->communityId);
    }

    #[Test]
    public function emptyArrayReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function validCookieDataCreatesContext(): void
    {
        // Simulates: json_decode('{"communityId":42,"communityName":"Thunder Bay",...}', true)
        $data = [
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ];

        $ctx = LocationContext::fromArray($data);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(42, $ctx->communityId);
    }

    #[Test]
    public function negativeCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => -5,
            'communityName' => 'Negative',
            'latitude' => 48.38,
            'longitude' => -89.25,
        ]);

        self::assertFalse($ctx->hasLocation());
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Domain/Geo/LocationServiceCookieTest.php
```

Expected: All 4 pass (the validation is already fixed in Task 1).

- [ ] **Step 3: Wrap cookie parsing in LocationService with try/catch**

In `src/Domain/Geo/Service/LocationService.php`, update the cookie section in `fromRequest()`:

Change from:

```php
        // 2. Check cookie.
        $cookieName = $this->config['cookie_name'] ?? 'minoo_location';
        $cookieValue = $request->cookies->get($cookieName);
        if ($cookieValue !== null) {
            $data = json_decode($cookieValue, true);
            if (is_array($data) && isset($data['communityId'])) {
                return LocationContext::fromArray($data);
            }
        }
```

To:

```php
        // 2. Check cookie.
        $cookieName = $this->config['cookie_name'] ?? 'minoo_location';
        $cookieValue = $request->cookies->get($cookieName);
        if ($cookieValue !== null) {
            try {
                $data = json_decode($cookieValue, true);
                if (is_array($data) && isset($data['communityId'])) {
                    $ctx = LocationContext::fromArray($data);
                    if ($ctx->hasLocation()) {
                        return $ctx;
                    }
                }
            } catch (\Throwable) {
                // Corrupted cookie — fall through to IP resolution.
            }
            // Invalid or corrupted cookie — clear it so the user isn't stuck.
            $this->clearCookie($cookieName);
        }
```

- [ ] **Step 4: Also wrap session parsing with the same guard**

Change the session section from:

```php
        // 1. Check session.
        $session = $request->attributes->get('_session') ?? ($_SESSION ?? []);
        if (isset($session['minoo_location']) && is_array($session['minoo_location'])) {
            return LocationContext::fromArray($session['minoo_location']);
        }
```

To:

```php
        // 1. Check session.
        $session = $request->attributes->get('_session') ?? ($_SESSION ?? []);
        if (isset($session['minoo_location']) && is_array($session['minoo_location'])) {
            $ctx = LocationContext::fromArray($session['minoo_location']);
            if ($ctx->hasLocation()) {
                return $ctx;
            }
            // Invalid session data — clear it.
            unset($_SESSION['minoo_location']);
        }
```

- [ ] **Step 5: Add clearCookie() method**

Add a private method to `LocationService`:

```php
    private function clearCookie(string $cookieName): void
    {
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
```

- [ ] **Step 6: Run full PHPUnit suite**

```bash
./vendor/bin/phpunit
```

Expected: 312+ tests pass (302 existing + 10 LocationContext + 4 cookie resilience = ~316).

- [ ] **Step 7: Commit**

```bash
git add src/Domain/Geo/Service/LocationService.php tests/Minoo/Unit/Domain/Geo/LocationServiceCookieTest.php
git commit -m "fix(#209): Defensive cookie/session parsing with bad cookie cleanup"
```

---

## Task 3: Playwright verification and PR

- [ ] **Step 1: Run full Playwright suite**

```bash
npx playwright test --reporter=list
```

Expected: All tests pass. The location bar tests from #133 should still work since `LocationContext::fromArray()` with valid data still works.

- [ ] **Step 2: Create PR**

```bash
gh pr create --base release/v1 --title "fix(#209): Server 500 on stale or corrupted location cookie" --body "$(cat <<'EOF'
## Summary
- `LocationContext::fromArray()` now validates: numeric communityId > 0, numeric lat/lon in range
- `LocationContext::hasLocation()` fixed: checks `communityId > 0` (was `!== null`)
- `LocationService::fromRequest()` wraps cookie/session parsing with guards
- Bad cookies are automatically cleared so users aren't stuck in a 500 loop
- Invalid session data is cleaned up on the same principle

## Root Cause
A stale cookie with a non-numeric `communityId` (e.g., `"test-id"`) was silently cast to `0`, passed `hasLocation()` (which only checked `!== null`), and caused `GeoDistance::haversine(0.0, 0.0, ...)` to crash on `sqrt(negative)`.

Closes #209

## Test Plan
- [ ] PHPUnit: 316+ tests pass (14 new Geo tests)
- [ ] Playwright: all existing tests pass (no regressions)
- [ ] Verified: homepage loads with no cookie, with valid cookie, with corrupted cookie

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
