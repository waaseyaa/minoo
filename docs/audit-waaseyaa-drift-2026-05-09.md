# Minoo ↔ Waaseyaa Framework Drift Audit

**Framework version:** Waaseyaa alpha.173 (synced to Minoo as of #750, 2026-05-06)  
**Audit scope:** Framework drift workarounds, API gaps, testing patterns  
**Report date:** 2026-05-09

---

## 1. Reflection/Private API Access (Severity: Should-fix)

### 1.1 Integration Test Reflection Usage
**Files affected:**
- `tests/App/Integration/*.php` (3+ files use `ReflectionMethod`)
- `/home/jones/dev/minoo/tests/App/Integration/SmokeTest.php` (documented)
- `/home/jones/dev/minoo/tests/App/Integration/*.php` (pattern repeats)

**What Minoo does:**
```php
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);
```

**Why it was a workaround:**
The `boot()` method on `AbstractKernel` is `protected`, not public. Direct kernel boot is not exposed.

**What framework now offers:**
**Still required as of alpha.173.** The framework's CHANGELOG does not document a public boot API. However, alpha.173's #1390 fix suggests the framework may expose boot elsewhere for script usage—needs verification in `/home/jones/dev/waaseyaa/packages/foundation/src/Kernel/`.

**Severity:** Should-fix (test infrastructure detail; not blocking feature work)

---

### 1.2 Script Kernel Boot via Reflection
**Files affected:**
- `scripts/populate_lnhl.php` (4 files; identical pattern)
- `scripts/populate_nginaajiiw.php`
- `scripts/populate_relationships.php`
- `scripts/create_shkoda_featured.php`
- ~10 other scripts

**What Minoo does:**
```php
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\Kernel\AbstractKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);
$etm = $kernel->getEntityTypeManager();
```

**Why it was a workaround:**
No public console entry point equivalent. The gotcha states "ConsoleKernel is broken — use HttpKernel via reflection" (`scripts/update_intro_post.php` comment).

**What framework now offers:**
- **Alpha.173 does NOT fix ConsoleKernel boot.** The CHANGELOG lists no ConsoleKernel repair.
- The gotcha persists: ConsoleKernel was broken on production due to missing `SqliteEmbeddingStorage` dependency. Alpha.173's #1388 and #1390 fixes address other domains (groups/taxonomy boot, controller implicit arrays), not ConsoleKernel.
- **Likelihood:** ConsoleKernel still requires HttpKernel fallback + reflection.

**Severity:** Critical (blocks CLI tool reliability; gotcha remains unfixed)

---

## 2. Manual Kernel Boot / Scripts Workaround (Severity: Critical)

### 2.1 HttpKernel vs ConsoleKernel Branch
**File:** `scripts/update_intro_post.php` line 13  
**Comment:** `// Boot the kernel (ConsoleKernel is broken — use HttpKernel via reflection)`

**Pattern across 8+ scripts:**
All use `HttpKernel` manually; none use the framework's console entry point.

**Framework status (alpha.173):**
- No fix for ConsoleKernel published.
- Reflection-based boot remains the recommended pattern in Minoo codebase.
- The gotcha in CLAUDE.md stands.

**Severity:** Critical (ConsoleKernel not repaired; reflection remains necessary)

---

## 3. Workarounds Documented as Gotchas (Severity: varies)

### 3.1 `InvalidArgumentException` try/catch in Controllers
**Files affected:**
- `src/Controller/EngagementController.php` (5 occurrences)
- `src/Controller/BlockController.php` (1 occurrence)
- `src/Controller/MessagingController.php` (1 occurrence)

**What Minoo does:**
```php
try {
    $storage->save($entity);
} catch (\InvalidArgumentException) {
    return $this->json(['error' => 'Invalid entity data'], 422);
}
```

**Why it was a workaround:**
Alpha.171 made `FieldDefinitionRegistry::registerCoreFields()` strict: every `FieldDefinition` must declare `targetEntityTypeId === $entityTypeId`. Entities with invalid field bindings throw `InvalidArgumentException` on save.

**What framework now offers:**
Alpha.172's #1388 fixed the framework providers' field declarations (groups, taxonomy); **the API is intentional, not a bug.** Controllers SHOULD validate field bindings.

**Assessment:**
The try/catch is **correct defensive programming**, not a workaround. The framework documents the invariant in alpha.172 spec update: "every FieldDefinition passed to registerCoreFields() or registerBundleFields() must declare targetEntityTypeId === $entityTypeId" — this is a validation gate, not a limitation.

**Severity:** Nice-to-have (correct pattern; keep as-is)

---

### 3.2 `EntityTypeManager` Concrete Class vs Interface
**Status:** Alpha.173 unchanged.  
**Gotcha citation:** "DI resolves EntityTypeManager (concrete), not the interface"

**What Minoo does:**
Everywhere in `src/`, inject `EntityTypeManager::class`, not `EntityTypeManagerInterface::class`.

**What framework offers:**
Alpha.173 makes no changes to the DI registration. The concrete binding persists by design.

**Severity:** Nice-to-have (working as intended; not a limitation)

---

### 3.3 `trans()` Not Callable from PHP Controllers
**Status:** Alpha.173 unchanged.

**Files:**
- `src/Support/CrisisOgImageService.php` uses `$this->translator->trans()` (correct; DI injected)
- No bare `trans()` calls found in controllers

**Assessment:**
Minoo correctly uses injected `TranslatorInterface`. No workaround present.

**Severity:** Non-issue

---

### 3.4 `_data` JSON Blob Manual Handling
**Status:** Alpha.173 unchanged.

**Gotcha:** Entity fields live in `_data` JSON; raw SQL queries fail.

**What Minoo does:**
Uses `getQuery()->condition()` for engagement entities (reaction, comment, follow). Confirmed via entity queries, not raw SQL.

**Framework fix (alpha.167):**
`SqlStorageDriver::write()` and `read()` now split `_data` routing automatically. Per CHANGELOG: "entity repository paths now re-merge _data keys."

**Assessment:**
Minoo's pattern already aligns with alpha.167+'s fix. No workaround needed going forward if migrating entities to repository pattern.

**Severity:** Nice-to-have (modern storage handles it)

---

### 3.5 Controller Implicit-Array Dispatcher
**Status:** FIXED in alpha.173 via #1390.

**What was broken:**
Alpha.171/172 rejected unannotated `array $params` / `array $query` parameters.

**What Minoo does:**
184 controller methods across 37 files use the canonical signature:
```php
public function show(array $params, array $query, AccountInterface $account, Request $request): Response
```

**What alpha.173 offers:**
A compatibility shim that defaults unannotated `array $params` → `#[MapRoute]` and unannotated `array $query` → `#[MapQuery]`. Each hit emits a `dispatcher.deprecation` notice with payload keys `controller_class`, `method`, `parameter_name`, `recommended_attribute`.

**Minoo's migration path:**
A Spec Kitty mission (`migrate-controllers-explicit-route-attributes-01KQYNX7`) is in progress (see WP01, WP02 in search results). Controllers are being migrated cluster-by-cluster to explicit `#[MapRoute]` / `#[MapQuery]` attributes. WP01 (Auth + Account) is already done per git log.

**Migration tool:**
Per the mission, a custom `contracts/migrate-cli.md` tool was created locally to handle token-aware parameter annotation.

**Severity:** Should-fix (migration in progress; alpha.173 provides compatibility window)

---

### 3.6 `HasCommandsInterface` Marker (alpha.173 new)
**Status:** IMPLEMENTED in alpha.173.

**What was new:**
Alpha.173 introduces `HasCommandsInterface` marker for command-providing providers.

**What Minoo does:**
`src/Provider/AppCommandServiceProvider.php` implements `HasCommandsInterface` (confirmed in grep).

**Adoption status:**
✓ Already applied. Command providers are marked. Other providers not checked; need sweep for completeness.

**Severity:** Nice-to-have (already adopted where needed)

---

### 3.7 `HttpKernel` Lacks `resolve()` Method
**Status:** Alpha.173 unchanged.

**Gotcha:** Use `$kernel->getEntityTypeManager()` not `$kernel->resolve()`.

**What Minoo does:**
Scripts correctly use `$kernel->getEntityTypeManager()`. No misuse found.

**Severity:** Non-issue (correct pattern already in place)

---

### 3.8 Schema Drift Detection
**Status:** Alpha.173 ship with evolved schema tooling.

**What Minoo does:**
Has `migrations/` directory and runs `bin/waaseyaa schema:check`.

**What alpha.165+ offers:**
- `schema:check` validates field/table alignment.
- `make:migration add_<column>` generates migration files.
- Alpha.173 unchanged; alpha.165+ `SchemaDiff` algebra + validation gates fully functional.

**Assessment:**
Minoo's approach aligns with framework. No workaround detected.

**Severity:** Non-issue

---

## 4. Custom Support Classes Duplicating Framework (Severity: varies)

### 4.1 ElderIdentity, Flash, FixtureResolver, SlugGenerator
**Status:** Review required.

**Files in `src/Support/`:**
- `ElderIdentity.php` — domain-specific (elder knowledge tracking)
- `FixtureResolver.php` — test data (app-specific)
- `FixtureLoader.php` — test data (app-specific)
- `LayoutTwigContext.php` — UI context wrapping (app-specific)
- `JsonResponseTrait.php` — JSON response helper
- `SlugGenerator.php` — URL-safe string generation
- `GeoDistance.php` — geo math (app domain)
- Others: GameEngine (Shkoda, Crossword, etc.), Crisis/Newsletter/Messaging support classes

**Assessment:**
Most are domain-specific (games, crisis response, newsletter, geo domain). **Not duplicating framework.**

**Flag:** `JsonResponseTrait.php` — alpha.172 redesigned `Waaseyaa\Api\JsonResponseTrait` to single `jsonApiResponse()` method. **Minoo's custom trait may conflict.**

**Severity:** Should-fix (verify no collisions with `Waaseyaa\Api\JsonResponseTrait`)

---

### 4.2 Custom Routing in `src/Provider/Routing/`
**Files:**
- `src/Provider/MinooRoutingStackProvider.php` — manual stack composition
- Multiple `*RouteProvider.php` files

**Assessment:**
Routing providers are manually wired (AdminRouteProvider, AuthApiRouteProvider, etc.). This is **standard Waaseyaa pattern**, not a workaround.

**Severity:** Non-issue

---

## 5. Vendor Patches / Direct Vendor Edits (Severity: Unknown)

**Finding:** No evidence of vendor edits or `git log` comments mentioning vendor/ hacks.

**Recommendation:** Run `git log --all -- vendor/` to confirm (not available in read-only audit).

**Severity:** Non-issue (none found in static analysis)

---

## 6. Half-Supported Features / Framework Limits (Severity: varies)

### 6.1 Newsletter Provider Split
**Files:**
- `src/Provider/EntityNewsletterProvider.php`
- `src/Provider/NewsletterEntityDefinitionsProvider.php`
- `src/Provider/NewsletterApiRouteProvider.php`

**Pattern:**
Manual entity/routing stack composition via `MinooEntityStackProvider`.

**Assessment:**
This is **intentional multi-provider architecture**, not a workaround. Standard Waaseyaa pattern for domain separation (newsletter is a distinct domain alongside feed, social, etc.).

**Severity:** Non-issue

---

### 6.2 CrisisIncident Crisis Response System
**Files:**
- `src/Support/CrisisOgImageService.php`
- `src/Support/CrisisOgAssetsCommand.php`
- `src/Support/CrisisOgAutomationPolicy.php`
- `src/Support/CrisisResolveContext.php`

**Assessment:**
Domain-specific crisis response system. No framework limitations detected.

**Severity:** Non-issue

---

## 7. Deprecated Framework Patterns (Severity: varies)

### 7.1 Alpha.172 Deprecation: `HasCommunityInterface` Marker
**Status:** Observed in logs during boot.

**Log output:**
```
[warning] [default] [deprecated] HasCommunityInterface on entity-type "post" 
  — declare tenancy: ['scope' => 'community'] on the EntityType registration. 
  Marker support will be removed in the next minor release.
```

**Affected entities:**
- Post, Leader, Event, Teaching, (possibly others)

**What Minoo does:**
Entities implement `HasCommunityInterface` marker.

**What framework requests:**
Migrate to explicit `tenancy: ['scope' => 'community']` registration.

**Timeline:** Will be removed in the next minor release (post-alpha.173).

**Migration status:** Not yet done. This is a **blocking technical debt** before upgrading past alpha.173.

**Severity:** Critical (breaking change coming; must migrate before next release)

---

### 7.2 Dispatcher Deprecation Notices: `dispatcher.deprecation`
**Status:** Active tracking in WP02/WP05 smoke tests.

**What's happening:**
Controllers with unannotated `array $params` / `array $query` emit `dispatcher.deprecation` notices per alpha.173 #1390 shim.

**Migration in progress:**
Spec Kitty mission `migrate-controllers-explicit-route-attributes-01KQYNX7` rolling out WP01–WP05 to migrate 35+ controllers to explicit `#[MapRoute]` / `#[MapQuery]` attributes.

**Status:**
- WP01 (Auth + Account): DONE ✓
- WP02 (Games): In progress / planned
- WP03–WP05: Planned

**Severity:** Should-fix (migration underway; alpha.173 provides deprecation window through alpha.174+)

---

## 8. Controller Patterns (Severity: varies)

### 8.1 Request Parsing Patterns
**Finding:** Controllers use bare `array $params`, `array $query` (legacy pattern). Modern pattern uses `#[MapRoute]`, `#[MapQuery]`.

**Status:** Actively being migrated via Spec Kitty mission.

**Examples:**
- `ShkodaController::page(array $params, array $query, ...)` — WP02
- `EngagementController::create(array $params, array $query, ...)` — needs migration

**Severity:** Should-fix (migration in progress)

---

### 8.2 JSON Response Building
**Pattern:** `$this->json(['key' => 'value'])` across engagement/messaging/game APIs.

**Custom trait:** `JsonResponseTrait.php`

**Framework equivalent:** Alpha.172 `Waaseyaa\Api\JsonResponseTrait::jsonApiResponse()` (single method; replaces `json()` / `jsonBody()`).

**Compatibility risk:** Minoo's `json()` method may collide with or be replaced by the framework's trait if both are used.

**Severity:** Should-fix (audit `JsonResponseTrait` for conflicts)

---

### 8.3 Auth/Access Checks
**Pattern:** Controllers inject `GateInterface` and check `$gate->allows()` inline.

**Assessment:** Standard Waaseyaa pattern. No workaround.

**Severity:** Non-issue

---

## 9. Test Infrastructure (Severity: varies)

### 9.1 Manifest Cache Cleanup
**Pattern:** All integration tests delete `storage/framework/packages.php` after test.

**Code:**
```php
$cachePath = self::$projectRoot . '/storage/framework/packages.php';
if (is_file($cachePath)) {
    unlink($cachePath);
}
```

**Assessment:** Necessary gotcha (documented). No framework fix available.

**Severity:** Non-issue (correct pattern)

---

### 9.2 In-Memory SQLite via `putenv()`
**Pattern:** Tests use `putenv('WAASEYAA_DB=:memory:')`.

**Assessment:** Standard Waaseyaa testing pattern. No workaround.

**Severity:** Non-issue

---

## 10. Missing/Uncertain Items (Needs Verification)

### 10.1 Public Boot API
No public `HttpKernel::boot()` or equivalent found in alpha.173 CHANGELOG.
- **Action:** Check `/home/jones/dev/waaseyaa/packages/foundation/src/Kernel/` for public boot entry point.

### 10.2 ConsoleKernel Repair Status
Alpha.173 CHANGELOG lists no ConsoleKernel fix.
- **Action:** Verify whether alpha.174+ roadmap includes ConsoleKernel repair.

### 10.3 `JsonResponseTrait` Conflict
Minoo custom `JsonResponseTrait` vs Waaseyaa alpha.172 redesigned trait.
- **Action:** Compare signatures; test for collision when both are in scope.

### 10.4 Complete `HasCommandsInterface` Adoption
Only `AppCommandServiceProvider` checked.
- **Action:** Sweep all provider classes for command-providing providers that lack the marker.

### 10.5 `HasCommunityInterface` Migration Timeline
Deprecation warning says "next minor release."
- **Action:** Determine if alpha.174 or later removes the marker; plan migration sprint.

---

## Summary Table

| Category | Finding | Severity | Status |
|----------|---------|----------|--------|
| Reflection/Tests | `ReflectionMethod` boot in tests | Should-fix | Still required; no public API |
| Scripts | HttpKernel reflection + ConsoleKernel broken comment | Critical | Unfixed in alpha.173 |
| InvalidArgumentException try/catch | Controllers wrap entity saves | Nice-to-have | Correct pattern; intentional API |
| Implicit-array dispatcher | 184 methods, undecorated `array` params | Should-fix | Migration in progress (WP01–WP05) |
| HasCommandsInterface | Marker adoption | Nice-to-have | Already applied to AppCommandServiceProvider |
| HasCommunityInterface | Marker deprecation | **Critical** | Deprecation warning active; no timeline |
| ConsoleKernel | Still broken; reflection workaround | Critical | Unfixed in alpha.173 |
| Custom Support classes | Domain-specific (games, geo, crisis, etc.) | Nice-to-have | Not duplicating framework |
| JsonResponseTrait collision | Custom vs framework alpha.172 redesign | Should-fix | Needs signature audit |
| Schema handling | Drift detection via schema:check | Non-issue | Modern storage compatible |

---

## Recommendations (Scoping, not fixes)

1. **Before next Waaseyaa release:** Complete `HasCommunityInterface` → `tenancy: ['scope' => 'community']` migration (blocking).
2. **In progress:** Complete Spec Kitty controller attribute migration (WP01–WP05).
3. **Verify:** Public boot API in alpha.174+; if available, refactor scripts away from reflection.
4. **Audit:** `JsonResponseTrait` signatures for compatibility.
5. **Track:** ConsoleKernel status in waaseyaa; may require upgrading HttpKernel patterns in scripts indefinitely.

