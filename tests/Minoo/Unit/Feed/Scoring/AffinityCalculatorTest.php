<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Database\DBALDatabase;

#[CoversClass(AffinityCalculator::class)]
final class AffinityCalculatorTest extends TestCase
{
    private DBALDatabase $db;
    private AffinityCache $affinityCache;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();
        $this->affinityCache = new AffinityCache(new MemoryBackend());

        $this->createTables();
    }

    private function createTables(): void
    {
        $schema = $this->db->schema();

        $schema->createTable('follow', [
            'fields' => [
                'fid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64, 'not null' => true],
                'target_id' => ['type' => 'int', 'not null' => true],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['fid'],
        ]);

        $schema->createTable('reaction', [
            'fields' => [
                'rid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64, 'not null' => true],
                'target_id' => ['type' => 'int', 'not null' => true],
                'reaction_type' => ['type' => 'varchar', 'length' => 32],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['rid'],
        ]);

        $schema->createTable('comment', [
            'fields' => [
                'cid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64, 'not null' => true],
                'target_id' => ['type' => 'int', 'not null' => true],
                'body' => ['type' => 'text'],
                'status' => ['type' => 'int', 'not null' => true, 'default' => '1'],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['cid'],
        ]);
    }

    private function insertFollow(int $userId, string $targetType, int $targetId): void
    {
        $this->db->insert('follow')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }

    private function insertReaction(int $userId, string $targetType, int $targetId): void
    {
        $this->db->insert('reaction')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reaction_type' => 'like',
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }

    private function insertComment(int $userId, string $targetType, int $targetId): void
    {
        $this->db->insert('comment')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'body' => 'test',
            'status' => 1,
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }

    private function makeCalculator(): AffinityCalculator
    {
        return new AffinityCalculator($this->db, $this->affinityCache);
    }

    #[Test]
    public function anonymous_user_returns_null(): void
    {
        $calc = $this->makeCalculator();

        $result = $calc->computeBatch(null, ['user:99'], null, null);

        $this->assertNull($result);
    }

    #[Test]
    public function unknown_source_gets_base_affinity(): void
    {
        $calc = $this->makeCalculator();

        $result = $calc->computeBatch(1, ['user:99'], null, null);

        $this->assertNotNull($result);
        $this->assertSame(1.0, $result['user:99']);
    }

    #[Test]
    public function follow_adds_points(): void
    {
        $this->insertFollow(1, 'user', 10);

        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['user:10', 'user:20'], null, null);

        $this->assertNotNull($result);
        // user:10: base(1) + follow(4) = 5
        $this->assertSame(5.0, $result['user:10']);
        // user:20: base(1) only
        $this->assertSame(1.0, $result['user:20']);
    }

    #[Test]
    public function reactions_add_capped_points(): void
    {
        // Add 7 reactions targeting user:10's content
        for ($i = 0; $i < 7; $i++) {
            $this->insertReaction(1, 'user', 10);
        }

        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['user:10'], null, null);

        $this->assertNotNull($result);
        // base(1) + min(7*1, 5) = 6.0
        $this->assertSame(6.0, $result['user:10']);
    }

    #[Test]
    public function comments_add_capped_points(): void
    {
        // Add 5 comments targeting community:5
        for ($i = 0; $i < 5; $i++) {
            $this->insertComment(1, 'community', 5);
        }

        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['community:5'], null, null);

        $this->assertNotNull($result);
        // base(1) + min(5*2, 6) = 7.0
        $this->assertSame(7.0, $result['community:5']);
    }

    #[Test]
    public function same_community_adds_points(): void
    {
        $calc = $this->makeCalculator();

        $sourceLocations = [
            'community:10' => ['lat' => 46.0, 'lon' => -81.0, 'community_id' => 10],
            'community:20' => ['lat' => 46.0, 'lon' => -81.0, 'community_id' => 20],
        ];

        $result = $calc->computeBatch(1, ['community:10', 'community:20'], 10, null, $sourceLocations);

        $this->assertNotNull($result);
        // community:10: base(1) + sameCommunity(3) = 4
        $this->assertSame(4.0, $result['community:10']);
        // community:20: base(1) only (different community)
        $this->assertSame(1.0, $result['community:20']);
    }

    #[Test]
    public function geo_proximity_close_adds_points(): void
    {
        $calc = $this->makeCalculator();

        $userLocation = ['lat' => 46.0, 'lon' => -81.0];
        $sourceLocations = [
            'community:1' => ['lat' => 46.1, 'lon' => -81.0],
        ];

        $result = $calc->computeBatch(1, ['community:1'], null, $userLocation, $sourceLocations);

        $this->assertNotNull($result);
        // base(1) + geoClose(2) = 3
        $this->assertSame(3.0, $result['community:1']);
    }

    #[Test]
    public function geo_proximity_mid_adds_points(): void
    {
        $calc = $this->makeCalculator();

        $userLocation = ['lat' => 46.0, 'lon' => -81.0];
        $sourceLocations = [
            'community:1' => ['lat' => 47.0, 'lon' => -81.0],
        ];

        $result = $calc->computeBatch(1, ['community:1'], null, $userLocation, $sourceLocations);

        $this->assertNotNull($result);
        // base(1) + geoMid(1) = 2
        $this->assertSame(2.0, $result['community:1']);
    }

    #[Test]
    public function geo_beyond_mid_range_gets_no_bonus(): void
    {
        $calc = $this->makeCalculator();

        $userLocation = ['lat' => 46.0, 'lon' => -81.0];
        $sourceLocations = [
            'community:1' => ['lat' => 51.0, 'lon' => -81.0],
        ];

        $result = $calc->computeBatch(1, ['community:1'], null, $userLocation, $sourceLocations);

        $this->assertNotNull($result);
        // base(1) only
        $this->assertSame(1.0, $result['community:1']);
    }

    #[Test]
    public function cache_is_used_on_second_call(): void
    {
        $this->insertFollow(1, 'user', 10);

        $calc = $this->makeCalculator();

        $result1 = $calc->computeBatch(1, ['user:10'], null, null);
        $this->assertSame(5.0, $result1['user:10']);

        // Remove the follow row — second call should still return cached value.
        $this->db->delete('follow')
            ->condition('user_id', 1)
            ->execute();

        $result2 = $calc->computeBatch(1, ['user:10'], null, null);
        $this->assertSame(5.0, $result2['user:10']);
    }

    #[Test]
    public function empty_source_keys_returns_empty_array(): void
    {
        $calc = $this->makeCalculator();

        $result = $calc->computeBatch(1, [], null, null);

        $this->assertSame([], $result);
    }
}
