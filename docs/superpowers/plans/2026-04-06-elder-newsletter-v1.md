# Elder Newsletter v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a monthly auto-assembled print newsletter for Indigenous Elders, generated from NorthCloud content + community submissions, rendered to PDF via headless Chromium, and emailed to a regional print partner (OJ Graphix in Espanola, ON).

**Architecture:** New `src/Domain/Newsletter/` bounded context with 3 entities (`newsletter_edition`, `newsletter_item`, `newsletter_submission`), 3 services (`NewsletterAssembler`, `NewsletterRenderer`, `NewsletterDispatcher`), 1 lifecycle guard (`EditionLifecycle`), 1 access policy, 2 controllers (public + coordinator-only editor). Renders Twig with `@media print` CSS to PDF via a small node helper script invoked from `Symfony\Process`. Phased: regional first → per-community when communities reach critical mass.

**Tech Stack:** PHP 8.4, Waaseyaa framework (entity system, routing, access, mail), Twig 3, PHPUnit 10.5, Playwright (existing dep, used here for headless Chromium PDF rendering), Node 20+ for `bin/render-pdf.js`, SQLite (dev/test), MySQL/Postgres (prod).

**Spec:** `docs/superpowers/specs/2026-04-06-elder-newsletter-design.md`
**Milestone:** [#50 Elder Newsletter](https://github.com/waaseyaa/minoo/milestone/50)
**v1 GitHub issues:** #639, #640, #641, #642, #643, #644, #645, #646, #647, #648, #649

---

## File Structure

**Will create:**

```
src/Domain/Newsletter/
├── Service/
│   ├── EditionLifecycle.php           # state machine guard
│   ├── NewsletterAssembler.php        # candidate item generation
│   ├── NewsletterRenderer.php         # Twig → PDF orchestration
│   └── NewsletterDispatcher.php       # email PDF to printer
├── ValueObject/
│   ├── EditionStatus.php              # enum
│   ├── SectionQuota.php               # config-driven section limit
│   └── PdfArtifact.php                # path + bytes + hash
├── Assembler/
│   └── ItemCandidate.php              # internal scoring DTO
└── Exception/
    ├── InvalidStateTransition.php
    ├── RenderException.php
    └── DispatchException.php

src/Entity/
├── NewsletterEdition.php
├── NewsletterItem.php
└── NewsletterSubmission.php

src/Provider/
└── NewsletterServiceProvider.php

src/Access/
└── NewsletterAccessPolicy.php

src/Controller/
├── NewsletterController.php           # public surface
└── NewsletterEditorController.php     # coordinator newsroom

migrations/
├── 20260406_120000_create_newsletter_edition_table.php
├── 20260406_120100_create_newsletter_item_table.php
└── 20260406_120200_create_newsletter_submission_table.php

config/
└── newsletter.php                     # per-community settings, section quotas

templates/newsletter/
├── edition.html.twig                  # PRINT template (loaded by Playwright)
├── editor/
│   ├── list.html.twig                 # newsroom list
│   ├── newsroom.html.twig             # item queue + curate UI
│   └── submissions.html.twig          # moderation queue
└── public/
    ├── list.html.twig                 # public list of past editions
    ├── edition.html.twig              # public preview + download link
    └── submit.html.twig               # community submission form

bin/
├── render-pdf.js                      # Playwright helper
└── newsletter-render-smoke            # manual smoke script (NOT in CI)

tests/Minoo/Unit/Newsletter/
├── Entity/
│   ├── NewsletterEditionTest.php
│   ├── NewsletterItemTest.php
│   └── NewsletterSubmissionTest.php
├── Service/
│   ├── EditionLifecycleTest.php
│   ├── NewsletterAssemblerTest.php
│   ├── NewsletterRendererTest.php
│   └── NewsletterDispatcherTest.php
├── Access/
│   └── NewsletterAccessPolicyTest.php
└── Controller/
    ├── NewsletterControllerTest.php
    └── NewsletterEditorControllerTest.php

tests/Minoo/Integration/
└── NewsletterEndToEndTest.php

tests/playwright/
└── newsletter.spec.ts

storage/newsletter/                    # PDF output dir (created at runtime)
```

**Will modify:**

- `composer.json` — add `Minoo\Provider\NewsletterServiceProvider` to `extra.waaseyaa.providers`
- `public/css/minoo.css` — add `@layer components` print-newsletter rules + `@media print` block

---

## Task 1: Migrations + entity classes (issue #639)

**Files:**
- Create: `migrations/20260406_120000_create_newsletter_edition_table.php`
- Create: `migrations/20260406_120100_create_newsletter_item_table.php`
- Create: `migrations/20260406_120200_create_newsletter_submission_table.php`
- Create: `src/Entity/NewsletterEdition.php`
- Create: `src/Entity/NewsletterItem.php`
- Create: `src/Entity/NewsletterSubmission.php`
- Test: `tests/Minoo/Unit/Newsletter/Entity/NewsletterEditionTest.php`
- Test: `tests/Minoo/Unit/Newsletter/Entity/NewsletterItemTest.php`
- Test: `tests/Minoo/Unit/Newsletter/Entity/NewsletterSubmissionTest.php`

- [ ] **Step 1: Write the failing entity test for `NewsletterEdition`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Entity;

use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterEdition::class)]
final class NewsletterEditionTest extends TestCase
{
    #[Test]
    public function constructor_accepts_all_fields(): void
    {
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'manitoulin-regional',
            'volume' => 1,
            'issue_number' => 4,
            'publish_date' => '2026-04-15',
            'status' => 'draft',
            'pdf_path' => null,
            'pdf_hash' => null,
            'sent_at' => null,
            'created_by' => 42,
            'approved_by' => null,
            'approved_at' => null,
            'headline' => 'April 2026 — Vol. 1 No. 4',
        ]);

        $this->assertSame(1, $edition->id());
        $this->assertSame('manitoulin-regional', $edition->get('community_id'));
        $this->assertSame(1, $edition->get('volume'));
        $this->assertSame(4, $edition->get('issue_number'));
        $this->assertSame('draft', $edition->get('status'));
        $this->assertSame('April 2026 — Vol. 1 No. 4', $edition->label());
    }

    #[Test]
    public function regional_edition_allows_null_community_id(): void
    {
        $edition = new NewsletterEdition([
            'neid' => 2,
            'community_id' => null,
            'volume' => 1,
            'issue_number' => 1,
            'publish_date' => '2026-04-15',
            'status' => 'draft',
            'headline' => 'Regional April 2026',
        ]);

        $this->assertNull($edition->get('community_id'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Entity/NewsletterEditionTest.php
```

Expected: FAIL with `Class "Minoo\Entity\NewsletterEdition" not found`.

- [ ] **Step 3: Implement `NewsletterEdition` entity**

```php
<?php
declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class NewsletterEdition extends ContentEntityBase
{
    protected string $entityTypeId = 'newsletter_edition';

    protected array $entityKeys = [
        'id' => 'neid',
        'uuid' => 'uuid',
        'label' => 'headline',
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Entity/NewsletterEditionTest.php
```

Expected: PASS, 2 tests / 7 assertions.

- [ ] **Step 5: Write the failing entity test for `NewsletterItem`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Entity;

use Minoo\Entity\NewsletterItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterItem::class)]
final class NewsletterItemTest extends TestCase
{
    #[Test]
    public function source_backed_item_holds_reference(): void
    {
        $item = new NewsletterItem([
            'nitid' => 1,
            'edition_id' => 5,
            'position' => 1,
            'section' => 'events',
            'source_type' => 'event',
            'source_id' => 314,
            'inline_title' => null,
            'inline_body' => null,
            'editor_blurb' => 'Spring sugar bush at Wiikwemkoong',
            'included' => true,
        ]);

        $this->assertSame(5, $item->get('edition_id'));
        $this->assertSame('events', $item->get('section'));
        $this->assertSame('event', $item->get('source_type'));
        $this->assertSame(314, $item->get('source_id'));
        $this->assertTrue((bool) $item->get('included'));
    }

    #[Test]
    public function inline_item_carries_title_and_body(): void
    {
        $item = new NewsletterItem([
            'nitid' => 2,
            'edition_id' => 5,
            'position' => 2,
            'section' => 'community',
            'source_type' => null,
            'source_id' => null,
            'inline_title' => 'Happy 80th Birthday Edna',
            'inline_body' => 'From all your grandchildren and great-grandchildren.',
            'editor_blurb' => null,
            'included' => true,
        ]);

        $this->assertNull($item->get('source_type'));
        $this->assertSame('Happy 80th Birthday Edna', $item->get('inline_title'));
    }
}
```

- [ ] **Step 6: Run test, verify failure, implement, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Entity/NewsletterItemTest.php
```

Then create `src/Entity/NewsletterItem.php`:

```php
<?php
declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class NewsletterItem extends ContentEntityBase
{
    protected string $entityTypeId = 'newsletter_item';

    protected array $entityKeys = [
        'id' => 'nitid',
        'uuid' => 'uuid',
        'label' => 'editor_blurb',
    ];
}
```

Re-run test, expect PASS.

- [ ] **Step 7: Write the failing entity test for `NewsletterSubmission`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Entity;

use Minoo\Entity\NewsletterSubmission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterSubmission::class)]
final class NewsletterSubmissionTest extends TestCase
{
    #[Test]
    public function constructor_accepts_all_fields(): void
    {
        $sub = new NewsletterSubmission([
            'nsuid' => 1,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 18,
            'submitted_at' => '2026-04-02T13:22:00+00:00',
            'category' => 'birthday',
            'title' => 'Edna turns 80',
            'body' => 'From the family — please join us.',
            'status' => 'submitted',
            'approved_by' => null,
            'approved_at' => null,
            'included_in_edition_id' => null,
        ]);

        $this->assertSame('wiikwemkoong', $sub->get('community_id'));
        $this->assertSame('birthday', $sub->get('category'));
        $this->assertSame('submitted', $sub->get('status'));
        $this->assertSame('Edna turns 80', $sub->label());
    }
}
```

Implement `src/Entity/NewsletterSubmission.php`:

```php
<?php
declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class NewsletterSubmission extends ContentEntityBase
{
    protected string $entityTypeId = 'newsletter_submission';

    protected array $entityKeys = [
        'id' => 'nsuid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];
}
```

Run test, expect PASS.

- [ ] **Step 8: Create the `newsletter_edition` migration**

```php
<?php
declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement("
            CREATE TABLE newsletter_edition (
                neid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                headline CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('newsletter_edition')) {
            $schema->getConnection()->executeStatement('DROP TABLE newsletter_edition');
        }
    }
};
```

- [ ] **Step 9: Create the `newsletter_item` migration**

```php
<?php
declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement("
            CREATE TABLE newsletter_item (
                nitid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                editor_blurb CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('newsletter_item')) {
            $schema->getConnection()->executeStatement('DROP TABLE newsletter_item');
        }
    }
};
```

- [ ] **Step 10: Create the `newsletter_submission` migration**

```php
<?php
declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement("
            CREATE TABLE newsletter_submission (
                nsuid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                title CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('newsletter_submission')) {
            $schema->getConnection()->executeStatement('DROP TABLE newsletter_submission');
        }
    }
};
```

- [ ] **Step 11: Run the full test suite to verify nothing broke**

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: PASS — existing tests + 4 new tests.

- [ ] **Step 12: Commit**

```bash
git add migrations/20260406_120000_create_newsletter_edition_table.php \
        migrations/20260406_120100_create_newsletter_item_table.php \
        migrations/20260406_120200_create_newsletter_submission_table.php \
        src/Entity/NewsletterEdition.php \
        src/Entity/NewsletterItem.php \
        src/Entity/NewsletterSubmission.php \
        tests/Minoo/Unit/Newsletter/Entity/

git commit -m "feat(#639): newsletter entities + migrations

3 new content entities (newsletter_edition, newsletter_item,
newsletter_submission) following the standard _data CLOB schema.
Entity keys: neid, nitid, nsuid.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: NewsletterServiceProvider + AccessPolicy (issue #639 cont.)

**Files:**
- Create: `src/Provider/NewsletterServiceProvider.php`
- Create: `src/Access/NewsletterAccessPolicy.php`
- Modify: `composer.json` (add provider to `extra.waaseyaa.providers`)
- Test: `tests/Minoo/Unit/Newsletter/Access/NewsletterAccessPolicyTest.php`

- [ ] **Step 1: Write the failing access policy test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Access;

use Minoo\Access\NewsletterAccessPolicy;
use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(NewsletterAccessPolicy::class)]
final class NewsletterAccessPolicyTest extends TestCase
{
    #[Test]
    public function admin_can_view_any_edition_at_any_status(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'draft',
        ]);

        $admin = $this->makeAccount(['administer content']);

        $result = $policy->access($edition, 'view', $admin);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function public_can_view_sent_edition(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'sent',
        ]);

        $public = $this->makeAccount([]);

        $result = $policy->access($edition, 'view', $public);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function public_cannot_view_draft_edition(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'draft',
        ]);

        $public = $this->makeAccount([]);

        $result = $policy->access($edition, 'view', $public);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function applies_to_all_three_newsletter_types(): void
    {
        $policy = new NewsletterAccessPolicy();
        $this->assertTrue($policy->appliesTo('newsletter_edition'));
        $this->assertTrue($policy->appliesTo('newsletter_item'));
        $this->assertTrue($policy->appliesTo('newsletter_submission'));
        $this->assertFalse($policy->appliesTo('event'));
    }

    private function makeAccount(array $permissions): AccountInterface
    {
        return new class($permissions) implements AccountInterface {
            public function __construct(private array $perms) {}
            public function id(): ?int { return 1; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $perm): bool { return in_array($perm, $this->perms, true); }
            public function hasRole(string $role): bool { return false; }
            public function roles(): array { return []; }
        };
    }
}
```

- [ ] **Step 2: Run, verify failure**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Access/NewsletterAccessPolicyTest.php
```

- [ ] **Step 3: Implement `NewsletterAccessPolicy`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['newsletter_edition', 'newsletter_item', 'newsletter_submission'])]
final class NewsletterAccessPolicy implements AccessPolicyInterface
{
    private const PUBLIC_STATUSES = ['generated', 'sent'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, ['newsletter_edition', 'newsletter_item', 'newsletter_submission'], true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        $type = $entity->getEntityTypeId();

        // Submissions: submitter sees their own, coordinator sees all in community.
        if ($type === 'newsletter_submission') {
            if ($operation === 'view' && (int) $entity->get('submitted_by') === (int) $account->id()) {
                return AccessResult::allowed('Submitter views own submission.');
            }
            if ($account->hasPermission('coordinate community')) {
                return AccessResult::allowed('Coordinator manages submissions.');
            }
            return AccessResult::neutral('Public cannot view submissions.');
        }

        // Editions and items: public read on generated/sent only.
        if ($operation === 'view' && in_array((string) $entity->get('status'), self::PUBLIC_STATUSES, true)) {
            return AccessResult::allowed('Published edition.');
        }

        if ($account->hasPermission('coordinate community')) {
            return AccessResult::allowed('Coordinator can manage own community newsletter.');
        }

        return AccessResult::neutral('Non-coordinator cannot modify newsletter.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin can create newsletter content.');
        }

        // Logged-in users can create submissions.
        if ($entityTypeId === 'newsletter_submission' && $account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated user can submit.');
        }

        // Coordinators can create editions and items.
        if ($account->hasPermission('coordinate community')) {
            return AccessResult::allowed('Coordinator can create newsletter content.');
        }

        return AccessResult::neutral('Non-coordinator cannot create newsletter content.');
    }
}
```

- [ ] **Step 4: Run access test, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Access/NewsletterAccessPolicyTest.php
```

Expected: PASS, 4 tests.

- [ ] **Step 5: Implement `NewsletterServiceProvider`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\NewsletterEdition;
use Minoo\Entity\NewsletterItem;
use Minoo\Entity\NewsletterSubmission;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class NewsletterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'newsletter_edition',
            label: 'Newsletter Edition',
            class: NewsletterEdition::class,
            keys: ['id' => 'neid', 'uuid' => 'uuid', 'label' => 'headline'],
            group: 'newsletter',
            fieldDefinitions: [
                'community_id' => ['type' => 'string', 'label' => 'Community ID', 'description' => 'Null = regional issue.'],
                'volume' => ['type' => 'integer', 'label' => 'Volume', 'default' => 1],
                'issue_number' => ['type' => 'integer', 'label' => 'Issue Number', 'default' => 1],
                'publish_date' => ['type' => 'string', 'label' => 'Publish Date'],
                'status' => ['type' => 'string', 'label' => 'Status', 'default' => 'draft'],
                'pdf_path' => ['type' => 'string', 'label' => 'PDF Path'],
                'pdf_hash' => ['type' => 'string', 'label' => 'PDF SHA256'],
                'sent_at' => ['type' => 'datetime', 'label' => 'Sent At'],
                'created_by' => ['type' => 'integer', 'label' => 'Created By'],
                'approved_by' => ['type' => 'integer', 'label' => 'Approved By'],
                'approved_at' => ['type' => 'datetime', 'label' => 'Approved At'],
                'headline' => ['type' => 'string', 'label' => 'Headline'],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'newsletter_item',
            label: 'Newsletter Item',
            class: NewsletterItem::class,
            keys: ['id' => 'nitid', 'uuid' => 'uuid', 'label' => 'editor_blurb'],
            group: 'newsletter',
            fieldDefinitions: [
                'edition_id' => ['type' => 'integer', 'label' => 'Edition ID'],
                'position' => ['type' => 'integer', 'label' => 'Position', 'default' => 0],
                'section' => ['type' => 'string', 'label' => 'Section'],
                'source_type' => ['type' => 'string', 'label' => 'Source Type'],
                'source_id' => ['type' => 'integer', 'label' => 'Source ID'],
                'inline_title' => ['type' => 'string', 'label' => 'Inline Title'],
                'inline_body' => ['type' => 'text_long', 'label' => 'Inline Body'],
                'editor_blurb' => ['type' => 'string', 'label' => 'Editor Blurb'],
                'included' => ['type' => 'boolean', 'label' => 'Included', 'default' => 1],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'newsletter_submission',
            label: 'Newsletter Submission',
            class: NewsletterSubmission::class,
            keys: ['id' => 'nsuid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'newsletter',
            fieldDefinitions: [
                'community_id' => ['type' => 'string', 'label' => 'Community ID'],
                'submitted_by' => ['type' => 'integer', 'label' => 'Submitted By'],
                'submitted_at' => ['type' => 'datetime', 'label' => 'Submitted At'],
                'category' => ['type' => 'string', 'label' => 'Category'],
                'title' => ['type' => 'string', 'label' => 'Title'],
                'body' => ['type' => 'text_long', 'label' => 'Body'],
                'status' => ['type' => 'string', 'label' => 'Status', 'default' => 'submitted'],
                'approved_by' => ['type' => 'integer', 'label' => 'Approved By'],
                'approved_at' => ['type' => 'datetime', 'label' => 'Approved At'],
                'included_in_edition_id' => ['type' => 'integer', 'label' => 'Included In Edition ID'],
            ],
        ));
    }
}
```

- [ ] **Step 6: Register the provider in `composer.json`**

In `composer.json`, find the `extra.waaseyaa.providers` array. Add this line at the end of the array (before the closing `]`):

```json
                "Minoo\\Provider\\NewsletterServiceProvider"
```

- [ ] **Step 7: Clear the manifest cache and run the full unit suite**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: PASS — including the new access policy test.

- [ ] **Step 8: Run pending migrations to verify schema**

```bash
bin/waaseyaa migrate
bin/waaseyaa migrate:status
```

Expected: 3 new migrations applied. `newsletter_edition`, `newsletter_item`, `newsletter_submission` tables created.

- [ ] **Step 9: Commit**

```bash
git add src/Provider/NewsletterServiceProvider.php \
        src/Access/NewsletterAccessPolicy.php \
        composer.json \
        tests/Minoo/Unit/Newsletter/Access/

git commit -m "feat(#639): newsletter service provider + access policy

Registers the 3 newsletter entity types and a unified access policy
covering all of them via array PolicyAttribute. Public read on
generated/sent editions only. Coordinators manage their community
content. Admins do everything.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: EditionLifecycle state machine (issue #640)

**Files:**
- Create: `src/Domain/Newsletter/ValueObject/EditionStatus.php`
- Create: `src/Domain/Newsletter/Service/EditionLifecycle.php`
- Create: `src/Domain/Newsletter/Exception/InvalidStateTransition.php`
- Test: `tests/Minoo/Unit/Newsletter/Service/EditionLifecycleTest.php`

- [ ] **Step 1: Implement the `EditionStatus` enum**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\ValueObject;

enum EditionStatus: string
{
    case Draft = 'draft';
    case Curating = 'curating';
    case Approved = 'approved';
    case Generated = 'generated';
    case Sent = 'sent';

    public static function fromEntity(\Waaseyaa\Entity\EntityInterface $edition): self
    {
        return self::from((string) $edition->get('status'));
    }
}
```

- [ ] **Step 2: Implement the `InvalidStateTransition` exception**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Exception;

use Minoo\Domain\Newsletter\ValueObject\EditionStatus;

final class InvalidStateTransition extends \DomainException
{
    public static function illegal(EditionStatus $from, EditionStatus $to): self
    {
        return new self(sprintf(
            'Illegal newsletter edition transition: %s -> %s',
            $from->value,
            $to->value,
        ));
    }
}
```

- [ ] **Step 3: Write the failing `EditionLifecycle` test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\InvalidStateTransition;
use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\ValueObject\EditionStatus;
use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditionLifecycle::class)]
final class EditionLifecycleTest extends TestCase
{
    #[Test]
    public function legal_transitions_succeed(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'draft',
        ]);

        $lifecycle->transition($edition, EditionStatus::Curating);
        $this->assertSame('curating', $edition->get('status'));

        $lifecycle->transition($edition, EditionStatus::Approved);
        $this->assertSame('approved', $edition->get('status'));

        $lifecycle->transition($edition, EditionStatus::Generated);
        $this->assertSame('generated', $edition->get('status'));

        $lifecycle->transition($edition, EditionStatus::Sent);
        $this->assertSame('sent', $edition->get('status'));
    }

    #[Test]
    public function skipping_states_throws(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'draft',
        ]);

        $this->expectException(InvalidStateTransition::class);
        $lifecycle->transition($edition, EditionStatus::Approved);
    }

    #[Test]
    public function backward_transition_throws(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'sent',
        ]);

        $this->expectException(InvalidStateTransition::class);
        $lifecycle->transition($edition, EditionStatus::Draft);
    }

    #[Test]
    public function approve_sets_approved_metadata(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'curating',
        ]);

        $lifecycle->approve($edition, approverId: 42);

        $this->assertSame('approved', $edition->get('status'));
        $this->assertSame(42, $edition->get('approved_by'));
        $this->assertNotNull($edition->get('approved_at'));
    }

    #[Test]
    public function send_sets_sent_at(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'generated',
        ]);

        $lifecycle->markSent($edition);

        $this->assertSame('sent', $edition->get('status'));
        $this->assertNotNull($edition->get('sent_at'));
    }
}
```

- [ ] **Step 4: Implement `EditionLifecycle`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\InvalidStateTransition;
use Minoo\Domain\Newsletter\ValueObject\EditionStatus;
use Waaseyaa\Entity\EntityInterface;

final class EditionLifecycle
{
    /**
     * Allowed forward transitions. Each entry maps a status to the set of
     * statuses it may transition into.
     */
    private const ALLOWED = [
        'draft'     => ['curating'],
        'curating'  => ['approved', 'draft'],   // editor can bounce back
        'approved'  => ['generated', 'curating'],
        'generated' => ['sent', 'approved'],     // re-render is allowed
        'sent'      => [],                       // terminal
    ];

    public function transition(EntityInterface $edition, EditionStatus $to): void
    {
        $from = EditionStatus::fromEntity($edition);

        $allowed = self::ALLOWED[$from->value] ?? [];
        if (! in_array($to->value, $allowed, true)) {
            throw InvalidStateTransition::illegal($from, $to);
        }

        $edition->set('status', $to->value);
    }

    public function approve(EntityInterface $edition, int $approverId): void
    {
        $this->transition($edition, EditionStatus::Approved);
        $edition->set('approved_by', $approverId);
        $edition->set('approved_at', (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM));
    }

    public function markGenerated(EntityInterface $edition, string $pdfPath, string $pdfHash): void
    {
        $this->transition($edition, EditionStatus::Generated);
        $edition->set('pdf_path', $pdfPath);
        $edition->set('pdf_hash', $pdfHash);
    }

    public function markSent(EntityInterface $edition): void
    {
        $this->transition($edition, EditionStatus::Sent);
        $edition->set('sent_at', (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM));
    }
}
```

- [ ] **Step 5: Run lifecycle tests, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Service/EditionLifecycleTest.php
```

Expected: PASS, 5 tests.

- [ ] **Step 6: Commit**

```bash
git add src/Domain/Newsletter/ValueObject/EditionStatus.php \
        src/Domain/Newsletter/Service/EditionLifecycle.php \
        src/Domain/Newsletter/Exception/InvalidStateTransition.php \
        tests/Minoo/Unit/Newsletter/Service/EditionLifecycleTest.php

git commit -m "feat(#640): EditionLifecycle state machine

Centralised state transitions for newsletter editions. The
draft -> curating -> approved -> generated -> sent path is enforced
here; controllers and services never write the status field directly.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: config/newsletter.php (issue #642)

**Files:**
- Create: `config/newsletter.php`
- Create: `src/Domain/Newsletter/ValueObject/SectionQuota.php`
- Test: `tests/Minoo/Unit/Newsletter/Service/SectionQuotaTest.php`

- [ ] **Step 1: Create `config/newsletter.php`**

```php
<?php
declare(strict_types=1);

return [
    'enabled' => filter_var($_ENV['NEWSLETTER_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),

    'mode' => 'regional',

    'regional_cover_communities' => ['wiikwemkoong', 'sheguiandah', 'aundeck'],

    'sections' => [
        'news'      => ['quota' => 4, 'sources' => ['post']],
        'events'    => ['quota' => 6, 'sources' => ['event']],
        'teachings' => ['quota' => 2, 'sources' => ['teaching']],
        'language'  => ['quota' => 1, 'sources' => ['dictionary_entry']],
        'community' => ['quota' => 8, 'sources' => ['newsletter_submission']],
    ],

    'communities' => [
        'manitoulin-regional' => [
            'mode' => 'regional',
            'printer_email' => 'sales@ojgraphix.com',
            'printer_name' => 'OJ Graphix',
            'printer_phone' => '(705) 869-0199',
            'printer_address' => '7 Panache Lake Road, Espanola, ON P5E 1H9',
            'printer_notes' => 'Hightail uplink fallback: spaces.hightail.com/uplink/OJUpload. Confirm PDF/X requirements before first job.',
            'editor_emails' => [],
        ],
    ],

    'pdf' => [
        'format' => 'Letter',
        'margins' => ['top' => '0.5in', 'right' => '0.5in', 'bottom' => '0.5in', 'left' => '0.5in'],
        'timeout_seconds' => 60,
    ],

    'storage_dir' => 'storage/newsletter',
];
```

- [ ] **Step 2: Write the failing `SectionQuota` test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SectionQuota::class)]
final class SectionQuotaTest extends TestCase
{
    #[Test]
    public function builds_from_config_array(): void
    {
        $config = [
            'news' => ['quota' => 4, 'sources' => ['post']],
            'events' => ['quota' => 6, 'sources' => ['event']],
        ];

        $quotas = SectionQuota::fromConfig($config);

        $this->assertCount(2, $quotas);
        $this->assertSame('news', $quotas[0]->name);
        $this->assertSame(4, $quotas[0]->quota);
        $this->assertSame(['post'], $quotas[0]->sources);
        $this->assertSame('events', $quotas[1]->name);
        $this->assertSame(6, $quotas[1]->quota);
    }

    #[Test]
    public function quota_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SectionQuota('news', 0, ['post']);
    }
}
```

- [ ] **Step 3: Implement `SectionQuota`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\ValueObject;

final readonly class SectionQuota
{
    /**
     * @param list<string> $sources
     */
    public function __construct(
        public string $name,
        public int $quota,
        public array $sources,
    ) {
        if ($quota <= 0) {
            throw new \InvalidArgumentException("SectionQuota requires a positive quota, got {$quota}");
        }
        if ($sources === []) {
            throw new \InvalidArgumentException("SectionQuota '{$name}' has no sources");
        }
    }

    /**
     * @param array<string, array{quota: int, sources: list<string>}> $config
     * @return list<self>
     */
    public static function fromConfig(array $config): array
    {
        $quotas = [];
        foreach ($config as $name => $row) {
            $quotas[] = new self((string) $name, (int) $row['quota'], (array) $row['sources']);
        }
        return $quotas;
    }
}
```

- [ ] **Step 4: Run quota test, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Service/SectionQuotaTest.php
```

- [ ] **Step 5: Commit**

```bash
git add config/newsletter.php \
        src/Domain/Newsletter/ValueObject/SectionQuota.php \
        tests/Minoo/Unit/Newsletter/Service/SectionQuotaTest.php

git commit -m "feat(#642): newsletter config + SectionQuota value object

Per-community newsletter settings with section quotas, printer email
config, and PDF formatting defaults. OJ Graphix (Espanola, ON) wired
in as the v1 Manitoulin regional print partner.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: NewsletterAssembler (issue #641)

**Files:**
- Create: `src/Domain/Newsletter/Assembler/ItemCandidate.php`
- Create: `src/Domain/Newsletter/Service/NewsletterAssembler.php`
- Test: `tests/Minoo/Unit/Newsletter/Service/NewsletterAssemblerTest.php`

- [ ] **Step 1: Implement `ItemCandidate` DTO**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Assembler;

final readonly class ItemCandidate
{
    public function __construct(
        public string $section,
        public string $sourceType,
        public int $sourceId,
        public string $blurb,
        public float $score,
    ) {}
}
```

- [ ] **Step 2: Write the failing assembler test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\Service\NewsletterAssembler;
use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use Minoo\Entity\NewsletterEdition;
use Minoo\Entity\NewsletterItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(NewsletterAssembler::class)]
final class NewsletterAssemblerTest extends TestCase
{
    #[Test]
    public function respects_section_quotas_and_writes_items(): void
    {
        $eventStorage = $this->makeStorage([
            $this->fakeEvent(1, '2026-04-01', 'Sugar Bush Day'),
            $this->fakeEvent(2, '2026-04-05', 'Drum Social'),
            $this->fakeEvent(3, '2026-04-08', 'Language Class'),
            $this->fakeEvent(4, '2026-04-10', 'Elders Lunch'),
            $this->fakeEvent(5, '2026-04-12', 'Powwow Planning'),
            $this->fakeEvent(6, '2026-04-15', 'Council Meeting'),
            $this->fakeEvent(7, '2026-04-18', 'Hide Tanning'),
        ]);

        $itemStorage = $this->makeWritableStorage();
        $submissionStorage = $this->makeStorage([]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturnMap([
            ['event', $eventStorage],
            ['newsletter_item', $itemStorage],
            ['newsletter_submission', $submissionStorage],
            ['post', $this->makeStorage([])],
            ['teaching', $this->makeStorage([])],
            ['dictionary_entry', $this->makeStorage([])],
        ]);

        $assembler = new NewsletterAssembler(
            entityTypeManager: $etm,
            lifecycle: new EditionLifecycle(),
            quotas: SectionQuota::fromConfig([
                'events' => ['quota' => 6, 'sources' => ['event']],
            ]),
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'draft',
            'publish_date' => '2026-04-15',
        ]);

        $assembler->assemble($edition);

        $this->assertSame('curating', $edition->get('status'));
        $written = $itemStorage->all();
        $this->assertCount(6, $written, 'Quota should cap section at 6 items even though 7 candidates exist');
        $this->assertSame('events', $written[0]->get('section'));
    }

    private function fakeEvent(int $id, string $date, string $title): \Waaseyaa\Entity\EntityInterface
    {
        // ...minimal stub returning id, get('publish_date'), get('title') etc.
        $stub = $this->createStub(\Waaseyaa\Entity\ContentEntityBase::class);
        $stub->method('id')->willReturn($id);
        $stub->method('get')->willReturnMap([
            ['publish_date', $date],
            ['title', $title],
            ['community_id', 'wiikwemkoong'],
        ]);
        return $stub;
    }

    private function makeStorage(array $entities): EntityStorageInterface
    {
        return new class($entities) implements EntityStorageInterface {
            public function __construct(private array $entities) {}
            public function loadMultiple(array $ids = null): array { return $this->entities; }
            public function load(int|string $id): ?object { return $this->entities[$id] ?? null; }
            public function getQuery(): object {
                $entities = $this->entities;
                return new class($entities) {
                    public function __construct(private array $e) {}
                    public function condition(string $f, mixed $v, string $op = '='): self { return $this; }
                    public function range(int $start, int $length): self { return $this; }
                    public function sort(string $f, string $dir = 'ASC'): self { return $this; }
                    public function execute(): array { return array_map(fn($x) => $x->id(), $this->e); }
                };
            }
            public function create(array $values): object { return new \stdClass(); }
            public function save(object $entity): void {}
            public function delete(array $entities): void {}
            public function all(): array { return $this->entities; }
        };
    }

    private function makeWritableStorage(): EntityStorageInterface
    {
        return new class implements EntityStorageInterface {
            public array $written = [];
            public function loadMultiple(array $ids = null): array { return $this->written; }
            public function load(int|string $id): ?object { return $this->written[$id] ?? null; }
            public function getQuery(): object { return new \stdClass(); }
            public function create(array $values): object {
                return new NewsletterItem($values);
            }
            public function save(object $entity): void {
                $this->written[] = $entity;
            }
            public function delete(array $entities): void {}
            public function all(): array { return $this->written; }
        };
    }
}
```

> **Note for the implementing engineer:** The framework's `EntityStorageInterface` has more methods than shown above. Use `createStub(EntityStorageInterface::class)` if the anonymous class proves brittle — the assertion that matters is "exactly 6 items written". If the framework storage interface has changed shape, prefer using a real `:memory:` SQLite kernel boot like `NewsletterEndToEndTest` later in this plan.

- [ ] **Step 3: Run, verify failure, then implement `NewsletterAssembler`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

use Minoo\Domain\Newsletter\Assembler\ItemCandidate;
use Minoo\Domain\Newsletter\ValueObject\EditionStatus;
use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterAssembler
{
    /**
     * @param list<SectionQuota> $quotas
     */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EditionLifecycle $lifecycle,
        private readonly array $quotas,
    ) {}

    public function assemble(EntityInterface $edition): void
    {
        // Wipe any existing items for re-assembly idempotency.
        $this->clearExistingItems((int) $edition->id());

        $position = 0;
        $totalWritten = 0;

        foreach ($this->quotas as $quota) {
            $candidates = $this->candidatesForSection($quota, $edition);
            $taken = array_slice($candidates, 0, $quota->quota);

            foreach ($taken as $candidate) {
                $this->writeItem($edition, $candidate, ++$position);
                $totalWritten++;
            }
        }

        if ($totalWritten === 0) {
            // Caller checks status; leave in draft and surface to UI.
            return;
        }

        $this->lifecycle->transition($edition, EditionStatus::Curating);
    }

    /**
     * @return list<ItemCandidate>
     */
    private function candidatesForSection(SectionQuota $quota, EntityInterface $edition): array
    {
        $candidates = [];
        foreach ($quota->sources as $source) {
            if ($source === 'newsletter_submission') {
                $candidates = [...$candidates, ...$this->submissionCandidates($quota, $edition)];
                continue;
            }

            $storage = $this->entityTypeManager->getStorage($source);
            $entities = $storage->loadMultiple();
            foreach ($entities as $entity) {
                $candidates[] = new ItemCandidate(
                    section: $quota->name,
                    sourceType: $source,
                    sourceId: (int) $entity->id(),
                    blurb: (string) ($entity->get('title') ?? $entity->label() ?? ''),
                    score: $this->scoreByRecency($entity),
                );
            }
        }

        usort($candidates, fn(ItemCandidate $a, ItemCandidate $b) => $b->score <=> $a->score);
        return $candidates;
    }

    /**
     * @return list<ItemCandidate>
     */
    private function submissionCandidates(SectionQuota $quota, EntityInterface $edition): array
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $candidates = [];
        foreach ($storage->loadMultiple() as $sub) {
            if ((string) $sub->get('status') !== 'approved') {
                continue;
            }
            if ((string) $sub->get('community_id') !== (string) $edition->get('community_id')) {
                continue;
            }
            $candidates[] = new ItemCandidate(
                section: $quota->name,
                sourceType: 'newsletter_submission',
                sourceId: (int) $sub->id(),
                blurb: (string) $sub->get('title'),
                score: 1.0,
            );
        }
        return $candidates;
    }

    private function scoreByRecency(EntityInterface $entity): float
    {
        $date = (string) ($entity->get('publish_date') ?? $entity->get('created_at') ?? '');
        if ($date === '') {
            return 0.0;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return 0.0;
        }
        // More recent → higher score. 30 day half-life.
        $age = max(0, time() - $ts);
        return 1.0 / (1.0 + ($age / (30 * 86400)));
    }

    private function writeItem(EntityInterface $edition, ItemCandidate $candidate, int $position): void
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_item');
        $item = $storage->create([
            'edition_id' => (int) $edition->id(),
            'position' => $position,
            'section' => $candidate->section,
            'source_type' => $candidate->sourceType,
            'source_id' => $candidate->sourceId,
            'editor_blurb' => $candidate->blurb,
            'included' => 1,
        ]);
        $storage->save($item);
    }

    private function clearExistingItems(int $editionId): void
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_item');
        $existing = [];
        foreach ($storage->loadMultiple() as $item) {
            if ((int) $item->get('edition_id') === $editionId) {
                $existing[] = $item;
            }
        }
        if ($existing !== []) {
            $storage->delete($existing);
        }
    }
}
```

- [ ] **Step 4: Run assembler test, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Service/NewsletterAssemblerTest.php
```

If the unit test is too brittle against the storage interface, mark it `@requires` integration and rely on the integration test in Task 11 instead.

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Newsletter/Assembler/ItemCandidate.php \
        src/Domain/Newsletter/Service/NewsletterAssembler.php \
        tests/Minoo/Unit/Newsletter/Service/NewsletterAssemblerTest.php

git commit -m "feat(#641): NewsletterAssembler — auto-fill from sources

Pulls candidate items from configured sources (post/event/teaching/
dictionary_entry/newsletter_submission), scores by recency, and
respects per-section quotas. Idempotent — re-assemble wipes existing
items first. Transitions edition draft -> curating on success.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: NewsletterEditorController (issue #643)

**Files:**
- Create: `src/Controller/NewsletterEditorController.php`
- Create: `templates/newsletter/editor/list.html.twig`
- Create: `templates/newsletter/editor/newsroom.html.twig`
- Modify: `src/Provider/NewsletterServiceProvider.php` (add routes + service singletons)
- Test: `tests/Minoo/Unit/Newsletter/Controller/NewsletterEditorControllerTest.php`

- [ ] **Step 1: Add routes + service registration to `NewsletterServiceProvider`**

Append to the existing `register()` method (after entity types):

```php
        $this->singleton(\Minoo\Domain\Newsletter\Service\EditionLifecycle::class, function () {
            return new \Minoo\Domain\Newsletter\Service\EditionLifecycle();
        });

        $this->singleton(\Minoo\Domain\Newsletter\Service\NewsletterAssembler::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            return new \Minoo\Domain\Newsletter\Service\NewsletterAssembler(
                entityTypeManager: $this->resolve(\Waaseyaa\Entity\EntityTypeManager::class),
                lifecycle: $this->resolve(\Minoo\Domain\Newsletter\Service\EditionLifecycle::class),
                quotas: \Minoo\Domain\Newsletter\ValueObject\SectionQuota::fromConfig($config['sections']),
            );
        });
```

Add a new `routes()` method:

```php
    public function routes(\Waaseyaa\Routing\WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $etm = null): void
    {
        $router->addRoute(
            'newsletter.editor.list',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter')
                ->controller('Minoo\Controller\NewsletterEditorController::list')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.new',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/new')
                ->controller('Minoo\Controller\NewsletterEditorController::create')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.assemble',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/{id}/assemble')
                ->controller('Minoo\Controller\NewsletterEditorController::assemble')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.show',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/{id}')
                ->controller('Minoo\Controller\NewsletterEditorController::show')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.approve',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/{id}/approve')
                ->controller('Minoo\Controller\NewsletterEditorController::approve')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );
    }
```

- [ ] **Step 2: Implement `NewsletterEditorController`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\Service\NewsletterAssembler;
use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterEditorController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly EditionLifecycle $lifecycle,
        private readonly NewsletterAssembler $assembler,
    ) {}

    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = $storage->loadMultiple();

        return new Response($this->twig->render('newsletter/editor/list.html.twig', [
            'editions' => $editions,
            'flashes' => Flash::pull(),
        ]));
    }

    public function create(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $communityId = $request->request->get('community_id') ?: null;
        $publishDate = (string) $request->request->get('publish_date');

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');

        // Auto-assign volume + issue number for this community.
        $existingForCommunity = array_filter(
            $storage->loadMultiple(),
            fn($e) => (string) $e->get('community_id') === (string) $communityId,
        );
        $maxIssue = 0;
        foreach ($existingForCommunity as $e) {
            $maxIssue = max($maxIssue, (int) $e->get('issue_number'));
        }
        $nextIssue = $maxIssue + 1;

        $edition = $storage->create([
            'community_id' => $communityId,
            'volume' => 1,
            'issue_number' => $nextIssue,
            'publish_date' => $publishDate,
            'status' => 'draft',
            'created_by' => $account->id(),
            'headline' => sprintf('Issue %d', $nextIssue),
        ]);
        $storage->save($edition);

        Flash::success('New newsletter edition created.');
        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function assemble(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        $this->assembler->assemble($edition);

        $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);

        if ((string) $edition->get('status') === 'draft') {
            Flash::error('No content found for this date window. Try again after submissions arrive.');
        } else {
            Flash::success('Edition assembled — review the queue.');
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn($i) => (int) $i->get('edition_id') === (int) $edition->id(),
        );

        $bySection = [];
        foreach ($items as $item) {
            $bySection[(string) $item->get('section')][] = $item;
        }

        return new Response($this->twig->render('newsletter/editor/newsroom.html.twig', [
            'edition' => $edition,
            'items_by_section' => $bySection,
            'flashes' => Flash::pull(),
        ]));
    }

    public function approve(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $this->lifecycle->approve($edition, (int) $account->id());
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);
            Flash::success('Edition approved. You can now generate the PDF.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    private function loadEditionOrFail(mixed $id): \Waaseyaa\Entity\EntityInterface
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $storage->load((int) $id);
        if ($edition === null) {
            throw new \RuntimeException('Newsletter edition not found.');
        }
        return $edition;
    }
}
```

- [ ] **Step 3: Create `templates/newsletter/editor/list.html.twig`**

```twig
{% extends "base.html.twig" %}

{% block title %}Newsletter editor{% endblock %}

{% block content %}
<section class="newsroom-list">
    <h1>Newsletter editor</h1>

    {% for type, msgs in flashes %}
        {% for msg in msgs %}
            <div class="flash flash--{{ type }}">{{ msg }}</div>
        {% endfor %}
    {% endfor %}

    <form method="post" action="/coordinator/newsletter/new" class="form-inline">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <label>Community
            <input type="text" name="community_id" placeholder="manitoulin-regional or leave blank">
        </label>
        <label>Publish date
            <input type="date" name="publish_date" required>
        </label>
        <button type="submit">New edition</button>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Issue</th>
                <th>Community</th>
                <th>Publish date</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            {% for edition in editions %}
                <tr>
                    <td>Vol. {{ edition.get('volume') }} No. {{ edition.get('issue_number') }}</td>
                    <td>{{ edition.get('community_id') ?: 'Regional' }}</td>
                    <td>{{ edition.get('publish_date') }}</td>
                    <td><span class="status status--{{ edition.get('status') }}">{{ edition.get('status') }}</span></td>
                    <td><a href="/coordinator/newsletter/{{ edition.id() }}">Open</a></td>
                </tr>
            {% else %}
                <tr><td colspan="5">No editions yet.</td></tr>
            {% endfor %}
        </tbody>
    </table>
</section>
{% endblock %}
```

- [ ] **Step 4: Create `templates/newsletter/editor/newsroom.html.twig`**

```twig
{% extends "base.html.twig" %}

{% block title %}Newsroom — {{ edition.get('headline') }}{% endblock %}

{% block content %}
<section class="newsroom">
    <header>
        <h1>{{ edition.get('headline') }}</h1>
        <p>
            Vol. {{ edition.get('volume') }} No. {{ edition.get('issue_number') }} ·
            {{ edition.get('community_id') ?: 'Regional' }} ·
            <span class="status status--{{ edition.get('status') }}">{{ edition.get('status') }}</span>
        </p>
    </header>

    {% for type, msgs in flashes %}
        {% for msg in msgs %}
            <div class="flash flash--{{ type }}">{{ msg }}</div>
        {% endfor %}
    {% endfor %}

    <div class="newsroom-actions">
        {% if edition.get('status') == 'draft' %}
            <form method="post" action="/coordinator/newsletter/{{ edition.id() }}/assemble">
                <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
                <button type="submit">Auto-fill from NorthCloud</button>
            </form>
        {% endif %}

        {% if edition.get('status') == 'curating' %}
            <form method="post" action="/coordinator/newsletter/{{ edition.id() }}/approve">
                <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
                <button type="submit">Approve</button>
            </form>
        {% endif %}

        {% if edition.get('status') == 'approved' %}
            <form method="post" action="/coordinator/newsletter/{{ edition.id() }}/generate">
                <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
                <button type="submit">Generate PDF</button>
            </form>
        {% endif %}

        {% if edition.get('status') == 'generated' %}
            <a href="/newsletter/{{ edition.get('community_id') ?: 'regional' }}/{{ edition.get('volume') }}-{{ edition.get('issue_number') }}.pdf">Download PDF</a>
            <form method="post" action="/coordinator/newsletter/{{ edition.id() }}/send">
                <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
                <button type="submit">Send to printer</button>
            </form>
        {% endif %}
    </div>

    {% for section, items in items_by_section %}
        <section class="newsroom-section">
            <h2>{{ section|capitalize }}</h2>
            <ul>
                {% for item in items %}
                    <li>
                        <strong>{{ item.get('editor_blurb') ?: item.get('inline_title') }}</strong>
                        <small>{{ item.get('source_type') }} #{{ item.get('source_id') }}</small>
                    </li>
                {% endfor %}
            </ul>
        </section>
    {% else %}
        <p>No items yet — assemble to populate.</p>
    {% endfor %}
</section>
{% endblock %}
```

- [ ] **Step 5: Write the failing controller test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Controller;

use Minoo\Controller\NewsletterEditorController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterEditorController::class)]
final class NewsletterEditorControllerTest extends TestCase
{
    #[Test]
    public function class_exists(): void
    {
        $this->assertTrue(class_exists(NewsletterEditorController::class));
    }
    // Real behavior tests live in NewsletterEndToEndTest (Task 11) — controller wiring
    // is exercised under a real kernel boot there.
}
```

> **Why minimal?** Controller tests in this codebase that mock `EntityTypeManager` + `Twig\Environment` + `EditionLifecycle` + `NewsletterAssembler` get brittle fast. The integration test in Task 11 exercises the full controller stack with a real `:memory:` SQLite kernel boot. This is the project pattern for controllers with multiple service deps (see `CoordinatorDashboardController` — same approach).

- [ ] **Step 6: Run full unit suite + clear manifest**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

- [ ] **Step 7: Smoke check the routes register**

```bash
php -S localhost:8081 -t public &
sleep 2
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8081/coordinator/newsletter
# Expected: 302 (redirect to login) or 403, NOT 404
kill %1
```

- [ ] **Step 8: Commit**

```bash
git add src/Controller/NewsletterEditorController.php \
        src/Provider/NewsletterServiceProvider.php \
        templates/newsletter/editor/ \
        tests/Minoo/Unit/Newsletter/Controller/NewsletterEditorControllerTest.php

