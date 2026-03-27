<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\UserBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserBlock::class)]
final class UserBlockTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $before = time();

        $block = new UserBlock([
            'blocker_id' => 1,
            'blocked_id' => 2,
        ]);

        $after = time();

        $this->assertSame(1, $block->get('blocker_id'));
        $this->assertSame(2, $block->get('blocked_id'));
        $this->assertGreaterThanOrEqual($before, $block->get('created_at'));
        $this->assertLessThanOrEqual($after, $block->get('created_at'));
    }

    #[Test]
    public function it_uses_provided_created_at(): void
    {
        $block = new UserBlock([
            'blocker_id' => 1,
            'blocked_id' => 2,
            'created_at' => 1000,
        ]);

        $this->assertSame(1000, $block->get('created_at'));
    }

    #[Test]
    public function constructor_requires_blocker_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: blocker_id');

        new UserBlock(['blocked_id' => 2]);
    }

    #[Test]
    public function constructor_requires_blocked_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: blocked_id');

        new UserBlock(['blocker_id' => 1]);
    }

    #[Test]
    public function constructor_rejects_self_block(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot block yourself');

        new UserBlock(['blocker_id' => 5, 'blocked_id' => 5]);
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $block = new UserBlock(['blocker_id' => 1, 'blocked_id' => 2]);

        $this->assertSame('user_block', $block->getEntityTypeId());
    }
}
