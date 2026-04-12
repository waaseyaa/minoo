<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\MatcherEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MatcherEngine::class)]
final class MatcherEngineTest extends TestCase
{
    #[Test]
    public function pair_count_for_easy(): void
    {
        $this->assertSame(4, MatcherEngine::pairCount('easy'));
    }

    #[Test]
    public function pair_count_for_medium(): void
    {
        $this->assertSame(6, MatcherEngine::pairCount('medium'));
    }

    #[Test]
    public function pair_count_for_hard(): void
    {
        $this->assertSame(8, MatcherEngine::pairCount('hard'));
    }

    #[Test]
    public function pair_count_defaults_to_easy(): void
    {
        $this->assertSame(4, MatcherEngine::pairCount('invalid'));
    }

    #[Test]
    public function validate_match_correct(): void
    {
        $pairs = [
            ['id' => 'deid_1', 'ojibwe' => 'makwa', 'english' => 'bear'],
            ['id' => 'deid_2', 'ojibwe' => 'nibi', 'english' => 'water'],
        ];

        $result = MatcherEngine::validateMatch('deid_1', 'deid_1', $pairs);
        $this->assertTrue($result['correct']);
    }

    #[Test]
    public function validate_match_incorrect(): void
    {
        $pairs = [
            ['id' => 'deid_1', 'ojibwe' => 'makwa', 'english' => 'bear'],
            ['id' => 'deid_2', 'ojibwe' => 'nibi', 'english' => 'water'],
        ];

        $result = MatcherEngine::validateMatch('deid_1', 'deid_2', $pairs);
        $this->assertFalse($result['correct']);
    }

    #[Test]
    public function daily_seed_is_deterministic(): void
    {
        $seed1 = MatcherEngine::dailySeed('2026-03-25');
        $seed2 = MatcherEngine::dailySeed('2026-03-25');
        $this->assertSame($seed1, $seed2);
    }

    #[Test]
    public function daily_seed_differs_across_dates(): void
    {
        $seed1 = MatcherEngine::dailySeed('2026-03-25');
        $seed2 = MatcherEngine::dailySeed('2026-03-26');
        $this->assertNotSame($seed1, $seed2);
    }

    #[Test]
    public function clean_definition_unwraps_json_array(): void
    {
        $this->assertSame('bear', MatcherEngine::cleanDefinition('["bear"]'));
    }

    #[Test]
    public function clean_definition_joins_multiple_values(): void
    {
        $this->assertSame('bear; grizzly', MatcherEngine::cleanDefinition('["bear", "grizzly"]'));
    }

    #[Test]
    public function clean_definition_expands_abbreviations(): void
    {
        $this->assertSame('she/he walks', MatcherEngine::cleanDefinition('s/he walks'));
    }

    #[Test]
    public function clean_definition_handles_plain_string(): void
    {
        $this->assertSame('bear', MatcherEngine::cleanDefinition('bear'));
    }

    #[Test]
    public function clean_definition_handles_empty_string(): void
    {
        $this->assertSame('', MatcherEngine::cleanDefinition(''));
    }

    #[Test]
    public function is_abbreviation_only_detects_pos_tags(): void
    {
        $this->assertTrue(MatcherEngine::isAbbreviationOnly('na'));
        $this->assertTrue(MatcherEngine::isAbbreviationOnly('vti'));
        $this->assertTrue(MatcherEngine::isAbbreviationOnly('ni'));
        $this->assertFalse(MatcherEngine::isAbbreviationOnly('bear'));
        $this->assertFalse(MatcherEngine::isAbbreviationOnly('a big bear'));
    }

    #[Test]
    public function select_pairs_filters_and_shuffles(): void
    {
        // Build mock entries: array of [id, word, definition]
        $entries = [
            ['id' => 1, 'word' => 'makwa', 'definition' => '["bear"]'],
            ['id' => 2, 'word' => 'nibi', 'definition' => '["water"]'],
            ['id' => 3, 'word' => 'giizis', 'definition' => '["sun"]'],
            ['id' => 4, 'word' => 'waabshki', 'definition' => '["white"]'],
            ['id' => 5, 'word' => 'goon', 'definition' => '["snow"]'],
            ['id' => 6, 'word' => '', 'definition' => '["empty word"]'],     // no word — filtered
            ['id' => 7, 'word' => 'test', 'definition' => ''],               // no def — filtered
            ['id' => 8, 'word' => 'test2', 'definition' => 'na'],            // abbreviation only — filtered
        ];

        $pairs = MatcherEngine::selectPairs($entries, 4);
        $this->assertCount(4, $pairs);

        // Each pair has required keys
        foreach ($pairs as $pair) {
            $this->assertArrayHasKey('id', $pair);
            $this->assertArrayHasKey('ojibwe', $pair);
            $this->assertArrayHasKey('english', $pair);
            $this->assertNotEmpty($pair['ojibwe']);
            $this->assertNotEmpty($pair['english']);
        }
    }

    #[Test]
    public function select_pairs_with_seed_is_deterministic(): void
    {
        $entries = [
            ['id' => 1, 'word' => 'makwa', 'definition' => '["bear"]'],
            ['id' => 2, 'word' => 'nibi', 'definition' => '["water"]'],
            ['id' => 3, 'word' => 'giizis', 'definition' => '["sun"]'],
            ['id' => 4, 'word' => 'waabshki', 'definition' => '["white"]'],
            ['id' => 5, 'word' => 'goon', 'definition' => '["snow"]'],
        ];

        $seed = MatcherEngine::dailySeed('2026-03-25');
        $pairs1 = MatcherEngine::selectPairs($entries, 4, $seed);
        $pairs2 = MatcherEngine::selectPairs($entries, 4, $seed);
        $this->assertSame($pairs1, $pairs2);
    }

    #[Test]
    public function select_pairs_avoids_duplicate_definitions(): void
    {
        $entries = [
            ['id' => 1, 'word' => 'makwa', 'definition' => '["bear"]'],
            ['id' => 2, 'word' => 'mkwa', 'definition' => '["bear"]'],  // duplicate def
            ['id' => 3, 'word' => 'nibi', 'definition' => '["water"]'],
            ['id' => 4, 'word' => 'giizis', 'definition' => '["sun"]'],
            ['id' => 5, 'word' => 'goon', 'definition' => '["snow"]'],
        ];

        $pairs = MatcherEngine::selectPairs($entries, 4);
        $definitions = array_map(fn($p) => $p['english'], $pairs);
        $this->assertSame(count($definitions), count(array_unique($definitions)));
    }
}
