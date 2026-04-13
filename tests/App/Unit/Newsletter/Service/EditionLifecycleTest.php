<?php
declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Service;

use App\Domain\Newsletter\Exception\InvalidStateTransition;
use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use App\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditionLifecycle::class)]
final class EditionLifecycleTest extends TestCase
{
    private EditionLifecycle $lifecycle;

    protected function setUp(): void
    {
        $this->lifecycle = new EditionLifecycle();
    }

    private function edition(string $status = 'draft'): NewsletterEdition
    {
        return new NewsletterEdition(['neid' => 1, 'status' => $status]);
    }

    // ------------------------------------------------------------------
    // Happy-path forward transitions
    // ------------------------------------------------------------------

    #[Test]
    public function full_forward_chain_succeeds(): void
    {
        $edition = $this->edition();

        $this->lifecycle->transition($edition, EditionStatus::Curating);
        $this->assertSame('curating', $edition->get('status'));

        $this->lifecycle->transition($edition, EditionStatus::Approved);
        $this->assertSame('approved', $edition->get('status'));

        $this->lifecycle->transition($edition, EditionStatus::Generated);
        $this->assertSame('generated', $edition->get('status'));

        $this->lifecycle->transition($edition, EditionStatus::Sent);
        $this->assertSame('sent', $edition->get('status'));
    }

    // ------------------------------------------------------------------
    // Back-bounce transitions
    // ------------------------------------------------------------------

    #[Test]
    public function back_bounce_curating_to_draft(): void
    {
        $edition = $this->edition('curating');
        $this->lifecycle->transition($edition, EditionStatus::Draft);
        $this->assertSame('draft', $edition->get('status'));
    }

    #[Test]
    public function back_bounce_approved_to_curating(): void
    {
        $edition = $this->edition('approved');
        $this->lifecycle->transition($edition, EditionStatus::Curating);
        $this->assertSame('curating', $edition->get('status'));
    }

    #[Test]
    public function back_bounce_generated_to_approved(): void
    {
        $edition = $this->edition('generated');
        $this->lifecycle->transition($edition, EditionStatus::Approved);
        $this->assertSame('approved', $edition->get('status'));
    }

    // ------------------------------------------------------------------
    // Illegal skip transitions (data-driven)
    // ------------------------------------------------------------------

    /** @return array<string, array{string, EditionStatus}> */
    public static function illegalSkipProvider(): array
    {
        return [
            'draft → approved'   => ['draft', EditionStatus::Approved],
            'draft → generated'  => ['draft', EditionStatus::Generated],
            'draft → sent'       => ['draft', EditionStatus::Sent],
            'curating → generated' => ['curating', EditionStatus::Generated],
            'curating → sent'    => ['curating', EditionStatus::Sent],
            'approved → sent'    => ['approved', EditionStatus::Sent],
        ];
    }

    #[Test]
    #[DataProvider('illegalSkipProvider')]
    public function skipping_states_throws(string $fromStatus, EditionStatus $to): void
    {
        $edition = $this->edition($fromStatus);

        $this->expectException(InvalidStateTransition::class);
        $this->lifecycle->transition($edition, $to);
    }

    // ------------------------------------------------------------------
    // Sent is terminal — no transitions out
    // ------------------------------------------------------------------

    /** @return array<string, array{EditionStatus}> */
    public static function allStatesProvider(): array
    {
        return [
            'draft'     => [EditionStatus::Draft],
            'curating'  => [EditionStatus::Curating],
            'approved'  => [EditionStatus::Approved],
            'generated' => [EditionStatus::Generated],
        ];
    }

    #[Test]
    #[DataProvider('allStatesProvider')]
    public function sent_is_terminal_no_transition_allowed(EditionStatus $target): void
    {
        $edition = $this->edition('sent');

        $this->expectException(InvalidStateTransition::class);
        $this->lifecycle->transition($edition, $target);
    }

    // ------------------------------------------------------------------
    // Idempotent same-state transitions (no-op)
    // ------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function allStatusStringsProvider(): array
    {
        return [
            'draft'     => ['draft'],
            'curating'  => ['curating'],
            'approved'  => ['approved'],
            'generated' => ['generated'],
            'sent'      => ['sent'],
        ];
    }

    #[Test]
    #[DataProvider('allStatusStringsProvider')]
    public function same_state_transition_is_noop(string $status): void
    {
        $edition = $this->edition($status);

        // Should NOT throw — idempotent no-op
        $this->lifecycle->transition($edition, EditionStatus::from($status));
        $this->assertSame($status, $edition->get('status'));
    }

    // ------------------------------------------------------------------
    // approve() — metadata correctness
    // ------------------------------------------------------------------

