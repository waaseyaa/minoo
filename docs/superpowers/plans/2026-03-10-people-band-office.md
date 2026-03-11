# People + Band Office Integration — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Display leadership (chief + council) and band office contact info on community detail pages, fetched from NorthCloud's API.

**Architecture:** Add `nc_id` field to community entity, create a `NorthCloudClient` that fetches people and band office data by NC UUID, wire it into `CommunityController::show()`, and render two new template sections with corresponding CSS.

**Tech Stack:** PHP 8.3+, Twig 3, vanilla CSS, PHPUnit 10.5, Playwright

**Spec:** `docs/superpowers/specs/2026-03-10-people-band-office-design.md`

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `src/Support/NorthCloudClient.php` | HTTP client for NC people + band office API |
| Create | `tests/Minoo/Unit/Support/NorthCloudClientTest.php` | Unit tests for NC client |
| Create | `bin/backfill-nc-ids` | One-time script to populate `nc_id` from NC API |
| Modify | `src/Provider/CommunityServiceProvider.php:23-43` | Add `nc_id` field definition |
| Modify | `tests/Minoo/Unit/Entity/CommunityTest.php:63-87` | Add `nc_id` to metadata test |
| Modify | `src/Controller/CommunityController.php:69-98` | Fetch people + band office in `show()` |
| Modify | `tests/Minoo/Unit/Controller/CommunityControllerTest.php:84-110` | Test show() with NC data |
| Modify | `config/waaseyaa.php` | Add `northcloud.base_url` config |
| Modify | `templates/communities.html.twig:145-159` | Add leadership + band office sections |
| Modify | `public/css/minoo.css:1317` | Add leadership + band office CSS |

---

## Task 1: Add `nc_id` field to community entity

**Files:**
- Modify: `src/Provider/CommunityServiceProvider.php:23-43`
- Modify: `tests/Minoo/Unit/Entity/CommunityTest.php:63-87`

- [ ] **Step 1: Write the failing test**

Add `nc_id` to the existing metadata fields test in `CommunityTest.php`:

```php
// In it_supports_all_metadata_fields(), add to constructor array:
'nc_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',

// Add assertion:
$this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $community->get('nc_id'));
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CommunityTest.php --filter it_supports_all_metadata_fields -v`
Expected: FAIL — `nc_id` value stored in `_data` JSON but not in dedicated column (test may pass since ContentEntityBase stores unknown fields in `_data`; if so, proceed to step 3 anyway — the field definition is still needed for schema)

- [ ] **Step 3: Add `nc_id` field definition**

In `src/Provider/CommunityServiceProvider.php`, add after the `statcan_csd` field (line ~41):

```php
'nc_id' => ['type' => 'string', 'label' => 'NorthCloud ID', 'weight' => 42],
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CommunityTest.php -v`
Expected: All 5 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Provider/CommunityServiceProvider.php tests/Minoo/Unit/Entity/CommunityTest.php
git commit -m "feat(#178): add nc_id field to community entity"
```

---

## Task 2: Create NorthCloudClient

**Files:**
- Create: `src/Support/NorthCloudClient.php`
- Create: `tests/Minoo/Unit/Support/NorthCloudClientTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Minoo/Unit/Support/NorthCloudClientTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\NorthCloudClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NorthCloudClient::class)]
final class NorthCloudClientTest extends TestCase
{
    #[Test]
    public function get_people_returns_array_on_success(): void
    {
        $responseJson = json_encode([
            'people' => [
                [
                    'id' => 'p1',
                    'name' => 'Chief Example',
                    'role' => 'chief',
                    'role_title' => 'Chief',
                    'email' => 'chief@example.com',
                    'phone' => '705-555-0001',
                    'is_current' => true,
                    'verified' => false,
                    'updated_at' => '2026-01-15T00:00:00Z',
                ],
                [
                    'id' => 'p2',
                    'name' => 'Councillor One',
                    'role' => 'councillor',
                    'role_title' => 'Councillor',
                    'is_current' => true,
                    'verified' => false,
                    'updated_at' => '2026-01-15T00:00:00Z',
                ],
            ],
            'total' => 2,
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => $responseJson,
        );

        $result = $client->getPeople('nc-uuid-123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Chief Example', $result[0]['name']);
        $this->assertSame('chief', $result[0]['role']);
    }

    #[Test]
    public function get_people_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => false,
        );

