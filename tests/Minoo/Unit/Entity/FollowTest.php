<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Follow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Follow::class)]
final class FollowTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();
        $follow = new Follow([
            'user_id' => 1,
            'target_type' => 'group',
            'target_id' => 10,
        ]);
        $after = time();

        $this->assertSame(1, $follow->get('user_id'));
        $this->assertSame('group', $follow->get('target_type'));
        $this->assertSame(10, $follow->get('target_id'));
        $this->assertGreaterThanOrEqual($before, $follow->get('created_at'));
        $this->assertLessThanOrEqual($after, $follow->get('created_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $follow = new Follow(['user_id' => 1, 'target_type' => 'event', 'target_id' => 1]);

        $this->assertSame('follow', $follow->getEntityTypeId());
    }

    #[Test]
    public function it_accepts_created_at(): void
    {
        $follow = new Follow([
            'user_id' => 1,
            'target_type' => 'group',
            'target_id' => 10,
            'created_at' => 1711000000,
        ]);

        $this->assertSame(1711000000, $follow->get('created_at'));
    }

    #[Test]
    public function constructor_requires_required_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Follow([]);
    }

    #[Test]
    public function constructor_requires_target_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Follow([
            'user_id' => 1,
            'target_id' => 10,
        ]);
    }

    #[Test]
    public function created_at_defaults_to_current_time(): void
    {
        $before = time();
        $follow = new Follow([
            'user_id' => 1,
            'target_type' => 'group',
            'target_id' => 10,
        ]);
        $after = time();

        $this->assertGreaterThanOrEqual($before, $follow->get('created_at'));
        $this->assertLessThanOrEqual($after, $follow->get('created_at'));
    }
}
