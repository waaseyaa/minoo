<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\DailyChallenge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DailyChallenge::class)]
final class DailyChallengeTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $challenge = new DailyChallenge([
            'date' => '2026-03-23',
            'dictionary_entry_id' => 42,
        ]);

        $this->assertSame('daily_challenge', $challenge->getEntityTypeId());
        $this->assertSame('2026-03-23', $challenge->get('date'));
        $this->assertSame(42, $challenge->get('dictionary_entry_id'));
        $this->assertSame('english_to_ojibwe', $challenge->get('direction'));
        $this->assertSame('easy', $challenge->get('difficulty_tier'));
    }

    #[Test]
    public function it_accepts_all_fields(): void
    {
        $challenge = new DailyChallenge([
            'date' => '2026-03-24',
            'dictionary_entry_id' => 99,
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'hard',
        ]);

        $this->assertSame('ojibwe_to_english', $challenge->get('direction'));
        $this->assertSame('hard', $challenge->get('difficulty_tier'));
    }
}
