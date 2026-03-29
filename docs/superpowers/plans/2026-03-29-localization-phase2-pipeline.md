# Localization Phase 2: Pipeline — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the OPD dictionary pipeline end-to-end and add dictionary search to the language page.

**Architecture:** The sync script (`bin/sync-dictionary`) fetches entries from the NC dictionary API via `NorthCloudClient::getDictionaryEntries()` and persists them locally. Search uses `NorthCloudClient::searchDictionary()` to query the NC API directly — local SQLite is for browse/display, NC is the search authority. A new `search()` action on `LanguageController` handles the search form.

**Tech Stack:** PHP 8.4, NorthCloudClient (HTTP), Twig 3, PHPUnit 10.5, Playwright

**Issues:** #331, #603

---

## File Structure

| Action | File | Responsibility |
|--------|------|---------------|
| Modify | `src/Controller/LanguageController.php` | Add `search()` method |
| Modify | `src/Provider/LanguageServiceProvider.php` | Add search route |
| Modify | `templates/language.html.twig` | Add search form, search results view |
| Modify | `resources/lang/en.php` | Add search translation keys |
| Modify | `resources/lang/oj.php` | Add matching keys |
| Create | `tests/Minoo/Unit/Controller/LanguageControllerSearchTest.php` | Search action unit test |
| Create | `tests/playwright/language-search.spec.ts` | E2E search tests |

---

### Task 1: Add search route and controller method (#603)

**Files:**
- Modify: `src/Controller/LanguageController.php`
- Modify: `src/Provider/LanguageServiceProvider.php`
- Create: `tests/Minoo/Unit/Controller/LanguageControllerSearchTest.php`

- [ ] **Step 1: Add search route to LanguageServiceProvider**

In `src/Provider/LanguageServiceProvider.php`, add after the `language.show` route (after line 105):

```php
        $router->addRoute(
            'language.search',
            RouteBuilder::create('/language/search')
                ->controller('Minoo\\Controller\\LanguageController::search')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
```

- [ ] **Step 2: Add NorthCloudClient to LanguageController constructor**

In `src/Controller/LanguageController.php`, add `NorthCloudClient` import and constructor parameter:

```php
use Minoo\Support\NorthCloudClient;
```

Update constructor:
```php
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly NorthCloudClient $northCloudClient,
    ) {}
```

Note: `NorthCloudClient` must be registered as a singleton for the DI to inject it. Check if it's already registered. If not, register it in the `LanguageServiceProvider::register()`:

```php
$this->singleton(NorthCloudClient::class, function () {
    $config = $this->getConfig();
    $baseUrl = rtrim((string) ($config['northcloud']['base_url'] ?? 'https://api.northcloud.one'), '/');
    $timeout = (int) ($config['northcloud']['search']['timeout'] ?? $config['northcloud']['timeout'] ?? 15);
    return new NorthCloudClient($baseUrl, $timeout);
});
```

- [ ] **Step 3: Add search method to LanguageController**

Add after the `show()` method in `src/Controller/LanguageController.php`:

```php
    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function search(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $q = trim((string) ($query['q'] ?? ''));

        if ($q === '') {
            return new SsrResponse(
                content: $this->twig->render('language.html.twig', LayoutTwigContext::withAccount($account, [
                    'path' => '/language',
                    'search_query' => '',
                    'search_results' => [],
                    'search_total' => 0,
                ])),
            );
        }

        $result = $this->northCloudClient->searchDictionary($q);
        $entries = $result['entries'] ?? [];
        $total = $result['total'] ?? count($entries);

        $html = $this->twig->render('language.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language',
            'search_query' => $q,
            'search_results' => $entries,
            'search_total' => $total,
        ]));

        return new SsrResponse(content: $html);
    }
```

- [ ] **Step 4: Write unit test for search action**

