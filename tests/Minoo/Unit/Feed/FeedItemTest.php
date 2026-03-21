<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\FeedItem;
use Minoo\Feed\FeedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedItem::class)]
#[CoversClass(FeedResponse::class)]
final class FeedItemTest extends TestCase
{
    #[Test]
    public function it_creates_entity_backed_item(): void
    {
        $item = new FeedItem(
            id: 'event:42',
            type: 'event',
            title: 'Language Circle',
            url: '/events/language-circle',
            badge: 'Event',
            weight: 0,
            createdAt: new \DateTimeImmutable('2026-03-21'),
            sortKey: '9999:0000000002.30:01:09223372036854775707:event:42',
        );

        $this->assertSame('event:42', $item->id);
        $this->assertSame('event', $item->type);
        $this->assertSame('/events/language-circle', $item->url);
        $this->assertNull($item->entity);
        $this->assertSame([], $item->payload);
    }

    #[Test]
    public function it_creates_synthetic_item(): void
    {
        $item = new FeedItem(
            id: 'welcome:global',
            type: 'welcome',
            title: 'Welcome to Minoo',
            url: '/about',
            badge: 'Welcome',
            weight: 999,
            createdAt: new \DateTimeImmutable('2026-03-21'),
            sortKey: '9000:0099999.99:00:09223372036854775707:welcome:global',
        );

        $this->assertSame('welcome:global', $item->id);
        $this->assertSame(999, $item->weight);
        $this->assertTrue($item->isSynthetic());
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $item = new FeedItem(
            id: 'event:42',
            type: 'event',
            title: 'Language Circle',
            url: '/events/language-circle',
            badge: 'Event',
            weight: 0,
            createdAt: new \DateTimeImmutable('2026-03-21'),
            sortKey: 'key',
            subtitle: 'Tomorrow at 6 PM',
            distance: 2.3,
            communityName: 'Sagamok',
            meta: 'Community Centre',
            date: '2026-03-22T18:00:00',
        );

        $json = $item->toArray();

        $this->assertSame('event:42', $json['id']);
        $this->assertSame('Event', $json['badge']);
        $this->assertSame(2.3, $json['distance']);
        $this->assertArrayNotHasKey('weight', $json);
        $this->assertArrayNotHasKey('sortKey', $json);
    }

    #[Test]
    public function it_creates_feed_response(): void
    {
        $items = [
            new FeedItem(
                id: 'event:1', type: 'event', title: 'Test',
                url: '/events/test', badge: 'Event', weight: 0,
                createdAt: new \DateTimeImmutable(), sortKey: 'key',
            ),
        ];
        $response = new FeedResponse($items, 'cursor123', 'all');

        $this->assertCount(1, $response->items);
        $this->assertSame('cursor123', $response->nextCursor);
        $this->assertSame('all', $response->activeFilter);
    }
}
