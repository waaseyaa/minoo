<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\DataSovereignty;

use Minoo\Entity\DictionaryEntry;
use Minoo\Entity\Teaching;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teaching::class)]
#[CoversClass(DictionaryEntry::class)]
final class ConsentFieldTest extends TestCase
{
    #[Test]
    public function teaching_defaults_consent_public_to_true(): void
    {
        $teaching = new Teaching(['title' => 'Test', 'type' => 'culture']);

        self::assertSame(1, $teaching->get('status'));
    }

    #[Test]
    public function teaching_can_set_consent_fields(): void
    {
        $teaching = new Teaching([
            'title' => 'Test',
            'type' => 'culture',
            'consent_public' => 1,
            'consent_ai_training' => 0,
        ]);

        self::assertSame(1, $teaching->get('consent_public'));
        self::assertSame(0, $teaching->get('consent_ai_training'));
    }

    #[Test]
    public function teaching_consent_ai_training_can_be_explicitly_enabled(): void
    {
        $teaching = new Teaching([
            'title' => 'Test',
            'type' => 'culture',
            'consent_ai_training' => 1,
        ]);

        self::assertSame(1, $teaching->get('consent_ai_training'));
    }

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
}
