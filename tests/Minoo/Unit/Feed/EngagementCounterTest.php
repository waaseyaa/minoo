<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\EngagementCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(EngagementCounter::class)]
final class EngagementCounterTest extends TestCase
{
    #[Test]
    public function it_returns_empty_for_no_targets(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->expects($this->never())->method('getStorage');

        $counter = new EngagementCounter($etm);
        $result = $counter->getCounts([]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_zero_counts_when_no_engagement(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('count')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        $counter = new EngagementCounter($etm);
        $result = $counter->getCounts([['type' => 'event', 'id' => 42]]);

        $this->assertSame(['event:42' => ['reactions' => 0, 'comments' => 0]], $result);
    }

    #[Test]
    public function it_counts_reactions_and_comments(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('count')->willReturnSelf();

        $callCount = 0;
        $query->method('execute')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            // First call = reactions (3 IDs), second call = comments (1 ID)
            return $callCount === 1 ? [1, 2, 3] : [10];
        });

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        $counter = new EngagementCounter($etm);
        $result = $counter->getCounts([['type' => 'event', 'id' => 1]]);

        $this->assertSame(3, $result['event:1']['reactions']);
        $this->assertSame(1, $result['event:1']['comments']);
    }

    #[Test]
    public function it_returns_counts_for_single_target(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('count')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        $counter = new EngagementCounter($etm);
        $result = $counter->getCountsForTarget('post', 5);

        $this->assertSame(['reactions' => 0, 'comments' => 0], $result);
    }
}