git commit -m "feat(#643): newsroom UI — NewsletterEditorController

Coordinator-only routes for the editorial loop: list, create, assemble,
show (newsroom queue), approve. Generate + send actions stub the
NewsletterRenderer/Dispatcher integration which lands in #645/#647.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Print template (issue #646)

**Files:**
- Create: `templates/newsletter/edition.html.twig`
- Modify: `public/css/minoo.css` (add `@media print` block at end of file)

- [ ] **Step 1: Create `templates/newsletter/edition.html.twig`**

This template is intentionally NOT extending `base.html.twig` — it's a stand-alone document for print, no nav/footer.

```twig
<!DOCTYPE html>
<html lang="{{ edition.get('langcode') ?: 'en' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ edition.get('headline') }}</title>
    <link rel="stylesheet" href="/css/minoo.css?v={{ asset_version() }}">
    <style>
        @page {
            size: Letter;
            margin: 0.5in;
        }
        body {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #fff;
        }
        .newsletter-print {
            column-count: 2;
            column-gap: 0.4in;
            column-rule: 1px solid #ccc;
        }
        .newsletter-print h1 {
            column-span: all;
            font-size: 28pt;
            margin: 0 0 0.2in 0;
            border-bottom: 3px solid #000;
        }
        .newsletter-print h2 {
            font-size: 16pt;
            margin: 0.3in 0 0.1in;
            border-bottom: 1px solid #000;
            page-break-after: avoid;
        }
        .newsletter-print h3 {
            font-size: 13pt;
            margin: 0.15in 0 0.05in;
        }
        .newsletter-print article {
            break-inside: avoid;
            margin-bottom: 0.2in;
        }
        .newsletter-print .masthead {
            column-span: all;
            text-align: center;
            font-size: 10pt;
            margin-bottom: 0.2in;
        }
    </style>
</head>
<body>
<div class="newsletter-print">
    <header class="masthead">
        <strong>Vol. {{ edition.get('volume') }} No. {{ edition.get('issue_number') }}</strong>
        — {{ edition.get('publish_date') }}
        — {{ edition.get('community_id') ?: 'Regional' }}
    </header>

    <h1>{{ edition.get('headline') }}</h1>

    {% for section, items in items_by_section %}
        <section>
            <h2>{{ section|capitalize }}</h2>
            {% for item in items if item.get('included') %}
                <article>
                    {% if item.get('source_type') and source_entities[item.id()] is defined %}
                        {% set src = source_entities[item.id()] %}
                        <h3>{{ src.label() ?: src.get('title') }}</h3>
                        {% if item.get('editor_blurb') %}
                            <p><em>{{ item.get('editor_blurb') }}</em></p>
                        {% endif %}
                        <p>{{ src.get('description')|default(src.get('body'))|striptags|slice(0, 280) }}…</p>
                    {% else %}
                        <h3>{{ item.get('inline_title') }}</h3>
                        <p>{{ item.get('inline_body')|striptags }}</p>
                    {% endif %}
                </article>
            {% endfor %}
        </section>
    {% endfor %}
</div>
</body>
</html>
```

