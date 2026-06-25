<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language;

use App\Language\TranslationMemoryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationMemoryService::class)]
final class TranslationMemoryServiceTest extends TestCase
{
    #[Test]
    public function normalize_trims_collapses_whitespace_and_lowercases(): void
    {
        self::assertSame('hello world', TranslationMemoryService::normalize("  Hello   World  "));
        self::assertSame('good morning', TranslationMemoryService::normalize("Good\tMorning"));
        self::assertSame('', TranslationMemoryService::normalize('   '));
    }

    #[Test]
    public function hash_is_deterministic_and_distinguishes_inputs(): void
    {
        $a = TranslationMemoryService::hash(TranslationMemoryService::normalize('bear'));
        $b = TranslationMemoryService::hash(TranslationMemoryService::normalize('Bear'));
        $c = TranslationMemoryService::hash(TranslationMemoryService::normalize('wolf'));

        self::assertSame($a, $b, 'Normalized inputs hash equally regardless of case.');
        self::assertNotSame($a, $c, 'Different strings hash differently.');
    }
}
