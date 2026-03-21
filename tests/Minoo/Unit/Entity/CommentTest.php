<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Comment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Comment::class)]
final class CommentTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $comment = new Comment([
            'body' => 'Great event!',
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
        ]);

        $this->assertSame('Great event!', $comment->get('body'));
        $this->assertSame(1, $comment->get('user_id'));
        $this->assertSame('event', $comment->get('target_type'));
        $this->assertSame(42, $comment->get('target_id'));
        $this->assertSame(1, $comment->get('status'));
        $this->assertSame(0, $comment->get('created_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $comment = new Comment(['body' => 'Test', 'user_id' => 1, 'target_type' => 'post', 'target_id' => 1]);

        $this->assertSame('comment', $comment->getEntityTypeId());
    }

    #[Test]
    public function it_allows_status_override(): void
    {
        $comment = new Comment([
            'body' => 'Hidden comment',
            'user_id' => 1,
            'target_type' => 'event',
            'target_id' => 42,
            'status' => 0,
        ]);

        $this->assertSame(0, $comment->get('status'));
    }
}
