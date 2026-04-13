<?php
declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Entity;

use App\Entity\NewsletterSubmission;
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

    #[Test]
    public function approval_sets_status_and_metadata(): void
    {
        $sub = new NewsletterSubmission([
            'nsuid' => 2,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 18,
            'submitted_at' => '2026-04-02T13:22:00+00:00',
            'title' => 'Community potluck',
            'body' => 'Bring a dish to share.',
            'status' => 'submitted',
        ]);

        $this->assertSame('submitted', $sub->get('status'));

        $sub->set('status', 'approved');
        $sub->set('approved_by', 5);
        $sub->set('approved_at', '2026-04-03T10:00:00+00:00');

        $this->assertSame('approved', $sub->get('status'));
        $this->assertSame(5, $sub->get('approved_by'));
        $this->assertSame('2026-04-03T10:00:00+00:00', $sub->get('approved_at'));
    }

    #[Test]
    public function rejection_sets_status_and_metadata(): void
    {
        $sub = new NewsletterSubmission([
            'nsuid' => 3,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 18,
            'submitted_at' => '2026-04-02T13:22:00+00:00',
            'title' => 'Off-topic submission',
            'body' => 'Not relevant to community.',
            'status' => 'submitted',
        ]);

        $sub->set('status', 'rejected');
        $sub->set('approved_by', 5);
        $sub->set('approved_at', '2026-04-03T10:00:00+00:00');

        $this->assertSame('rejected', $sub->get('status'));
        $this->assertSame(5, $sub->get('approved_by'));
    }
}
