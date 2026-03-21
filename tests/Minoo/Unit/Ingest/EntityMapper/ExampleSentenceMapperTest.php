<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingestion\EntityMapper\ExampleSentenceMapper;
use Minoo\Ingestion\ValueObject\ExampleSentenceFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleSentenceMapper::class)]
#[CoversClass(ExampleSentenceFields::class)]
final class ExampleSentenceMapperTest extends TestCase
{
    #[Test]
    public function it_maps_sentence_with_all_fields(): void
    {
        $mapper = new ExampleSentenceMapper();
        $data = [
            'ojibwe_text' => 'Makwa agamiing dago.',
            'english_text' => 'The bear is by the lake.',
            'audio_url' => 'https://example.com/audio.mp3',
            'source_sentence_id' => 'makwa-es-001',
        ];

        $result = $mapper->map($data, 42, 7, 'oj');

        $this->assertInstanceOf(ExampleSentenceFields::class, $result);
        $this->assertSame('Makwa agamiing dago.', $result->ojibweText);
        $this->assertSame('The bear is by the lake.', $result->englishText);
        $this->assertSame(42, $result->dictionaryEntryId);
        $this->assertSame(7, $result->contributorId);
        $this->assertSame('oj', $result->languageCode);
        $this->assertSame('https://example.com/audio.mp3', $result->audioUrl);
        $this->assertSame('makwa-es-001', $result->sourceSentenceId);
        $this->assertSame(0, $result->status);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $mapper = new ExampleSentenceMapper();
        $data = ['ojibwe_text' => 'Test.', 'english_text' => 'Test.'];

        $array = $mapper->map($data, 1, null, 'oj')->toArray();

        $this->assertSame('Test.', $array['ojibwe_text']);
        $this->assertSame(1, $array['dictionary_entry_id']);
        $this->assertNull($array['contributor_id']);
    }
}