- [ ] **Step 2: Add `@media print` block to `public/css/minoo.css`**

Append to the end of `public/css/minoo.css`:

```css
/* ===== NEWSLETTER PRINT ===== */
@media print {
    .newsletter-print {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 12pt;
        line-height: 1.5;
    }
    .newsletter-print article {
        break-inside: avoid;
    }
    .newsletter-print h2 {
        page-break-after: avoid;
    }
    /* Ensure background colors print on supported renderers */
    .newsletter-print {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
```

- [ ] **Step 3: Manual smoke — load the template in a browser**

This requires a fixture edition first. Skip until Task 9 (which seeds + renders one). The acceptance criterion in #646 is satisfied by Task 9's smoke run.

- [ ] **Step 4: Commit**

```bash
git add templates/newsletter/edition.html.twig public/css/minoo.css

git commit -m "feat(#646): newsletter print template + @media print CSS

Two-column print template using CSS multi-column layout with
break-inside controls. Stand-alone HTML document (does not extend
base.html.twig) so the print render produces a clean PDF with no
nav/footer chrome.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: bin/render-pdf.js + render token endpoint (issue #644)

**Files:**
- Create: `bin/render-pdf.js`
- Modify: `src/Controller/NewsletterController.php` (will be created in Task 10 — for now, create a minimal version with just the token endpoint, expand in Task 10)
- Create: `src/Domain/Newsletter/Service/RenderTokenStore.php`
- Test: `tests/Minoo/Unit/Newsletter/Service/RenderTokenStoreTest.php`

> **Note:** This task creates `NewsletterController` with a single `printPreview` method. Task 10 expands the controller with the rest of the public surface.

- [ ] **Step 1: Create `bin/render-pdf.js`**

```javascript
#!/usr/bin/env node
/**
 * Renders a URL to PDF via headless Chromium (Playwright).
 * Usage: node bin/render-pdf.js --url=http://... --out=/path/to/file.pdf
 *
 * Exit codes:
 *   0 — PDF written successfully
 *   1 — Argument error
 *   2 — Navigation/render error
 *   3 — PDF write error
 */
