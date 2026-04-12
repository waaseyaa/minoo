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
