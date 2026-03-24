<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\FeedItem;
use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Database\DBALDatabase;

#[CoversClass(FeedScorer::class)]
final class FeedScorerTest extends TestCase
{
    private FeedScorer $scorer;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();
        $this->createTables($db);

        $this->scorer = new FeedScorer(
            affinity: new AffinityCalculator($db, new AffinityCache(new MemoryBackend())),
            engagement: new EngagementCalculator($db),
            decay: new DecayCalculator(96.0),
            reranker: new DiversityReranker(3, 5),
            featuredBoost: 100.0,
        );
    }

    #[Test]
    public function scores_items_with_positive_values(): void
    {
        $items = [$this->item('post:1', 'post', time())];

        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

        self::assertCount(1, $result);
        self::assertNotNull($result[0]->score);
        self::assertGreaterThan(0.0, $result[0]->score);
    }

    #[Test]
    public function anonymous_user_gets_engagement_and_decay_only(): void
    {
        $items = [$this->item('post:1', 'post', time())];

        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

        // Anonymous: affinity = 1.0, engagement = 1.0, decay ≈ 1.0
        self::assertEqualsWithDelta(1.0, $result[0]->score, 0.1);
    }

    #[Test]
    public function featured_items_get_boosted_score(): void
    {
        $items = [
            $this->item('post:1', 'post', time()),
            $this->item('featured:1', 'featured', time(), weight: 1000),
        ];

        $result = $this->scorer->score($items, null, null, null, null, [
            'post:1' => 'user:10',
            'featured:1' => 'user:10',
        ]);

        // Featured should rank first (score ≈ 100 vs ≈ 1)
        self::assertSame('featured:1', $result[0]->id);
        self::assertGreaterThan($result[1]->score, $result[0]->score);
    }

    #[Test]
    public function synthetic_items_pinned_to_top(): void
    {
        $welcome = new FeedItem(
            id: 'welcome:0', type: 'welcome', title: 'Welcome',
            url: '', badge: '', weight: 999,
            createdAt: new \DateTimeImmutable(), sortKey: '',
        );
        $items = [$this->item('post:1', 'post', time()), $welcome];

        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

        self::assertSame('welcome:0', $result[0]->id);
    }

    #[Test]
    public function older_content_scores_lower_than_newer(): void
    {
        $now = time();
        $items = [
            $this->item('post:1', 'post', $now - (7 * 86400)), // 7 days old
            $this->item('post:2', 'post', $now),                // brand new
        ];

        $result = $this->scorer->score($items, null, null, null, null, [
            'post:1' => 'user:10',
            'post:2' => 'user:10',
        ]);

        // Newer should rank first
        self::assertSame('post:2', $result[0]->id);
    }

    #[Test]
    public function engagement_counts_attached_to_scored_items(): void
    {
        $items = [$this->item('post:1', 'post', time())];

        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

        // With empty DB, counts should be 0
        self::assertSame(0, $result[0]->reactionCount);
        self::assertSame(0, $result[0]->commentCount);
    }

    #[Test]
    public function sort_key_updated_with_score(): void
    {
        $items = [$this->item('post:1', 'post', time())];

        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

        // Sort key should contain the item ID
        self::assertStringContainsString('post:1', $result[0]->sortKey);
    }

    private function item(string $id, string $type, int $createdAt, int $weight = 0): FeedItem
    {
        return new FeedItem(
            id: $id, type: $type, title: 'Test', url: '/test',
            badge: $type, weight: $weight,
            createdAt: (new \DateTimeImmutable())->setTimestamp($createdAt),
            sortKey: '',
        );
    }

    private function createTables(DBALDatabase $db): void
    {
        $schema = $db->schema();
        foreach (['reaction', 'comment'] as $table) {
            $fields = [
                ($table === 'reaction' ? 'rid' : 'cid') => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ];
            if ($table === 'reaction') {
                $fields['reaction_type'] = ['type' => 'varchar', 'length' => 32];
            } else {
                $fields['body'] = ['type' => 'text'];
                $fields['status'] = ['type' => 'int', 'not null' => true, 'default' => '1'];
            }
            $schema->createTable($table, [
                'fields' => $fields,
                'primary key' => [$table === 'reaction' ? 'rid' : 'cid'],
            ]);
        }

        $schema->createTable('follow', [
            'fields' => [
                'fid' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_type' => ['type' => 'varchar', 'length' => 64],
                'target_id' => ['type' => 'int', 'not null' => true],
                'created_at' => ['type' => 'int', 'not null' => true],
                '_data' => ['type' => 'text', 'not null' => true, 'default' => '{}'],
            ],
            'primary key' => ['fid'],
        ]);
    }
}