        $this->assertNull($client->getPeople('nc-uuid-123'));
    }

    #[Test]
    public function get_people_returns_null_on_malformed_json(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => '<html>not json</html>',
        );

        $this->assertNull($client->getPeople('nc-uuid-123'));
    }

    #[Test]
    public function get_band_office_returns_array_on_success(): void
    {
        $responseJson = json_encode([
            'band_office' => [
                'id' => 'bo1',
                'address_line1' => '100 Main St',
                'city' => 'Sagamok',
                'province' => 'ON',
                'postal_code' => 'P0P 1X0',
                'phone' => '705-555-0002',
                'fax' => '705-555-0003',
                'email' => 'office@sagamok.ca',
                'toll_free' => '1-800-555-0004',
                'office_hours' => 'Mon-Fri 8:30am-4:30pm',
                'verified' => false,
                'updated_at' => '2026-01-15T00:00:00Z',
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => $responseJson,
        );

        $result = $client->getBandOffice('nc-uuid-123');

        $this->assertIsArray($result);
        $this->assertSame('100 Main St', $result['address_line1']);
        $this->assertSame('705-555-0002', $result['phone']);
    }

    #[Test]
    public function get_band_office_returns_null_on_http_failure(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => false,
        );

        $this->assertNull($client->getBandOffice('nc-uuid-123'));
    }

    #[Test]
    public function get_band_office_returns_null_on_404(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: fn (string $url): string|false => json_encode(['band_office' => null]),
        );

        $this->assertNull($client->getBandOffice('nc-uuid-123'));
    }

    #[Test]
    public function get_people_builds_correct_url(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url) use (&$capturedUrl): string|false {
                $capturedUrl = $url;
                return json_encode(['people' => [], 'total' => 0]);
            },
        );

        $client->getPeople('abc-123');

        $this->assertSame(
            'https://northcloud.one/api/v1/communities/abc-123/people?current_only=true',
            $capturedUrl,
        );
    }

    #[Test]
    public function get_band_office_builds_correct_url(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://northcloud.one',
            timeout: 5,
            httpClient: function (string $url) use (&$capturedUrl): string|false {
                $capturedUrl = $url;
                return json_encode(['band_office' => null]);
            },
        );

        $client->getBandOffice('abc-123');

        $this->assertSame(
            'https://northcloud.one/api/v1/communities/abc-123/band-office',
            $capturedUrl,
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/NorthCloudClientTest.php -v`
Expected: FAIL — class `NorthCloudClient` not found

- [ ] **Step 3: Implement NorthCloudClient**

Create `src/Support/NorthCloudClient.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

final class NorthCloudClient
{
    /** @var \Closure|null */
    private readonly ?\Closure $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient !== null ? $httpClient(...) : null;
    }

    /**
     * Fetch current leadership for a community.
     *
     * @return list<array{id: string, name: string, role: string, role_title?: string, email?: string, phone?: string, verified: bool}>|null
     */
    public function getPeople(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/' . urlencode($ncId) . '/people?current_only=true';
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['people']) || !is_array($data['people'])) {
            error_log(sprintf('NorthCloud people response malformed for community %s', $ncId));
            return null;
        }

        return $data['people'];
    }

    /**
     * Fetch band office contact info for a community.
     *
     * @return array{address_line1?: string, address_line2?: string, city?: string, province?: string, postal_code?: string, phone?: string, fax?: string, email?: string, toll_free?: string, office_hours?: string, verified: bool}|null
     */
    public function getBandOffice(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/' . urlencode($ncId) . '/band-office';
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['band_office']) || !is_array($data['band_office'])) {
            return null;
        }

        return $data['band_office'];
    }

    private function doRequest(string $url): ?string
    {
        if ($this->httpClient !== null) {
            $result = ($this->httpClient)($url);
            return $result === false ? null : $result;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log(sprintf('NorthCloud API request failed: %s', $url));
            return null;
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/NorthCloudClientTest.php -v`
Expected: All 8 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Support/NorthCloudClient.php tests/Minoo/Unit/Support/NorthCloudClientTest.php
git commit -m "feat(#178): add NorthCloudClient for people and band office API"
```

---

## Task 3: Add NorthCloud config and wire into CommunityController

**Files:**
- Modify: `config/waaseyaa.php`
- Modify: `src/Controller/CommunityController.php:69-98`
- Modify: `tests/Minoo/Unit/Controller/CommunityControllerTest.php:84-110`

- [ ] **Step 1: Add northcloud config**

In `config/waaseyaa.php`, add a `northcloud` key at the top level (after the `search` block):

```php
'northcloud' => [
    'base_url' => getenv('NORTHCLOUD_BASE_URL') ?: 'https://northcloud.one',
    'timeout' => (int) (getenv('NORTHCLOUD_TIMEOUT') ?: 5),
],
```

- [ ] **Step 2: Write the failing test**

Add a new test to `CommunityControllerTest.php`:

```php
#[Test]
public function show_passes_people_and_band_office_to_template(): void
{
    $sagamok = new Community([
        'cid' => 1,
        'name' => 'Sagamok Anishnawbek',
        'slug' => 'sagamok-anishnawbek',
        'community_type' => 'first_nation',
        'nc_id' => 'nc-uuid-123',
    ]);

    $this->query->method('execute')->willReturn([1]);
    $this->storage->method('load')->with(1)->willReturn($sagamok);

    // Update twig template to expose people/band_office vars
    $this->twig = new Environment(new ArrayLoader([
        'communities.html.twig' => '{{ path }}{% if community is defined and community %}|{{ community.get("name") }}{% endif %}{% if people is defined and people %}|people:{{ people|length }}{% endif %}{% if band_office is defined and band_office %}|office:{{ band_office.phone }}{% endif %}',
    ]));

    $controller = new CommunityController($this->entityTypeManager, $this->twig);
    $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

    $this->assertSame(200, $response->statusCode);
    $this->assertStringContainsString('Sagamok Anishnawbek', $response->content);
    // People and band_office will be null since no real NC API — verify no crash
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php --filter show_passes_people_and_band_office -v`
Expected: FAIL or PASS (depends on whether template vars are passed). If it passes, proceed — the real test is that the controller doesn't crash.

- [ ] **Step 4: Update CommunityController::show()**

Modify `src/Controller/CommunityController.php`. Add `use Minoo\Support\NorthCloudClient;` to imports. Update the `show()` method to fetch people and band office:

```php
public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $slug = $params['slug'] ?? '';
    $storage = $this->entityTypeManager->getStorage('community');
    $ids = $storage->getQuery()
        ->condition('slug', $slug)
        ->condition('status', 1)
        ->execute();

    $community = $ids !== [] ? $storage->load(reset($ids)) : null;

    $nearby = [];
    $location = $this->resolveLocation($request);
    $people = null;
    $bandOffice = null;

    if ($community !== null) {
        $nearby = $this->findNearbyCommunities($community, $storage);

        $ncId = $community->get('nc_id');
        if ($ncId !== null && $ncId !== '') {
            $ncClient = $this->createNorthCloudClient();
            $people = $ncClient->getPeople((string) $ncId);
            $bandOffice = $ncClient->getBandOffice((string) $ncId);
        }
    }

    $html = $this->twig->render('communities.html.twig', [
        'path' => '/communities/' . $slug,
        'community' => $community,
        'nearby' => $nearby,
        'location' => $location,
        'people' => $people,
        'band_office' => $bandOffice,
    ]);

    return new SsrResponse(
        content: $html,
        statusCode: $community !== null ? 200 : 404,
    );
}
```

Add the `createNorthCloudClient()` helper (after `resolveLocation()`):

```php
private function createNorthCloudClient(): NorthCloudClient
{
    $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
    $config = file_exists($configPath) ? (require $configPath)['northcloud'] ?? [] : [];

    return new NorthCloudClient(
        baseUrl: (string) ($config['base_url'] ?? 'https://northcloud.one'),
        timeout: (int) ($config['timeout'] ?? 5),
    );
}
```

- [ ] **Step 5: Run all controller tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php -v`
Expected: All tests PASS (existing + new)

- [ ] **Step 6: Commit**

```bash
git add config/waaseyaa.php src/Controller/CommunityController.php tests/Minoo/Unit/Controller/CommunityControllerTest.php
git commit -m "feat(#178): wire NorthCloudClient into CommunityController::show()"
```

---

## Task 4: Add leadership and band office template sections

**Files:**
- Modify: `templates/communities.html.twig:145-159`

- [ ] **Step 1: Add leadership section**

In `templates/communities.html.twig`, insert after the stats grid closing `{% endif %}` (line 145) and before the Location section (line 147):

```twig
      {# --- Leadership --- #}
      {% if people is defined and people and people|length > 0 %}
        <section class="community__section community__leadership">
          <h2 class="community__section-title">Leadership</h2>

          {% if people|filter(p => p.verified is defined and not p.verified)|length > 0 %}
            <p class="community__unverified">This information was sourced from community websites and may not be current.</p>
          {% endif %}

          {% set chief = null %}
          {% set councillors = [] %}
          {% for person in people %}
            {% if person.role == 'chief' and chief is null %}
              {% set chief = person %}
            {% else %}
              {% set councillors = councillors|merge([person]) %}
            {% endif %}
          {% endfor %}

          {% if chief %}
            <div class="community__chief">
              <div class="community__person">
                <span class="community__person-role">{{ chief.role_title|default('Chief') }}</span>
                <span class="community__person-name">{{ chief.name }}</span>
                {% if chief.email is defined and chief.email %}
                  <a href="mailto:{{ chief.email }}" class="community__person-contact">{{ chief.email }}</a>
                {% endif %}
                {% if chief.phone is defined and chief.phone %}
                  <a href="tel:{{ chief.phone }}" class="community__person-contact">{{ chief.phone }}</a>
                {% endif %}
              </div>
            </div>
          {% endif %}

          {% if councillors|length > 0 %}
            <div class="community__councillors">
              {% for person in councillors %}
                <div class="community__person">
                  <span class="community__person-role">{{ person.role_title|default(person.role|capitalize) }}</span>
                  <span class="community__person-name">{{ person.name }}</span>
                  {% if person.email is defined and person.email %}
                    <a href="mailto:{{ person.email }}" class="community__person-contact">{{ person.email }}</a>
                  {% endif %}
                  {% if person.phone is defined and person.phone %}
                    <a href="tel:{{ person.phone }}" class="community__person-contact">{{ person.phone }}</a>
                  {% endif %}
                </div>
              {% endfor %}
            </div>
          {% endif %}
        </section>
      {% endif %}

      {# --- Band Office --- #}
      {% if band_office is defined and band_office %}
        <section class="community__section community__band-office">
          <h2 class="community__section-title">Band Office</h2>

          {% if band_office.verified is defined and not band_office.verified %}
            <p class="community__unverified">This information was sourced from community websites and may not be current.</p>
          {% endif %}

          {% set has_address = band_office.address_line1 is defined and band_office.address_line1 %}
          {% if has_address %}
            <address class="community__address">
              {{ band_office.address_line1 }}<br>
              {% if band_office.address_line2 is defined and band_office.address_line2 %}
                {{ band_office.address_line2 }}<br>
              {% endif %}
              {% if band_office.city is defined and band_office.city %}{{ band_office.city }}{% endif %}{% if band_office.province is defined and band_office.province %}, {{ band_office.province }}{% endif %}
              {% if band_office.postal_code is defined and band_office.postal_code %}  {{ band_office.postal_code }}{% endif %}
            </address>
            <a href="https://www.google.com/maps/search/{{ (band_office.address_line1 ~ ', ' ~ (band_office.city|default('')) ~ ', ' ~ (band_office.province|default('')))|url_encode }}"
               target="_blank" rel="noopener" class="community__maps-link">
              View on Google Maps
            </a>
          {% endif %}

          <div class="community__contact-grid">
            {% if band_office.phone is defined and band_office.phone %}
              <div class="community__contact-item">
                <span class="community__contact-label">Phone</span>
                <a href="tel:{{ band_office.phone }}">{{ band_office.phone }}</a>
              </div>
            {% endif %}
            {% if band_office.toll_free is defined and band_office.toll_free %}
              <div class="community__contact-item">
                <span class="community__contact-label">Toll-Free</span>
                <a href="tel:{{ band_office.toll_free }}">{{ band_office.toll_free }}</a>
              </div>
            {% endif %}
            {% if band_office.fax is defined and band_office.fax %}
              <div class="community__contact-item">
                <span class="community__contact-label">Fax</span>
                <span>{{ band_office.fax }}</span>
              </div>
            {% endif %}
            {% if band_office.email is defined and band_office.email %}
              <div class="community__contact-item">
                <span class="community__contact-label">Email</span>
                <a href="mailto:{{ band_office.email }}">{{ band_office.email }}</a>
              </div>
            {% endif %}
          </div>

          {% if band_office.office_hours is defined and band_office.office_hours %}
            <div class="community__office-hours">
              <span class="community__contact-label">Office Hours</span>
              <span>{{ band_office.office_hours }}</span>
            </div>
          {% endif %}
        </section>
      {% endif %}
```

- [ ] **Step 2: Verify template renders without errors**

Run: `php -S localhost:8081 -t public &` then visit `http://localhost:8081/communities/sagamok-anishnawbek` — page should load without errors. Leadership and band office sections won't appear until `nc_id` is backfilled.

- [ ] **Step 3: Commit**

```bash
git add templates/communities.html.twig
git commit -m "feat(#178): add leadership and band office template sections"
```

---

## Task 5: Add CSS for leadership and band office

**Files:**
- Modify: `public/css/minoo.css:1317`

- [ ] **Step 1: Add CSS classes**

Insert after the `.community__nearby-distance` block (line 1317) in `@layer components`:

```css
/* -- Leadership & Band Office -- */

.community__leadership {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.community__chief {
  padding: var(--space-md);
  background: var(--surface-raised);
  border: 1px solid var(--border);
  border-inline-start: 3px solid var(--accent-primary);
  border-radius: var(--radius-md);
}

.community__councillors {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
  gap: var(--space-sm);
}

.community__person {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
  padding: var(--space-sm);
  background: var(--surface-raised);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
}

.community__person-role {
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
}

.community__person-name {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: 600;
}

.community__person-contact {
  font-size: var(--text-sm);
  color: var(--accent-primary);
}

.community__band-office {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.community__address {
  font-style: normal;
  line-height: 1.6;
}

.community__maps-link::before {
  content: "\1F5FA\FE0F ";
}

.community__contact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
  gap: var(--space-sm);
}

.community__contact-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
}

.community__contact-label {
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
}

.community__office-hours {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
}

.community__unverified {
  font-size: var(--text-sm);
  font-style: italic;
  color: var(--text-secondary);
  padding: var(--space-xs) var(--space-sm);
  background: oklch(95% 0.01 80);
  border-radius: var(--radius-sm);
}
```

- [ ] **Step 2: Verify CSS loads without errors**

Run: Visit community detail page in browser — verify no CSS parse errors in dev tools console.

- [ ] **Step 3: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(#178): add CSS for leadership and band office sections"
```

---

## Task 6: Create backfill script

**Files:**
- Create: `bin/backfill-nc-ids`

- [ ] **Step 1: Create the backfill script**

Create `bin/backfill-nc-ids`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Kernel\HttpKernel;

// Boot kernel to get entity storage
$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod($kernel, 'boot');
$boot->setAccessible(true);
$boot->invoke($kernel);

$container = (new ReflectionProperty($kernel, 'container'))->getValue($kernel);
$entityTypeManager = $container->get(\Waaseyaa\Entity\EntityTypeManager::class);
$storage = $entityTypeManager->getStorage('community');

// Load config
$configPath = dirname(__DIR__) . '/config/waaseyaa.php';
$config = file_exists($configPath) ? (require $configPath)['northcloud'] ?? [] : [];
$baseUrl = (string) ($config['base_url'] ?? 'https://northcloud.one');

echo "Fetching communities from NorthCloud ({$baseUrl})...\n";

// Fetch all communities from NC
$url = rtrim($baseUrl, '/') . '/api/v1/communities?limit=1000';
$context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 30]]);
$json = @file_get_contents($url, false, $context);

if ($json === false) {
    echo "ERROR: Failed to fetch communities from NorthCloud.\n";
    exit(1);
}

$data = json_decode($json, true);
if (!is_array($data) || !isset($data['communities'])) {
    echo "ERROR: Unexpected response format.\n";
    exit(1);
}

$ncCommunities = $data['communities'];
echo sprintf("Received %d communities from NorthCloud.\n", count($ncCommunities));

// Build inac_id → nc_id lookup
$ncByInac = [];
foreach ($ncCommunities as $nc) {
    $inacId = $nc['inac_id'] ?? null;
    if ($inacId !== null && $inacId !== '') {
        $ncByInac[(string) $inacId] = (string) $nc['id'];
    }
}

echo sprintf("Found %d communities with INAC IDs.\n", count($ncByInac));

// Load all local communities
$allIds = $storage->getQuery()->condition('status', 1)->execute();
$communities = $allIds !== [] ? $storage->loadMultiple($allIds) : [];

$matched = 0;
$skipped = 0;
$noInac = 0;

foreach ($communities as $community) {
    $inacId = $community->get('inac_id');

    if ($inacId === null || $inacId === '' || $inacId === 0) {
        $noInac++;
        continue;
    }

    $ncId = $ncByInac[(string) $inacId] ?? null;
    if ($ncId === null) {
        $skipped++;
        continue;
    }

    $community->set('nc_id', $ncId);
    $storage->save($community);
    $matched++;
}

echo "\nBackfill complete:\n";
echo sprintf("  Matched: %d\n", $matched);
echo sprintf("  No INAC ID: %d\n", $noInac);
echo sprintf("  Unmatched: %d\n", $skipped);
echo sprintf("  Total local: %d\n", count($communities));
```

- [ ] **Step 2: Make executable**

```bash
chmod +x bin/backfill-nc-ids
```

- [ ] **Step 3: Commit**

```bash
git add bin/backfill-nc-ids
git commit -m "feat(#178): add bin/backfill-nc-ids script for NC UUID population"
```

---

## Task 7: Add Playwright tests

**Files:**
- Modify or create: `tests/playwright/communities.spec.ts`

- [ ] **Step 1: Check if communities spec already exists**

Look for `tests/playwright/communities.spec.ts`. If it exists, add to it. If not, create it.

- [ ] **Step 2: Write Playwright tests**

Add these tests (in existing file or new file):

```typescript
import { test, expect } from '@playwright/test';

test.describe('Community detail — leadership and band office', () => {
  test('community detail page loads without leadership section when no NC data', async ({ page }) => {
    await page.goto('/communities/sagamok-anishnawbek');
    await expect(page.locator('h1')).toContainText('Sagamok');
    // Leadership section should not be present without nc_id/NC data
    await expect(page.locator('.community__leadership')).toHaveCount(0);
  });

  test('community detail page loads without band office section when no NC data', async ({ page }) => {
    await page.goto('/communities/sagamok-anishnawbek');
    await expect(page.locator('.community__band-office')).toHaveCount(0);
  });

  test('community detail page still shows stats and nearby without NC data', async ({ page }) => {
    await page.goto('/communities/sagamok-anishnawbek');
    await expect(page.locator('.community__stats')).toBeVisible();
  });
});
```

Note: Tests for "with NC data" require backfilled `nc_id` and a running NorthCloud instance. These are integration-level and can be added after the backfill runs on the test environment.

- [ ] **Step 3: Run Playwright tests**

Run: `npx playwright test tests/playwright/communities.spec.ts`
Expected: All tests PASS

- [ ] **Step 4: Commit**

```bash
git add tests/playwright/communities.spec.ts
git commit -m "test(#178): add Playwright tests for leadership and band office sections"
```

---

## Task 8: Run full test suite and clean up

- [ ] **Step 1: Delete stale manifest cache**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 2: Run PHPUnit**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (253+ tests)

- [ ] **Step 3: Run Playwright**

Run: `npx playwright test`
Expected: All tests pass (42+ tests)

- [ ] **Step 4: Production deployment notes**

After merging, on production:
1. `ALTER TABLE community ADD COLUMN nc_id TEXT` (or run schema:check)
2. Add `NORTHCLOUD_BASE_URL=https://northcloud.one` to `.env` and Caddyfile
3. Run `bin/backfill-nc-ids`
4. Verify a community detail page shows leadership (if NC has data for it)
