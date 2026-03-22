<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Reaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reaction::class)]
final class ReactionTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();
        $reaction = new Reaction([
            'reaction_type' => 'like',
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
        ]);
        $after = time();

        $this->assertSame('like', $reaction->get('reaction_type'));
        $this->assertSame(1, $reaction->get('user_id'));
        $this->assertSame('event', $reaction->get('target_type'));
        $this->assertSame(42, $reaction->get('target_id'));
        $this->assertGreaterThanOrEqual($before, $reaction->get('created_at'));
        $this->assertLessThanOrEqual($after, $reaction->get('created_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $reaction = new Reaction(['reaction_type' => 'miigwech', 'user_id' => 1, 'target_type' => 'post', 'target_id' => 1]);

        $this->assertSame('reaction', $reaction->getEntityTypeId());
    }

    #[Test]
    public function it_accepts_created_at(): void
    {
        $reaction = new Reaction([
            'reaction_type' => 'interested',
            'user_id' => 1,
            'target_type' => 'post',
            'target_id' => 1,
            'created_at' => 1711000000,
        ]);

        $this->assertSame(1711000000, $reaction->get('created_at'));
    }

    #[Test]
    public function constructor_requires_user_id_and_target(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Reaction([]);
    }

    #[Test]
    public function constructor_requires_reaction_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Reaction([
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
        ]);
    }

    #[Test]
    public function rejects_invalid_reaction_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Reaction([
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
            'reaction_type' => 'invalid_type',
        ]);
    }

    #[Test]
    public function accepts_all_valid_reaction_types(): void
    {
        foreach (Reaction::ALLOWED_REACTION_TYPES as $type) {
            $reaction = new Reaction([
                'reaction_type' => $type,
                'user_id' => 1,
                'target_type' => 'event',
                'target_id' => 1,
            ]);
            $this->assertSame($type, $reaction->get('reaction_type'));
        }
    }
}
