<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Language;

use App\Entity\Language\TranslationMemory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationMemory::class)]
final class TranslationMemoryTest extends TestCase
{
    #[Test]
    public function it_creates_with_source_translation_and_tag(): void
    {
        $tm = new TranslationMemory([
            'source_en' => 'bear',
            'translation' => 'makwa',
            'language_tag' => 'oj-x-sagamok',
        ]);

        $this->assertSame('bear', $tm->get('source_en'));
        $this->assertSame('makwa', $tm->get('translation'));
        $this->assertSame('oj-x-sagamok', $tm->get('language_tag'));
        $this->assertSame('translation_memory', $tm->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_to_needs_speaker_review_with_zero_confidence(): void
    {
        $tm = new TranslationMemory(['source_en' => 'water', 'translation' => 'nibi']);

        $this->assertSame(1, $tm->get('needs_speaker_review'));
        $this->assertSame(0, $tm->get('confidence'));
    }

    #[Test]
    public function it_defaults_language_code_status_and_consent(): void
    {
        $tm = new TranslationMemory(['source_en' => 'fire', 'translation' => 'ishkode']);

        $this->assertSame('oj', $tm->get('language_code'));
        $this->assertSame(1, $tm->get('status'));
        $this->assertSame(1, $tm->get('consent_public'));
        $this->assertSame(0, $tm->get('consent_ai_training'));
    }

    #[Test]
    public function explicit_values_override_defaults(): void
    {
        $tm = new TranslationMemory([
            'source_en' => 'hello',
            'translation' => 'aaniin',
            'needs_speaker_review' => 0,
            'confidence' => 95,
            'match_origin' => 'speaker',
        ]);

        $this->assertSame(0, $tm->get('needs_speaker_review'));
        $this->assertSame(95, $tm->get('confidence'));
        $this->assertSame('speaker', $tm->get('match_origin'));
    }
}
