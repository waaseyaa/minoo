<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Community\HasCommunityInterface;

#[CoversClass(Post::class)]
final class PostHasCommunityTest extends TestCase
{
    #[Test]
    public function implements_has_community_interface(): void
    {
        $this->assertInstanceOf(HasCommunityInterface::class, new Post(['user_id' => 'u1', 'body' => 'hello', 'community_id' => 'nc-uuid-123']));
    }

    #[Test]
    public function get_community_id_returns_value_set_in_constructor(): void
    {
        $post = new Post(['user_id' => 'u1', 'body' => 'hello', 'community_id' => 'nc-uuid-123']);
        $this->assertSame('nc-uuid-123', $post->getCommunityId());
    }

    #[Test]
    public function set_community_id_updates_value(): void
    {
        $post = new Post(['user_id' => 'u1', 'body' => 'hello', 'community_id' => 'nc-uuid-123']);
        $post->setCommunityId('nc-uuid-456');
        $this->assertSame('nc-uuid-456', $post->getCommunityId());
    }
}
