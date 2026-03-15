# i18n Subsystem Audit & Refactor — Design Spec

**Date:** 2026-03-15
**Status:** Draft
**Scope:** waaseyaa/packages/ssr, waaseyaa/packages/i18n, minoo/src

---

## 1. Subsystem Map

### Current Architecture (3 language resolution paths)

```
Path 1 — SsrPageHandler [ACTIVE, primary]
  handleRenderPage(path, account, request)
    ├─ EARLY RETURN: if path == "/" → renderPath("/") WITHOUT negotiation  ← BUG
    ├─ resolveRenderLanguageAndAliasPath(path, request)
    │   ├─ resolveLanguageManager() → app-level via serviceResolver, fallback to config
    │   ├─ LanguageNegotiator.negotiate() → URL prefix → Accept-Language → default
    │   ├─ manager.setCurrentLanguage(negotiatedLanguage)
    │   ├─ detectLanguagePrefixFromPath() → finds prefix segment
    │   └─ stripLanguagePrefix() → removes prefix from path
    └─ rendering pipeline (alias resolution, entity lookup, Twig render)

Path 2 — SsrPageHandler.buildLanguageManager() [FALLBACK]
  └─ 70-line config parser creates a NEW LanguageManager from config['i18n']['languages']
     Used only when no app-level LanguageManagerInterface registered via serviceResolver.

Path 3 — LanguageMiddleware [DEAD CODE — minoo/src/Middleware/, never registered in any provider]
  ├─ UrlPrefixNegotiator.negotiate()
  ├─ manager.setCurrentLanguage()
  └─ Strips prefix, reinitializes Request
```

### Package Ownership

| Package | Responsibility | Key Classes |
|---------|---------------|-------------|
| `i18n` | Language model, translation, Twig extension | `LanguageManager`, `Translator`, `TranslationTwigExtension` |
| `routing` | Negotiation strategies | `UrlPrefixNegotiator`, `AcceptHeaderNegotiator`, `LanguageNegotiator` |
| `ssr` | Orchestration: negotiate → activate → strip → render | `SsrPageHandler` |
| `foundation` | Kernel wiring, serviceResolver injection | `HttpKernel` |
| Minoo app | App languages, translator config, Twig extension boot | `I18nServiceProvider` |

---

## 2. Invariants

1. **Single instance** — Exactly one `LanguageManager` per request. The app-level instance (registered via `I18nServiceProvider`) is authoritative. The config-built fallback exists only for framework-only deployments without an app provider.
2. **Negotiate-then-activate** — Language is negotiated (URL prefix → Accept-Language → default), then `setCurrentLanguage()` is called on the shared manager, before any rendering occurs.
3. **Strip-before-route** — The language prefix is removed from the path before alias resolution or template matching. `/oj/events/123` → alias path `/events/123`.
4. **Twig coherence** — `trans()`, `current_language()`, `lang_url()`, `lang_switch_url()` all read from the same `LanguageManager` instance that was activated during negotiation.
5. **Homepage language** — A request to `/<lang>/` (e.g., `/oj/`) must negotiate language before rendering the homepage. The early return for `/` must NOT bypass negotiation.

---

## 3. Problems & Failure Modes

| # | Problem | Severity | Failure Mode |
|---|---------|----------|-------------|
| 1 | Homepage early return bypasses negotiation | **High** | `GET /` with `Accept-Language: oj` renders English; no language activation occurs |
| 2 | `resolveRenderLanguageAndAliasPath()` does 4 things (negotiate, activate, detect prefix, strip) | Medium | Hard to test independently; SRP violation |
| 3 | `buildLanguageManager()` is a 70-line config parser in the rendering handler | Medium | Wrong location; duplicates app-level registration logic |
| 4 | `LanguageMiddleware` is dead code | Low | Confusion; maintenance burden; could diverge from real negotiation path |
| 5 | No test verifies `resolveLanguageManager()` prefers app-level instance | **High** | Regression risk if serviceResolver changes |
| 6 | No test verifies `setCurrentLanguage()` called after negotiation | **High** | Twig could silently render wrong locale |
| 7 | `detectLanguagePrefixFromPath()` + `stripLanguagePrefix()` duplicate `UrlPrefixNegotiator` logic | Low | Bug in one but not the other |

