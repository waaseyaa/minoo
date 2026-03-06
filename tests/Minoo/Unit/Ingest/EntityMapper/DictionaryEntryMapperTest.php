<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\DictionaryEntryMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryEntryMapper::class)]
final class DictionaryEntryMapperTest extends TestCase
{
    private DictionaryEntryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DictionaryEntryMapper();
    }

    #[Test]
    public function it_maps_full_payload(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'stem' => '/makw-/',
            'language_code' => 'oj',
            'inflected_forms' => [
                ['form' => 'makwag', 'label' => 'plural'],
            ],
        ];

        $result = $this->mapper->map($data, 'https://ojibwe.lib.umn.edu/main-entry/makwa-na');

        $this->assertSame('makwa', $result['word']);
        $this->assertSame('bear', $result['definition']);
        $this->assertSame('na', $result['part_of_speech']);
        $this->assertSame('/makw-/', $result['stem']);
        $this->assertSame('oj', $result['language_code']);
        $this->assertSame('makwa', $result['slug']);
        $this->assertSame(0, $result['status']);
        $this->assertSame('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $result['source_url']);
        $this->assertStringContainsString('makwag', $result['inflected_forms']);
    }

    #[Test]
    public function it_defaults_language_code(): void
    {
        $data = ['lemma' => 'makwa', 'definition' => 'bear'];

        $result = $this->mapper->map($data, '');

        $this->assertSame('oj', $result['language_code']);
    }

    #[Test]
    public function it_joins_array_definition(): void
    {
        $data = ['lemma' => 'test', 'definition' => ['bear', 'a bear']];

        $result = $this->mapper->map($data, '');

        $this->assertSame('bear; a bear', $result['definition']);
    }

    #[Test]
    public function it_generates_slug_from_lemma(): void
    {
        $data = ['lemma' => 'Makwa (bear)'];

        $result = $this->mapper->map($data, '');

        $this->assertSame('makwa-bear', $result['slug']);
    }
}