    #[Test]
    public function approve_sets_metadata(): void
    {
        $edition = $this->edition('curating');

        $this->lifecycle->approve($edition, approverId: 42);

        $this->assertSame('approved', $edition->get('status'));
        $this->assertSame(42, $edition->get('approved_by'));

        $approvedAt = $edition->get('approved_at');
        $this->assertNotNull($approvedAt);
        // Must be valid ATOM format
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $approvedAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
    }

    #[Test]
    public function approve_from_wrong_state_throws_and_writes_no_metadata(): void
    {
        $edition = $this->edition('draft');

        try {
            $this->lifecycle->approve($edition, approverId: 42);
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame('draft', $edition->get('status'));
        $this->assertNull($edition->get('approved_by'));
        $this->assertNull($edition->get('approved_at'));
    }

    #[Test]
    public function approve_is_idempotent_preserves_original_metadata(): void
    {
        $edition = $this->edition('curating');

        $this->lifecycle->approve($edition, approverId: 42);
        $originalApprovedAt = $edition->get('approved_at');
        $this->assertSame(42, $edition->get('approved_by'));

        // Second call with different approver — should no-op, preserving original
        $this->lifecycle->approve($edition, approverId: 99);
        $this->assertSame(42, $edition->get('approved_by'));
        $this->assertSame($originalApprovedAt, $edition->get('approved_at'));
    }

    // ------------------------------------------------------------------
    // markGenerated() — metadata correctness
    // ------------------------------------------------------------------

    #[Test]
    public function mark_generated_sets_pdf_metadata(): void
    {
        $edition = $this->edition('approved');

        $this->lifecycle->markGenerated($edition, '/storage/newsletter/v1-1.pdf', 'abc123def456');

        $this->assertSame('generated', $edition->get('status'));
        $this->assertSame('/storage/newsletter/v1-1.pdf', $edition->get('pdf_path'));
        $this->assertSame('abc123def456', $edition->get('pdf_hash'));
    }

    #[Test]
    public function mark_generated_from_wrong_state_throws_and_writes_no_metadata(): void
    {
        $edition = $this->edition('curating');

        try {
            $this->lifecycle->markGenerated($edition, '/tmp/issue.pdf', 'hash');
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame('curating', $edition->get('status'));
        $this->assertNull($edition->get('pdf_path'));
        $this->assertNull($edition->get('pdf_hash'));
    }

    #[Test]
    public function mark_generated_is_idempotent_preserves_original_metadata(): void
    {
        $edition = $this->edition('approved');

        $this->lifecycle->markGenerated($edition, '/first.pdf', 'hash1');
        $this->assertSame('/first.pdf', $edition->get('pdf_path'));
        $this->assertSame('hash1', $edition->get('pdf_hash'));

        // Second call with different values — should no-op
        $this->lifecycle->markGenerated($edition, '/second.pdf', 'hash2');
        $this->assertSame('/first.pdf', $edition->get('pdf_path'));
        $this->assertSame('hash1', $edition->get('pdf_hash'));
    }

    // ------------------------------------------------------------------
    // markSent() — metadata correctness
    // ------------------------------------------------------------------

    #[Test]
    public function mark_sent_sets_sent_at(): void
    {
        $edition = $this->edition('generated');

        $this->lifecycle->markSent($edition);

        $this->assertSame('sent', $edition->get('status'));
        $sentAt = $edition->get('sent_at');
        $this->assertNotNull($sentAt);
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $sentAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
    }

    #[Test]
    public function mark_sent_from_wrong_state_throws_and_writes_no_metadata(): void
    {
        $edition = $this->edition('approved');

        try {
            $this->lifecycle->markSent($edition);
            $this->fail('Expected InvalidStateTransition');
        } catch (InvalidStateTransition) {
            // expected
        }

        $this->assertSame('approved', $edition->get('status'));
        $this->assertNull($edition->get('sent_at'));
    }

    #[Test]
    public function mark_sent_is_idempotent_preserves_original_timestamp(): void
    {
        $edition = $this->edition('generated');

        $this->lifecycle->markSent($edition);
        $originalSentAt = $edition->get('sent_at');
        $this->assertNotNull($originalSentAt);

        // Second call — should no-op, preserving original timestamp
        $this->lifecycle->markSent($edition);
        $this->assertSame($originalSentAt, $edition->get('sent_at'));
    }

    // ------------------------------------------------------------------
    // EditionStatus::fromEntity defaults
    // ------------------------------------------------------------------

    #[Test]
    public function fromEntity_defaults_to_draft_on_null_status(): void
    {
        $edition = new NewsletterEdition(['neid' => 1]);
        $this->assertSame(EditionStatus::Draft, EditionStatus::fromEntity($edition));
    }

    #[Test]
    public function fromEntity_defaults_to_draft_on_empty_string(): void
    {
        $edition = new NewsletterEdition(['neid' => 1, 'status' => '']);
        $this->assertSame(EditionStatus::Draft, EditionStatus::fromEntity($edition));
    }
}