Create `tests/Minoo/Unit/Controller/LanguageControllerSearchTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\LanguageController;
use Minoo\Support\NorthCloudClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(LanguageController::class)]
final class LanguageControllerSearchTest extends TestCase
{
    #[Test]
    public function search_with_empty_query_returns_empty_results(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $twig = $this->createMock(Environment::class);
        $nc = $this->createMock(NorthCloudClient::class);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/language/search');

        $nc->expects($this->never())->method('searchDictionary');
        $twig->expects($this->once())->method('render')
            ->with('language.html.twig', $this->callback(function (array $context): bool {
                return $context['search_query'] === ''
                    && $context['search_results'] === []
                    && $context['search_total'] === 0;
            }))
            ->willReturn('<html></html>');

        $controller = new LanguageController($etm, $twig, $nc);
        $response = $controller->search([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function search_with_query_calls_northcloud_and_returns_results(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $twig = $this->createMock(Environment::class);
        $nc = $this->createMock(NorthCloudClient::class);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/language/search?q=makwa');

        $nc->expects($this->once())->method('searchDictionary')
            ->with('makwa')
            ->willReturn([
                'entries' => [['lemma' => 'makwa', 'definitions' => ['a bear']]],
                'total' => 1,
            ]);

        $twig->expects($this->once())->method('render')
            ->with('language.html.twig', $this->callback(function (array $context): bool {
                return $context['search_query'] === 'makwa'
                    && count($context['search_results']) === 1
                    && $context['search_total'] === 1;
            }))
            ->willReturn('<html></html>');

        $controller = new LanguageController($etm, $twig, $nc);
        $response = $controller->search([], ['q' => 'makwa'], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }
}
```

Note: `NorthCloudClient` must NOT be `final` for mocking. Check if it is — if so, use an interface or remove `final`.

- [ ] **Step 5: Run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit tests/Minoo/Unit/Controller/LanguageControllerSearchTest.php
```

Expected: 2 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/LanguageController.php src/Provider/LanguageServiceProvider.php tests/Minoo/Unit/Controller/LanguageControllerSearchTest.php
git commit -m "feat(#603): add dictionary search endpoint to LanguageController"
```

---

### Task 2: Add search UI to language template (#603)

**Files:**
- Modify: `templates/language.html.twig`
- Modify: `resources/lang/en.php`
- Modify: `resources/lang/oj.php`

- [ ] **Step 1: Add translation keys**

Add to `resources/lang/en.php`:
```php
'language.search_placeholder' => 'Search the dictionary…',
'language.search_button' => 'Search',
'language.search_results_title' => 'Search results for "{query}"',
'language.search_results_count' => '{count} results',
'language.search_no_results' => 'No results found for "{query}"',
'language.search_try_again' => 'Try a different search term or browse the dictionary.',
'language.browse_all' => 'Browse all entries',
```

Add to `resources/lang/oj.php`:
```php
'language.search_placeholder' => '', // needs translation
'language.search_button' => '', // needs translation
'language.search_results_title' => '', // needs translation
'language.search_results_count' => '', // needs translation
'language.search_no_results' => '', // needs translation
'language.search_try_again' => '', // needs translation
'language.browse_all' => '', // needs translation
```

- [ ] **Step 2: Add search form to language template**

In `templates/language.html.twig`, add the search form after the subtitle (after line 17), before the entries listing:

```twig
      <form action="{{ lang_url('/language/search') }}" method="get" class="search-form" role="search">
        <label for="dict-search" class="sr-only">{{ trans('language.search_placeholder') }}</label>
        <input type="search" id="dict-search" name="q" value="{{ search_query|default('') }}" placeholder="{{ trans('language.search_placeholder') }}" class="search-form__input" />
        <button type="submit" class="search-form__button">{{ trans('language.search_button') }}</button>
      </form>
```

- [ ] **Step 3: Add search results view**

In `templates/language.html.twig`, add a search results section. After the search form, before the existing entries listing, add:

