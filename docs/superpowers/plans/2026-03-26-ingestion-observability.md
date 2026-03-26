# Ingestion Observability Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin page at `/admin/ingestion` that shows NorthCloud sync status and IngestLog entries with filtering.

**Architecture:** New controller + provider + Twig template following the CoordinatorDashboardController pattern. Data comes from querying `ingest_log` entities. Sync summary is derived from IngestLog aggregate counts (no JSON status file exists). CSS reuses existing dashboard card and badge patterns.

**Tech Stack:** PHP 8.4, Twig 3, Waaseyaa entity system, vanilla CSS

**Design spec:** `docs/superpowers/specs/2026-03-26-ingestion-observability-design.md`

---

### Task 1: Service Provider + Route Registration

**Files:**
- Create: `src/Provider/IngestionDashboardServiceProvider.php`
- Modify: `composer.json` (add provider to `extra.waaseyaa.providers[]`)

- [ ] **Step 1: Create the service provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\Provider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;

final class IngestionDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->route(
            'admin.ingestion',
            RouteBuilder::create('/admin/ingestion')
                ->controller('Minoo\Controller\IngestionDashboardController::index')
                ->requirePermission('administer content')
        );
    }
}
```

- [ ] **Step 2: Register the provider in composer.json**

Add `"Minoo\\Provider\\IngestionDashboardServiceProvider"` to `extra.waaseyaa.providers[]`.

- [ ] **Step 3: Clear manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 4: Commit**

```bash
git add src/Provider/IngestionDashboardServiceProvider.php composer.json
git commit -m "feat: register ingestion dashboard route and provider"
```

---

### Task 2: Controller

**Files:**
- Create: `src/Controller/IngestionDashboardController.php`

- [ ] **Step 1: Create the controller**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

final class IngestionDashboardController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, string> $params */
    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('ingest_log');

        // Status filter
        $validStatuses = ['pending_review', 'approved', 'rejected', 'failed'];
        $statusFilter = isset($query['status']) && in_array($query['status'], $validStatuses, true)
            ? $query['status']
            : null;

        // Load recent logs
        $logQuery = $storage->getQuery()->sort('created_at', 'DESC');
        if ($statusFilter !== null) {
            $logQuery->condition('status', $statusFilter);
        }
        $ids = $logQuery->range(0, 50)->execute();
        $logs = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        // Sync summary — aggregate counts from all ingest logs
        $allIds = $storage->getQuery()->execute();
        $totalCount = count($allIds);
        $statusCounts = ['pending_review' => 0, 'approved' => 0, 'rejected' => 0, 'failed' => 0];
        if ($allIds !== []) {
            $allLogs = $storage->loadMultiple($allIds);
            foreach ($allLogs as $log) {
                $status = $log->get('status') ?? 'pending_review';
                if (isset($statusCounts[$status])) {
                    $statusCounts[$status]++;
                }
            }
        }

        // Last sync timestamp — most recent ingest log created_at
        $latestIds = $storage->getQuery()->sort('created_at', 'DESC')->range(0, 1)->execute();
        $lastSync = null;
        if ($latestIds !== []) {
            $latest = $storage->load(reset($latestIds));
            if ($latest !== null) {
                $lastSync = $latest->get('created_at');
            }
        }

        $html = $this->twig->render('admin/ingestion.html.twig', [
            'logs' => $logs,
            'total_count' => $totalCount,
            'status_counts' => $statusCounts,
            'last_sync' => $lastSync,
            'status_filter' => $statusFilter,
            'hide_sidebar' => true,
        ]);

        return new SsrResponse(content: $html);
    }
}
```

- [ ] **Step 2: Verify it boots without errors**

