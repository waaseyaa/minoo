<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\IshkodeEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IshkodeEngine::class)]
final class IshkodeEngineTest extends TestCase
{
    #[Test]
    public function difficulty_tier_for_short_noun(): void
    {
        $this->assertSame('easy', IshkodeEngine::difficultyTier('makwa', 'na'));
    }

    #[Test]
    public function difficulty_tier_for_medium_verb(): void
    {
        $this->assertSame('medium', IshkodeEngine::difficultyTier('bimosed', 'vai'));
    }

    #[Test]
    public function difficulty_tier_for_long_word(): void
    {
        $this->assertSame('hard', IshkodeEngine::difficultyTier('ishkodewaaboo', 'ni'));
    }

    #[Test]
    public function max_wrong_guesses_per_tier(): void
    {
        $this->assertSame(7, IshkodeEngine::maxWrongGuesses('easy'));
        $this->assertSame(6, IshkodeEngine::maxWrongGuesses('medium'));
        $this->assertSame(5, IshkodeEngine::maxWrongGuesses('hard'));
    }

    #[Test]
    public function process_correct_guess(): void
    {
        $result = IshkodeEngine::processGuess('ishkode', 'i', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([0], $result['positions']);
    }

    #[Test]
    public function process_wrong_guess(): void
    {
        $result = IshkodeEngine::processGuess('ishkode', 'z', []);
        $this->assertFalse($result['correct']);
        $this->assertSame([], $result['positions']);
    }

    #[Test]
    public function process_guess_finds_multiple_positions(): void
    {
        // "baabaa" has 'a' at positions 1, 2, 4, 5 (0-indexed)
        $result = IshkodeEngine::processGuess('baabaa', 'a', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([1, 2, 4, 5], $result['positions']);
    }

    #[Test]
    public function process_guess_is_case_insensitive(): void
    {
        $result = IshkodeEngine::processGuess('Makwa', 'm', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([0], $result['positions']);
    }

    #[Test]
    public function duplicate_guess_returns_already_guessed(): void
    {
        $result = IshkodeEngine::processGuess('ishkode', 'i', ['i']);
        $this->assertArrayHasKey('already_guessed', $result);
        $this->assertTrue($result['already_guessed']);
    }

    #[Test]
    public function daily_tier_for_day_of_week(): void
    {
        // Monday = easy
        $this->assertSame('easy', IshkodeEngine::dailyTier(1));
        // Tuesday = medium
        $this->assertSame('medium', IshkodeEngine::dailyTier(2));
        // Saturday = hard
        $this->assertSame('hard', IshkodeEngine::dailyTier(6));
        // Sunday = hard
        $this->assertSame('hard', IshkodeEngine::dailyTier(0));
    }

    #[Test]
    public function generate_share_text(): void
    {
        $guesses = ['i', 's', 'r', 'h', 'k', 'l', 'o', 'd', 'e'];
        $word = 'ishkode';
        $text = IshkodeEngine::generateShareText($word, $guesses, 'english_to_ojibwe', 'easy', '2026-03-23');

        $this->assertStringContainsString('Ishkode', $text);
        $this->assertStringContainsString('2026-03-23', $text);
        $this->assertStringContainsString("\u{1F525}", $text);
        $this->assertStringContainsString("\u{1FAA8}", $text);
    }
}
