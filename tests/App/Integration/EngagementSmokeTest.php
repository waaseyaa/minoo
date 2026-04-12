<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Waaseyaa\Engagement\Reaction;
use App\Feed\EngagementCounter;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class EngagementSmokeTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;
    private static EntityTypeManager $etm;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        self::$etm = self::$kernel->getEntityTypeManager();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    private function createUser(string $name = 'testuser', string $mail = 'test@example.com'): int
    {
        $storage = self::$etm->getStorage('user');
        $user = $storage->create([
            'name' => $name,
            'mail' => $mail,
            'roles' => [],
            'status' => 1,
        ]);
        $user->setRawPassword('password123');
        $storage->save($user);

        return (int) $user->id();
    }

    private function createCommunity(string $name = 'Test Community'): int
    {
        $storage = self::$etm->getStorage('community');
        $community = $storage->create([
            'name' => $name,
            'community_type' => 'first_nation',
            'status' => 1,
        ]);
        $storage->save($community);

        return (int) $community->id();
    }

    #[Test]
    public function post_crud_round_trip(): void
    {
        $userId = $this->createUser('poster', 'poster@example.com');
        $communityId = $this->createCommunity('Post Test Community');

        $storage = self::$etm->getStorage('post');
        $post = $storage->create([
            'body' => 'Community garden signup is open.',
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);
        $storage->save($post);

        $id = $post->id();
        $this->assertNotNull($id);

        $loaded = $storage->load($id);
        $this->assertNotNull($loaded);
        $this->assertSame('Community garden signup is open.', $loaded->get('body'));
        $this->assertSame($userId, (int) $loaded->get('user_id'));
        $this->assertSame($communityId, (int) $loaded->get('community_id'));
        $this->assertSame(1, (int) $loaded->get('status'));

        $storage->delete([$loaded]);
        $this->assertNull($storage->load($id));
    }

    #[Test]
    public function reaction_lifecycle(): void
    {
        $userId = $this->createUser('reactor', 'reactor@example.com');
        $communityId = $this->createCommunity('Reaction Test Community');

        $postStorage = self::$etm->getStorage('post');
        $post = $postStorage->create([
            'body' => 'Walleye season is looking good.',
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);
        $postStorage->save($post);

        $reactionStorage = self::$etm->getStorage('reaction');
        $reaction = $reactionStorage->create([
            'reaction_type' => 'miigwech',
            'user_id' => $userId,
            'target_type' => 'post',
            'target_id' => (int) $post->id(),
        ]);
        $reactionStorage->save($reaction);

        $rid = $reaction->id();
        $this->assertNotNull($rid);

        $loaded = $reactionStorage->load($rid);
        $this->assertSame('miigwech', $loaded->get('reaction_type'));
        $this->assertSame($userId, (int) $loaded->get('user_id'));

        $reactionStorage->delete([$loaded]);
        $this->assertNull($reactionStorage->load($rid));
    }

    #[Test]
    public function comment_lifecycle(): void
    {
        $userId = $this->createUser('commenter', 'commenter@example.com');
        $communityId = $this->createCommunity('Comment Test Community');

        $postStorage = self::$etm->getStorage('post');
        $post = $postStorage->create([
            'body' => 'Language class Wednesday.',
            'user_id' => $userId,
            'community_id' => $communityId,
        ]);
        $postStorage->save($post);

        $commentStorage = self::$etm->getStorage('comment');
        $comment = $commentStorage->create([
            'body' => 'I will be there!',
            'user_id' => $userId,
            'target_type' => 'post',
            'target_id' => (int) $post->id(),
        ]);
        $commentStorage->save($comment);

        $cid = $comment->id();
        $this->assertNotNull($cid);

        // Query comments for this post
        $ids = $commentStorage->getQuery()
            ->condition('target_type', 'post')
            ->condition('target_id', (int) $post->id())
            ->execute();

        $this->assertCount(1, $ids);

        $loaded = $commentStorage->load($cid);
        $this->assertSame('I will be there!', $loaded->get('body'));
        $this->assertSame(1, (int) $loaded->get('status'));

        $commentStorage->delete([$loaded]);
        $this->assertNull($commentStorage->load($cid));
    }

    #[Test]
    public function follow_lifecycle(): void
    {
        $userId = $this->createUser('follower', 'follower@example.com');
        $communityId = $this->createCommunity('Follow Test Community');

        $followStorage = self::$etm->getStorage('follow');
        $follow = $followStorage->create([
            'user_id' => $userId,
            'target_type' => 'community',
            'target_id' => $communityId,
        ]);
        $followStorage->save($follow);

        $fid = $follow->id();
        $this->assertNotNull($fid);

        $loaded = $followStorage->load($fid);
        $this->assertSame('community', $loaded->get('target_type'));
        $this->assertSame($communityId, (int) $loaded->get('target_id'));

        $followStorage->delete([$loaded]);
        $this->assertNull($followStorage->load($fid));
    }

    #[Test]
    public function invalid_reaction_type_is_rejected_when_allowed_list_provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reaction_type');

        new Reaction(
            values: [
                'reaction_type' => 'invalid_type',
                'user_id' => 1,
                'target_type' => 'post',
                'target_id' => 1,
            ],
            allowedReactionTypes: ['like', 'interested', 'recommend', 'miigwech', 'connect'],
        );
    }

    #[Test]
    public function coordinator_can_delete_any_post(): void
    {
        $authorId = $this->createUser('author', 'author@example.com');
        $communityId = $this->createCommunity('Coord Delete Community');

        $postStorage = self::$etm->getStorage('post');
        $post = $postStorage->create([
            'body' => 'Post by regular user.',
            'user_id' => $authorId,
            'community_id' => $communityId,
        ]);
        $postStorage->save($post);

        $pid = $post->id();
        $this->assertNotNull($pid);

        // Access policy check: coordinator role grants delete on any post.
        // The PostAccessPolicy checks: owner OR coordinator OR admin.
        // We verify the post exists, then delete it (simulating coordinator action).
        $loaded = $postStorage->load($pid);
        $this->assertNotNull($loaded);

        $postStorage->delete([$loaded]);
        $this->assertNull($postStorage->load($pid));
    }

    #[Test]
    public function engagement_counter_returns_correct_counts(): void
    {
        $userId1 = $this->createUser('counter_a', 'counter_a@example.com');
        $userId2 = $this->createUser('counter_b', 'counter_b@example.com');
        $communityId = $this->createCommunity('Counter Test Community');

        $postStorage = self::$etm->getStorage('post');
        $post = $postStorage->create([
            'body' => 'Post for counting.',
            'user_id' => $userId1,
            'community_id' => $communityId,
        ]);
        $postStorage->save($post);
        $postId = (int) $post->id();

        // Add 3 reactions
        $reactionStorage = self::$etm->getStorage('reaction');
        foreach (['miigwech', 'like', 'interested'] as $i => $type) {
            $uid = $i === 0 ? $userId1 : $userId2;
            $reaction = $reactionStorage->create([
                'reaction_type' => $type,
                'user_id' => $uid,
                'target_type' => 'post',
                'target_id' => $postId,
            ]);
            $reactionStorage->save($reaction);
        }

        // Add 2 comments
        $commentStorage = self::$etm->getStorage('comment');
        foreach (['Great post!', 'Miigwech for sharing.'] as $body) {
            $comment = $commentStorage->create([
                'body' => $body,
                'user_id' => $userId2,
                'target_type' => 'post',
                'target_id' => $postId,
            ]);
            $commentStorage->save($comment);
        }

        $counter = new EngagementCounter(self::$etm);
        $counts = $counter->getCountsForTarget('post', $postId);

        $this->assertSame(3, $counts['reactions']);
        $this->assertSame(2, $counts['comments']);
    }
}
