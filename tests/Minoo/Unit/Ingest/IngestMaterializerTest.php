<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\IngestMaterializer;
use Minoo\Ingest\MaterializationContext;
use Minoo\Ingest\MaterializationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[CoversClass(IngestMaterializer::class)]
final class IngestMaterializerTest extends TestCase
{
    #[Test]
    public function dry_run_returns_preview_without_persisting(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->expects($this->never())->method('getStorage');

        $materializer = new IngestMaterializer($manager);
        $log = $this->createDictionaryLog();

        $result = $materializer->materialize($log, dryRun: true);

        $this->assertInstanceOf(MaterializationResult::class, $result);
        $this->assertNotEmpty($result->getCreated());
        $this->assertNull($result->getPrimaryEntityId());
    }

    #[Test]
    public function dry_run_previews_dictionary_entry_with_children(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $materializer = new IngestMaterializer($manager);
        $log = $this->createDictionaryLog();

        $result = $materializer->materialize($log, dryRun: true);

        $types = array_column($result->getCreated(), 'type');
        $this->assertContains('dictionary_entry', $types);
        $this->assertContains('speaker', $types);
        $this->assertContains('example_sentence', $types);
        $this->assertContains('word_part', $types);
    }

    private function createDictionaryLog(): IngestLog
    {
        $parsed = [
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'stem' => '/makw-/',
            'language_code' => 'oj',
            'inflected_forms' => '[{"form":"makwag","label":"plural"}]',
            'source_url' => 'https://example.com',
            'slug' => 'makwa',
            'status' => 0,
            'created_at' => 0,
            'updated_at' => 0,
        ];

        $raw = json_encode([
            'payload_id' => 'test-uuid',
            'version' => '1.0',
            'source' => 'ojibwe_lib',
            'snapshot_type' => 'full',
            'timestamp' => '2026-03-06T14:30:00Z',
            'entity_type' => 'dictionary_entry',
            'source_url' => 'https://example.com',
            'data' => [
                'lemma' => 'makwa',
                'definition' => 'bear',
                'part_of_speech' => 'na',
                'stem' => '/makw-/',
                'language_code' => 'oj',
                'example_sentences' => [
                    [
                        'source_sentence_id' => 'makwa-es-001',
                        'ojibwe_text' => 'Makwa agamiing dago.',
                        'english_text' => 'The bear is by the lake.',
                        'speaker_code' => 'es',
                    ],
                ],
                'word_parts' => [
                    ['form' => 'makw-', 'morphological_role' => 'initial', 'definition' => 'bear'],
                ],
            ],
        ]);

        return new IngestLog([
            'title' => 'ojibwe_lib — test',
            'source' => 'ojibwe_lib',
            'entity_type_target' => 'dictionary_entry',
            'payload_raw' => $raw,
            'payload_parsed' => json_encode($parsed),
            'status' => 'pending_review',
        ]);
    }
}
