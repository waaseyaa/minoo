<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Entity\Reaction;
use Minoo\Seed\EngagementSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EngagementSeeder::class)]
final class EngagementSeederTest extends TestCase
{
    #[Test]
    public function users_returns_six_entries_with_required_fields(): void
    {
        $users = EngagementSeeder::users();

        $this->assertCount(6, $users);

        foreach ($users as $user) {
            $this->assertArrayHasKey('name', $user);
            $this->assertArrayHasKey('mail', $user);
            $this->assertArrayHasKey('roles', $user);
            $this->assertArrayHasKey('status', $user);
            $this->assertArrayHasKey('community_index', $user);
            $this->assertSame(1, $user['status']);
            $this->assertIsArray($user['roles']);
        }
    }

    #[Test]
    public function posts_returns_twelve_entries_with_required_fields(): void
    {
        $posts = EngagementSeeder::posts();

        $this->assertCount(12, $posts);

        $userCount = count(EngagementSeeder::users());
        $communityCount = count(EngagementSeeder::communityNames());

        foreach ($posts as $post) {
            $this->assertArrayHasKey('body', $post);
            $this->assertArrayHasKey('user_index', $post);
            $this->assertArrayHasKey('community_index', $post);
            $this->assertNotEmpty($post['body']);
            $this->assertLessThan($userCount, $post['user_index']);
            $this->assertLessThan($communityCount, $post['community_index']);
        }
    }

    #[Test]
    public function reactions_have_valid_reaction_types(): void
    {
        $reactions = EngagementSeeder::reactions();

        $this->assertGreaterThanOrEqual(25, count($reactions));

        foreach ($reactions as $reaction) {
            $this->assertArrayHasKey('reaction_type', $reaction);
            $this->assertArrayHasKey('user_index', $reaction);
            $this->assertArrayHasKey('target_type', $reaction);
            $this->assertArrayHasKey('post_index', $reaction);
            $this->assertContains($reaction['reaction_type'], Reaction::ALLOWED_REACTION_TYPES);
        }
    }

    #[Test]
    public function comments_have_body_and_target(): void
    {
        $comments = EngagementSeeder::comments();

        $this->assertGreaterThanOrEqual(10, count($comments));

        foreach ($comments as $comment) {
            $this->assertArrayHasKey('body', $comment);
            $this->assertArrayHasKey('user_index', $comment);
            $this->assertArrayHasKey('target_type', $comment);
            $this->assertArrayHasKey('post_index', $comment);
            $this->assertNotEmpty($comment['body']);
        }
    }

    #[Test]
    public function follows_have_valid_target_types(): void
    {
        $follows = EngagementSeeder::follows();

        $this->assertGreaterThanOrEqual(8, count($follows));

        $allowedTargetTypes = ['community', 'post'];

        foreach ($follows as $follow) {
            $this->assertArrayHasKey('user_index', $follow);
            $this->assertArrayHasKey('target_type', $follow);
            $this->assertArrayHasKey('target_index', $follow);
            $this->assertContains($follow['target_type'], $allowedTargetTypes);
        }
    }

    #[Test]
    public function community_names_returns_three_entries(): void
    {
        $names = EngagementSeeder::communityNames();

        $this->assertCount(3, $names);

        foreach ($names as $name) {
            $this->assertIsString($name);
            $this->assertNotEmpty($name);
        }
    }

    #[Test]
    public function all_reaction_types_are_represented(): void
    {
        $reactions = EngagementSeeder::reactions();
        $usedTypes = array_unique(array_column($reactions, 'reaction_type'));

        foreach (Reaction::ALLOWED_REACTION_TYPES as $type) {
            $this->assertContains($type, $usedTypes, "Reaction type '{$type}' is not represented in seed data.");
        }
    }
}
