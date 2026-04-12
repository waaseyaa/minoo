<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingestion\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadValidator::class)]
final class PayloadValidatorTest extends TestCase
{
    private PayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PayloadValidator();
    }

    #[Test]
    public function valid_envelope_passes(): void
    {
        $envelope = [
            'payload_id' => '550e8400-e29b-41d4-a716-446655440000',
            'version' => '1.0',
            'source' => 'ojibwe_lib',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
            'data' => ['lemma' => 'makwa', 'definition' => 'bear', 'part_of_speech' => 'na'],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->errors);
    }

    #[Test]
    public function missing_required_fields_fails(): void
    {
        $envelope = ['version' => '1.0'];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors);
    }

    #[Test]
    public function unsupported_version_fails(): void
    {
        $envelope = [
            'payload_id' => 'test-uuid',
            'version' => '99.0',
            'source' => 'test',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://example.com',
            'data' => ['lemma' => 'test'],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function unknown_entity_type_fails(): void
    {
        $envelope = [
            'payload_id' => 'test-uuid',
            'version' => '1.0',
            'source' => 'test',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'nonexistent_type',
            'source_url' => 'https://example.com',
            'data' => [],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function invalid_part_of_speech_fails(): void
    {
        $envelope = [
            'payload_id' => 'test-uuid',
            'version' => '1.0',
            'source' => 'test',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://example.com',
            'data' => ['lemma' => 'test', 'definition' => 'test', 'part_of_speech' => 'INVALID'],
        ];

        $result = $this->validator->validate($envelope);

        $this->assertFalse($result->isValid());
    }
}