Run: `php -S localhost:8081 -t public` and visit `http://localhost:8081/admin/ingestion` (will 500 until template exists — that's expected).

- [ ] **Step 3: Commit**

```bash
git add src/Controller/IngestionDashboardController.php
git commit -m "feat: ingestion dashboard controller with log loading and summary"
```

---

### Task 3: Twig Template

**Files:**
- Create: `templates/admin/ingestion.html.twig`

- [ ] **Step 1: Create the template**

```twig
{% extends "base.html.twig" %}

{% block title %}Ingestion Dashboard — Minoo{% endblock %}

{% block content %}
<section class="content-section flow-lg">
  <h1 class="section-title">Ingestion Dashboard</h1>

  {# ── Sync Summary Cards ── #}
  <div class="ingest-summary">
    <div class="ingest-summary__card">
      <span class="ingest-summary__label">Last Sync</span>
      <span class="ingest-summary__value">{{ last_sync ? last_sync : 'No sync data' }}</span>
    </div>
    <div class="ingest-summary__card">
      <span class="ingest-summary__label">Total Logs</span>
      <span class="ingest-summary__value">{{ total_count }}</span>
    </div>
    <div class="ingest-summary__card ingest-summary__card--approved">
      <span class="ingest-summary__label">Approved</span>
      <span class="ingest-summary__value">{{ status_counts.approved }}</span>
    </div>
    <div class="ingest-summary__card ingest-summary__card--pending">
      <span class="ingest-summary__label">Pending Review</span>
      <span class="ingest-summary__value">{{ status_counts.pending_review }}</span>
    </div>
    <div class="ingest-summary__card ingest-summary__card--failed">
      <span class="ingest-summary__label">Failed</span>
      <span class="ingest-summary__value">{{ status_counts.failed }}</span>
    </div>
    <div class="ingest-summary__card ingest-summary__card--rejected">
      <span class="ingest-summary__label">Rejected</span>
      <span class="ingest-summary__value">{{ status_counts.rejected }}</span>
    </div>
  </div>

  {# ── Status Filter ── #}
  <nav class="ingest-filters" aria-label="Filter by status">
    <a href="/admin/ingestion" class="feed-chip{% if not status_filter %} feed-chip--active{% endif %}">All</a>
    <a href="/admin/ingestion?status=pending_review" class="feed-chip{% if status_filter == 'pending_review' %} feed-chip--active{% endif %}">Pending</a>
    <a href="/admin/ingestion?status=approved" class="feed-chip{% if status_filter == 'approved' %} feed-chip--active{% endif %}">Approved</a>
    <a href="/admin/ingestion?status=failed" class="feed-chip{% if status_filter == 'failed' %} feed-chip--active{% endif %}">Failed</a>
    <a href="/admin/ingestion?status=rejected" class="feed-chip{% if status_filter == 'rejected' %} feed-chip--active{% endif %}">Rejected</a>
  </nav>

  {# ── Ingest Log Table ── #}
  {% if logs|length > 0 %}
    <div class="ingest-table-wrap">
      <table class="ingest-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Source</th>
            <th>Target Type</th>
            <th>Title</th>
            <th>Created</th>
            <th>Error</th>
          </tr>
        </thead>
        <tbody>
          {% for log in logs %}
            <tr>
              <td colspan="6">
                <details class="ingest-row">
                  <summary class="ingest-row__summary">
                    <span class="ingest-badge ingest-badge--{{ log.get('status') }}">{{ log.get('status')|replace({'_': ' '}) }}</span>
                    <span class="ingest-row__cell">{{ log.get('source')|default('—') }}</span>
                    <span class="ingest-row__cell">{{ log.get('entity_type_target')|default('—') }}</span>
                    <span class="ingest-row__cell ingest-row__cell--title">{{ log.label()|default('—') }}</span>
                    <span class="ingest-row__cell ingest-row__cell--date">{{ log.get('created_at')|default('—') }}</span>
                    <span class="ingest-row__cell ingest-row__cell--error">{{ log.get('error_message')|default('')|slice(0, 80) }}{% if log.get('error_message')|default('')|length > 80 %}…{% endif %}</span>
                  </summary>
                  <div class="ingest-row__detail">
                    {% if log.get('error_message') %}
                      <div class="ingest-row__section">
                        <h4>Error</h4>
                        <pre>{{ log.get('error_message') }}</pre>
                      </div>
                    {% endif %}
                    {% if log.get('payload_raw') %}
                      <div class="ingest-row__section">
                        <h4>Raw Payload</h4>
                        <pre>{{ log.get('payload_raw') }}</pre>
                      </div>
                    {% endif %}
                    {% if log.get('payload_parsed') %}
                      <div class="ingest-row__section">
                        <h4>Parsed Payload</h4>
                        <pre>{{ log.get('payload_parsed') }}</pre>
                      </div>
                    {% endif %}
                  </div>
                </details>
              </td>
            </tr>
          {% endfor %}
        </tbody>
      </table>
    </div>
  {% else %}
    <p class="dashboard__empty">No ingest logs found{% if status_filter %} with status "{{ status_filter }}"{% endif %}.</p>
  {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 2: Verify the page renders**

Visit `http://localhost:8081/admin/ingestion` — should render with empty state or data from production DB copy.

- [ ] **Step 3: Commit**

```bash
git add templates/admin/ingestion.html.twig
git commit -m "feat: ingestion dashboard template with summary, filters, and log table"
```

---

### Task 4: CSS Styles

**Files:**
- Modify: `public/css/minoo.css` (add ingestion dashboard styles in `@layer components`)
- Modify: `templates/base.html.twig` (bump cache version)

- [ ] **Step 1: Add ingestion dashboard styles**

Add the following at the end of the `@layer components` section in `public/css/minoo.css`:

```css
/* ══════════════════════════════════════════════════════════
   Ingestion Dashboard
   ══════════════════════════════════════════════════════════ */

.ingest-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: var(--space-xs);
}

.ingest-summary__card {
  background-color: var(--surface-card);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-md);
  padding: var(--space-sm);
  display: flex;
  flex-direction: column;
  gap: var(--space-3xs);
}

.ingest-summary__label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
}

.ingest-summary__value {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--text-primary);
}

.ingest-summary__card--approved { border-inline-start: 3px solid var(--color-language); }
.ingest-summary__card--pending { border-inline-start: 3px solid var(--color-teachings); }
.ingest-summary__card--failed { border-inline-start: 3px solid var(--color-events); }
.ingest-summary__card--rejected { border-inline-start: 3px solid var(--text-muted); }

.ingest-filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3xs);
}

.ingest-table-wrap {
  overflow-x: auto;
}

.ingest-table {
  inline-size: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.ingest-table thead {
  display: none;
}

.ingest-table td {
  padding: 0;
  border-block-end: 1px solid var(--border-subtle);
}

.ingest-row summary {
  display: grid;
  grid-template-columns: 6.5rem 1fr 1fr 2fr 8rem 2fr;
  gap: var(--space-xs);
  align-items: center;
  padding: var(--space-xs) var(--space-2xs);
  cursor: pointer;
  list-style: none;
}

.ingest-row summary::-webkit-details-marker { display: none; }

.ingest-row summary:hover {
  background-color: var(--surface-raised);
}

.ingest-row__cell {
  color: var(--text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ingest-row__cell--title {
  color: var(--text-primary);
  font-weight: 500;
}

.ingest-row__cell--date {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.ingest-row__cell--error {
  font-size: 0.8rem;
  color: var(--color-events);
}

.ingest-row__detail {
  padding: var(--space-sm);
  background-color: var(--surface-raised);
  border-radius: 0 0 var(--radius-sm) var(--radius-sm);
}

.ingest-row__section {
  margin-block-end: var(--space-sm);
}

.ingest-row__section:last-child {
  margin-block-end: 0;
}

.ingest-row__section h4 {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-block-end: var(--space-3xs);
}

.ingest-row__section pre {
  font-family: var(--font-mono);
  font-size: 0.8rem;
  color: var(--text-secondary);
  white-space: pre-wrap;
  word-break: break-all;
  background-color: var(--surface-card);
  padding: var(--space-xs);
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-subtle);
  max-block-size: 300px;
  overflow-y: auto;
}

.ingest-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.ingest-badge--approved { background-color: rgba(42, 157, 143, 0.2); color: var(--color-language); }
.ingest-badge--pending_review { background-color: rgba(244, 162, 97, 0.2); color: var(--color-teachings); }
.ingest-badge--failed { background-color: rgba(230, 57, 70, 0.2); color: var(--color-events); }
.ingest-badge--rejected { background-color: rgba(119, 119, 119, 0.2); color: var(--text-muted); }

@media (max-width: 768px) {
  .ingest-row summary {
    grid-template-columns: 1fr;
    gap: var(--space-3xs);
  }
  .ingest-row__cell--error,
  .ingest-row__cell--date {
    display: none;
  }
}
```

- [ ] **Step 2: Bump CSS cache version in base.html.twig**

Change `minoo.css?v=32` to `minoo.css?v=33`.

- [ ] **Step 3: Verify the page looks correct**

Visit `http://localhost:8081/admin/ingestion` and check summary cards, filter chips, table rows, expandable details.

- [ ] **Step 4: Commit**

```bash
git add public/css/minoo.css templates/base.html.twig
git commit -m "feat: ingestion dashboard CSS — summary cards, table, badges, responsive"
```

---

### Task 5: Unit Test

**Files:**
- Create: `tests/Minoo/Unit/Controller/IngestionDashboardControllerTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Minoo\Controller\IngestionDashboardController;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Entity\Query\EntityQuery;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;

#[CoversClass(IngestionDashboardController::class)]
final class IngestionDashboardControllerTest extends TestCase
{
    #[Test]
    public function index_renders_with_empty_logs(): void
    {
        $query = $this->createMock(EntityQuery::class);
        $query->method('sort')->willReturn($query);
        $query->method('condition')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->willReturn([]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'admin/ingestion.html.twig',
                $this->callback(function (array $vars): bool {
                    return $vars['logs'] === []
                        && $vars['total_count'] === 0
                        && $vars['last_sync'] === null
                        && $vars['status_filter'] === null
                        && $vars['hide_sidebar'] === true;
                })
            )
            ->willReturn('<html>ok</html>');

        $controller = new IngestionDashboardController($etm, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest();

        $response = $controller->index([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function index_filters_by_status(): void
    {
        $query = $this->createMock(EntityQuery::class);
        $query->method('sort')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn([]);
        $query->expects($this->atLeastOnce())
            ->method('condition')
            ->willReturn($query);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->willReturn([]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'admin/ingestion.html.twig',
                $this->callback(fn(array $vars) => $vars['status_filter'] === 'failed')
            )
            ->willReturn('<html>ok</html>');

        $controller = new IngestionDashboardController($etm, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest();

        $response = $controller->index([], ['status' => 'failed'], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function index_rejects_invalid_status_filter(): void
    {
        $query = $this->createMock(EntityQuery::class);
        $query->method('sort')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->willReturn([]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'admin/ingestion.html.twig',
                $this->callback(fn(array $vars) => $vars['status_filter'] === null)
            )
            ->willReturn('<html>ok</html>');

        $controller = new IngestionDashboardController($etm, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest();

        $response = $controller->index([], ['status' => 'evil_injection'], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/IngestionDashboardControllerTest.php`
Expected: 3 tests, all passing.

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit --testsuite MinooUnit`
Expected: All passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Minoo/Unit/Controller/IngestionDashboardControllerTest.php
git commit -m "test: ingestion dashboard controller — empty state, filter, invalid filter"
```

---

### Task 6: Final Verification & Deploy

- [ ] **Step 1: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Controller/IngestionDashboardController.php src/Provider/IngestionDashboardServiceProvider.php --no-progress`
Expected: No errors. If baseline needs updating, regenerate with `./vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`.

- [ ] **Step 2: Optimize manifest**

Run: `php bin/waaseyaa optimize:manifest`

- [ ] **Step 3: Visual verification with production data**

Visit `http://localhost:8081/admin/ingestion` with the production DB copy. Verify:
- Summary cards show correct counts
- Filter chips work
- Log table rows expand with payload details
- Empty state shows when filtering to a status with no logs

- [ ] **Step 4: Push and watch CI**

```bash
git push origin main
gh run watch $(gh run list --limit 1 --json databaseId -q '.[0].databaseId') --exit-status
```