```twig
      {% if search_query is defined and search_query != '' %}
        <h2>{{ trans('language.search_results_title')|replace({'{query}': search_query}) }}</h2>
        {% if search_results is defined and search_results|length > 0 %}
          <p class="listing-hero__count">{{ trans('language.search_results_count')|replace({'{count}': search_total}) }}</p>
          <div class="card-grid">
            {% for r in search_results %}
              {% include "components/dictionary-entry-card.html.twig" with {
                word: r.lemma|default(r.word|default('')),
                part_of_speech: r.word_class_normalized|default(r.word_class|default(r.part_of_speech|default(''))),
                definition: r.definitions is iterable ? r.definitions|join('; ') : (r.definition|default('')),
                attribution_source: 'Ojibwe People\'s Dictionary',
                attribution_url: r.source_url|default(''),
                example: '',
                tags: [],
                url: ''
              } %}
            {% endfor %}
          </div>
          <p><a href="{{ lang_url('/language') }}">{{ trans('language.browse_all') }}</a></p>
        {% else %}
          <p>{{ trans('language.search_no_results')|replace({'{query}': search_query}) }}</p>
          <p>{{ trans('language.search_try_again') }}</p>
          <p><a href="{{ lang_url('/language') }}">{{ trans('language.browse_all') }}</a></p>
        {% endif %}
      {% else %}
```

Close the else block at the end of the existing entries listing (before the attribution footer):
```twig
      {% endif %} {# end search_query check #}
```

Note: The NC search API returns raw `entries` arrays with fields like `lemma`, `word_class_normalized`, `definitions` (array), `source_url`. These differ from the entity field names. The template handles both formats.

- [ ] **Step 4: Run full tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add templates/language.html.twig resources/lang/en.php resources/lang/oj.php
git commit -m "feat(#603): add search form and results UI to language page"
```

---

### Task 3: Sync verification (#331)

**Files:** No code changes — operational verification.

- [ ] **Step 1: Run sync in dry-run mode**

```bash
php bin/sync-dictionary --dry-run 2>&1 | tail -5
```

Expected: Shows CREATE/UPDATE entries, total count near 22,186. If it fails with ConsoleKernel error, the script uses ConsoleKernel (known broken — #493). Check and switch to HttpKernel boot pattern if needed.

- [ ] **Step 2: Run actual sync**

```bash
php bin/sync-dictionary 2>&1 | tail -10
```

Expected: `Done: ~22186 fetched, N created, M updated, 0 errors.`

If ConsoleKernel is broken, use HttpKernel workaround — create a temp script:
```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
$kernel = new Waaseyaa\Foundation\Kernel\HttpKernel(dirname(__DIR__));
(new ReflectionMethod(Waaseyaa\Foundation\Kernel\AbstractKernel::class, 'boot'))->invoke($kernel);
// ... rest of sync logic from bin/sync-dictionary
```

- [ ] **Step 3: Verify entry count**

```bash
php -r "
require 'vendor/autoload.php';
\$k = new Waaseyaa\Foundation\Kernel\HttpKernel(__DIR__);
(new ReflectionMethod(Waaseyaa\Foundation\Kernel\AbstractKernel::class, 'boot'))->invoke(\$k);
\$s = \$k->getEntityTypeManager()->getStorage('dictionary_entry');
\$ids = \$s->getQuery()->condition('status', 1)->execute();
echo count(\$ids) . ' published entries\n';
"
```

Expected: 20,000+ entries.

- [ ] **Step 4: Verify display on local dev server**

Start server and check:
```bash
php -S localhost:8081 -t public &
```

Open http://localhost:8081/language — verify entries appear with word, definition, part_of_speech badge, attribution.

- [ ] **Step 5: Commit sync results (if any config changes)**

```bash
git add -A
git status
# Only commit if there are actual code changes
```

---

### Task 4: Playwright E2E — language search + browse (local + prod)

**Files:**
- Create: `tests/playwright/language-search.spec.ts`

- [ ] **Step 1: Write Playwright tests**

Create `tests/playwright/language-search.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';