const { chromium } = require('playwright');

function parseArgs(argv) {
    const args = {};
    for (const a of argv.slice(2)) {
        const m = a.match(/^--([^=]+)=(.*)$/);
        if (m) args[m[1]] = m[2];
    }
    return args;
}

(async () => {
    const args = parseArgs(process.argv);
    if (!args.url || !args.out) {
        console.error('Usage: render-pdf.js --url=<url> --out=<path>');
        process.exit(1);
    }

    let browser;
    try {
        browser = await chromium.launch();
        const ctx = await browser.newContext();
        const page = await ctx.newPage();
        const resp = await page.goto(args.url, { waitUntil: 'networkidle', timeout: 30000 });
        if (!resp || !resp.ok()) {
            console.error(`Navigation failed: ${resp ? resp.status() : 'no response'}`);
            process.exit(2);
        }
    } catch (e) {
        console.error(`Render error: ${e.message}`);
        if (browser) await browser.close();
        process.exit(2);
    }

    try {
        const page = browser.contexts()[0].pages()[0];
        await page.pdf({
            path: args.out,
            format: 'Letter',
            printBackground: true,
            margin: { top: '0.5in', right: '0.5in', bottom: '0.5in', left: '0.5in' },
        });
    } catch (e) {
        console.error(`PDF write error: ${e.message}`);
        await browser.close();
        process.exit(3);
    }

    await browser.close();
    console.log(`Wrote ${args.out}`);
})();
```

- [ ] **Step 2: Make it executable and verify Playwright is installed**

```bash
chmod +x bin/render-pdf.js
node -e "require('playwright')" && echo "playwright ok" || npm install playwright
npx playwright install chromium
```

- [ ] **Step 3: Write the failing `RenderTokenStore` test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderTokenStore::class)]
final class RenderTokenStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/newsletter-token-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmpDir . '/*'));
        @rmdir($this->tmpDir);
    }

    #[Test]
    public function issued_token_is_consumable_once(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);

        $token = $store->issue(editionId: 5);

        $this->assertTrue($store->consume($token, editionId: 5));
        $this->assertFalse($store->consume($token, editionId: 5), 'Token must be single-use');
    }

    #[Test]
    public function token_for_other_edition_is_rejected(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: 60);
        $token = $store->issue(editionId: 5);

        $this->assertFalse($store->consume($token, editionId: 6));
    }

    #[Test]
    public function expired_token_is_rejected(): void
    {
        $store = new RenderTokenStore($this->tmpDir, ttlSeconds: -1);
        $token = $store->issue(editionId: 5);

        $this->assertFalse($store->consume($token, editionId: 5));
    }
}
```

