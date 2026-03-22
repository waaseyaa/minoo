<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\PostAccessPolicy;
use Minoo\Entity\Post;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostAccessPolicy::class)]
final class PostAccessPolicyTest extends TestCase
{
    #[Test]
    public function anonymous_can_view_post(): void
    {
        $policy = new PostAccessPolicy();
        $post = new Post(['body' => 'Hello community', 'user_id' => 42]);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($post, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_post(): void
    {
        $policy = new PostAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('post', 'post', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function authenticated_can_create_post(): void
    {
        $policy = new PostAccessPolicy();
        $account = $this->createAuthenticatedAccount(10);

        $result = $policy->createAccess('post', 'post', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function author_can_edit_own_post(): void
    {
        $policy = new PostAccessPolicy();
        $post = new Post(['body' => 'My post', 'user_id' => 10]);
        $account = $this->createAuthenticatedAccount(10);

        $result = $policy->access($post, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function non_author_cannot_edit_post(): void
    {
        $policy = new PostAccessPolicy();
        $post = new Post(['body' => 'Their post', 'user_id' => 10]);
        $account = $this->createAuthenticatedAccount(99);

        $result = $policy->access($post, 'update', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function author_can_delete_own_post(): void
    {
        $policy = new PostAccessPolicy();
        $post = new Post(['body' => 'My post', 'user_id' => 10]);
        $account = $this->createAuthenticatedAccount(10);

        $result = $policy->access($post, 'delete', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function non_author_cannot_delete_post(): void
    {
        $policy = new PostAccessPolicy();
        $post = new Post(['body' => 'Their post', 'user_id' => 10]);
        $account = $this->createAuthenticatedAccount(99);

        $result = $policy->access($post, 'delete', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function coordinator_can_delete_any_post(): void
    {
        $policy = new PostAccessPolicy();
        $post = new Post(['body' => 'Their post', 'user_id' => 10]);
        $account = $this->createCoordinatorAccount(99);

        $result = $policy->access($post, 'delete', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function applies_to_post_type(): void
    {
        $policy = new PostAccessPolicy();

        $this->assertTrue($policy->appliesTo('post'));
        $this->assertFalse($policy->appliesTo('event'));
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    private function createAuthenticatedAccount(int $id): AccountInterface
    {
        return new class($id) implements AccountInterface {
            public function __construct(private readonly int $uid) {}
            public function id(): int { return $this->uid; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['authenticated']; }
            public function isAuthenticated(): bool { return true; }
        };
    }

    private function createCoordinatorAccount(int $id): AccountInterface
    {
        return new class($id) implements AccountInterface {
            public function __construct(private readonly int $uid) {}
            public function id(): int { return $this->uid; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['authenticated', 'coordinator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
