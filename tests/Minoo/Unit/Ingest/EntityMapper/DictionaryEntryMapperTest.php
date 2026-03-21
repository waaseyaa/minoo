<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingestion\EntityMapper\DictionaryEntryMapper;
use Minoo\Ingestion\ValueObject\DictionaryEntryFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryEntryMapper::class)]
#[CoversClass(DictionaryEntryFields::class)]
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

        $this->assertInstanceOf(DictionaryEntryFields::class, $result);
        $this->assertSame('makwa', $result->word);
        $this->assertSame('bear', $result->definition);
        $this->assertSame('na', $result->partOfSpeech);
        $this->assertSame('/makw-/', $result->stem);
        $this->assertSame('oj', $result->languageCode);
        $this->assertSame('makwa', $result->slug);
        $this->assertSame(0, $result->status);
        $this->assertSame('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $result->sourceUrl);
        $this->assertStringContainsString('makwag', $result->inflectedForms);
    }

    #[Test]
    public function it_defaults_language_code(): void
    {
        $data = ['lemma' => 'makwa', 'definition' => 'bear'];

        $result = $this->mapper->map($data, '');

        $this->assertSame('oj', $result->languageCode);
    }

    #[Test]
    public function it_joins_array_definition(): void
    {
        $data = ['lemma' => 'test', 'definition' => ['bear', 'a bear']];

        $result = $this->mapper->map($data, '');

        $this->assertSame('bear; a bear', $result->definition);
    }

    #[Test]
    public function it_generates_slug_from_lemma(): void
    {
        $data = ['lemma' => 'Makwa (bear)'];

        $result = $this->mapper->map($data, '');

        $this->assertSame('makwa-bear', $result->slug);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $data = ['lemma' => 'makwa', 'definition' => 'bear'];

        $result = $this->mapper->map($data, 'https://example.com');
        $array = $result->toArray();

        $this->assertSame('makwa', $array['word']);
        $this->assertSame('bear', $array['definition']);
        $this->assertSame('https://example.com', $array['source_url']);
    }

    #[Test]
    public function it_maps_nc_api_format(): void
    {
        $data = [
            'id' => 'uuid-123',
            'lemma' => 'makwa',
            'definitions' => 'bear',
            'word_class_normalized' => 'na',
            'inflections' => '[{"form":"makwag","label":"plural"}]',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
            'attribution' => 'OPD',
            'consent_public_display' => true,
        ];

        $attribution = "Ojibwe People's Dictionary, University of Minnesota";
        $result = $this->mapper->map($data, '', $attribution);

        $this->assertSame('makwa', $result->word);
        $this->assertSame('bear', $result->definition);
        $this->assertSame('na', $result->partOfSpeech);
        $this->assertSame('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $result->sourceUrl);
        $this->assertStringContainsString('makwag', $result->inflectedForms);
    }

    #[Test]
    public function it_stores_attribution_from_nc_api(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definitions' => 'bear',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
        ];

        $attribution = "Ojibwe People's Dictionary, University of Minnesota";
        $result = $this->mapper->map($data, '', $attribution);

        $this->assertSame($attribution, $result->attributionSource);
        $this->assertSame('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $result->attributionUrl);

        $array = $result->toArray();
        $this->assertSame($attribution, $array['attribution_source']);
        $this->assertSame('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $array['attribution_url']);
    }

    #[Test]
    public function it_prefers_entry_attribution_over_header(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definitions' => 'bear',
            'attribution' => 'Entry-level attribution',
        ];

        $result = $this->mapper->map($data, '', 'Header attribution');

        $this->assertSame('Entry-level attribution', $result->attributionSource);
    }

    #[Test]
    public function it_prefers_entry_source_url_over_parameter(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definitions' => 'bear',
            'source_url' => 'https://from-entry.com',
        ];

        $result = $this->mapper->map($data, 'https://from-param.com');

        $this->assertSame('https://from-entry.com', $result->sourceUrl);
    }

    #[Test]
    public function it_decodes_json_encoded_definitions(): void
    {
        $data = [
            'lemma' => "'a",
            'definitions' => '["that (animate singular)"]',
            'consent_public_display' => true,
        ];

        $result = $this->mapper->map($data, '');

        $this->assertSame('that (animate singular)', $result->definition);
    }

    #[Test]
    public function it_joins_multiple_json_encoded_definitions(): void
    {
        $data = [
            'lemma' => 'makwa',
            'definitions' => '["bear", "a bear"]',
            'consent_public_display' => true,
        ];

        $result = $this->mapper->map($data, '');

        $this->assertSame('bear; a bear', $result->definition);
    }

    #[Test]
    public function it_falls_back_to_word_class(): void
    {
        $data = [
            'lemma' => "'a",
            'definitions' => '["that"]',
            'word_class' => 'pron dem',
            'consent_public_display' => true,
        ];

        $result = $this->mapper->map($data, '');

        $this->assertSame('pron dem', $result->partOfSpeech);
    }

    #[Test]
    public function it_treats_empty_json_inflections_as_empty(): void
    {
        $data = [
            'lemma' => "'a",
            'definitions' => '["that"]',
            'inflections' => '{}',
            'consent_public_display' => true,
        ];

        $result = $this->mapper->map($data, '');

        $this->assertSame('', $result->inflectedForms);
    }

    #[Test]
    public function it_maps_real_nc_api_entry(): void
    {
        $data = [
            'id' => '05c79d28-2aa4-4211-91a8-63fd9b4612bc',
            'lemma' => "'a",
            'word_class' => 'pron dem',
            'definitions' => '["that (animate singular)"]',
            'inflections' => '{}',
            'examples' => '["animate singular demonstrative"]',
            'source_url' => 'https://ojibwe.lib.umn.edu/main-entry/a-pron-dem',
            'attribution' => "Ojibwe People's Dictionary, University of Minnesota",
            'consent_public_display' => true,
        ];

        $result = $this->mapper->map($data, '');

        $this->assertSame("'a", $result->word);
        $this->assertSame('that (animate singular)', $result->definition);
        $this->assertSame('pron dem', $result->partOfSpeech);
        $this->assertSame('', $result->inflectedForms);
        $this->assertSame(1, $result->status);
        $this->assertSame(1, $result->consentPublic);
        $this->assertSame("Ojibwe People's Dictionary, University of Minnesota", $result->attributionSource);
    }
}
