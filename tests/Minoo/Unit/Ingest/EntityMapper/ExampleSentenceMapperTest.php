<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\ExampleSentenceMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleSentenceMapper::class)]
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

        $this->assertSame('Makwa agamiing dago.', $result['ojibwe_text']);
        $this->assertSame('The bear is by the lake.', $result['english_text']);
        $this->assertSame(42, $result['dictionary_entry_id']);
        $this->assertSame(7, $result['speaker_id']);
        $this->assertSame('oj', $result['language_code']);
        $this->assertSame('https://example.com/audio.mp3', $result['audio_url']);
        $this->assertSame('makwa-es-001', $result['source_sentence_id']);
        $this->assertSame(0, $result['status']);
    }
}
