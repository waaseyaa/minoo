<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed\Scoring;

use App\Feed\FeedItem;
use App\Feed\Scoring\AffinityCache;
use App\Feed\Scoring\AffinityCalculator;
use App\Feed\Scoring\DecayCalculator;
use App\Feed\Scoring\DiversityReranker;
use App\Feed\Scoring\EngagementCalculator;
use App\Feed\Scoring\FeedScorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(FeedScorer::class)]
final class FeedScorerTest extends TestCase
{
    private FeedScorer $scorer;

    protected function setUp(): void
    {
        $etm = $this->createEmptyEntityTypeManager();

        $this->scorer = new FeedScorer(
            affinity: new AffinityCalculator($etm, new AffinityCache(new MemoryBackend())),
            engagement: new EngagementCalculator($etm),
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

        // Anonymous: affinity=1.0, engagement=1.0, decay≈1.0
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
            $this->item('post:1', 'post', $now - (7 * 86400)),
            $this->item('post:2', 'post', $now),
        ];
        $result = $this->scorer->score($items, null, null, null, null, [
            'post:1' => 'user:10',
            'post:2' => 'user:10',
        ]);

        self::assertSame('post:2', $result[0]->id);
    }

    #[Test]
    public function engagement_counts_attached_to_scored_items(): void
    {
        $items = [$this->item('post:1', 'post', time())];
        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

        self::assertSame(0, $result[0]->reactionCount);
        self::assertSame(0, $result[0]->commentCount);
    }

    #[Test]
    public function sort_key_updated_with_score(): void
    {
        $items = [$this->item('post:1', 'post', time())];
        $result = $this->scorer->score($items, null, null, null, null, ['post:1' => 'user:10']);

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

    private function createEmptyEntityTypeManager(): EntityTypeManager
    {
        $etm = $this->createMock(EntityTypeManager::class);

        $etm->method('getStorage')->willReturnCallback(function () {
            $storage = $this->createMock(EntityStorageInterface::class);
            $query = $this->createMock(EntityQueryInterface::class);
            $query->method('condition')->willReturnSelf();
            $query->method('count')->willReturnSelf();
            $query->method('execute')->willReturn([]);
            $storage->method('getQuery')->willReturn($query);
            $storage->method('loadMultiple')->willReturn([]);

            return $storage;
        });

        return $etm;
    }
}
