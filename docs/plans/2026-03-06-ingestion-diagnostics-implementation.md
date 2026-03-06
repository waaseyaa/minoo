# Ingestion Diagnostics & Admin Review Queue — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an `ingest_log` entity type with access policy and service provider, plus a dashboard summary widget in the admin SPA.

**Architecture:** New Minoo entity type (`ingest_log`) follows the same pattern as the existing 12 entity types. A Vue widget on the admin dashboard aggregates ingest_log status counts via the existing JSON:API entity list endpoint.

**Tech Stack:** PHP 8.3+ (Waaseyaa entity system), Vue 3 + Nuxt (admin SPA), PHPUnit 10.5, Playwright MCP

---

### Task 1: IngestLog Entity Class

**Files:**
- Create: `src/Entity/IngestLog.php`
- Test: `tests/Minoo/Unit/Entity/IngestLogTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\IngestLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestLog::class)]
final class IngestLogTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $log = new IngestLog([
            'title' => 'northcloud — 2026-03-06 12:00:00',
            'source' => 'northcloud',
            'entity_type_target' => 'dictionary_entry',
            'payload_raw' => '{"word":"makwa"}',
            'payload_parsed' => '{"word":"makwa","definition":"bear"}',
        ]);

        $this->assertSame('northcloud — 2026-03-06 12:00:00', $log->get('title'));
        $this->assertSame('northcloud', $log->get('source'));
        $this->assertSame('pending_review', $log->get('status'));
        $this->assertSame(0, $log->get('created_at'));
        $this->assertSame(0, $log->get('updated_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $log = new IngestLog(['title' => 'test', 'source' => 'test', 'entity_type_target' => 'node', 'payload_raw' => '{}', 'payload_parsed' => '{}']);

        $this->assertSame('ingest_log', $log->getEntityTypeId());
    }

    #[Test]
    public function it_supports_review_fields(): void
    {
        $log = new IngestLog([
            'title' => 'test',
            'source' => 'ojibwe_lib',
            'entity_type_target' => 'dictionary_entry',
            'payload_raw' => '{}',
            'payload_parsed' => '{}',
            'status' => 'approved',
            'entity_id' => 42,
            'reviewed_by' => 1,
            'reviewed_at' => 1709740800,
        ]);

        $this->assertSame('approved', $log->get('status'));
        $this->assertSame(42, $log->get('entity_id'));
        $this->assertSame(1, $log->get('reviewed_by'));
        $this->assertSame(1709740800, $log->get('reviewed_at'));
    }

    #[Test]
    public function it_supports_error_message(): void
    {
        $log = new IngestLog([
            'title' => 'test',
            'source' => 'northcloud',
            'entity_type_target' => 'node',
            'payload_raw' => '{}',
            'payload_parsed' => '{}',
            'status' => 'failed',
            'error_message' => 'Connection refused',
        ]);

        $this->assertSame('failed', $log->get('status'));
        $this->assertSame('Connection refused', $log->get('error_message'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/IngestLogTest.php`
