<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed\Scoring;

use App\Feed\Scoring\DecayCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecayCalculator::class)]
final class DecayCalculatorTest extends TestCase
{
    #[Test]
    public function brandNewContentHasNoDecay(): void
    {
        $calculator = new DecayCalculator();
        $now = 1_000_000;

        $score = $calculator->compute($now, $now);

        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    #[Test]
    public function contentAtHalfLifeDecaysToHalf(): void
    {
        $calculator = new DecayCalculator(halfLifeHours: 96.0);
        $now = 1_000_000;
        $createdAt = $now - (96 * 3600);

        $score = $calculator->compute($createdAt, $now);

        $this->assertEqualsWithDelta(0.5, $score, 0.001);
    }

    #[Test]
    public function twoHalfLivesDecaysToQuarter(): void
    {
        $calculator = new DecayCalculator(halfLifeHours: 96.0);
        $now = 1_000_000;
        $createdAt = $now - (2 * 96 * 3600);

        $score = $calculator->compute($createdAt, $now);

        $this->assertEqualsWithDelta(0.25, $score, 0.001);
    }

    #[Test]
    public function customHalfLifeWorks(): void
    {
        $calculator = new DecayCalculator(halfLifeHours: 24.0);
        $now = 1_000_000;
        $createdAt = $now - (24 * 3600);

        $score = $calculator->compute($createdAt, $now);

        $this->assertEqualsWithDelta(0.5, $score, 0.001);
    }

    #[Test]
    public function decayNeverReachesZero(): void
    {
        $calculator = new DecayCalculator();
        $now = 1_000_000;
        $thirtyDaysAgo = $now - (30 * 24 * 3600);

        $score = $calculator->compute($thirtyDaysAgo, $now);

        $this->assertGreaterThan(0.0, $score);
    }
}
