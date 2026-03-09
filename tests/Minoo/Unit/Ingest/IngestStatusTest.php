<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Ingestion\IngestStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestStatus::class)]
final class IngestStatusTest extends TestCase
{
    #[Test]
    public function it_has_expected_cases(): void
    {
        $this->assertSame('pending_review', IngestStatus::PendingReview->value);
        $this->assertSame('approved', IngestStatus::Approved->value);
        $this->assertSame('failed', IngestStatus::Failed->value);
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(IngestStatus::PendingReview, IngestStatus::from('pending_review'));
        $this->assertSame(IngestStatus::Failed, IngestStatus::from('failed'));
    }
}
