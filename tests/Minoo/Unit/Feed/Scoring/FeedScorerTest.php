<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\EngagementCounter;
use Minoo\Feed\FeedItem;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedScorer::class)]
final class FeedScorerTest extends TestCase
{
    private FeedScorer $scorer;
    private AffinityCalculator $affinity;
    private EngagementCounter $engagementCounter;

    protected function setUp(): void
    {
        $this->affinity = $this->createMock(AffinityCalculator::class);
        $this->engagementCounter = $this->createMock(EngagementCounter::class);

        $this->scorer = new FeedScorer(
            affinity: $this->affinity,
            engagement: new EngagementCalculator(),
            decay: new DecayCalculator(halfLifeHours: 24.0),
            reranker: new DiversityReranker(),
            engagementCounter: $this->engagementCounter,
        );
    }

    #[Test]
    public function it_returns_empty_array_for_empty_input(): void
    {
        $this->assertSame([], $this->scorer->score([]));
    }

    #[Test]
    public function it_scores_items_and_sorts_by_score_descending(): void
    {
        $now = new \DateTimeImmutable();
        $recentItem = $this->makeItem('1', 'post', $now->modify('-1 hour'));
        $oldItem = $this->makeItem('2', 'event', $now->modify('-48 hours'));

        $this->engagementCounter->method('getCounts')->willReturn([
            'post:1' => ['reactions' => 0, 'comments' => 0],
            'event:2' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$oldItem, $recentItem]);

        $this->assertCount(2, $result);
        // Recent item should score higher (less decay)
        $this->assertSame('1', $result[0]->id);
        $this->assertSame('2', $result[1]->id);
    }

    #[Test]
    public function it_pins_synthetic_items_to_top(): void
    {
        $now = new \DateTimeImmutable();
        $welcome = $this->makeItem('welcome:1', 'welcome', $now);
        $post = $this->makeItem('1', 'post', $now->modify('-1 hour'));

        $this->engagementCounter->method('getCounts')->willReturn([
            'post:1' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$post, $welcome]);

        $this->assertCount(2, $result);
        $this->assertSame('welcome', $result[0]->type);
        $this->assertSame('post', $result[1]->type);
    }

    #[Test]
    public function it_boosts_featured_items(): void
    {
        $now = new \DateTimeImmutable();
        $featured = $this->makeItem('1', 'featured', $now->modify('-2 hours'), weight: 1000);
        $regular = $this->makeItem('2', 'post', $now->modify('-1 hour'));

        $this->engagementCounter->method('getCounts')->willReturn([
            'featured:1' => ['reactions' => 0, 'comments' => 0],
            'post:2' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$regular, $featured]);

        $this->assertCount(2, $result);
        // Featured should rank first due to boost
        $this->assertSame('1', $result[0]->id);
        $this->assertNotNull($result[0]->score);
        $this->assertGreaterThan($result[1]->score, $result[0]->score);
    }

    #[Test]
    public function it_incorporates_engagement_into_scoring(): void
    {
        $now = new \DateTimeImmutable();
        $popular = $this->makeItem('1', 'post', $now->modify('-2 hours'));
        $unpopular = $this->makeItem('2', 'post', $now->modify('-1 hour'));

        $this->engagementCounter->method('getCounts')->willReturn([
            'post:1' => ['reactions' => 50, 'comments' => 20],
            'post:2' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$unpopular, $popular]);

        // Popular item (despite being older) should score higher due to engagement
        $this->assertSame('1', $result[0]->id);
    }

    #[Test]
    public function it_uses_affinity_for_authenticated_users(): void
    {
        $now = new \DateTimeImmutable();
        $item1 = $this->makeItem('1', 'event', $now->modify('-1 hour'));
        $item2 = $this->makeItem('2', 'event', $now->modify('-1 hour'));

        $this->affinity->method('computeBatch')->willReturn([
            'event:1' => 10.0, // High affinity
            'event:2' => 1.0,  // Base affinity
        ]);

        $this->engagementCounter->method('getCounts')->willReturn([
            'event:1' => ['reactions' => 0, 'comments' => 0],
            'event:2' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$item2, $item1], userId: 42);

        $this->assertCount(2, $result);
        $this->assertSame('1', $result[0]->id);
    }

    #[Test]
    public function it_attaches_engagement_counts_to_items(): void
    {
        $now = new \DateTimeImmutable();
        $item = $this->makeItem('1', 'post', $now);

        $this->engagementCounter->method('getCounts')->willReturn([
            'post:1' => ['reactions' => 5, 'comments' => 3],
        ]);

        $result = $this->scorer->score([$item]);

        $this->assertSame(5, $result[0]->reactionCount);
        $this->assertSame(3, $result[0]->commentCount);
    }

    #[Test]
    public function it_sets_score_on_returned_items(): void
    {
        $now = new \DateTimeImmutable();
        $item = $this->makeItem('1', 'post', $now);

        $this->engagementCounter->method('getCounts')->willReturn([
            'post:1' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$item]);

        $this->assertNotNull($result[0]->score);
        $this->assertGreaterThan(0.0, $result[0]->score);
    }

    #[Test]
    public function score_appears_in_toArray_output(): void
    {
        $now = new \DateTimeImmutable();
        $item = $this->makeItem('1', 'post', $now);

        $this->engagementCounter->method('getCounts')->willReturn([
            'post:1' => ['reactions' => 0, 'comments' => 0],
        ]);

        $result = $this->scorer->score([$item]);
        $array = $result[0]->toArray();

        $this->assertArrayHasKey('score', $array);
        $this->assertIsFloat($array['score']);
    }

    private function makeItem(string $id, string $type, \DateTimeImmutable $createdAt, int $weight = 0): FeedItem
    {
        return new FeedItem(
            id: $id,
            type: $type,
            title: 'Test ' . $type . ' ' . $id,
            url: '/' . $type . '/' . $id,
            badge: ucfirst($type),
            weight: $weight,
            createdAt: $createdAt,
            sortKey: sprintf('%010d_%05d_%s', 999999999 - $weight, 0, $id),
        );
    }
}
