<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Ingestion\IngestImporter;
use Minoo\Ingestion\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class LeadershipPipelineTest extends TestCase
{
    #[Test]
    public function leader_payload_produces_pending_review_log(): void
    {
        $envelope = [
            'payload_id' => 'leader-001',
            'version' => '1.0',
            'source' => 'northcloud',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-14T12:00:00Z',
            'entity_type' => 'leader',
            'source_url' => 'https://northcloud.one/api/v1/communities/nc-uuid-123/people',
            'data' => [
                'name' => 'Chief Example',
                'role' => 'Chief',
                'email' => 'chief@example.com',
                'phone' => '705-555-0001',
                'community_id' => 'nc-uuid-123',
            ],
        ];

        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($envelope);

        $this->assertSame('pending_review', $log->get('status'));
        $this->assertSame('leader', $log->get('entity_type_target'));
        $this->assertSame('northcloud', $log->get('source'));

        // Verify parsed payload contains mapped fields.
        $parsed = json_decode($log->get('payload_parsed'), true);
        $this->assertSame('Chief Example', $parsed['name']);
        $this->assertSame('Chief', $parsed['role']);
        $this->assertSame('chief@example.com', $parsed['email']);
        $this->assertSame('nc-uuid-123', $parsed['community_id']);
    }

    #[Test]
    public function leader_payload_without_required_fields_fails(): void
    {
        $envelope = [
            'payload_id' => 'leader-002',
            'version' => '1.0',
            'source' => 'northcloud',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-14T12:00:00Z',
            'entity_type' => 'leader',
            'source_url' => 'https://northcloud.one/api/v1/communities/nc-uuid-123/people',
            'data' => [
                'name' => '',
                'role' => '',
                'community_id' => '',
            ],
        ];

        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($envelope);

        $this->assertSame('failed', $log->get('status'));
        $this->assertStringContainsString('Leader requires name', $log->get('error_message'));
        $this->assertStringContainsString('Leader requires role', $log->get('error_message'));
        $this->assertStringContainsString('Leader requires community_id', $log->get('error_message'));
    }

    #[Test]
    public function leader_payload_with_minimal_fields_succeeds(): void
    {
        $envelope = [
            'payload_id' => 'leader-003',
            'version' => '1.0',
            'source' => 'northcloud',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-14T12:00:00Z',
            'entity_type' => 'leader',
            'source_url' => 'https://northcloud.one/api/v1/communities/nc-uuid-456/people',
            'data' => [
                'name' => 'Councillor One',
                'role' => 'Councillor',
                'community_id' => 'nc-uuid-456',
            ],
        ];

        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($envelope);

        $this->assertSame('pending_review', $log->get('status'));

        $parsed = json_decode($log->get('payload_parsed'), true);
        $this->assertSame('Councillor One', $parsed['name']);
        $this->assertNull($parsed['email']);
        $this->assertNull($parsed['phone']);
    }
}
