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
        $reaction = new Reaction([
            'emoji' => "\u{2764}\u{FE0F}",
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
        ]);

        $this->assertSame("\u{2764}\u{FE0F}", $reaction->get('emoji'));
        $this->assertSame(1, $reaction->get('user_id'));
        $this->assertSame('event', $reaction->get('target_type'));
        $this->assertSame(42, $reaction->get('target_id'));
        $this->assertSame(0, $reaction->get('created_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $reaction = new Reaction(['emoji' => "\u{1F44D}", 'user_id' => 1, 'target_type' => 'post', 'target_id' => 1]);

        $this->assertSame('reaction', $reaction->getEntityTypeId());
    }

    #[Test]
    public function it_accepts_created_at(): void
    {
        $reaction = new Reaction([
            'emoji' => "\u{1F44D}",
            'user_id' => 1,
            'target_type' => 'post',
            'target_id' => 1,
            'created_at' => 1711000000,
        ]);

        $this->assertSame(1711000000, $reaction->get('created_at'));
    }
}
