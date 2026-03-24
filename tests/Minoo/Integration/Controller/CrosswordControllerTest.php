<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Controller;

use Minoo\Entity\CrosswordPuzzle;
use Minoo\Entity\GameSession;
use Minoo\Support\CrosswordEngine;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CrosswordControllerTest extends TestCase
{
    #[Test]
    public function validate_word_identifies_correct_answer(): void
    {
        $result = CrosswordEngine::validateWord(['s', 'h', 'k', 'o', 'd', 'a'], 'shkoda');
        $this->assertTrue($result['correct']);
        $this->assertSame([0, 1, 2, 3, 4, 5], $result['correct_positions']);
        $this->assertSame([], $result['wrong_positions']);
    }

    #[Test]
    public function validate_word_identifies_partial_answer(): void
    {
        $result = CrosswordEngine::validateWord(['s', 'h', 'k', 'x', 'x', 'x'], 'shkoda');
        $this->assertFalse($result['correct']);
        $this->assertSame([0, 1, 2], $result['correct_positions']);
        $this->assertSame([3, 4, 5], $result['wrong_positions']);
    }

    #[Test]
    public function validate_word_handles_case_insensitive_input(): void
    {
        $result = CrosswordEngine::validateWord(['S', 'H', 'K', 'O', 'D', 'A'], 'shkoda');
        $this->assertTrue($result['correct']);
    }

    #[Test]
    public function crossword_session_tracks_grid_state(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'daily',
            'puzzle_id' => 'daily-2026-03-25',
        ]);
        $gridState = ['word_0' => 'completed', 'word_1' => 'completed'];
        $session->set('grid_state', json_encode($gridState));
        $decoded = json_decode((string) $session->get('grid_state'), true);
        $this->assertSame('completed', $decoded['word_0']);
        $this->assertSame('completed', $decoded['word_1']);
    }

    #[Test]
    public function crossword_session_defaults_to_in_progress(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'practice',
            'puzzle_id' => 'practice-001',
        ]);
        $this->assertSame('in_progress', $session->get('status'));
        $this->assertSame(0, $session->get('hints_used'));
    }

    #[Test]
    public function crossword_puzzle_stores_and_retrieves_words(): void
    {
        $words = [
            [
                'dictionary_entry_id' => 1,
                'row' => 0,
                'col' => 0,
                'direction' => 'across',
                'word' => 'shkoda',
            ],
        ];
        $clues = [
            '0' => ['auto' => 'fire', 'elder' => null, 'elder_author' => null],
        ];
        $puzzle = new CrosswordPuzzle([
            'id' => 'test-1',
            'grid_size' => 7,
            'words' => json_encode($words),
            'clues' => json_encode($clues),
        ]);
        $decoded = json_decode((string) $puzzle->get('words'), true);
        $this->assertCount(1, $decoded);
        $this->assertSame('shkoda', $decoded[0]['word']);
        $this->assertSame('across', $decoded[0]['direction']);
    }

    #[Test]
    public function crossword_puzzle_validates_difficulty_tier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid difficulty_tier');

        new CrosswordPuzzle([
            'id' => 'bad-tier',
            'grid_size' => 7,
            'words' => '[]',
            'clues' => '{}',
            'difficulty_tier' => 'impossible',
        ]);
    }

    #[Test]
    public function crossword_puzzle_defaults_to_easy_tier(): void
    {
        $puzzle = new CrosswordPuzzle([
            'id' => 'default-tier',
            'grid_size' => 7,
            'words' => '[]',
            'clues' => '{}',
        ]);
        $this->assertSame('easy', $puzzle->get('difficulty_tier'));
    }

    #[Test]
    public function resolve_clue_prefers_elder_authored(): void
    {
        $clueData = [
            'auto' => 'fire',
            'elder' => 'The warmth that gathers our people',
            'elder_author' => 'Elder Mary',
        ];
        $resolved = CrosswordEngine::resolveClue($clueData);
        $this->assertSame('The warmth that gathers our people', $resolved['text']);
        $this->assertSame('Elder Mary', $resolved['author']);
    }

    #[Test]
    public function resolve_clue_falls_back_to_auto(): void
    {
        $clueData = ['auto' => 'fire', 'elder' => null, 'elder_author' => null];
        $resolved = CrosswordEngine::resolveClue($clueData);
        $this->assertSame('fire', $resolved['text']);
        $this->assertNull($resolved['author']);
    }

    #[Test]
    public function max_hints_varies_by_tier(): void
    {
        $this->assertSame(-1, CrosswordEngine::maxHints('easy'));
        $this->assertSame(2, CrosswordEngine::maxHints('medium'));
        $this->assertSame(0, CrosswordEngine::maxHints('hard'));
    }
}
