<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Newsletter\Exception\InvalidStateTransition;
use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterAssembler;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use App\Domain\Newsletter\ValueObject\SectionQuota;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class NewsletterEndToEndTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        // tests/Minoo/Integration/ → 3 levels up to project root.
        self::$projectRoot = dirname(__DIR__, 3);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    private function createEdition(string $community, int $issue = 1): \Waaseyaa\Entity\EntityInterface
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('newsletter_edition');
        $edition = $storage->create([
            'community_id' => $community,
            'volume' => 1,
            'issue_number' => $issue,
            'publish_date' => '2026-05-01',
            'status' => 'draft',
            'headline' => "Test Issue {$issue}",
        ]);
        $storage->save($edition);
        return $edition;
    }

    private function createSubmission(string $community, string $title, string $status = 'submitted'): \Waaseyaa\Entity\EntityInterface
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('newsletter_submission');
        $sub = $storage->create([
            'community_id' => $community,
            'submitted_by' => 1,
            'submitted_at' => '2026-04-01T00:00:00+00:00',
            'category' => 'announcement',
            'title' => $title,
            'body' => "Body of: {$title}",
            'status' => $status,
        ]);
        $storage->save($sub);
        return $sub;
    }

    private function assembleWithCommunityQuota(\Waaseyaa\Entity\EntityInterface $edition, int $quota = 8): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $lifecycle = new EditionLifecycle();
        $quotas = SectionQuota::fromConfig([
            'community' => ['quota' => $quota, 'sources' => ['newsletter_submission']],
        ]);
        $assembler = new NewsletterAssembler(
            entityTypeManager: $etm,
            lifecycle: $lifecycle,
            quotas: $quotas,
        );
        $assembler->assemble($edition);
        $etm->getStorage('newsletter_edition')->save($edition);
    }

    private function itemsForEdition(\Waaseyaa\Entity\EntityInterface $edition): array
    {
        $etm = self::$kernel->getEntityTypeManager();
        $itemStorage = $etm->getStorage('newsletter_item');
        return array_values(array_filter(
            $itemStorage->loadMultiple(),
            static fn($i) => (string) $i->get('edition_id') === (string) $edition->id(),
        ));
    }

    #[Test]
    public function assembler_includes_only_approved_submissions(): void
    {
        $this->createSubmission('wiikwemkoong', 'Approved one', 'approved');
        $this->createSubmission('wiikwemkoong', 'Still pending', 'submitted');
        $this->createSubmission('wiikwemkoong', 'Was rejected', 'rejected');

        $edition = $this->createEdition('wiikwemkoong', issue: 10);
        $this->assembleWithCommunityQuota($edition);

        $items = $this->itemsForEdition($edition);
        $blurbs = array_map(fn($i) => (string) $i->get('editor_blurb'), $items);

        $this->assertContains('Approved one', $blurbs, 'Approved submission should be included');
        $this->assertNotContains('Still pending', $blurbs, 'Pending submission must be excluded');
        $this->assertNotContains('Was rejected', $blurbs, 'Rejected submission must be excluded');
    }

    #[Test]
    public function assembler_excludes_submissions_from_other_communities(): void
    {
        $this->createSubmission('sheguiandah', 'From Sheguiandah', 'approved');

        $edition = $this->createEdition('wiikwemkoong', issue: 11);
        $this->assembleWithCommunityQuota($edition);

        $items = $this->itemsForEdition($edition);
        $blurbs = array_map(fn($i) => (string) $i->get('editor_blurb'), $items);

        $this->assertNotContains('From Sheguiandah', $blurbs, 'Submission from different community must be excluded');
    }

    #[Test]
    public function assembler_is_idempotent_on_rerun(): void
    {
        $this->createSubmission('wiikwemkoong', 'Idempotent test', 'approved');

        $edition = $this->createEdition('wiikwemkoong', issue: 12);
        $this->assembleWithCommunityQuota($edition);

        $firstRunItems = $this->itemsForEdition($edition);
        $firstCount = count($firstRunItems);
        $this->assertGreaterThan(0, $firstCount);

        // Reset to draft for re-assembly
        $lifecycle = new EditionLifecycle();
        $lifecycle->transition($edition, \App\Domain\Newsletter\ValueObject\EditionStatus::Draft);
        self::$kernel->getEntityTypeManager()->getStorage('newsletter_edition')->save($edition);

        $this->assembleWithCommunityQuota($edition);

        $secondRunItems = $this->itemsForEdition($edition);
        $this->assertCount($firstCount, $secondRunItems, 'Re-assembly should produce same item count');
    }

    #[Test]
    public function submission_approval_makes_it_available_to_assembler(): void
    {
        // Create a pending submission
        $sub = $this->createSubmission('wiikwemkoong', 'Pending then approved', 'submitted');

        $edition = $this->createEdition('wiikwemkoong', issue: 13);
        $this->assembleWithCommunityQuota($edition);

        $items = $this->itemsForEdition($edition);
        $blurbs = array_map(fn($i) => (string) $i->get('editor_blurb'), $items);
        $this->assertNotContains('Pending then approved', $blurbs, 'Pending should not appear');

        // Approve the submission
        $sub->set('status', 'approved');
        $sub->set('approved_by', 5);
        $sub->set('approved_at', date(\DateTimeInterface::ATOM));
        self::$kernel->getEntityTypeManager()->getStorage('newsletter_submission')->save($sub);

        // Reset edition and re-assemble
        $lifecycle = new EditionLifecycle();
        $lifecycle->transition($edition, \App\Domain\Newsletter\ValueObject\EditionStatus::Draft);
        self::$kernel->getEntityTypeManager()->getStorage('newsletter_edition')->save($edition);

        $this->assembleWithCommunityQuota($edition);

        $items = $this->itemsForEdition($edition);
        $blurbs = array_map(fn($i) => (string) $i->get('editor_blurb'), $items);
        $this->assertContains('Pending then approved', $blurbs, 'After approval, submission should appear');
    }

    #[Test]
    public function full_lifecycle_draft_to_sent(): void
    {
        $etm = self::$kernel->getEntityTypeManager();

        // 1. Seed a draft edition for a community.
        $editionStorage = $etm->getStorage('newsletter_edition');
        $edition = $editionStorage->create([
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 1,
            'publish_date' => '2026-04-15',
            'status' => 'draft',
            'headline' => 'Test Issue 1',
        ]);
        $editionStorage->save($edition);
        $this->assertNotNull($edition->id(), 'Edition should persist and receive an ID.');
        $this->assertSame('draft', $edition->get('status'));

        // 2. Seed an approved community submission.
        $subStorage = $etm->getStorage('newsletter_submission');
        $sub = $subStorage->create([
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 1,
            'submitted_at' => '2026-04-01T00:00:00+00:00',
            'category' => 'birthday',
            'title' => 'Edna turns 80',
            'body' => 'Happy 80th birthday, Edna.',
            'status' => 'approved',
        ]);
        $subStorage->save($sub);
        $this->assertNotNull($sub->id());

        // 3. Run the assembler manually with a minimal quota config.
        // Skips NorthCloud content sources — this test only exercises the
        // submission path and the lifecycle wiring.
        $lifecycle = new EditionLifecycle();
        $quotas = SectionQuota::fromConfig([
            'community' => ['quota' => 8, 'sources' => ['newsletter_submission']],
        ]);
        $assembler = new NewsletterAssembler(
            entityTypeManager: $etm,
            lifecycle: $lifecycle,
            quotas: $quotas,
        );
        $assembler->assemble($edition);
        $editionStorage->save($edition);

        $this->assertSame(
            'curating',
            $edition->get('status'),
            'Assembler should transition draft → curating when items are produced.',
        );

        // 4. Assert at least one item was written for this edition.
        $itemStorage = $etm->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            static fn($i) => (string) $i->get('edition_id') === (string) $edition->id(),
        );
        $this->assertGreaterThan(0, count($items), 'Assembler should write newsletter_item rows.');

        // 5. Approve the edition.
        $lifecycle->approve($edition, approverId: 1);
        $editionStorage->save($edition);
        $this->assertSame('approved', $edition->get('status'));
        $this->assertSame(1, (int) $edition->get('approved_by'));
        $this->assertNotEmpty((string) $edition->get('approved_at'));

        // 6. Skip actual Chromium render — just assert the lifecycle wiring
        // handles the markGenerated path with a fake path + hash.
        $lifecycle->markGenerated($edition, '/tmp/fake-edition.pdf', 'deadbeef');
        $editionStorage->save($edition);
        $this->assertSame('generated', $edition->get('status'));
        $this->assertSame('/tmp/fake-edition.pdf', (string) $edition->get('pdf_path'));
        $this->assertSame('deadbeef', (string) $edition->get('pdf_hash'));

        // 7. Skip actual SendGrid dispatch — assert markSent wiring.
        $lifecycle->markSent($edition);
        $editionStorage->save($edition);
        $this->assertSame('sent', $edition->get('status'));
        $this->assertNotEmpty((string) $edition->get('sent_at'));
    }

    #[Test]
    public function lifecycle_metadata_persists_across_reload(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('newsletter_edition');
        $lifecycle = new EditionLifecycle();

        $edition = $storage->create([
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 50,
            'publish_date' => '2026-06-01',
            'status' => 'draft',
            'headline' => 'Persistence test',
        ]);
        $storage->save($edition);

        // Walk the full chain, saving at each step
        $lifecycle->transition($edition, EditionStatus::Curating);
        $storage->save($edition);

        $lifecycle->approve($edition, approverId: 7);
        $storage->save($edition);

        $lifecycle->markGenerated($edition, '/storage/newsletter/persist-test.pdf', 'sha256abc');
        $storage->save($edition);

        $lifecycle->markSent($edition);
        $storage->save($edition);

        // Reload from DB and verify all metadata survived
        $reloaded = $storage->load($edition->id());
        $this->assertSame('sent', $reloaded->get('status'));
        $this->assertSame(7, (int) $reloaded->get('approved_by'));
        $this->assertNotEmpty((string) $reloaded->get('approved_at'));
        $this->assertSame('/storage/newsletter/persist-test.pdf', (string) $reloaded->get('pdf_path'));
        $this->assertSame('sha256abc', (string) $reloaded->get('pdf_hash'));
        $this->assertNotEmpty((string) $reloaded->get('sent_at'));
    }

    #[Test]
    public function illegal_skip_does_not_corrupt_persisted_state(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('newsletter_edition');
        $lifecycle = new EditionLifecycle();

        $edition = $storage->create([
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 51,
            'publish_date' => '2026-06-01',
            'status' => 'draft',
            'headline' => 'Skip guard test',
        ]);
        $storage->save($edition);

        try {
            $lifecycle->transition($edition, EditionStatus::Sent);
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        // Save after failed transition — status must remain draft
        $storage->save($edition);
        $reloaded = $storage->load($edition->id());
        $this->assertSame('draft', $reloaded->get('status'));
    }

    #[Test]
    public function idempotent_approve_preserves_metadata_after_persist(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('newsletter_edition');
        $lifecycle = new EditionLifecycle();

        $edition = $storage->create([
            'community_id' => 'wiikwemkoong',
            'volume' => 1,
            'issue_number' => 52,
            'publish_date' => '2026-06-01',
            'status' => 'draft',
            'headline' => 'Idempotent approve test',
        ]);
        $storage->save($edition);

        $lifecycle->transition($edition, EditionStatus::Curating);
        $lifecycle->approve($edition, approverId: 3);
        $storage->save($edition);

        $originalApprovedAt = $edition->get('approved_at');

        // Reload and approve again — should be idempotent
        $reloaded = $storage->load($edition->id());
        $lifecycle->approve($reloaded, approverId: 99);
        $storage->save($reloaded);

        $finalReload = $storage->load($edition->id());
        $this->assertSame('approved', $finalReload->get('status'));
        $this->assertSame(3, (int) $finalReload->get('approved_by'));
        $this->assertSame((string) $originalApprovedAt, (string) $finalReload->get('approved_at'));
    }
}
