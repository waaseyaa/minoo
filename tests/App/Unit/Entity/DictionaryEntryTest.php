<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DictionaryEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryEntry::class)]
final class DictionaryEntryTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'jiimaan',
            'definition' => 'a canoe; a boat',
            'part_of_speech' => 'ni',
        ]);

        $this->assertSame('jiimaan', $entry->get('word'));
        $this->assertSame('a canoe; a boat', $entry->get('definition'));
        $this->assertSame('ni', $entry->get('part_of_speech'));
        $this->assertSame('dictionary_entry', $entry->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_language_code_to_oj(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'makwa',
            'definition' => 'a bear',
            'part_of_speech' => 'na',
        ]);

        $this->assertSame('oj', $entry->get('language_code'));
    }

    #[Test]
    public function it_stores_inflected_forms_as_json_string(): void
    {
        $forms = json_encode([
            ['form' => 'jiimaanan', 'label' => 'pl'],
            ['form' => 'jiimaanens', 'label' => 'dim'],
        ], JSON_THROW_ON_ERROR);

        $entry = new DictionaryEntry([
            'word' => 'jiimaan',
            'definition' => 'a canoe',
            'part_of_speech' => 'ni',
            'inflected_forms' => $forms,
        ]);

        $this->assertSame($forms, $entry->get('inflected_forms'));
    }
}