test.describe('Dictionary browse and search', () => {
  test('language page shows dictionary entries', async ({ page }) => {
    await page.goto('/language');
    const heading = page.locator('h1');
    await expect(heading).toBeVisible();
    // Should have at least one dictionary entry card
    const cards = page.locator('.card--language');
    await expect(cards.first()).toBeVisible();
  });

  test('language page has pagination', async ({ page }) => {
    await page.goto('/language');
    const pagination = page.locator('.pagination');
    await expect(pagination).toBeVisible();
  });

  test('search form is present on language page', async ({ page }) => {
    await page.goto('/language');
    const searchInput = page.locator('#dict-search');
    await expect(searchInput).toBeVisible();
  });

  test('search returns results for "makwa"', async ({ page }) => {
    await page.goto('/language/search?q=makwa');
    // Should show search results
    const cards = page.locator('.card--language');
    await expect(cards.first()).toBeVisible({ timeout: 15000 });
  });

  test('search with no results shows empty state', async ({ page }) => {
    await page.goto('/language/search?q=xyznotaword123');
    // Should show no results message
    const content = page.locator('main');
    await expect(content).toContainText('No results');
  });

  test('dictionary entry detail page works', async ({ page }) => {
    await page.goto('/language');
    // Click the first entry link
    const firstLink = page.locator('.card--language .card__title a').first();
    await firstLink.click();
    // Should load detail page
    await expect(page.locator('h1')).toBeVisible();
  });

  test('attribution is visible on entries', async ({ page }) => {
    await page.goto('/language');
    const attribution = page.locator('.card__meta').first();
    await expect(attribution).toBeVisible();
  });
});
```

- [ ] **Step 2: Run against local**

```bash
npx playwright test tests/playwright/language-search.spec.ts
```

Expected: All 7 tests pass. The search test may need the NC API to be reachable — if running locally without internet, the search test will fail (acceptable for offline dev).

- [ ] **Step 3: Commit**

```bash
git add tests/playwright/language-search.spec.ts
git commit -m "test(#603): add Playwright E2E for dictionary browse and search"
```

---

### Task 5: Checkpoint 2 — full verification

- [ ] **Step 1: Run full PHPUnit suite**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

Expected: All tests pass (835+ existing + new search tests).

- [ ] **Step 2: Run full Playwright suite locally**

```bash
npx playwright test
```

Expected: All tests pass including new language-search tests.

- [ ] **Step 3: Push and wait for deploy**

```bash
git push
```

Wait for CI + auto-deploy to complete:
```bash
gh run list --repo waaseyaa/minoo --limit 2 --json databaseId,name,status --jq '.[] | "\(.databaseId) | \(.name) | \(.status)"'
```

Watch the deploy run to completion.

- [ ] **Step 4: Verify production with Playwright MCP**

Navigate to `https://minoo.live/language`:
- Dictionary entries render with cards
- Pagination works
- Search form is visible
- Search for "makwa" returns results
- Entry detail page works
- Attribution visible

Navigate to `https://minoo.live/oj/language`:
- Same functionality works with Ojibwe URL prefix
- i18n regression check — nav is still in Ojibwe

- [ ] **Step 5: Manual verification checklist**

- [ ] `/language` shows paginated dictionary entries
- [ ] Entry cards show: word, part_of_speech badge, definition, attribution
- [ ] Clicking an entry navigates to `/language/{slug}` detail page
- [ ] Detail page shows inflected forms
- [ ] Search form works — enter "makwa", get bear entry
- [ ] Empty search shows empty state
- [ ] No-results search shows helpful message
- [ ] `/oj/language` works (i18n regression)
- [ ] Phase 1 regression: language switcher still works

**Phase 2 complete.** All gates green before starting Phase 3 (Research & Enrichment).