**Note on unrecognized prefixes:** A path like `/xx/about` where `xx` is not a registered language passes through with the full path intact. This will 404 at the template/alias layer — acceptable behavior. The framework does not attempt to guess whether a segment is a language code; it only recognizes explicitly registered languages.

---

## 4. Design: SRP Decomposition

### 4.1 Homepage Early Return Fix

**Change required in `handleRenderPage()`:** Remove the early return at lines 90-95 that renders `/` without language negotiation. Instead, let ALL paths (including `/` and `/<lang>/`) flow through the negotiation pipeline first. The negotiation returns `alias_path: '/'` for both bare `/` and `/<lang>/`, and the existing `if ($aliasLookupPath === '/')` block at line 103 handles homepage rendering.

```php
// BEFORE (buggy):
if ($normalizedPath === '' || $normalizedPath === '/') {
    $response = (new RenderController($twig))->renderPath('/');  // No negotiation!
    return $this->htmlResult(...);
}

// AFTER:
// No early return. All paths go through resolveRequestLanguageAndPath().
// Homepage is handled by the existing aliasLookupPath === '/' branch.
```

**Note:** This ensures `/oj/` negotiates language `oj` before rendering the homepage. It also means bare `/` with `Accept-Language: oj` will activate Ojibwe — a correct behavior change.

### 4.2 Method Decomposition

Replace `resolveRenderLanguageAndAliasPath()` with focused methods:

```php
/**
 * Get the shared LanguageManager. App-level (via serviceResolver) required.
 * Throws RuntimeException if no manager is registered.
 * Private — tested indirectly via resolveRequestLanguageAndPath().
 */
private function resolveLanguageManager(): LanguageManagerInterface

/**
 * Run negotiation strategies and activate the result on the shared manager.
 * Returns the activated Language.
 */
private function negotiateAndActivateLanguage(
    string $path,
    HttpRequest $request,
    LanguageManagerInterface $manager,
): Language

/**
 * If path starts with a known language prefix, strip it.
 * Returns the path without the prefix.
 */
private function stripLanguagePrefixFromPath(
    string $path,
    LanguageManagerInterface $manager,
): string

/**
 * Coordinator: negotiate language, strip prefix, return structured result.
 * Replaces resolveRenderLanguageAndAliasPath().
 */
public function resolveRequestLanguageAndPath(
    string $path,
    HttpRequest $request,
): array  // {langcode: string, alias_path: string}
```

### 4.3 No Fallback — Hard Requirement

**Remove `buildLanguageManager()` entirely.** The app MUST register a `LanguageManagerInterface` via a service provider. This is a greenfield stack where we control all consumers (Minoo, Claudriel). Keeping a fallback config-built manager only preserves legacy complexity and a second source of truth.

`resolveLanguageManager()` becomes:
- Attempt `($this->serviceResolver)(LanguageManagerInterface::class)`
- If `null` or `serviceResolver` is `null`, **throw `RuntimeException`** with message: `"LanguageManagerInterface not registered. Register via I18nServiceProvider or provide via serviceResolver."`

**Consequences:**
- `buildLanguageManager()` and its 70-line config parser are deleted
- The `i18n.languages` config key is no longer used by the framework (apps can still use it for their own provider logic)
- Tests that relied on `buildLanguageManager()` are replaced with tests using a serviceResolver mock
- Misconfiguration fails fast at request time with a clear error, not silently rendering English

---

## 5. Files to Change

| File | Change | Phase |
|------|--------|-------|
| `waaseyaa/packages/ssr/src/SsrPageHandler.php` | SRP decomposition; remove homepage early return; add deprecation log to fallback | C |
| `waaseyaa/packages/ssr/tests/Unit/SsrPageHandlerTest.php` | Add 7 new tests (see §6); rename existing test for new method name | B |
| `minoo/src/Middleware/LanguageMiddleware.php` | Delete after verification (see §5.1) | C |
| `minoo/.build/src/Middleware/LanguageMiddleware.php` | Delete (deployment artifact) | C |

