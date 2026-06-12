<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataSovereignty;

use App\Entity\Language\DictionaryEntry;
use App\Entity\Language\Speaker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Speaker::class)]
#[CoversClass(DictionaryEntry::class)]
final class ConsentFieldTest extends TestCase
{
    #[Test]
    public function dictionary_entry_can_set_consent_fields(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'nibi',
            'consent_public' => 1,
            'consent_ai_training' => 0,
        ]);

        self::assertSame(1, $entry->get('consent_public'));
        self::assertSame(0, $entry->get('consent_ai_training'));
    }

    #[Test]
    public function dictionary_entry_with_consent_public_false_is_filterable(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'nibi',
            'consent_public' => 0,
        ]);

        self::assertSame(0, $entry->get('consent_public'));
    }

    #[Test]
    public function speaker_consent_flags_can_be_forced_off(): void
    {
        $speaker = new Speaker([
            'name' => 'Test Speaker',
            'consent_public_display' => 0,
            'consent_ai_training' => 0,
        ]);

        self::assertSame(0, $speaker->get('consent_public_display'));
        self::assertSame(0, $speaker->get('consent_ai_training'));
    }
}
