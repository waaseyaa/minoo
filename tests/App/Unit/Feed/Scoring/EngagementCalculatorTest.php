<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed\Scoring;

use App\Feed\Scoring\EngagementCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(EngagementCalculator::class)]
final class EngagementCalculatorTest extends TestCase
{
    #[Test]
    public function zero_interactions_returns_base_weight(): void
    {
        $calc = $this->makeCalculator(reactionCount: 0, commentCount: 0);
        $result = $calc->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        self::assertEqualsWithDelta(1.0, $result['post:1']['weight'], 0.01);
        self::assertSame(0, $result['post:1']['reactions']);
        self::assertSame(0, $result['post:1']['comments']);
    }

    #[Test]
    public function reactions_increase_weight(): void
    {
        $calc = $this->makeCalculator(reactionCount: 3, commentCount: 0);
        $result = $calc->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 3*1.0) = 1.0 + 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['post:1']['weight'], 0.01);
        self::assertSame(3, $result['post:1']['reactions']);
    }

    #[Test]
    public function comments_weigh_more_than_reactions(): void
    {
        $calc = $this->makeCalculator(reactionCount: 0, commentCount: 1);
        $result = $calc->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 1*3.0) = 1.0 + 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function log_dampening_prevents_runaway(): void
    {
        $calc = $this->makeCalculator(reactionCount: 100, commentCount: 0);
        $result = $calc->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 100) ≈ 7.66
        self::assertLessThan(10.0, $result['post:1']['weight']);
        self::assertGreaterThan(7.0, $result['post:1']['weight']);
    }

    #[Test]
    public function empty_input_returns_empty(): void
    {
        $calc = $this->makeCalculator(reactionCount: 0, commentCount: 0);
        self::assertSame([], $calc->computeBatch([]));
    }

    private function makeCalculator(int $reactionCount, int $commentCount): EngagementCalculator
    {
        $etm = $this->createMock(EntityTypeManager::class);

        $etm->method('getStorage')->willReturnCallback(function (string $type) use ($reactionCount, $commentCount) {
            $count = $type === 'reaction' ? $reactionCount : $commentCount;
            $storage = $this->createMock(EntityStorageInterface::class);
            $query = $this->createMock(EntityQueryInterface::class);
            $query->method('condition')->willReturnSelf();
            $query->method('count')->willReturnSelf();
            $query->method('execute')->willReturn([$count]);
            $storage->method('getQuery')->willReturn($query);

            return $storage;
        });

        return new EngagementCalculator($etm);
    }
}