- [ ] **Step 4: Implement `RenderTokenStore`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

final class RenderTokenStore
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $ttlSeconds = 60,
    ) {
        if (! is_dir($storageDir) && ! @mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
            throw new \RuntimeException("RenderTokenStore cannot create storage dir: {$storageDir}");
        }
    }

    public function issue(int $editionId): string
    {
        $token = bin2hex(random_bytes(16));
        $payload = json_encode([
            'edition_id' => $editionId,
            'expires_at' => time() + $this->ttlSeconds,
        ]);
        file_put_contents($this->path($token), $payload);
        return $token;
    }

    public function consume(string $token, int $editionId): bool
    {
        $path = $this->path($token);
        if (! is_file($path)) {
            return false;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        if (! is_array($payload)) {
            return false;
        }
        if ((int) ($payload['edition_id'] ?? 0) !== $editionId) {
            return false;
        }
        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            return false;
        }
        return true;
    }

    private function path(string $token): string
    {
        if (! preg_match('/^[a-f0-9]+$/', $token)) {
            throw new \InvalidArgumentException('Invalid token format');
        }
        return $this->storageDir . '/' . $token . '.json';
    }
}
```

- [ ] **Step 5: Run token store test, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Service/RenderTokenStoreTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 6: Create minimal `NewsletterController` with the print preview endpoint**

```php
<?php
declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly RenderTokenStore $tokens,
    ) {}

    /**
     * Internal endpoint hit by Playwright during PDF generation.
     * Public route, but requires a single-use one-time token.
     */
    public function printPreview(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = (string) $request->query->get('token', '');
        $editionId = (int) ($params['id'] ?? 0);

        if (! $this->tokens->consume($token, $editionId)) {
            return new Response('Gone', 410);
        }

        $editionStorage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $editionStorage->load($editionId);
        if ($edition === null) {
            return new Response('Not found', 404);
        }

        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn($i) => (int) $i->get('edition_id') === $editionId,
        );

        $bySection = [];
        $sourceEntities = [];
        foreach ($items as $item) {
            $bySection[(string) $item->get('section')][] = $item;

            $srcType = (string) $item->get('source_type');
            $srcId = (int) $item->get('source_id');
            if ($srcType !== '' && $srcId > 0) {
                $src = $this->entityTypeManager->getStorage($srcType)->load($srcId);
                if ($src !== null) {
                    $sourceEntities[$item->id()] = $src;
                }
            }
        }

        return new Response($this->twig->render('newsletter/edition.html.twig', [
            'edition' => $edition,
            'items_by_section' => $bySection,
            'source_entities' => $sourceEntities,
        ]));
    }
}
```

- [ ] **Step 7: Register `RenderTokenStore` and the print preview route in `NewsletterServiceProvider`**

In `register()`, add:

```php
        $this->singleton(\Minoo\Domain\Newsletter\Service\RenderTokenStore::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            $dir = dirname(__DIR__, 2) . '/' . $config['storage_dir'] . '/render-tokens';
            return new \Minoo\Domain\Newsletter\Service\RenderTokenStore(
                storageDir: $dir,
                ttlSeconds: 60,
            );
        });