### 5.1 LanguageMiddleware Deletion Verification

**Pre-deletion checklist (must ALL pass):**
1. `grep -r "LanguageMiddleware" /home/fsd42/dev/minoo` — only hits: source file, `.build/` copy, Composer autoload maps
2. No provider registers it (confirmed: zero references in `src/Provider/`)
3. No config, CI, or docs reference it
4. Composer autoload maps regenerate automatically on `composer dump-autoload`
5. `.build/` is a deployment artifact rebuilt on deploy

**Result:** All checks pass. Safe to delete.

---

## 6. Test Plan (Phase B — TDD)

### 6.1 New Tests

| # | Test Name | Assertion | Mock/Setup Strategy |
|---|-----------|-----------|-------------------|
| 1 | `resolve_language_manager_returns_app_instance` | `assertSame($appManager, $resolved)` — identity check, same object | Create handler with `serviceResolver` closure returning a pre-built `LanguageManager(['en','oj'])` when asked for `LanguageManagerInterface::class`. Test via `resolveRequestLanguageAndPath('/about', $request)` — if it returns `langcode: 'en'` using the app manager's language list, the app instance is being used. Additionally verify by checking `$appManager->getCurrentLanguage()->id === 'en'` (state mutation on the passed-in instance). |
| 2 | `resolve_language_manager_throws_when_no_app_manager` | `expectException(RuntimeException::class)` with message containing `LanguageManagerInterface not registered` | Create handler WITHOUT serviceResolver (default `null`). Call `resolveRequestLanguageAndPath('/about', $request)`. Must throw — no fallback. |
| 3 | `negotiate_and_activate_sets_current_language` | `assertSame('oj', $appManager->getCurrentLanguage()->id)` after call | Create `$appManager = new LanguageManager(['en','oj'])`. Pass via serviceResolver. Call `resolveRequestLanguageAndPath('/oj/about', $request)`. Assert `$appManager->getCurrentLanguage()->id === 'oj'` — verifies `setCurrentLanguage()` was called on the shared instance. |
| 4 | `url_prefix_detection_oj_homepage` | `assertSame('oj', $result['langcode'])` and `assertSame('/', $result['alias_path'])` | Same serviceResolver setup with `['en','oj']` manager. Call with `/oj/`. Prefix detected → stripped to `/`, language negotiated to `oj`. |
| 5 | `accept_language_fallback_when_no_prefix` | `assertSame('oj', $result['langcode'])` and `assertSame('/about', $result['alias_path'])` | Same serviceResolver setup. Create request: `HttpRequest::create('/about', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'oj'])`. No URL prefix → Accept-Language header used as fallback. |
| 6 | `invalid_prefix_falls_through_to_default` | `assertSame('en', $result['langcode'])` and `assertSame('/xx/about', $result['alias_path'])` | Same serviceResolver setup with `['en','oj']`. Call with `/xx/about`. `xx` not registered → no prefix detected, default `en`, path unchanged. |
| 7 | `default_language_no_prefix` | `assertSame('en', $result['langcode'])` and `assertSame('/about', $result['alias_path'])` | Same serviceResolver setup. Call with `/about`, no Accept-Language header. Default negotiation → `en`. |
| 8 | `homepage_without_prefix_negotiates_language` | `assertSame('en', $result['langcode'])` and `assertSame('/', $result['alias_path'])` | Same serviceResolver setup. Call with `/`. Verifies the old early-return bug is gone — `/` flows through negotiation instead of bypassing it. |

### 6.2 Existing Tests to Update

