<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Post::class)]
final class PostTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();
        $post = new Post([
            'body' => 'Community gathering this weekend!',
            'user_id' => 1,
            'community_id' => 5,
        ]);
        $after = time();

        $this->assertSame('Community gathering this weekend!', $post->get('body'));
        $this->assertSame(1, $post->get('user_id'));
        $this->assertSame(5, $post->get('community_id'));
        $this->assertSame(1, $post->get('status'));
        $this->assertGreaterThanOrEqual($before, $post->get('created_at'));
        $this->assertLessThanOrEqual($after, $post->get('created_at'));
        $this->assertGreaterThanOrEqual($before, $post->get('updated_at'));
        $this->assertLessThanOrEqual($after, $post->get('updated_at'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $post = new Post(['body' => 'Test', 'user_id' => 1, 'community_id' => 1]);

        $this->assertSame('post', $post->getEntityTypeId());
    }

    #[Test]
    public function it_allows_status_override(): void
    {
        $post = new Post([
            'body' => 'Draft post',
            'user_id' => 1,
            'community_id' => 1,
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
            'community_id' => 1,
            'created_at' => 1711000000,
            'updated_at' => 1711000100,
        ]);

        $this->assertSame(1711000000, $post->get('created_at'));
        $this->assertSame(1711000100, $post->get('updated_at'));
    }

    #[Test]
    public function constructor_requires_required_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Post([]);
    }

    #[Test]
    public function constructor_requires_community_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Post([
            'body' => 'Test',
            'user_id' => 1,
        ]);
    }

    #[Test]
    public function constructor_requires_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Post([
            'user_id' => 1,
            'community_id' => 1,
        ]);
    }
}
