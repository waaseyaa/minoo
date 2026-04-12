<?php
declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Service;

use App\Domain\Newsletter\Exception\InvalidStateTransition;
use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use App\Entity\NewsletterEdition;
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

    #[Test]
    public function approve_from_wrong_state_throws_and_writes_no_metadata(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'draft', // approve requires curating
        ]);

        try {
            $lifecycle->approve($edition, approverId: 42);
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame('draft', $edition->get('status'));
        $this->assertNull($edition->get('approved_by'));
        $this->assertNull($edition->get('approved_at'));
    }

    #[Test]
    public function mark_generated_happy_path_sets_pdf_metadata(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'approved',
        ]);

        $lifecycle->markGenerated($edition, '/tmp/issue.pdf', 'deadbeefcafef00d');

        $this->assertSame('generated', $edition->get('status'));
        $this->assertSame('/tmp/issue.pdf', $edition->get('pdf_path'));
        $this->assertSame('deadbeefcafef00d', $edition->get('pdf_hash'));
    }

    #[Test]
    public function mark_generated_from_wrong_state_throws_and_writes_no_metadata(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'curating', // generate requires approved
        ]);

        try {
            $lifecycle->markGenerated($edition, '/tmp/issue.pdf', 'hash');
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame('curating', $edition->get('status'));
        $this->assertNull($edition->get('pdf_path'));
        $this->assertNull($edition->get('pdf_hash'));
    }

    #[Test]
    public function mark_sent_from_wrong_state_throws_and_writes_no_metadata(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'approved', // send requires generated
        ]);

        try {
            $lifecycle->markSent($edition);
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame('approved', $edition->get('status'));
        $this->assertNull($edition->get('sent_at'));
    }

    #[Test]
    public function backward_bounce_curating_to_draft_is_allowed(): void
    {
        $lifecycle = new EditionLifecycle();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'status' => 'curating',
        ]);

        $lifecycle->transition($edition, EditionStatus::Draft);

        $this->assertSame('draft', $edition->get('status'));
    }

    #[Test]
    public function fromEntity_defaults_to_draft_on_null_status(): void
    {
        $edition = new NewsletterEdition([
            'neid' => 1,
            // no status field at all
        ]);

        $this->assertSame(EditionStatus::Draft, EditionStatus::fromEntity($edition));
    }
}
