<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterAssembler;
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
}
