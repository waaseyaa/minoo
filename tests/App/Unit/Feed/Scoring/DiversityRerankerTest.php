<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed\Scoring;

use App\Feed\FeedItem;
use App\Feed\Scoring\DiversityReranker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiversityReranker::class)]
final class DiversityRerankerTest extends TestCase
{
    private DiversityReranker $reranker;

    protected function setUp(): void
    {
        $this->reranker = new DiversityReranker(
            maxConsecutiveType: 2,
            maxConsecutiveCommunity: 2,
            postGuaranteeSlot: 3,
        );
    }

    #[Test]
    public function alreadyDiverseListStaysUnchanged(): void
    {
        $items = [
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'event', 'c2'),
            $this->makeItem('3', 'teaching', 'c3'),
            $this->makeItem('4', 'post', 'c1'),
            $this->makeItem('5', 'event', 'c2'),
        ];

        $result = $this->reranker->rerank($items);

        $this->assertSame(
            ['1', '2', '3', '4', '5'],
            array_map(fn(FeedItem $item) => $item->id, $result),
        );
    }

    #[Test]
    public function swapsWhenTypeThresholdExceeded(): void
    {
        // 3 consecutive posts (exceeds maxConsecutiveType=2), then an event
        $items = [
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'post', 'c2'),
            $this->makeItem('3', 'post', 'c3'),
            $this->makeItem('4', 'event', 'c4'),
        ];

        $result = $this->reranker->rerank($items);

        // Item 4 (event) should be swapped forward to break the run
        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        $this->assertSame('4', $ids[2], 'Event should be swapped into position 2 to break type run');
        $this->assertSame('3', $ids[3], 'Displaced post should move to position 3');
    }

    #[Test]
    public function syntheticItemsAreSkippedAndPinned(): void
    {
        $items = [
            $this->makeSynthetic('welcome:0', 'welcome'),
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'post', 'c2'),
            $this->makeItem('3', 'post', 'c3'),
            $this->makeItem('4', 'post', 'c4'),
            $this->makeItem('5', 'event', 'c5'),
        ];

        $result = $this->reranker->rerank($items);

        // Synthetic item stays pinned at position 0
        $this->assertSame('welcome:0', $result[0]->id);
        $this->assertTrue($result[0]->isSynthetic());
    }

    #[Test]
    public function noSwapWhenNoDifferentItemFound(): void
    {
        // All same type — no swap candidate exists
        $items = [
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'post', 'c1'),
            $this->makeItem('3', 'post', 'c1'),
            $this->makeItem('4', 'post', 'c1'),
            $this->makeItem('5', 'post', 'c1'),
        ];

        $result = $this->reranker->rerank($items);

        // Order unchanged since no different item to swap
        $this->assertSame(
            ['1', '2', '3', '4', '5'],
            array_map(fn(FeedItem $item) => $item->id, $result),
        );
    }

    #[Test]
    public function communityConsecutiveThresholdTriggersSwap(): void
    {
        // 3 items from same community (exceeds maxConsecutiveCommunity=2), different types
        $items = [
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'event', 'c1'),
            $this->makeItem('3', 'teaching', 'c1'),
            $this->makeItem('4', 'post', 'c2'),
        ];

        $result = $this->reranker->rerank($items);

        // Item 4 (different community) should be swapped forward
        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        $this->assertSame('4', $ids[2], 'Item from different community should be swapped into position 2');
    }

    #[Test]
    public function postGuaranteePromotesFirstPostIntoTopSlots(): void
    {
        // Top 3 non-synthetic items are all non-posts; first post is at position 4
        $items = [
            $this->makeItem('1', 'featured', 'c1'),
            $this->makeItem('2', 'person', 'c2'),
            $this->makeItem('3', 'event', 'c3'),
            $this->makeItem('4', 'post', 'c4'),
            $this->makeItem('5', 'event', 'c5'),
        ];

        $result = $this->reranker->rerank($items);

        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        // Post should be pulled into slot 3 (the guarantee position)
        $this->assertSame('4', $ids[2], 'Post should be promoted into position 2 (third slot)');
    }

    #[Test]
    public function postGuaranteeNoOpWhenPostAlreadyPresent(): void
    {
        // A post already in the top 3
        $items = [
            $this->makeItem('1', 'featured', 'c1'),
            $this->makeItem('2', 'post', 'c2'),
            $this->makeItem('3', 'event', 'c3'),
            $this->makeItem('4', 'person', 'c4'),
        ];

        $result = $this->reranker->rerank($items);

        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        $this->assertSame('2', $ids[1], 'Post stays in place — already in top 3');
    }

    #[Test]
    public function postGuaranteeSkipsSyntheticItems(): void
    {
        // Synthetic at top, then 3 non-posts, then a post
        $items = [
            $this->makeSynthetic('welcome:0', 'welcome'),
            $this->makeItem('1', 'featured', 'c1'),
            $this->makeItem('2', 'person', 'c2'),
            $this->makeItem('3', 'event', 'c3'),
            $this->makeItem('4', 'post', 'c4'),
        ];

        $result = $this->reranker->rerank($items);

        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        // Synthetic stays at 0, post pulled into slot 3 (third non-synthetic)
        $this->assertSame('welcome:0', $ids[0]);
        $this->assertSame('4', $ids[3], 'Post promoted to third non-synthetic slot');
    }

    private function makeItem(string $id, string $type, string $community): FeedItem
    {
        return new FeedItem(
            id: $id,
            type: $type,
            title: "Item {$id}",
            url: "/item/{$id}",
            badge: $type,
            weight: 0,
            createdAt: new \DateTimeImmutable(),
            sortKey: str_pad($id, 10, '0', STR_PAD_LEFT) . ":{$type}:{$id}",
            communitySlug: $community,
        );
    }

    private function makeSynthetic(string $id, string $type): FeedItem
    {
        return new FeedItem(
            id: $id,
            type: $type,
            title: ucfirst($type),
            url: '',
            badge: '',
            weight: 999,
            createdAt: new \DateTimeImmutable(),
            sortKey: '9999999999:' . $type . ':0',
        );
    }
}
