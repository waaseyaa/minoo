<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Post::class)]
final class PostTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $post = new Post([
            'body' => 'Community gathering this weekend!',
            'user_id' => 1,
        ]);

        $this->assertSame('Community gathering this weekend!', $post->get('body'));
        $this->assertSame(1, $post->get('user_id'));
        $this->assertSame(1, $post->get('status'));
        $this->assertSame(0, $post->get('created_at'));
        $this->assertSame(0, $post->get('updated_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $post = new Post(['body' => 'Test', 'user_id' => 1]);

        $this->assertSame('post', $post->getEntityTypeId());
    }

    #[Test]
    public function it_allows_status_override(): void
    {
        $post = new Post([
            'body' => 'Draft post',
            'user_id' => 1,
            'status' => 0,
        ]);

        $this->assertSame(0, $post->get('status'));
    }

    #[Test]
    public function it_accepts_timestamps(): void
    {
        $post = new Post([
            'body' => 'Test',
            'user_id' => 1,
            'created_at' => 1711000000,
            'updated_at' => 1711000100,
        ]);

        $this->assertSame(1711000000, $post->get('created_at'));
        $this->assertSame(1711000100, $post->get('updated_at'));
    }
}
