<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ExampleSentence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleSentence::class)]
final class ExampleSentenceTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Biidaaboode jiimaan.',
            'english_text' => 'The canoe is floating this way.',
            'dictionary_entry_id' => 1,
        ]);

        $this->assertSame('Biidaaboode jiimaan.', $sentence->get('ojibwe_text'));
        $this->assertSame('The canoe is floating this way.', $sentence->get('english_text'));
        $this->assertSame(1, $sentence->get('dictionary_entry_id'));
        $this->assertSame('example_sentence', $sentence->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_language_code_to_oj(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Test',
            'english_text' => 'Test',
            'dictionary_entry_id' => 1,
        ]);

        $this->assertSame('oj', $sentence->get('language_code'));
    }

    #[Test]
    public function it_supports_speaker_and_audio(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Test',
            'english_text' => 'Test',
            'dictionary_entry_id' => 1,
            'speaker_id' => 5,
            'audio_url' => 'https://ojibwe.lib.umn.edu/audio/123.mp3',
        ]);

        $this->assertSame(5, $sentence->get('speaker_id'));
        $this->assertSame('https://ojibwe.lib.umn.edu/audio/123.mp3', $sentence->get('audio_url'));
    }

    #[Test]
    public function it_supports_source_sentence_id(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Makwa agamiing dago.',
            'english_text' => 'The bear is by the lake.',
            'dictionary_entry_id' => 1,
            'source_sentence_id' => 'makwa-es-001',
        ]);

        $this->assertSame('makwa-es-001', $sentence->get('source_sentence_id'));
    }
}
