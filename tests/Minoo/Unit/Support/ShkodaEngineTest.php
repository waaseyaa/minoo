<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\ShkodaEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShkodaEngine::class)]
final class ShkodaEngineTest extends TestCase
{
    #[Test]
    public function difficulty_tier_for_short_noun(): void
    {
        $this->assertSame('easy', ShkodaEngine::difficultyTier('makwa', 'na'));
    }

    #[Test]
    public function difficulty_tier_for_medium_verb(): void
    {
        $this->assertSame('medium', ShkodaEngine::difficultyTier('bimosed', 'vai'));
    }

    #[Test]
    public function difficulty_tier_for_long_word(): void
    {
        $this->assertSame('hard', ShkodaEngine::difficultyTier('ishkodewaaboo', 'ni'));
    }

    #[Test]
    public function difficulty_tier_falls_back_to_length_when_pos_empty(): void
    {
        $this->assertSame('easy', ShkodaEngine::difficultyTier('makwa', ''));
        $this->assertSame('medium', ShkodaEngine::difficultyTier('bimosed', ''));
        $this->assertSame('hard', ShkodaEngine::difficultyTier('ishkodewaaboo', ''));
    }

    #[Test]
    public function max_wrong_guesses_per_tier(): void
    {
        $this->assertSame(7, ShkodaEngine::maxWrongGuesses('easy'));
        $this->assertSame(6, ShkodaEngine::maxWrongGuesses('medium'));
        $this->assertSame(5, ShkodaEngine::maxWrongGuesses('hard'));
    }

    #[Test]
    public function process_correct_guess(): void
    {
        $result = ShkodaEngine::processGuess('ishkode', 'i', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([0], $result['positions']);
    }

    #[Test]
    public function process_wrong_guess(): void
    {
        $result = ShkodaEngine::processGuess('ishkode', 'z', []);
        $this->assertFalse($result['correct']);
        $this->assertSame([], $result['positions']);
    }

    #[Test]
    public function process_guess_finds_multiple_positions(): void
    {
        // "baabaa" has 'a' at positions 1, 2, 4, 5 (0-indexed)
        $result = ShkodaEngine::processGuess('baabaa', 'a', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([1, 2, 4, 5], $result['positions']);
    }

    #[Test]
    public function process_guess_is_case_insensitive(): void
    {
        $result = ShkodaEngine::processGuess('Makwa', 'm', []);
        $this->assertTrue($result['correct']);
        $this->assertSame([0], $result['positions']);
    }

    #[Test]
    public function duplicate_guess_returns_already_guessed(): void
    {
        $result = ShkodaEngine::processGuess('ishkode', 'i', ['i']);
        $this->assertArrayHasKey('already_guessed', $result);
        $this->assertTrue($result['already_guessed']);
    }

    #[Test]
    public function daily_tier_for_day_of_week(): void
    {
        // Monday = easy
        $this->assertSame('easy', ShkodaEngine::dailyTier(1));
        // Tuesday = medium
        $this->assertSame('medium', ShkodaEngine::dailyTier(2));
        // Saturday = hard
        $this->assertSame('hard', ShkodaEngine::dailyTier(6));
        // Sunday = hard
        $this->assertSame('hard', ShkodaEngine::dailyTier(0));
    }

    #[Test]
    public function generate_share_text(): void
    {
        $guesses = ['i', 's', 'r', 'h', 'k', 'l', 'o', 'd', 'e'];
        $word = 'ishkode';
        $text = ShkodaEngine::generateShareText($word, $guesses, 'english_to_ojibwe', 'easy', '2026-03-23');

        $this->assertStringContainsString('Shkoda', $text);
        $this->assertStringContainsString('2026-03-23', $text);
        $this->assertStringContainsString("\u{1F525}", $text);
        $this->assertStringContainsString("\u{1FAA8}", $text);
    }
}
