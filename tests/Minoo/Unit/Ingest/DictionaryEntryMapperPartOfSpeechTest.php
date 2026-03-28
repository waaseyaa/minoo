<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Ingestion\EntityMapper\DictionaryEntryMapper;
use Minoo\Ingestion\ValueObject\DictionaryEntryFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryEntryMapper::class)]
#[CoversClass(DictionaryEntryFields::class)]
final class DictionaryEntryMapperPartOfSpeechTest extends TestCase
{
    private DictionaryEntryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DictionaryEntryMapper();
    }

    #[Test]
    public function it_maps_word_class_normalized_as_part_of_speech(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definition' => 'bear',
            'word_class_normalized' => 'na',
            'word_class' => 'noun animate',
            'part_of_speech' => 'noun',
        ];

        $result = $this->mapper->map($data);

        $this->assertSame('na', $result->partOfSpeech);
    }

    #[Test]
    public function it_falls_back_to_word_class_when_normalized_missing(): void
    {
        $data = [
            'lemma' => 'jiimaan',
            'definition' => 'canoe, boat',
            'word_class' => 'ni',
            'part_of_speech' => 'noun',
        ];

        $result = $this->mapper->map($data);

        $this->assertSame('ni', $result->partOfSpeech);
    }

    #[Test]
    public function it_falls_back_to_part_of_speech_field(): void
    {
        $data = [
            'lemma' => 'giizis',
            'definition' => 'sun, moon, month',
            'part_of_speech' => 'na',
        ];

        $result = $this->mapper->map($data);

        $this->assertSame('na', $result->partOfSpeech);
    }

    #[Test]
    public function it_returns_empty_string_when_no_pos_fields_present(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definition' => 'bear',
        ];

        $result = $this->mapper->map($data);

        $this->assertSame('', $result->partOfSpeech);
    }
}
