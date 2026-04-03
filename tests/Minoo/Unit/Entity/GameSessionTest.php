<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\GameSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GameSession::class)]
final class GameSessionTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();
        $session = new GameSession([
            'mode' => 'daily',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 42,
        ]);
        $after = time();

        $this->assertSame('game_session', $session->getEntityTypeId());
        $this->assertSame('daily', $session->get('mode'));
        $this->assertSame('english_to_ojibwe', $session->get('direction'));
        $this->assertSame(42, $session->get('dictionary_entry_id'));
        $this->assertSame('in_progress', $session->get('status'));
        $this->assertSame(0, $session->get('wrong_count'));
        $this->assertSame('[]', $session->get('guesses'));
        $this->assertSame('easy', $session->get('difficulty_tier'));
        $this->assertNull($session->get('user_id'));
        $this->assertNull($session->get('daily_date'));
        $this->assertGreaterThanOrEqual($before, $session->get('created_at'));
        $this->assertLessThanOrEqual($after, $session->get('updated_at'));
    }

    #[Test]
    public function it_requires_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession(['direction' => 'english_to_ojibwe', 'dictionary_entry_id' => 1]);
    }

    #[Test]
    public function it_requires_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession(['mode' => 'daily', 'dictionary_entry_id' => 1]);
    }

    #[Test]
    public function it_requires_dictionary_entry_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession(['mode' => 'daily', 'direction' => 'english_to_ojibwe']);
    }

    #[Test]
    public function it_validates_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession([
            'mode' => 'invalid',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 1,
        ]);
    }

    #[Test]
    public function it_validates_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GameSession([
            'mode' => 'daily',
            'direction' => 'invalid',
            'dictionary_entry_id' => 1,
        ]);
    }

    #[Test]
    public function it_accepts_all_fields(): void
    {
        $session = new GameSession([
            'mode' => 'streak',
            'direction' => 'ojibwe_to_english',
            'dictionary_entry_id' => 99,
            'user_id' => 7,
            'daily_date' => '2026-03-23',
            'difficulty_tier' => 'hard',
            'guesses' => '["a","b"]',
            'wrong_count' => 2,
            'status' => 'won',
        ]);

        $this->assertSame(7, $session->get('user_id'));
        $this->assertSame('2026-03-23', $session->get('daily_date'));
        $this->assertSame('hard', $session->get('difficulty_tier'));
        $this->assertSame('["a","b"]', $session->get('guesses'));
        $this->assertSame(2, $session->get('wrong_count'));
        $this->assertSame('won', $session->get('status'));
    }

    #[Test]
    public function it_defaults_game_type_to_shkoda(): void
    {
        $session = new GameSession([
            'mode' => 'daily',
            'direction' => 'english_to_ojibwe',
            'dictionary_entry_id' => 42,
        ]);

        $this->assertSame('shkoda', $session->get('game_type'));
    }

    #[Test]
    public function it_accepts_crossword_game_type(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'daily',
        ]);

        $this->assertSame('crossword', $session->get('game_type'));
        $this->assertSame('daily', $session->get('mode'));
        $this->assertNull($session->get('puzzle_id'));
        $this->assertNull($session->get('grid_state'));
        $this->assertSame(0, $session->get('hints_used'));
    }

    #[Test]
    public function it_validates_game_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid game_type: bogus');
        new GameSession([
            'game_type' => 'bogus',
            'mode' => 'daily',
        ]);
    }

    #[Test]
    public function crossword_accepts_themed_mode(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'themed',
            'puzzle_id' => 'animals-2026-03',
        ]);

        $this->assertSame('themed', $session->get('mode'));
        $this->assertSame('animals-2026-03', $session->get('puzzle_id'));
    }

    #[Test]
    public function crossword_accepts_abandoned_status(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'daily',
            'status' => 'abandoned',
        ]);

        $this->assertSame('abandoned', $session->get('status'));
    }

    #[Test]
    public function crossword_accepts_completed_status(): void
    {
        $session = new GameSession([
            'game_type' => 'crossword',
            'mode' => 'daily',
            'status' => 'completed',
        ]);

        $this->assertSame('completed', $session->get('status'));
    }

    #[Test]
    public function matcher_game_type_is_valid(): void
    {
        $session = new GameSession([
            'game_type' => 'matcher',
            'mode' => 'daily',
            'direction' => 'ojibwe_to_english',
        ]);

        $this->assertSame('matcher', $session->get('game_type'));
    }

    #[Test]
    public function agim_game_type_is_valid(): void
    {
        $session = new GameSession([
            'game_type' => 'agim',
            'mode' => 'practice',
        ]);

        $this->assertSame('agim', $session->get('game_type'));
        $this->assertSame('practice', $session->get('mode'));
    }

    #[Test]
    public function agim_accepts_streak_difficulty_tier(): void
    {
        $session = new GameSession([
            'game_type' => 'agim',
            'mode' => 'practice',
            'difficulty_tier' => 'streak',
        ]);

        $this->assertSame('streak', $session->get('difficulty_tier'));
    }

    #[Test]
    public function shkoda_still_requires_direction_and_entry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: direction');
        new GameSession([
            'game_type' => 'shkoda',
            'mode' => 'daily',
        ]);
    }
}
