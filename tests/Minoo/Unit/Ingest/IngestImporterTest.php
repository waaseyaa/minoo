<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\IngestImporter;
use Minoo\Ingest\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestImporter::class)]
final class IngestImporterTest extends TestCase
{
    private IngestImporter $importer;

    protected function setUp(): void
    {
        $this->importer = new IngestImporter(new PayloadValidator());
    }

    #[Test]
    public function it_creates_ingest_log_from_valid_envelope(): void
    {
        $envelope = $this->validEnvelope();

        $result = $this->importer->import($envelope);

        $this->assertInstanceOf(IngestLog::class, $result);
        $this->assertSame('pending_review', $result->get('status'));
        $this->assertSame('ojibwe_lib', $result->get('source'));
        $this->assertSame('dictionary_entry', $result->get('entity_type_target'));
        $this->assertStringContainsString('ojibwe_lib', $result->get('title'));
    }

    #[Test]
    public function it_stores_raw_envelope_in_payload_raw(): void
    {
        $envelope = $this->validEnvelope();

        $result = $this->importer->import($envelope);

        $raw = json_decode($result->get('payload_raw'), true);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $raw['payload_id']);
    }

    #[Test]
    public function it_stores_mapped_fields_in_payload_parsed(): void
    {
        $envelope = $this->validEnvelope();

        $result = $this->importer->import($envelope);

        $parsed = json_decode($result->get('payload_parsed'), true);
        $this->assertSame('makwa', $parsed['word']);
        $this->assertSame('bear', $parsed['definition']);
    }

    #[Test]
    public function it_creates_failed_log_for_invalid_envelope(): void
    {
        $envelope = ['version' => '1.0'];

        $result = $this->importer->import($envelope);

        $this->assertSame('failed', $result->get('status'));
        $this->assertNotEmpty($result->get('error_message'));
    }

    /** @return array<string, mixed> */
    private function validEnvelope(): array
    {
        return [
            'payload_id' => '550e8400-e29b-41d4-a716-446655440000',
            'version' => '1.0',
            'source' => 'ojibwe_lib',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
            'data' => [
                'lemma' => 'makwa',
                'definition' => 'bear',
                'part_of_speech' => 'na',
                'stem' => '/makw-/',
                'language_code' => 'oj',
            ],
        ];
    }
}