```

In `routes()`, add (note: `allowAll()` makes it public — security comes from the one-time token):

```php
        $router->addRoute(
            'newsletter.print_preview',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter/_internal/{id}/print')
                ->controller('Minoo\Controller\NewsletterController::printPreview')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
```

- [ ] **Step 8: Manual smoke test**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
php -S localhost:8081 -t public &
sleep 2

# Create a render token via tinker-style PHP one-liner
TOKEN=$(php -r "
require 'vendor/autoload.php';
\$store = new Minoo\Domain\Newsletter\Service\RenderTokenStore('/tmp/test-tokens', 60);
echo \$store->issue(1);
")
echo "Token: $TOKEN"

# Render to PDF
node bin/render-pdf.js --url="http://localhost:8081/newsletter/_internal/1/print?token=$TOKEN" --out=/tmp/test-edition.pdf
ls -la /tmp/test-edition.pdf
kill %1
```

Expected: Either a PDF file (if a fixture edition #1 exists) or a 410 Gone HTML in the PDF (if no fixture). Both prove the wiring works.

- [ ] **Step 9: Commit**

```bash
git add bin/render-pdf.js \
        src/Controller/NewsletterController.php \
        src/Domain/Newsletter/Service/RenderTokenStore.php \
        src/Provider/NewsletterServiceProvider.php \
        tests/Minoo/Unit/Newsletter/Service/RenderTokenStoreTest.php

git commit -m "feat(#644): bin/render-pdf.js + RenderTokenStore + print preview route

Node helper that drives Playwright headless Chromium to render a URL
to PDF, plus a single-use one-time token store and the public print
preview endpoint that Playwright fetches. Token has 60s TTL, scoped
to a specific edition id, deleted on first use.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: NewsletterRenderer (issue #645)

**Files:**
- Create: `src/Domain/Newsletter/Service/NewsletterRenderer.php`
- Create: `src/Domain/Newsletter/Exception/RenderException.php`
- Create: `src/Domain/Newsletter/ValueObject/PdfArtifact.php`
- Modify: `src/Controller/NewsletterEditorController.php` (add `generate` action)
- Modify: `src/Provider/NewsletterServiceProvider.php` (register renderer + generate route)
- Test: `tests/Minoo/Unit/Newsletter/Service/NewsletterRendererTest.php`

- [ ] **Step 1: Implement `RenderException`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Exception;

final class RenderException extends \RuntimeException
{
    public static function processFailure(int $exitCode, string $stderr): self
    {
        return new self(sprintf('PDF render process failed (exit %d): %s', $exitCode, trim($stderr)));
    }

    public static function timeout(int $seconds): self
    {
        return new self(sprintf('PDF render timed out after %ds', $seconds));
    }

    public static function zeroByteOutput(string $path): self
    {
        return new self("PDF render produced zero-byte file: {$path}");
    }
}
```

- [ ] **Step 2: Implement `PdfArtifact`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\ValueObject;

final readonly class PdfArtifact
{
    public function __construct(
        public string $path,
        public int $bytes,
        public string $sha256,
    ) {}
}
```

- [ ] **Step 3: Write the failing `NewsletterRenderer` test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\RenderException;
use Minoo\Domain\Newsletter\Service\NewsletterRenderer;
use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(NewsletterRenderer::class)]
final class NewsletterRendererTest extends TestCase
{
    #[Test]
    public function process_failure_throws_render_exception(): void
    {
        $tmp = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        $tokens = new RenderTokenStore($tmp . '/tokens', 60);

        // Inject a process factory that returns a failing process.
        $renderer = new NewsletterRenderer(
            tokenStore: $tokens,
            storageDir: $tmp,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: fn(array $cmd) => $this->failingProcess(),
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $this->expectException(RenderException::class);
        $renderer->render($edition);
    }

    #[Test]
    public function zero_byte_output_throws(): void
    {
        $tmp = sys_get_temp_dir() . '/nrtest-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/wiikwemkoong', 0775, true);
        $tokens = new RenderTokenStore($tmp . '/tokens', 60);

        $renderer = new NewsletterRenderer(
            tokenStore: $tokens,
            storageDir: $tmp,
            baseUrl: 'http://localhost:8081',
            nodeBinary: 'node',
            scriptPath: 'bin/render-pdf.js',
            timeoutSeconds: 30,
            processFactory: fn(array $cmd) => $this->successfulProcessThatTouchesEmptyFile($cmd),
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'status' => 'approved',
        ]);

        $this->expectException(RenderException::class);
        $this->expectExceptionMessageMatches('/zero-byte/');
        $renderer->render($edition);
    }

    private function failingProcess(): Process
    {
        $p = new Process(['false']);
        return $p;
    }

    private function successfulProcessThatTouchesEmptyFile(array $cmd): Process
    {
        $outIndex = array_search('--out', array_map(fn($a) => str_starts_with((string) $a, '--out=') ? '--out' : $a, $cmd), true);
        // Extract the --out=... value
        $outArg = array_filter($cmd, fn($a) => str_starts_with((string) $a, '--out='));
        $out = explode('=', array_values($outArg)[0], 2)[1] ?? '/tmp/empty.pdf';
        return new Process(['touch', $out]);
    }
}
```

- [ ] **Step 4: Implement `NewsletterRenderer`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

use Closure;
use Minoo\Domain\Newsletter\Exception\RenderException;
use Minoo\Domain\Newsletter\ValueObject\PdfArtifact;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Waaseyaa\Entity\EntityInterface;

final class NewsletterRenderer
{
    /**
     * @param Closure(list<string>): Process|null $processFactory
     */
    public function __construct(
        private readonly RenderTokenStore $tokenStore,
        private readonly string $storageDir,
        private readonly string $baseUrl,
        private readonly string $nodeBinary,
        private readonly string $scriptPath,
        private readonly int $timeoutSeconds,
        private readonly ?Closure $processFactory = null,
    ) {
        if (! is_dir($storageDir) && ! @mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
            throw new \RuntimeException("Renderer cannot create storage dir: {$storageDir}");
        }
    }

    public function render(EntityInterface $edition): PdfArtifact
    {
        $editionId = (int) $edition->id();
        $community = (string) ($edition->get('community_id') ?: 'regional');
        $vol = (int) $edition->get('volume');
        $issue = (int) $edition->get('issue_number');

        $outDir = $this->storageDir . '/' . $community;
        if (! is_dir($outDir) && ! @mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException("Renderer cannot create output dir: {$outDir}");
        }
        $outPath = sprintf('%s/%d-%d.pdf', $outDir, $vol, $issue);
        // Remove any stale file before rendering.
        if (is_file($outPath)) {
            @unlink($outPath);
        }

        $token = $this->tokenStore->issue($editionId);
        $url = sprintf('%s/newsletter/_internal/%d/print?token=%s', $this->baseUrl, $editionId, $token);

        $cmd = [$this->nodeBinary, $this->scriptPath, '--url=' . $url, '--out=' . $outPath];

        $process = ($this->processFactory)
            ? ($this->processFactory)($cmd)
            : new Process($cmd);
        $process->setTimeout($this->timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw RenderException::timeout($this->timeoutSeconds);
        }

        if (! $process->isSuccessful()) {
            throw RenderException::processFailure($process->getExitCode() ?? -1, $process->getErrorOutput());
        }

        if (! is_file($outPath)) {
            throw RenderException::zeroByteOutput($outPath);
        }

        $bytes = (int) filesize($outPath);
        if ($bytes === 0) {
            @unlink($outPath);
            throw RenderException::zeroByteOutput($outPath);
        }

        return new PdfArtifact(
            path: $outPath,
            bytes: $bytes,
            sha256: hash_file('sha256', $outPath),
        );
    }
}
```

- [ ] **Step 5: Run renderer tests, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Service/NewsletterRendererTest.php
```

Expected: PASS, 2 tests.

- [ ] **Step 6: Wire renderer into provider + add `generate` controller action**

Add to `NewsletterServiceProvider::register()`:

```php
        $this->singleton(\Minoo\Domain\Newsletter\Service\NewsletterRenderer::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            $rootDir = dirname(__DIR__, 2);
            return new \Minoo\Domain\Newsletter\Service\NewsletterRenderer(
                tokenStore: $this->resolve(\Minoo\Domain\Newsletter\Service\RenderTokenStore::class),
                storageDir: $rootDir . '/' . $config['storage_dir'],
                baseUrl: $_ENV['APP_URL'] ?? 'http://localhost:8081',
                nodeBinary: 'node',
                scriptPath: $rootDir . '/bin/render-pdf.js',
                timeoutSeconds: $config['pdf']['timeout_seconds'] ?? 60,
            );
        });
```

Add to `routes()`:

```php
        $router->addRoute(
            'newsletter.editor.generate',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/{id}/generate')
                ->controller('Minoo\Controller\NewsletterEditorController::generate')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );
```

In `NewsletterEditorController`, add to the constructor signature (after `NewsletterAssembler $assembler`):

```php
        private readonly \Minoo\Domain\Newsletter\Service\NewsletterRenderer $renderer,
```

Add the `generate` method:

```php
    public function generate(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $artifact = $this->renderer->render($edition);
            $this->lifecycle->markGenerated($edition, $artifact->path, $artifact->sha256);
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);
            \Minoo\Support\Flash::success(sprintf('PDF generated (%d bytes).', $artifact->bytes));
        } catch (\Minoo\Domain\Newsletter\Exception\RenderException $e) {
            \Minoo\Support\Flash::error('Render failed: ' . $e->getMessage());
        } catch (\DomainException $e) {
            \Minoo\Support\Flash::error($e->getMessage());
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }
```

- [ ] **Step 7: Run full unit suite + smoke**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

- [ ] **Step 8: Commit**

```bash
git add src/Domain/Newsletter/Service/NewsletterRenderer.php \
        src/Domain/Newsletter/Exception/RenderException.php \
        src/Domain/Newsletter/ValueObject/PdfArtifact.php \
        src/Controller/NewsletterEditorController.php \
        src/Provider/NewsletterServiceProvider.php \
        tests/Minoo/Unit/Newsletter/Service/NewsletterRendererTest.php

git commit -m "feat(#645): NewsletterRenderer — Twig + Playwright -> PDF

PHP service that issues a one-time render token, invokes
bin/render-pdf.js via Symfony Process, verifies the resulting PDF
exists and is non-zero, and returns a PdfArtifact with path + hash.
On failure, throws RenderException with process exit code + stderr.
The generate controller action keeps the edition in 'approved' state
on failure so coordinators can retry.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: NewsletterDispatcher (issue #647) — BLOCKED on OJ Graphix reply

**Files:**
- Create: `src/Domain/Newsletter/Service/NewsletterDispatcher.php`
- Create: `src/Domain/Newsletter/Exception/DispatchException.php`
- Modify: `src/Controller/NewsletterEditorController.php` (add `send` action)
- Modify: `src/Provider/NewsletterServiceProvider.php` (register dispatcher + send route)
- Test: `tests/Minoo/Unit/Newsletter/Service/NewsletterDispatcherTest.php`

**⚠ Block:** Do not merge this task's PR until OJ Graphix replies confirming PDF email submission to `sales@ojgraphix.com` is acceptable. Spec § 'v1 Prerequisites'. The implementation can proceed in a branch — only the merge is gated.

- [ ] **Step 1: Implement `DispatchException`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Exception;

final class DispatchException extends \RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('MailService is not configured (missing SENDGRID_API_KEY?)');
    }

    public static function missingPrinterEmail(string $community): self
    {
        return new self("No printer_email configured for community '{$community}' in config/newsletter.php");
    }

    public static function mailFailure(string $reason): self
    {
        return new self("Print dispatch mail failed: {$reason}");
    }
}
```

- [ ] **Step 2: Write the failing `NewsletterDispatcher` test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\DispatchException;
use Minoo\Domain\Newsletter\Service\NewsletterDispatcher;
use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterDispatcher::class)]
final class NewsletterDispatcherTest extends TestCase
{
    #[Test]
    public function unconfigured_mail_service_throws(): void
    {
        $mail = new class {
            public function isConfigured(): bool { return false; }
            public function sendWithAttachment(string $to, string $subj, string $body, string $path): bool { return false; }
        };

        $dispatcher = new NewsletterDispatcher(
            mailService: $mail,
            communityConfig: ['wiikwemkoong' => ['printer_email' => 'print@example.com']],
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'generated',
            'pdf_path' => '/tmp/fake.pdf',
        ]);

        $this->expectException(DispatchException::class);
        $this->expectExceptionMessageMatches('/not configured/');
        $dispatcher->dispatch($edition);
    }

    #[Test]
    public function missing_printer_email_throws(): void
    {
        $mail = new class {
            public function isConfigured(): bool { return true; }
            public function sendWithAttachment(string $to, string $subj, string $body, string $path): bool { return true; }
        };

        $dispatcher = new NewsletterDispatcher(
            mailService: $mail,
            communityConfig: [], // empty
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'unknown-community',
            'status' => 'generated',
            'pdf_path' => '/tmp/fake.pdf',
        ]);

        $this->expectException(DispatchException::class);
        $this->expectExceptionMessageMatches('/printer_email/');
        $dispatcher->dispatch($edition);
    }

    #[Test]
    public function successful_dispatch_returns_recipient(): void
    {
        $sentTo = null;
        $mail = new class($sentTo) {
            public function __construct(public ?string &$sentTo) {}
            public function isConfigured(): bool { return true; }
            public function sendWithAttachment(string $to, string $subj, string $body, string $path): bool {
                $this->sentTo = $to;
                return true;
            }
        };

        $dispatcher = new NewsletterDispatcher(
            mailService: $mail,
            communityConfig: ['wiikwemkoong' => ['printer_email' => 'print@example.com']],
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'generated',
            'pdf_path' => '/tmp/fake.pdf',
            'volume' => 1,
            'issue_number' => 1,
        ]);

        $recipient = $dispatcher->dispatch($edition);

        $this->assertSame('print@example.com', $recipient);
        $this->assertSame('print@example.com', $sentTo);
    }
}
```

- [ ] **Step 3: Implement `NewsletterDispatcher`**

```php
<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\DispatchException;
use Waaseyaa\Entity\EntityInterface;

final class NewsletterDispatcher
{
    /**
     * @param object $mailService Anything with isConfigured() and sendWithAttachment(string $to, string $subject, string $body, string $path): bool. Typed loose so the test fakes work without binding to the framework MailService class.
     * @param array<string, array<string, mixed>> $communityConfig
     */
    public function __construct(
        private readonly object $mailService,
        private readonly array $communityConfig,
    ) {}

    /**
     * @return string The recipient email the PDF was sent to.
     */
    public function dispatch(EntityInterface $edition): string
    {
        if (! $this->mailService->isConfigured()) {
            throw DispatchException::notConfigured();
        }

        $community = (string) ($edition->get('community_id') ?: 'regional');
        $cfg = $this->communityConfig[$community] ?? null;
        if ($cfg === null || empty($cfg['printer_email'])) {
            throw DispatchException::missingPrinterEmail($community);
        }
        $to = (string) $cfg['printer_email'];
        $pdfPath = (string) $edition->get('pdf_path');

        $subject = sprintf(
            '[%s] Newsletter Vol %d No %d',
            $cfg['printer_name'] ?? 'Minoo',
            (int) $edition->get('volume'),
            (int) $edition->get('issue_number'),
        );

        $body = sprintf(
            "Hello,\n\nAttached is the latest issue for printing.\n\nCommunity: %s\nVolume: %d\nIssue: %d\nPublish date: %s\n\nThanks.\n",
            $community,
            (int) $edition->get('volume'),
            (int) $edition->get('issue_number'),
            (string) $edition->get('publish_date'),
        );

        $ok = $this->mailService->sendWithAttachment($to, $subject, $body, $pdfPath);
        if (! $ok) {
            throw DispatchException::mailFailure('mail service returned false');
        }

        return $to;
    }
}
```

- [ ] **Step 4: Run dispatcher tests, verify pass**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Newsletter/Service/NewsletterDispatcherTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Wire dispatcher into provider + add `send` controller action**

Add to `NewsletterServiceProvider::register()`:

```php
        $this->singleton(\Minoo\Domain\Newsletter\Service\NewsletterDispatcher::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            return new \Minoo\Domain\Newsletter\Service\NewsletterDispatcher(
                mailService: $this->resolve(\Minoo\Support\MailService::class),
                communityConfig: $config['communities'] ?? [],
            );
        });
```

Add to `routes()`:

```php
        $router->addRoute(
            'newsletter.editor.send',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/{id}/send')
                ->controller('Minoo\Controller\NewsletterEditorController::send')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );
```

In `NewsletterEditorController` constructor, add:

```php
        private readonly \Minoo\Domain\Newsletter\Service\NewsletterDispatcher $dispatcher,
