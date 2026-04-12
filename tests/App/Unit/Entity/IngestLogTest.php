<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\IngestLog;
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
