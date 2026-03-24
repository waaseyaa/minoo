<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\EngagementCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;

#[CoversClass(EngagementCalculator::class)]
final class EngagementCalculatorTest extends TestCase
{
    private DBALDatabase $db;
    private EngagementCalculator $calculator;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();
        $this->createTables();
        $this->calculator = new EngagementCalculator($this->db, reactionWeight: 1.0, commentWeight: 3.0);
    }

    #[Test]
    public function zero_interactions_returns_base_weight(): void
    {
        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);
        self::assertEqualsWithDelta(1.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function reactions_increase_weight(): void
    {
        $this->insertReaction('post', 1, 100);
        $this->insertReaction('post', 1, 101);
        $this->insertReaction('post', 1, 102);

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 3*1.0) = 1.0 + 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function comments_weigh_more_than_reactions(): void
    {
        $this->insertComment('post', 1, 100);

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 1*3.0) = 1.0 + 2.0 = 3.0
        self::assertEqualsWithDelta(3.0, $result['post:1']['weight'], 0.01);
    }

    #[Test]
    public function log_dampening_prevents_runaway(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->insertReaction('post', 1, $i);
        }

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        // 1.0 + log2(1 + 100) ≈ 7.66 — not 100
        self::assertLessThan(10.0, $result['post:1']['weight']);
        self::assertGreaterThan(7.0, $result['post:1']['weight']);
    }

    #[Test]
    public function batch_returns_counts_for_display(): void
    {
        $this->insertReaction('post', 1, 100);
        $this->insertReaction('post', 1, 101);
        $this->insertComment('post', 1, 100);

        $result = $this->calculator->computeBatch(['post:1' => ['type' => 'post', 'id' => 1]]);

        self::assertSame(2, $result['post:1']['reactions']);
        self::assertSame(1, $result['post:1']['comments']);
    }

    #[Test]
    public function empty_input_returns_empty(): void
    {
        $result = $this->calculator->computeBatch([]);
        self::assertSame([], $result);
    }

    private function createTables(): void
    {
        $schema = $this->db->schema();
        $schema->createTable('reaction', [
            'fields' => [
                'rid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
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
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'body' => ['type' => 'text'],
                'status' => ['type' => 'int', 'not null' => true, 'default' => '1'],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['cid'],
        ]);
    }

    private function insertReaction(string $targetType, int $targetId, int $userId): void
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

    private function insertComment(string $targetType, int $targetId, int $userId): void
    {
        $this->db->insert('comment')->values([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'body' => 'test comment',
            'status' => 1,
            'created_at' => time(),
            '_data' => '{}',
        ])->execute();
    }
}
