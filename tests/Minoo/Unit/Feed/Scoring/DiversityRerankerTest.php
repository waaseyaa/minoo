<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\FeedItem;
use Minoo\Feed\Scoring\DiversityReranker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiversityReranker::class)]
final class DiversityRerankerTest extends TestCase
{
    private DiversityReranker $reranker;

    protected function setUp(): void
    {
        $this->reranker = new DiversityReranker();
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
        // 4 consecutive posts (exceeds maxConsecutiveType=3), then an event
        $items = [
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'post', 'c2'),
            $this->makeItem('3', 'post', 'c3'),
            $this->makeItem('4', 'post', 'c4'),
            $this->makeItem('5', 'event', 'c5'),
        ];

        $result = $this->reranker->rerank($items);

        // Item 5 (event) should be swapped forward to break the run
        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        $this->assertSame('5', $ids[3], 'Event should be swapped into position 3 to break type run');
        $this->assertSame('4', $ids[4], 'Displaced post should move to position 4');
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
        // 6 items from same community (exceeds maxConsecutiveCommunity=5), different types
        $items = [
            $this->makeItem('1', 'post', 'c1'),
            $this->makeItem('2', 'event', 'c1'),
            $this->makeItem('3', 'teaching', 'c1'),
            $this->makeItem('4', 'post', 'c1'),
            $this->makeItem('5', 'event', 'c1'),
            $this->makeItem('6', 'teaching', 'c1'),
            $this->makeItem('7', 'post', 'c2'),
        ];

        $result = $this->reranker->rerank($items);

        // Item 7 (different community) should be swapped forward
        $ids = array_map(fn(FeedItem $item) => $item->id, $result);
        $this->assertSame('7', $ids[5], 'Item from different community should be swapped into position 5');
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