Expected: FAIL — class `Minoo\Entity\IngestLog` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class IngestLog extends ContentEntityBase
{
    protected string $entityTypeId = 'ingest_log';

    protected array $entityKeys = [
        'id' => 'ilid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 'pending_review';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/IngestLogTest.php`
Expected: OK (4 tests)

**Step 5: Commit**

```bash
git add src/Entity/IngestLog.php tests/Minoo/Unit/Entity/IngestLogTest.php
git commit -m "feat: add IngestLog entity class with tests"
```

---

### Task 2: IngestAccessPolicy

**Files:**
- Create: `src/Access/IngestAccessPolicy.php`
- Test: `tests/Minoo/Unit/Access/IngestAccessPolicyTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\IngestAccessPolicy;
use Minoo\Entity\IngestLog;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestAccessPolicy::class)]
final class IngestAccessPolicyTest extends TestCase
{
    #[Test]
    public function admin_can_view_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $log = new IngestLog(['title' => 'test', 'source' => 'northcloud', 'entity_type_target' => 'node', 'payload_raw' => '{}', 'payload_parsed' => '{}']);
        $account = $this->createAdminAccount();

        $result = $policy->access($log, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $log = new IngestLog(['title' => 'test', 'source' => 'northcloud', 'entity_type_target' => 'node', 'payload_raw' => '{}', 'payload_parsed' => '{}']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($log, 'view', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_create_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $account = $this->createAdminAccount();

        $result = $policy->createAccess('ingest_log', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('ingest_log', '', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function applies_to_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();

        $this->assertTrue($policy->appliesTo('ingest_log'));
        $this->assertFalse($policy->appliesTo('node'));
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    private function createAdminAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['administrator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/IngestAccessPolicyTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'ingest_log')]
final class IngestAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'ingest_log';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => $account->hasPermission('review ingestion')
                ? AccessResult::allowed('User has review ingestion permission.')
                : AccessResult::neutral('Cannot view ingestion logs.'),
            default => AccessResult::neutral('Non-admin cannot modify ingestion logs.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create ingestion logs.');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/IngestAccessPolicyTest.php`
Expected: OK (5 tests)

**Step 5: Commit**

```bash
git add src/Access/IngestAccessPolicy.php tests/Minoo/Unit/Access/IngestAccessPolicyTest.php
git commit -m "feat: add IngestAccessPolicy with tests"
```

---

### Task 3: IngestServiceProvider

**Files:**
- Create: `src/Provider/IngestServiceProvider.php`
- Test: `tests/Minoo/Unit/Provider/IngestServiceProviderTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Provider;

use Minoo\Provider\IngestServiceProvider;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestServiceProvider::class)]
final class IngestServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_ingest_log_entity_type(): void
    {
        $manager = new EntityTypeManager();
        $provider = new IngestServiceProvider($manager);
        $provider->register();

        $definition = $manager->getDefinition('ingest_log');

        $this->assertNotNull($definition);
        $this->assertSame('Ingestion Log', $definition->getLabel());
        $this->assertSame('ingestion', $definition->getGroup());
        $this->assertSame('ilid', $definition->getKeys()['id']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Provider/IngestServiceProviderTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\IngestLog;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class IngestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'ingest_log',
            label: 'Ingestion Log',
            class: IngestLog::class,
            keys: ['id' => 'ilid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'ingestion',
            fieldDefinitions: [
                'status' => [
                    'type' => 'string',
                    'label' => 'Status',
                    'description' => 'pending_review, approved, rejected, or failed.',
                    'weight' => 1,
                    'default' => 'pending_review',
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Origin identifier (e.g. northcloud, ojibwe_lib).',
                    'weight' => 2,
                ],
                'entity_type_target' => [
                    'type' => 'string',
                    'label' => 'Target Entity Type',
                    'description' => 'Entity type machine name for the parsed content.',
                    'weight' => 3,
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'label' => 'Created Entity ID',
                    'description' => 'ID of the entity created after approval.',
                    'weight' => 4,
                ],
                'payload_raw' => [
                    'type' => 'text',
                    'label' => 'Raw Payload',
                    'description' => 'Original payload JSON from source.',
                    'weight' => 10,
                ],
                'payload_parsed' => [
                    'type' => 'text',
                    'label' => 'Parsed Payload',
                    'description' => 'Mapped/transformed fields JSON.',
                    'weight' => 11,
                ],
                'error_message' => [
                    'type' => 'text',
                    'label' => 'Error Message',
                    'description' => 'Error details if status is failed.',
                    'weight' => 12,
                ],
                'reviewed_by' => [
                    'type' => 'entity_reference',
                    'label' => 'Reviewed By',
                    'settings' => ['target_type' => 'user'],
                    'weight' => 20,
                ],
                'reviewed_at' => [
                    'type' => 'timestamp',
                    'label' => 'Reviewed At',
                    'weight' => 21,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Provider/IngestServiceProviderTest.php`
Expected: OK (1 test)

**Step 5: Register provider in composer.json**

Check `composer.json` for the `extra.waaseyaa.providers` array and add `Minoo\\Provider\\IngestServiceProvider`.

**Step 6: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass. Delete `storage/framework/packages.php` if new provider isn't discovered.

**Step 7: Commit**

```bash
git add src/Provider/IngestServiceProvider.php tests/Minoo/Unit/Provider/IngestServiceProviderTest.php composer.json
git commit -m "feat: add IngestServiceProvider with ingest_log entity type"
```

---

### Task 4: Update Integration Test

**Files:**
- Modify: `tests/Minoo/Integration/BootTest.php`

**Step 1: Add `ingest_log` to the entity type list**

In `kernel_boots_with_all_minoo_entity_types()`, update the comment and array:

```php
        // All 13 Minoo entity types from app service providers.
        $minooTypes = [
            'event', 'event_type',
            'group', 'group_type',
            'cultural_group',
            'teaching', 'teaching_type',
            'cultural_collection',
            'dictionary_entry', 'example_sentence', 'word_part', 'speaker',
            'ingest_log',
        ];
```

**Step 2: Run integration tests**

Run: `./vendor/bin/phpunit --testsuite MinooIntegration`
Expected: All pass — kernel discovers `ingest_log` and `IngestAccessPolicy`.

**Step 3: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Minoo/Integration/BootTest.php
git commit -m "test: add ingest_log to integration boot test"
```

---

### Task 5: Admin i18n — Ingestion Group and Widget Labels

**Files:**
- Modify: `/home/jones/dev/waaseyaa/packages/admin/app/i18n/en.json`
- Modify: `/home/jones/dev/waaseyaa/packages/admin/app/i18n/fr.json`

**Step 1: Add translation keys to en.json**

Add before `"nav_group_other"`:

```json
  "nav_group_ingestion": "Ingestion",
  "ingest_widget_title": "Ingestion Status",
  "ingest_widget_empty": "No ingestion activity yet.",
  "ingest_status_pending_review": "Pending Review",
  "ingest_status_approved": "Approved",
  "ingest_status_rejected": "Rejected",
  "ingest_status_failed": "Failed",
```

**Step 2: Add translation keys to fr.json**

Add before `"nav_group_other"`:

```json
  "nav_group_ingestion": "Ingestion",
  "ingest_widget_title": "État de l'ingestion",
  "ingest_widget_empty": "Aucune activité d'ingestion.",
  "ingest_status_pending_review": "En attente de révision",
  "ingest_status_approved": "Approuvé",
  "ingest_status_rejected": "Rejeté",
  "ingest_status_failed": "Échoué",
```

**Step 3: Commit (in waaseyaa repo)**

```bash
cd /home/jones/dev/waaseyaa
git add packages/admin/app/i18n/en.json packages/admin/app/i18n/fr.json
git commit -m "feat: add ingestion i18n keys for admin sidebar and widget"
```

---

### Task 6: Dashboard Ingestion Summary Widget

**Files:**
- Create: `/home/jones/dev/waaseyaa/packages/admin/app/components/IngestSummaryWidget.vue`
- Modify: `/home/jones/dev/waaseyaa/packages/admin/app/pages/index.vue`

**Step 1: Create the widget component**

```vue
<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { useEntity, type JsonApiResource } from '~/composables/useEntity'

const { t } = useLanguage()
const { list } = useEntity()

const counts = ref<Record<string, number>>({
  pending_review: 0,
  approved: 0,
  rejected: 0,
  failed: 0,
})
const total = computed(() => Object.values(counts.value).reduce((a, b) => a + b, 0))
const loading = ref(true)
const error = ref(false)

async function fetchCounts() {
  try {
    const result = await list('ingest_log', { page: { limit: 1000 } })
    const fresh: Record<string, number> = {
      pending_review: 0,
      approved: 0,
      rejected: 0,
      failed: 0,
    }
    for (const item of result.data) {
      const status = item.attributes.status as string
      if (status in fresh) {
        fresh[status]++
      }
    }
    counts.value = fresh
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(fetchCounts)
</script>

<template>
  <div class="ingest-widget">
    <h2 class="ingest-widget-title">{{ t('ingest_widget_title') }}</h2>

    <div v-if="loading" class="ingest-widget-loading">{{ t('loading') }}</div>
    <div v-else-if="error" class="ingest-widget-error">{{ t('error_generic') }}</div>

    <template v-else>
      <div v-if="total === 0" class="ingest-widget-empty">
        {{ t('ingest_widget_empty') }}
      </div>
      <div v-else class="ingest-widget-counters">
        <NuxtLink
          v-for="status in ['pending_review', 'approved', 'rejected', 'failed']"
          :key="status"
          :to="`/ingest_log?filter[status]=${status}`"
          class="ingest-counter"
          :class="`ingest-counter--${status}`"
        >
          <span class="ingest-counter-value">{{ counts[status] }}</span>
          <span class="ingest-counter-label">{{ t(`ingest_status_${status}`) }}</span>
        </NuxtLink>
      </div>
    </template>
  </div>
</template>

<style scoped>
.ingest-widget {
  margin-bottom: 24px;
  padding: 20px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
}
.ingest-widget-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 12px;
}
.ingest-widget-empty {
  font-size: 14px;
  color: var(--color-muted);
}
.ingest-widget-counters {
  display: flex;
  gap: 12px;
}
.ingest-counter {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px;
  border-radius: 6px;
  text-decoration: none;
  color: var(--color-text);
  background: var(--color-bg);
  transition: border-color 0.15s;
  border: 1px solid transparent;
}
.ingest-counter:hover { border-color: var(--color-primary); }
.ingest-counter-value {
  font-size: 24px;
  font-weight: 700;
}
.ingest-counter-label {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 4px;
}
.ingest-counter--failed .ingest-counter-value { color: var(--color-danger, #c00); }
.ingest-counter--pending_review .ingest-counter-value { color: var(--color-warning, #b86e00); }
.ingest-counter--approved .ingest-counter-value { color: var(--color-success, #080); }
.ingest-widget-loading,
.ingest-widget-error {
  font-size: 13px;
  color: var(--color-muted);
}
</style>
```

**Step 2: Add the widget to the dashboard page**

In `pages/index.vue`, import and place the widget above the card grid:

Replace the `<template>` section:

```vue
<template>
  <div>
    <div class="page-header">
      <h1>{{ t('dashboard') }}</h1>
    </div>

    <IngestSummaryWidget />

    <div v-if="loading" class="loading">{{ t('loading') }}</div>
    <div v-else-if="loadError" class="error">{{ loadError }}</div>

    <div v-else class="card-grid">
      <NuxtLink
        v-for="et in entityTypes"
        :key="et.id"
        :to="`/${et.id}`"
        class="card"
      >
        <h2 class="card-title">{{ et.label }}</h2>
        <p class="card-sub">{{ et.id }}</p>
      </NuxtLink>
    </div>
  </div>
</template>
```

Nuxt auto-imports components from `components/`, so no explicit import needed.

**Step 3: Commit (in waaseyaa repo)**

```bash
cd /home/jones/dev/waaseyaa
git add packages/admin/app/components/IngestSummaryWidget.vue packages/admin/app/pages/index.vue
git commit -m "feat: add ingestion summary widget to admin dashboard"
```

---

### Task 7: Update CLAUDE.md

**Files:**
- Modify: `CLAUDE.md`

**Step 1: Update entity domain table**

Add the Ingestion domain row:

```
| Ingestion | `ingest_log` | `IngestServiceProvider` | `IngestAccessPolicy` |
```

Update entity count from 12 to 13 where referenced.

**Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md with ingest_log entity type"
```

---

### Task 8: Playwright Verification

**No files to create** — use Playwright MCP tools interactively.

**Step 1: Start servers**

```bash
rm -f storage/framework/packages.php
php -S localhost:8081 -t public &
cd /home/jones/dev/waaseyaa/packages/admin && npm run dev &
```

**Step 2: Verify sidebar grouping**

Navigate to `http://localhost:3000`. Confirm sidebar shows "Ingestion" group with "Ingestion Log" underneath.

**Step 3: Verify dashboard widget**

On the dashboard page, confirm the IngestSummaryWidget renders with "No ingestion activity yet." message (zero state).

**Step 4: Verify ingest_log list page**

Navigate to `/ingest_log`. Confirm the entity list page loads (empty).

**Step 5: Verify create form**

Navigate to `/ingest_log/create`. Confirm the create form renders with all expected fields.

**Step 6: Take screenshot**

Capture screenshot for issue documentation.

**Step 7: Stop servers and commit any fixes**

---

### Task 9: Final PR

**Step 1: Push minoo branch and create PR**

```bash
git push origin feat/sidebar-grouping
gh pr create --title "feat: add ingestion diagnostics and admin review queue" --body "..."
```

Reference: Closes #4. Depends on waaseyaa/framework PR for i18n and widget.

**Step 2: Push waaseyaa changes and create/update PR**

Commit the i18n and widget changes to the existing framework branch or a new one.
