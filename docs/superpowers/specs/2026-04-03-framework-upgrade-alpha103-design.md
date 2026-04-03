# Framework Upgrade: alpha.90 to alpha.103

**Issue:** waaseyaa/minoo#619
**Date:** 2026-04-03

## Problem

Minoo's waaseyaa packages are pinned at alpha.90. The framework is at alpha.103 (13 releases behind). The key breaking change: `Waaseyaa\User\PasswordResetTokenRepository` was removed — auth was extracted into the new `waaseyaa/auth` package.

## Scope

### What changes

1. **Add `waaseyaa/auth` dependency** — provides `AuthTokenRepositoryInterface`, `AuthConfig`, `RateLimiter`
2. **Migrate token handling** — replace `PasswordResetTokenRepository` (removed) and `EmailVerificationService` (Minoo-owned) with framework's `AuthTokenRepositoryInterface`
3. **Migrate rate limiting** — replace `RateLimitMiddleware` with framework's `RateLimiter`
4. **Add auth config** — `config/waaseyaa.php` gets `auth` block for registration mode, token TTLs, mail policy
5. **Update AuthController** — new constructor deps, updated method calls
6. **Update AuthServiceProvider** — register via framework bindings, remove custom PDO wiring
7. **Update tests** — mock `AuthTokenRepositoryInterface` instead of concrete classes
8. **Delete dead code** — `EmailVerificationService`, `RateLimitMiddleware`

### What stays

- Minoo's SSR auth controllers (login, register, forgot-password, reset-password, verify-email) — framework's are API-only for Admin SPA
- Minoo's auth routes (HTML form POST endpoints)
- All other Minoo code — no other breaking changes detected

## API Migration

### Token creation

**Before (two separate classes):**
```php
// PasswordResetTokenRepository — raw PDO, plain-text token
$token = $this->passwordResetTokenRepository->createToken($userId);

// EmailVerificationService — raw PDO, plain-text token
$token = $this->emailVerificationService->createToken($userId);
```

**After (unified interface):**
```php
// AuthTokenRepositoryInterface — DatabaseInterface, HMAC-hashed
$token = $this->tokenRepo->createToken($userId, 'password_reset', 3600);
$token = $this->tokenRepo->createToken($userId, 'email_verification', 86400);
```

### Token validation

**Before:**
```php
$userId = $this->passwordResetTokenRepository->validateToken($token);
// Returns int|string|null
```

**After:**
```php
$result = $this->tokenRepo->validateToken($token, 'password_reset');
// Returns array{id: int, user_id: int|string|null, meta: array|null}|null
$userId = $result['user_id'] ?? null;
```

### Token consumption

**Before:**
```php
$this->passwordResetTokenRepository->consumeToken($token); // by token string
```

**After:**
```php
$this->tokenRepo->consumeToken($result['id']); // by token ID (int)
```

This means `validateToken` result must be kept to get the `id` for consumption.

### Rate limiting

**Before (hand-rolled):**
```php
$limiter = new RateLimitMiddleware($dbPath);
if (!$limiter->check($ip, '/login', 5, 300)) { ... }
$limiter->record($ip, '/login');
```

**After (framework):**
The framework's `RateLimiter` is injected via DI. Exact API TBD — check implementation.

## Config additions

```php
// config/waaseyaa.php
'auth' => [
    'registration' => 'open',           // open, admin, invite
    'require_verified_email' => false,
    'mail_missing_policy' => 'dev_log', // dev_log, fail, skip
    'token_ttls' => [
        'password_reset' => 3600,
        'email_verification' => 86400,
    ],
],
```

## Files to modify

| File | Action |
|------|--------|
| `composer.json` | Add `waaseyaa/auth` |
| `config/waaseyaa.php` | Add `auth` config block |
| `src/Controller/AuthController.php` | Replace token deps + rate limiter |
| `src/Provider/AuthServiceProvider.php` | Remove PDO wiring, inject framework bindings |
| `tests/Minoo/Unit/Controller/AuthControllerTest.php` | Mock `AuthTokenRepositoryInterface` |
| `src/Support/EmailVerificationService.php` | Delete |
| `src/Middleware/RateLimitMiddleware.php` | Delete (if exists as standalone) |

## Risk

- **Low:** Token table schema changes (old `password_reset_tokens` / `email_verification_tokens` tables become `auth_tokens`). Production migration needed.
- **Low:** HMAC hashing means existing tokens in production become invalid after deploy. Acceptable — tokens are short-lived.
- **None:** No other API breakage detected in alpha.90 → 103 range.
