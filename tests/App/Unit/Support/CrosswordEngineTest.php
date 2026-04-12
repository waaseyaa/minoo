<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\CrosswordEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrosswordEngine::class)]
final class CrosswordEngineTest extends TestCase
{
    #[Test]
    public function generate_grid_produces_valid_placement(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig'];
        $result = CrosswordEngine::generateGrid($words, 10, 4);

        $this->assertNotNull($result, 'Should produce a grid');
        $this->assertArrayHasKey('placements', $result);
        $this->assertArrayHasKey('grid', $result);
        $this->assertGreaterThanOrEqual(4, count($result['placements']));
    }

    #[Test]
    public function generate_grid_returns_null_when_too_few_words(): void
    {
        $result = CrosswordEngine::generateGrid(['hi'], 7, 4);
        $this->assertNull($result);
    }

    #[Test]
    public function placements_have_no_letter_conflicts(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig', 'dewe'];
        $result = CrosswordEngine::generateGrid($words, 10, 4);

        $this->assertNotNull($result, 'Deterministic grid generation should always produce a result for this word set');

        // Verify no two words place different letters in the same cell
        $cells = [];
        foreach ($result['placements'] as $p) {
            $word = $p['word'];
            $len = mb_strlen($word);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_strtolower(mb_substr($word, $i, 1));
                $r = $p['direction'] === 'across' ? $p['row'] : $p['row'] + $i;
                $c = $p['direction'] === 'across' ? $p['col'] + $i : $p['col'];
                $key = "{$r},{$c}";
                if (isset($cells[$key])) {
                    $this->assertSame($cells[$key], $char, "Conflict at {$key}");
                }
                $cells[$key] = $char;
            }
        }
    }

    #[Test]
    public function placements_stay_within_grid_bounds(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig'];
        $result = CrosswordEngine::generateGrid($words, 10, 3);

        $this->assertNotNull($result, 'Deterministic grid generation should always produce a result');

        foreach ($result['placements'] as $p) {
            $len = mb_strlen($p['word']);
            if ($p['direction'] === 'across') {
                $this->assertLessThanOrEqual(10, $p['col'] + $len, "Word '{$p['word']}' overflows grid horizontally");
            } else {
                $this->assertLessThanOrEqual(10, $p['row'] + $len, "Word '{$p['word']}' overflows grid vertically");
            }
            $this->assertGreaterThanOrEqual(0, $p['row']);
            $this->assertGreaterThanOrEqual(0, $p['col']);
        }
    }

    #[Test]
    public function all_words_are_connected(): void
    {
        $words = ['shkoda', 'nibi', 'mkwa', 'ziibi', 'giizhig'];
        $result = CrosswordEngine::generateGrid($words, 10, 4);

        $this->assertNotNull($result, 'Deterministic grid generation should always produce a result');

        $this->assertTrue(
            CrosswordEngine::areAllWordsConnected($result['placements']),
            'All placed words must share at least one intersection'
        );
    }

    #[Test]
    public function quality_score_rejects_sparse_grid(): void
    {
        // A single word on a 10x10 grid is too sparse
        $placements = [
            ['word' => 'nibi', 'row' => 0, 'col' => 0, 'direction' => 'across'],
        ];
        $score = CrosswordEngine::qualityScore($placements, 10);
        $this->assertFalse($score['passes']);
    }

    #[Test]
    public function daily_tier_matches_shkoda_pattern(): void
    {
        $this->assertSame('easy', CrosswordEngine::dailyTier(1));   // Mon
        $this->assertSame('medium', CrosswordEngine::dailyTier(2)); // Tue
        $this->assertSame('hard', CrosswordEngine::dailyTier(0));   // Sun
    }

    #[Test]
    public function resolve_clue_prefers_elder_over_auto(): void
    {
        $clueData = [
            'auto' => 'the Ojibwe word for fire',
            'elder' => 'It keeps you warm at night in the bush',
            'elder_author' => 'Elder Name',
        ];
        $resolved = CrosswordEngine::resolveClue($clueData);
        $this->assertSame('It keeps you warm at night in the bush', $resolved['text']);
        $this->assertSame('Elder Name', $resolved['author']);
    }

    #[Test]
    public function resolve_clue_falls_back_to_auto(): void
    {
        $clueData = [
            'auto' => 'the Ojibwe word for fire',
            'elder' => null,
            'elder_author' => null,
        ];
        $resolved = CrosswordEngine::resolveClue($clueData);
        $this->assertSame('the Ojibwe word for fire', $resolved['text']);
        $this->assertNull($resolved['author']);
    }

    #[Test]
    public function validate_word_correct(): void
    {
        $result = CrosswordEngine::validateWord(['s', 'h', 'k', 'o', 'd', 'a'], 'shkoda');
        $this->assertTrue($result['correct']);
        $this->assertSame([0, 1, 2, 3, 4, 5], $result['correct_positions']);
        $this->assertSame([], $result['wrong_positions']);
    }

    #[Test]
    public function validate_word_partial(): void
    {
        $result = CrosswordEngine::validateWord(['s', 'h', 'k', 'x', 'd', 'a'], 'shkoda');
        $this->assertFalse($result['correct']);
        $this->assertSame([0, 1, 2, 4, 5], $result['correct_positions']);
        $this->assertSame([3], $result['wrong_positions']);
    }

    #[Test]
    public function validate_word_is_case_insensitive(): void
    {
        $result = CrosswordEngine::validateWord(['S', 'H', 'K', 'O', 'D', 'A'], 'shkoda');
        $this->assertTrue($result['correct']);
    }

    #[Test]
    public function max_hints_per_tier(): void
    {
        $this->assertSame(-1, CrosswordEngine::maxHints('easy'));    // unlimited
        $this->assertSame(2, CrosswordEngine::maxHints('medium'));
        $this->assertSame(0, CrosswordEngine::maxHints('hard'));
    }
}