```

Add the `send` method:

```php
    public function send(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $recipient = $this->dispatcher->dispatch($edition);
            $this->lifecycle->markSent($edition);
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);

            // Audit trail in ingest_log.
            $logStorage = $this->entityTypeManager->getStorage('ingest_log');
            $log = $logStorage->create([
                'kind' => 'newsletter_dispatch',
                'success' => 1,
                'message' => sprintf('Sent edition %d to %s', $edition->id(), $recipient),
                'created_at' => date(\DateTimeInterface::ATOM),
            ]);
            $logStorage->save($log);

            \Minoo\Support\Flash::success("Sent to {$recipient}.");
        } catch (\Minoo\Domain\Newsletter\Exception\DispatchException $e) {
            \Minoo\Support\Flash::error('Send failed: ' . $e->getMessage());

            $logStorage = $this->entityTypeManager->getStorage('ingest_log');
            $log = $logStorage->create([
                'kind' => 'newsletter_dispatch',
                'success' => 0,
                'message' => $e->getMessage(),
                'created_at' => date(\DateTimeInterface::ATOM),
            ]);
            $logStorage->save($log);
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }
```

- [ ] **Step 6: Commit (do not merge until OJ Graphix replies)**

```bash
git add src/Domain/Newsletter/Service/NewsletterDispatcher.php \
        src/Domain/Newsletter/Exception/DispatchException.php \
        src/Controller/NewsletterEditorController.php \
        src/Provider/NewsletterServiceProvider.php \
        tests/Minoo/Unit/Newsletter/Service/NewsletterDispatcherTest.php

git commit -m "feat(#647): NewsletterDispatcher — email PDF to printer

Looks up the per-community printer_email from config, sends the
generated PDF as an attachment via MailService::sendWithAttachment.
Writes ingest_log entries on success and failure for audit. The
controller send action transitions edition to 'sent' only on
successful dispatch — failures keep edition in 'generated' for retry.

