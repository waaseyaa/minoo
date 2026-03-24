<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\CrosswordPuzzle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrosswordPuzzle::class)]
final class CrosswordPuzzleTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $words = [
            ['dictionary_entry_id' => 1, 'row' => 0, 'col' => 0, 'direction' => 'across', 'word' => 'shkoda'],
            ['dictionary_entry_id' => 2, 'row' => 0, 'col' => 3, 'direction' => 'down', 'word' => 'nibi'],
        ];
        $clues = [
            '0' => ['auto' => 'fire', 'elder' => null, 'elder_author' => null],
            '1' => ['auto' => 'water', 'elder' => null, 'elder_author' => null],
        ];

        $puzzle = new CrosswordPuzzle([
            'id' => 'daily-2026-03-25',
            'grid_size' => 7,
            'words' => json_encode($words),
            'clues' => json_encode($clues),
            'difficulty_tier' => 'easy',
        ]);

        $this->assertSame('crossword_puzzle', $puzzle->getEntityTypeId());
        $this->assertSame('daily-2026-03-25', $puzzle->id());
        $this->assertSame(7, $puzzle->get('grid_size'));
        $this->assertNull($puzzle->get('theme'));
        $this->assertSame('easy', $puzzle->get('difficulty_tier'));
    }

    #[Test]
    public function it_requires_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['grid_size' => 7, 'words' => '[]', 'clues' => '{}']);
    }

    #[Test]
    public function it_requires_grid_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['id' => 'test-1', 'words' => '[]', 'clues' => '{}']);
    }

    #[Test]
    public function it_requires_words(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['id' => 'test-1', 'grid_size' => 7, 'clues' => '{}']);
    }

    #[Test]
    public function it_requires_clues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle(['id' => 'test-1', 'grid_size' => 7, 'words' => '[]']);
    }

    #[Test]
    public function it_validates_difficulty_tier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CrosswordPuzzle([
            'id' => 'test-1',
            'grid_size' => 7,
            'words' => '[]',
            'clues' => '{}',
            'difficulty_tier' => 'impossible',
        ]);
    }

    #[Test]
    public function it_accepts_theme(): void
    {
        $puzzle = new CrosswordPuzzle([
            'id' => 'animals-003',
            'grid_size' => 10,
            'words' => '[]',
            'clues' => '{}',
            'theme' => 'animals',
            'difficulty_tier' => 'medium',
        ]);

        $this->assertSame('animals', $puzzle->get('theme'));
        $this->assertSame(10, $puzzle->get('grid_size'));
    }
}