| Current Test Name | Change |
|-------------------|--------|
| `render_language_resolution_uses_url_prefix` | Rename method call `resolveRenderLanguageAndAliasPath` → `resolveRequestLanguageAndPath`; update `createHandler()` to pass serviceResolver with matching languages |
| `render_language_resolution_defaults_to_english` | Same rename; add serviceResolver |
| `build_language_manager_defaults_to_english` | **Delete** — `buildLanguageManager()` is removed |
| `build_language_manager_uses_configured_languages` | **Delete** |
| `build_language_manager_assigns_default_when_none_configured` | **Delete** |

---

## 7. Migration Checklist for Apps

After this refactor, `SsrPageHandler` **requires** an app-level `LanguageManagerInterface`. Missing registration is a startup error, not a silent fallback.

### Steps

1. **Create or update a service provider** that registers `LanguageManagerInterface`:
   ```php
   $this->singleton(LanguageManagerInterface::class, function (): LanguageManagerInterface {
       return new LanguageManager([
           new Language('en', 'English', isDefault: true),
           new Language('fr', 'Français'),
       ]);
   });
   ```

2. **Register the provider** in `composer.json` under `extra.waaseyaa.providers`.

3. **(Optional) Keep languages in config** for provider's own use — the framework no longer reads `i18n.languages` directly:
   ```php
   'i18n' => [
       'languages' => [
           ['id' => 'en', 'label' => 'English', 'is_default' => true],
           ['id' => 'fr', 'label' => 'Français'],
       ],
   ],
   ```

4. **Verify the kernel passes `serviceResolver`** — this is automatic if using `HttpKernel::boot()` (the kernel already wires this; see `packages/foundation/src/Kernel/HttpKernel.php:111`).

5. **Remove any custom language middleware** — the `SsrPageHandler` now handles language negotiation, activation, and prefix stripping internally.

6. **Test:** Verify `/<lang>/` paths resolve correctly and `trans()` outputs the right locale.

### What Breaks Without Migration

- **`RuntimeException` at request time** — if no `LanguageManagerInterface` is registered via a service provider, the first page render throws: `"LanguageManagerInterface not registered. Register via I18nServiceProvider or provide via serviceResolver."`
- This is intentional. Silent fallbacks masked broken i18n for months (the `/oj/` 404 bug). Fail-fast surfaces misconfiguration immediately.

### Claudriel Migration

1. Add or enable `I18nServiceProvider` that registers `LanguageManagerInterface`
2. Ensure `serviceResolver` in kernel can resolve `LanguageManagerInterface`
3. Run `./vendor/bin/phpunit --filter=SsrPageHandler` and fix failures
4. Verify `/<lang>/` renders correct translations in frontend
5. Remove any local fallback code that attempted to build a manager

---

## 8. GitHub Issues to Create

| Repo | Title | Purpose |
|------|-------|---------|
| waaseyaa | `refactor(i18n): require app-level LanguageManager, remove fallback` | Main refactor: SRP decomposition, remove `buildLanguageManager()`, add RuntimeException |
| waaseyaa | `chore: delete dead LanguageMiddleware from Minoo` | Remove unused middleware after repo-wide reference check |
| waaseyaa | `test(i18n): add language negotiation unit tests` | 8 new tests from §6 |
| claudriel | `chore(i18n): register LanguageManagerInterface` | Migration checklist for Claudriel to comply with new contract |

---

## 9. Implementation Order

1. Write 8 failing tests (Phase B — TDD red)
2. `resolveLanguageManager()` → throw RuntimeException when no app manager (makes test 2 pass)
3. `resolveLanguageManager()` → return app instance via serviceResolver (makes test 1 pass)
4. Remove homepage early return — all paths flow through negotiation (makes test 8 pass)
5. Add `negotiateAndActivateLanguage()` (makes test 3 pass)
6. Add `stripLanguagePrefixFromPath()` using shared manager's language list (makes tests 4, 6, 7 pass)
7. Add `resolveRequestLanguageAndPath()` coordinator (makes test 5 pass)
8. Delete `buildLanguageManager()` and its 3 existing tests
9. Update 2 existing tests to use new method name + serviceResolver
10. Delete `LanguageMiddleware` (after verification)
11. Run full test suite (waaseyaa + minoo), verify green