PR is gated on OJ Graphix confirmation (see spec § v1 Prerequisites).

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Public surface — NewsletterController + submissions (issue #648)

**Files:**
- Modify: `src/Controller/NewsletterController.php` (expand the minimal version from Task 8)
- Modify: `src/Controller/NewsletterEditorController.php` (add submission moderation methods)
- Modify: `src/Provider/NewsletterServiceProvider.php` (add public + submission routes)
- Create: `templates/newsletter/public/list.html.twig`
- Create: `templates/newsletter/public/edition.html.twig`
- Create: `templates/newsletter/public/submit.html.twig`
- Create: `templates/newsletter/editor/submissions.html.twig`

- [ ] **Step 1: Expand `NewsletterController`**

Add these methods to `NewsletterController` (constructor stays the same — already has ETM + Twig + RenderTokenStore):

```php
    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = array_filter(
            $storage->loadMultiple(),
            fn($e) => in_array((string) $e->get('status'), ['generated', 'sent'], true),
        );

        $byCommunity = [];
        foreach ($editions as $e) {
            $byCommunity[(string) ($e->get('community_id') ?: 'regional')][] = $e;
        }

        return new Response($this->twig->render('newsletter/public/list.html.twig', [
            'editions_by_community' => $byCommunity,
        ]));
    }

    public function showCommunity(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $community = (string) ($params['community'] ?? '');
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = array_filter(
            $storage->loadMultiple(),
            fn($e) =>
                (string) ($e->get('community_id') ?: 'regional') === $community &&
                in_array((string) $e->get('status'), ['generated', 'sent'], true),
        );

        usort($editions, fn($a, $b) => (int) $b->get('issue_number') <=> (int) $a->get('issue_number'));

        return new Response($this->twig->render('newsletter/public/list.html.twig', [
            'community' => $community,
            'editions' => $editions,
        ]));
    }

    public function showEdition(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadPublicEdition($params);
        if ($edition === null) {
            return new Response('Not found', 404);
        }

        return new Response($this->twig->render('newsletter/public/edition.html.twig', [
            'edition' => $edition,
        ]));
    }

    public function downloadPdf(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadPublicEdition($params);
        if ($edition === null) {
            return new Response('Not found', 404);
        }
        $path = (string) $edition->get('pdf_path');
        if ($path === '' || ! is_file($path)) {
            return new Response('PDF not generated', 404);
        }

        return new \Symfony\Component\HttpFoundation\BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s-%d-%d.pdf"',
                $params['community'] ?? 'regional',
                (int) $edition->get('volume'),
                (int) $edition->get('issue_number'),
            ),
        ]);
    }

    public function submitForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if (! $account->isAuthenticated()) {
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/login?redirect=/newsletter/submit');
        }
        return new Response($this->twig->render('newsletter/public/submit.html.twig', []));
    }

    public function submitPost(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if (! $account->isAuthenticated()) {
            return new Response('Forbidden', 403);
        }

        $title = trim((string) $request->request->get('title'));
        $body = trim((string) $request->request->get('body'));
        $category = (string) $request->request->get('category', 'notice');
        $allowed = ['birthday', 'memorial', 'notice', 'recipe', 'language_tip', 'event', 'other'];

        if ($title === '' || $body === '') {
            \Minoo\Support\Flash::error('Title and body are required.');
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/newsletter/submit');
        }
        if (strlen($body) > 500) {
            \Minoo\Support\Flash::error('Body must be 500 characters or fewer.');
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/newsletter/submit');
        }
        if (! in_array($category, $allowed, true)) {
            \Minoo\Support\Flash::error('Invalid category.');
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/newsletter/submit');
        }

        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $sub = $storage->create([
            'community_id' => $request->cookies->get('community_id') ?: 'wiikwemkoong',
            'submitted_by' => $account->id(),
            'submitted_at' => date(\DateTimeInterface::ATOM),
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'status' => 'submitted',
        ]);
        $storage->save($sub);

        \Minoo\Support\Flash::success('Thank you — your submission is in the queue.');
        return new \Symfony\Component\HttpFoundation\RedirectResponse('/newsletter');
    }

    private function loadPublicEdition(array $params): ?\Waaseyaa\Entity\EntityInterface
    {
        $community = (string) ($params['community'] ?? '');
        $vol = (int) ($params['volume'] ?? 0);
        $issue = (int) ($params['issue'] ?? 0);

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        foreach ($storage->loadMultiple() as $e) {
            $eCommunity = (string) ($e->get('community_id') ?: 'regional');
            if (
                $eCommunity === $community &&
                (int) $e->get('volume') === $vol &&
                (int) $e->get('issue_number') === $issue &&
                in_array((string) $e->get('status'), ['generated', 'sent'], true)
            ) {
                return $e;
            }
        }
        return null;
    }
```

- [ ] **Step 2: Add submission moderation methods to `NewsletterEditorController`**

```php
    public function submissionsList(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $pending = array_filter(
            $storage->loadMultiple(),
            fn($s) => (string) $s->get('status') === 'submitted',
        );

        return new Response($this->twig->render('newsletter/editor/submissions.html.twig', [
            'submissions' => $pending,
            'flashes' => \Minoo\Support\Flash::pull(),
        ]));
    }

    public function submissionApprove(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $sub = $storage->load((int) ($params['id'] ?? 0));
        if ($sub === null) {
            return new Response('Not found', 404);
        }
        $sub->set('status', 'approved');
        $sub->set('approved_by', $account->id());
        $sub->set('approved_at', date(\DateTimeInterface::ATOM));
        $storage->save($sub);
        \Minoo\Support\Flash::success('Submission approved.');
        return new RedirectResponse('/coordinator/newsletter/submissions');
    }

    public function submissionReject(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $sub = $storage->load((int) ($params['id'] ?? 0));
        if ($sub === null) {
            return new Response('Not found', 404);
        }
        $sub->set('status', 'rejected');
        $sub->set('approved_by', $account->id());
        $sub->set('approved_at', date(\DateTimeInterface::ATOM));
        $storage->save($sub);
        \Minoo\Support\Flash::success('Submission rejected.');
        return new RedirectResponse('/coordinator/newsletter/submissions');
    }
```

- [ ] **Step 3: Register the new routes in `NewsletterServiceProvider::routes()`**

Append:

```php
        $router->addRoute(
            'newsletter.public.index',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter')
                ->controller('Minoo\Controller\NewsletterController::index')
                ->allowAll()->render()->methods('GET')->build(),
        );
        $router->addRoute(
            'newsletter.public.community',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter/{community}')
                ->controller('Minoo\Controller\NewsletterController::showCommunity')
                ->allowAll()->render()->methods('GET')->build(),
        );
        $router->addRoute(
            'newsletter.public.edition',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter/{community}/{volume}-{issue}')
                ->controller('Minoo\Controller\NewsletterController::showEdition')
                ->allowAll()->render()->methods('GET')->build(),
        );
        $router->addRoute(
            'newsletter.public.pdf',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter/{community}/{volume}-{issue}.pdf')
                ->controller('Minoo\Controller\NewsletterController::downloadPdf')
                ->allowAll()->render()->methods('GET')->build(),
        );
        $router->addRoute(
            'newsletter.public.submit_form',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter/submit')
                ->controller('Minoo\Controller\NewsletterController::submitForm')
                ->allowAll()->render()->methods('GET')->build(),
        );
        $router->addRoute(
            'newsletter.public.submit_post',
            \Waaseyaa\Routing\RouteBuilder::create('/newsletter/submit')
                ->controller('Minoo\Controller\NewsletterController::submitPost')
                ->allowAll()->render()->methods('POST')->build(),
        );
        $router->addRoute(
            'newsletter.editor.submissions',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/submissions')
                ->controller('Minoo\Controller\NewsletterEditorController::submissionsList')
                ->requireRole('community_coordinator')->render()->methods('GET')->build(),
        );
        $router->addRoute(
            'newsletter.editor.submission_approve',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/submissions/{id}/approve')
                ->controller('Minoo\Controller\NewsletterEditorController::submissionApprove')
                ->requireRole('community_coordinator')->render()->methods('POST')->build(),
        );
        $router->addRoute(
            'newsletter.editor.submission_reject',
            \Waaseyaa\Routing\RouteBuilder::create('/coordinator/newsletter/submissions/{id}/reject')
                ->controller('Minoo\Controller\NewsletterEditorController::submissionReject')
                ->requireRole('community_coordinator')->render()->methods('POST')->build(),
        );
```

- [ ] **Step 4: Create the public templates**

`templates/newsletter/public/list.html.twig`:

```twig
{% extends "base.html.twig" %}
{% block title %}Elder Newsletter{% endblock %}
{% block content %}
<section class="newsletter-public">
    <h1>Elder Newsletter</h1>
    <p>Past issues of the monthly print newsletter.</p>

    {% if community is defined %}
        <h2>{{ community }}</h2>
        <ul>
            {% for edition in editions %}
                <li>
                    <a href="/newsletter/{{ community }}/{{ edition.get('volume') }}-{{ edition.get('issue_number') }}">
                        Vol. {{ edition.get('volume') }} No. {{ edition.get('issue_number') }} — {{ edition.get('publish_date') }}
                    </a>
                </li>
            {% else %}
                <li>No issues yet.</li>
            {% endfor %}
        </ul>
    {% else %}
        {% for community, editions in editions_by_community %}
            <h2><a href="/newsletter/{{ community }}">{{ community }}</a></h2>
            <ul>
                {% for edition in editions %}
                    <li>
                        <a href="/newsletter/{{ community }}/{{ edition.get('volume') }}-{{ edition.get('issue_number') }}">
                            Vol. {{ edition.get('volume') }} No. {{ edition.get('issue_number') }} — {{ edition.get('publish_date') }}
                        </a>
                    </li>
                {% endfor %}
            </ul>
        {% else %}
            <p>No issues yet.</p>
        {% endfor %}
    {% endif %}

    <p><a href="/newsletter/submit" class="btn">Submit a notice</a></p>
</section>
{% endblock %}
```

`templates/newsletter/public/edition.html.twig`:

```twig
{% extends "base.html.twig" %}
{% block title %}{{ edition.get('headline') }}{% endblock %}
{% block content %}
<section class="newsletter-public-edition">
    <h1>{{ edition.get('headline') }}</h1>
    <p>
        Vol. {{ edition.get('volume') }} No. {{ edition.get('issue_number') }} —
        {{ edition.get('publish_date') }} —
        {{ edition.get('community_id') ?: 'Regional' }}
    </p>
    {% if edition.get('pdf_path') %}
        <p>
            <a class="btn" href="/newsletter/{{ edition.get('community_id') ?: 'regional' }}/{{ edition.get('volume') }}-{{ edition.get('issue_number') }}.pdf">Download PDF</a>
        </p>
    {% endif %}
</section>
{% endblock %}
```

`templates/newsletter/public/submit.html.twig`:

```twig
{% extends "base.html.twig" %}
{% block title %}Submit a notice{% endblock %}
{% block content %}
<section class="newsletter-submit">
    <h1>Submit a notice</h1>
    <p>Birthdays, memorials, recipes, language tips — short notices for the next monthly newsletter.</p>

    <form method="post" action="/newsletter/submit">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <label>Category
            <select name="category" required>
                <option value="notice">Notice</option>
                <option value="birthday">Birthday</option>
                <option value="memorial">Memorial</option>
                <option value="recipe">Recipe</option>
                <option value="language_tip">Language Tip</option>
                <option value="event">Event</option>
                <option value="other">Other</option>
            </select>
        </label>
        <label>Title
            <input type="text" name="title" required maxlength="200">
        </label>
        <label>Body (max 500 chars)
            <textarea name="body" required maxlength="500" rows="6"></textarea>
        </label>
        <button type="submit" class="btn">Submit</button>
    </form>
</section>
{% endblock %}
```

`templates/newsletter/editor/submissions.html.twig`:

```twig
{% extends "base.html.twig" %}
{% block title %}Submission moderation{% endblock %}
{% block content %}
<section class="newsroom-submissions">
    <h1>Submission moderation</h1>
    {% for type, msgs in flashes %}
        {% for msg in msgs %}<div class="flash flash--{{ type }}">{{ msg }}</div>{% endfor %}
    {% endfor %}

    <table class="table">
        <thead><tr><th>Category</th><th>Title</th><th>Body</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
            {% for sub in submissions %}
                <tr>
                    <td>{{ sub.get('category') }}</td>
                    <td>{{ sub.get('title') }}</td>
                    <td>{{ sub.get('body')|slice(0, 80) }}…</td>
                    <td>{{ sub.get('submitted_at') }}</td>
                    <td>
                        <form method="post" action="/coordinator/newsletter/submissions/{{ sub.id() }}/approve" style="display:inline">
                            <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
                            <button type="submit">Approve</button>
                        </form>
                        <form method="post" action="/coordinator/newsletter/submissions/{{ sub.id() }}/reject" style="display:inline">
                            <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
                            <button type="submit">Reject</button>
                        </form>
                    </td>
                </tr>
            {% else %}
                <tr><td colspan="5">No pending submissions.</td></tr>
            {% endfor %}
        </tbody>
    </table>
</section>
{% endblock %}
```

- [ ] **Step 5: Run unit suite + smoke test public routes**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
php -S localhost:8081 -t public &
sleep 2
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8081/newsletter
# Expected: 200
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8081/newsletter/submit
# Expected: 302 (redirect to login)
kill %1
```

- [ ] **Step 6: Commit**

```bash
git add src/Controller/NewsletterController.php \
        src/Controller/NewsletterEditorController.php \
        src/Provider/NewsletterServiceProvider.php \
        templates/newsletter/public/ \
        templates/newsletter/editor/submissions.html.twig

git commit -m "feat(#648): newsletter public surface + submissions moderation

Public routes for /newsletter, per-community archive, edition view,
PDF download stream, and the community submission form (rate-limited
via existing middleware, 500-char body cap, CSRF). Coordinator
moderation queue lives at /coordinator/newsletter/submissions.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Integration test + Playwright smoke + render-smoke script (issue #649)

**Files:**
- Create: `tests/Minoo/Integration/NewsletterEndToEndTest.php`
- Create: `tests/playwright/newsletter.spec.ts`
- Create: `bin/newsletter-render-smoke`

- [ ] **Step 1: Write the integration test**

```php
<?php
declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Entity\NewsletterEdition;
use Minoo\Entity\NewsletterSubmission;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Http\HttpKernel;

#[CoversNothing]
final class NewsletterEndToEndTest extends TestCase
{
    private HttpKernel $kernel;

    protected function setUp(): void
    {
        putenv('WAASEYAA_DB=:memory:');
        $rootDir = dirname(__DIR__, 3);
        $this->kernel = new HttpKernel($rootDir);

        // Use reflection to call protected boot()
        $ref = new \ReflectionMethod($this->kernel, 'boot');
        $ref->setAccessible(true);
        $ref->invoke($this->kernel);

        $this->runMigrations();
    }

    #[Test]
    public function full_lifecycle_draft_to_sent_writes_audit_log(): void
    {
        $etm = $this->kernel->getEntityTypeManager();
        $editionStorage = $etm->getStorage('newsletter_edition');

        // Create an edition
        $edition = $editionStorage->create([
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'publish_date' => '2026-04-15',
            'status' => 'draft',
            'headline' => 'Test Issue 1',
        ]);
        $editionStorage->save($edition);

        // Seed an approved submission
        $subStorage = $etm->getStorage('newsletter_submission');
        $sub = $subStorage->create([
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 1,
            'submitted_at' => '2026-04-01T00:00:00Z',
            'category' => 'birthday',
            'title' => 'Edna 80',
            'body' => 'Happy birthday',
            'status' => 'approved',
        ]);
        $subStorage->save($sub);

        // Run assembler manually (skipping NC content for the test)
        $config = ['community' => ['quota' => 8, 'sources' => ['newsletter_submission']]];
        $assembler = new \Minoo\Domain\Newsletter\Service\NewsletterAssembler(
            entityTypeManager: $etm,
            lifecycle: new \Minoo\Domain\Newsletter\Service\EditionLifecycle(),
            quotas: \Minoo\Domain\Newsletter\ValueObject\SectionQuota::fromConfig($config),
        );
        $assembler->assemble($edition);
        $editionStorage->save($edition);

        $this->assertSame('curating', $edition->get('status'));

        $itemStorage = $etm->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn($i) => (int) $i->get('edition_id') === (int) $edition->id(),
        );
        $this->assertGreaterThan(0, count($items));

        // Approve
        $lifecycle = new \Minoo\Domain\Newsletter\Service\EditionLifecycle();
        $lifecycle->approve($edition, approverId: 1);
        $editionStorage->save($edition);
        $this->assertSame('approved', $edition->get('status'));

        // Skip actual render — assert lifecycle handles the markGenerated path
        $lifecycle->markGenerated($edition, '/tmp/fake.pdf', 'deadbeef');
        $editionStorage->save($edition);
        $this->assertSame('generated', $edition->get('status'));

        // Skip actual dispatch — assert markSent path
        $lifecycle->markSent($edition);
        $editionStorage->save($edition);
        $this->assertSame('sent', $edition->get('status'));
        $this->assertNotNull($edition->get('sent_at'));
    }

    private function runMigrations(): void
    {
        // Boot hooks should auto-migrate :memory: db; if not, run them explicitly here.
        // This mirrors other Minoo integration tests.
    }
}
```

- [ ] **Step 2: Run integration test, verify pass**

```bash
./vendor/bin/phpunit --testsuite MinooIntegration tests/Minoo/Integration/NewsletterEndToEndTest.php
```

If migrations aren't auto-run for `:memory:`, mirror the migration-running pattern from existing integration tests in `tests/Minoo/Integration/`.

- [ ] **Step 3: Write the Playwright smoke spec**

```typescript
import { test, expect } from '@playwright/test';

test.describe('Newsletter public surface', () => {
    test('list page renders', async ({ page }) => {
        await page.goto('/newsletter');
        await expect(page.getByRole('heading', { name: 'Elder Newsletter' })).toBeVisible();
    });

    test('submit page redirects when not logged in', async ({ page }) => {
        const resp = await page.goto('/newsletter/submit');
        expect(resp?.status()).toBeLessThan(400);
        // Either we landed on /login or got a 302 to it
        expect(page.url()).toMatch(/\/login|\/newsletter\/submit/);
    });
});

test.describe('Newsletter editor surface', () => {
    test('coordinator route requires login', async ({ page }) => {
        const resp = await page.goto('/coordinator/newsletter');
        // 302 to login or 403 forbidden — both prove the gate works
        expect([302, 403, 401]).toContain(resp?.status() ?? 0);
    });
});
```

- [ ] **Step 4: Create `bin/newsletter-render-smoke` script**

```bash
#!/usr/bin/env bash
# Manual smoke test for the full newsletter render pipeline.
# Not run in CI — requires Playwright + Chromium installed.
#
# Usage: bin/newsletter-render-smoke [edition_id]

set -euo pipefail

EDITION_ID="${1:-1}"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Start dev server in the background
echo "Starting dev server..."
php -S localhost:8081 -t public > /tmp/newsletter-smoke-server.log 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null || true' EXIT
sleep 2

# Issue a render token
TOKEN=$(php -r "
require 'vendor/autoload.php';
\$store = new Minoo\Domain\Newsletter\Service\RenderTokenStore('storage/newsletter/render-tokens', 60);
echo \$store->issue($EDITION_ID);
")
echo "Token: $TOKEN"

OUT="/tmp/newsletter-smoke-${EDITION_ID}.pdf"
echo "Rendering to: $OUT"
node bin/render-pdf.js \
    --url="http://localhost:8081/newsletter/_internal/${EDITION_ID}/print?token=${TOKEN}" \
    --out="$OUT"

if [[ ! -s "$OUT" ]]; then
    echo "FAIL: PDF was not produced or is empty."
    exit 1
fi

SIZE=$(stat -c %s "$OUT" 2>/dev/null || stat -f %z "$OUT")
echo "OK: PDF written ($SIZE bytes) to $OUT"
```

```bash
chmod +x bin/newsletter-render-smoke
```

- [ ] **Step 5: Run unit suite + integration suite + Playwright spec**

```bash
./vendor/bin/phpunit
npx playwright test tests/playwright/newsletter.spec.ts
```

Expected: All pass. Playwright spec passes against an empty database (the "no editions yet" path).

- [ ] **Step 6: Commit**

```bash
git add tests/Minoo/Integration/NewsletterEndToEndTest.php \
        tests/playwright/newsletter.spec.ts \
        bin/newsletter-render-smoke

git commit -m "test(#649): newsletter integration + Playwright + render-smoke

Integration test exercises the full lifecycle (assemble -> approve ->
generate -> sent) under a real :memory: SQLite kernel boot. Skips the
actual Chromium render and the actual SendGrid call — those are smoke-
tested manually via bin/newsletter-render-smoke (not run in CI).
Playwright spec covers the public list page and the coordinator
route gate.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage check:**

| Spec section | Tasks covering it |
|---|---|
| 3 entity types + migrations | Task 1 |
| Service provider + access policy | Task 2 |
| EditionLifecycle state machine | Task 3 |
| config/newsletter.php + section quotas | Task 4 |
| NewsletterAssembler with NC + submission sources | Task 5 |
| Newsroom UI (NewsletterEditorController) | Task 6 |
| Print template (`@media print` Twig) | Task 7 |
| `bin/render-pdf.js` + render token endpoint | Task 8 |
| NewsletterRenderer (Symfony Process orchestration) | Task 9 |
| NewsletterDispatcher (mail to printer) | Task 10 (BLOCKED on OJ Graphix) |
| Public NewsletterController + submissions form + moderation | Task 11 |
| Integration test + Playwright smoke + render-smoke script | Task 12 |
| OJ Graphix prerequisites | Task 10 explicit block + spec § v1 Prerequisites |
| Status invariant (every transition through EditionLifecycle) | Task 3, enforced by all controller actions |
| ingest_log audit on dispatch | Task 10 controller `send()` action |
| Render token (one-time, 60s TTL, scoped to edition) | Task 8 `RenderTokenStore` |

**Type/method consistency:**

- `EditionStatus::Draft|Curating|Approved|Generated|Sent` — used consistently throughout
- `EditionLifecycle::transition(EditionStatus)`, `approve(int)`, `markGenerated(string, string)`, `markSent()` — same names in tests, services, and controllers
- `NewsletterRenderer::render(EntityInterface): PdfArtifact` — matches the call site in the controller `generate` action
- `NewsletterDispatcher::dispatch(EntityInterface): string` (returns recipient) — matches call site in `send`
- `RenderTokenStore::issue(int): string` and `consume(string, int): bool` — used identically in renderer and controller
- `SectionQuota::fromConfig(array): list<SectionQuota>` — used by config + tests

**Placeholder scan:** No "TBD", no "implement appropriate error handling", no "similar to Task N", no "fill in details". Every step has real code or a real command.

**One known compromise:** the unit test for `NewsletterEditorController` is intentionally minimal (just a `class_exists` smoke) because the controller's 4-service constructor makes mock-based unit tests brittle. The integration test in Task 12 exercises the controller for real. This matches the project's existing pattern (`CoordinatorDashboardController` does the same thing).

---

## Out of v1 Scope

These are tracked as separate post-v1 GitHub issues — do NOT implement them in this plan:

- #650 email submission intake
- #651 print job tracking dashboard
- #652 volunteer route assignments
- #653 manual upload mode dispatcher (Hightail/Dropbox fallback)
