# Social Feed Redesign Review (PRs #400-#405)

Reviewed: `git diff main...social-feed-smoke-test` -- 35 files, +3071 lines

## Critical (90-100)

### 1. No CSRF validation on API endpoints (Confidence: 98)
**Files:** `src/Controller/EngagementController.php` (all 9 methods), `src/Controller/FeedController.php` (generateCsrfToken)

FeedController generates a CSRF token via HMAC-SHA256 and passes it to the template as a `<meta>` tag. The JS reads it and sends it as `X-CSRF-Token` header. However, **EngagementController never validates the token**. There is no `validateCsrfToken()` method, no middleware, and no check anywhere in the request pipeline. The CSRF token is generated but never verified -- it is purely decorative.

**Fix:** Add a private `validateCsrf(HttpRequest $request)` method to EngagementController (or a middleware) that regenerates the expected token from the session cookie + APP_SECRET and compares it with the `X-CSRF-Token` header using `hash_equals()`. Return 403 on mismatch. Call it at the top of every mutating endpoint (react, comment, createPost, follow, and all delete methods).

### 2. No authentication enforcement on mutating API routes (Confidence: 96)
**Files:** `src/Provider/EngagementServiceProvider.php` (routes method), `src/Controller/EngagementController.php`

All 9 engagement routes use `->allowAll()`, meaning anonymous users can reach every endpoint. The access policy's `createAccess()` correctly requires `$account->isAuthenticated()`, but EngagementController **never calls the access system**. It directly calls `$storage->create()` and `$storage->save()` without checking `createAccess()` or `access()` on the entity type. An anonymous user (uid 0) can create reactions, comments, posts, and follows.

**Fix:** Either (a) add authentication-required middleware at the route level (replace `allowAll()` with an auth guard), or (b) check `$account->isAuthenticated()` at the top of each mutating controller method and return 401 if false. Option (a) is preferred.

### 3. No input validation on `target_type` -- stored XSS and data integrity risk (Confidence: 95)
**Files:** `src/Controller/EngagementController.php` (react, comment, follow methods)

`target_type` is accepted from user input with no whitelist validation. An attacker can store arbitrary strings (including script payloads) in the database. While Twig auto-escapes by default, the `renderComments` JS function builds HTML from API response data using template literals, and any future template that uses `|raw` on entity data would be vulnerable.

**Fix:** Add a whitelist: `$allowedTypes = ['event', 'teaching', 'group', 'business', 'post', 'community'];` and reject requests where `target_type` is not in the list.

### 4. No `emoji` input validation -- arbitrary string storage (Confidence: 91)
**File:** `src/Controller/EngagementController.php::react()`

The `emoji` field accepts any string with no length or content validation. Users can store arbitrary text (megabytes) as "emoji" values.

**Fix:** Validate emoji against an allowlist or at minimum enforce `mb_strlen($data['emoji']) <= 10`.

## Important (80-89)

### 5. CSRF token for anonymous users is unverifiable (Confidence: 88)
**File:** `src/Controller/FeedController.php::generateCsrfToken()`

When `PHPSESSID` is empty, `generateCsrfToken()` returns `bin2hex(random_bytes(32))` -- a one-time random value that can never be regenerated for comparison. Even once server-side validation is added, anonymous tokens will always fail verification.

**Fix:** Since anonymous users should not be able to use mutating endpoints anyway (see issue #2), this becomes moot once auth is enforced. But if anonymous read-API calls need CSRF, tie the token to a cookie the server sets.

### 6. Hardcoded CSRF fallback secret (Confidence: 87)
**File:** `src/Controller/FeedController.php` line with `'minoo-csrf-fallback-key'`

`getenv('APP_SECRET') ?: 'minoo-csrf-fallback-key'` means if `APP_SECRET` is not set, all CSRF tokens are derived from a publicly known secret. Any attacker who knows the session ID can forge valid tokens.

**Fix:** Throw an exception or log a warning if `APP_SECRET` is not configured. Never use a hardcoded fallback for cryptographic secrets.

### 7. Missing UNIQUE constraint on reactions table (Confidence: 85)
**File:** `migrations/20260321_120000_create_reactions_table.php`

The `follow` table correctly has `CREATE UNIQUE INDEX idx_follow_unique ON follow (user_id, target_type, target_id)`, but the `reaction` table has no equivalent. A user can spam unlimited duplicate reactions on the same target. The controller has no duplicate-check logic either.

**Fix:** Add `CREATE UNIQUE INDEX idx_reaction_unique ON reaction (user_id, emoji, target_type, target_id)` to the migration, and handle the constraint violation gracefully in the controller (upsert or return existing).

### 8. `EntityTypeManager` is not `final` but is mocked in tests (Confidence: 82)
**File:** `tests/Minoo/Unit/Controller/FeedControllerTest.php`

Per CLAUDE.md: "Don't use final on services that need mocking." This is correctly followed. However, the test creates the mock via `$this->createMock(EntityTypeManager::class)` which works only because `EntityTypeManager` is not final. This is fine, but worth noting that the test will break if the framework marks it final. Consider using an interface mock (`EntityTypeManagerInterface`) if one exists.

### 9. No test coverage for EngagementController (Confidence: 85)
**Files:** `tests/` directory

There are unit tests for the 4 new entity types, EngagementCounter, RelativeTime, and FeedController, but **zero tests for EngagementController** -- the most security-sensitive new class with 9 API endpoints handling user input.

**Fix:** Add unit tests covering: auth rejection for anonymous users, CSRF validation, input validation (bad target_type, oversized body, missing fields), owner-check on delete operations, and happy paths.

### 10. Comment body max length is 2000 in JS but 5000 in controller (Confidence: 80)
**Files:** `templates/feed.html.twig` (JS: `maxlength="2000"`), `src/Controller/EngagementController.php` (PHP: `mb_strlen($body) > 5000`)

The JS input has `maxlength="2000"` but the server allows up to 5000 characters. These should be consistent. Server-side is the authority, but the mismatch confuses the UX and means the HTML constraint is more restrictive than the API.

**Fix:** Align both to the same limit (recommend 2000 or 5000, pick one).

## Summary

The entity types, access policy, CSS architecture, migrations, feed assembly, and Twig templates are well-structured and follow project conventions (strict_types, final class, PHPUnit attributes, CSS @layer architecture). The most serious issues are all in the security layer: CSRF tokens are generated but never verified, API routes allow anonymous access to mutating endpoints, and user input (target_type, emoji) is stored without validation. These must be fixed before merging to main.
