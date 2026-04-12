<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed;

use App\Entity\Event;
use App\Entity\Group;
use App\Feed\EntityLoaderService;
use App\Feed\FeedAssembler;
use App\Feed\FeedContext;
use App\Feed\FeedItemFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedAssembler::class)]
final class FeedAssemblerTest extends TestCase
{
    private FeedAssembler $assembler;
    private EntityLoaderService $loader;

    protected function setUp(): void
    {
        $this->loader = $this->createMock(EntityLoaderService::class);
        $this->assembler = new FeedAssembler($this->loader, new FeedItemFactory());
    }

    #[Test]
    public function it_returns_empty_feed_when_no_content(): void
    {
        $this->loader->method('loadUpcomingEvents')->willReturn([]);
        $this->loader->method('loadGroups')->willReturn([]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadPosts')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = FeedContext::defaults();
        $response = $this->assembler->assemble($ctx);

        // Communities synthetic card is always injected, even with no entity content
        $this->assertCount(1, $response->items);
        $this->assertSame('communities:global', $response->items[0]->id);
        $this->assertNull($response->nextCursor);
        $this->assertSame('all', $response->activeFilter);
    }

    #[Test]
    public function it_interleaves_types_in_feed(): void
    {
        $event = new Event(['eid' => 1, 'title' => 'E1', 'slug' => 'e1', 'status' => 1, 'created_at' => time()]);
        $group = new Group(['gid' => 2, 'name' => 'G1', 'slug' => 'g1', 'type' => 'organization', 'status' => 1, 'created_at' => time()]);
        $this->loader->method('loadUpcomingEvents')->willReturn([$event]);
        $this->loader->method('loadGroups')->willReturn([$group]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPosts')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = FeedContext::defaults();
        $response = $this->assembler->assemble($ctx);

        $types = array_map(fn($item) => $item->type, $response->items);

        // Communities synthetic is always injected; entities interleaved by typeSlot
        $this->assertContains('communities', $types);
        $this->assertContains('event', $types);
        $this->assertContains('group', $types);
        $this->assertNotContains('person', $types);
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        $event = new Event(['eid' => 1, 'title' => 'E1', 'slug' => 'e1', 'status' => 1, 'created_at' => time()]);
        $group = new Group(['gid' => 2, 'name' => 'G1', 'slug' => 'g1', 'type' => 'organization', 'status' => 1, 'created_at' => time()]);

        $this->loader->method('loadUpcomingEvents')->willReturn([$event]);
        $this->loader->method('loadGroups')->willReturn([$group]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadPosts')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = new FeedContext(activeFilter: 'event');
        $response = $this->assembler->assemble($ctx);

        $entityTypes = array_filter(
            array_map(fn($item) => $item->type, $response->items),
            fn($t) => !in_array($t, ['communities', 'welcome'], true),
        );

        foreach ($entityTypes as $t) {
            $this->assertSame('event', $t);
        }
    }

    #[Test]
    public function it_injects_welcome_card_on_first_visit(): void
    {
        $this->loader->method('loadUpcomingEvents')->willReturn([]);
        $this->loader->method('loadGroups')->willReturn([]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadPosts')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = new FeedContext(isFirstVisit: true);
        $response = $this->assembler->assemble($ctx);

        $types = array_map(fn($item) => $item->type, $response->items);
        $this->assertContains('welcome', $types);
    }

    #[Test]
    public function it_paginates_with_cursor(): void
    {
        $events = [];
        for ($i = 1; $i <= 25; $i++) {
            $events[] = new Event([
                'eid' => $i,
                'title' => "Event {$i}",
                'slug' => "event-{$i}",
                'status' => 1,
                'created_at' => time() - $i,
            ]);
        }

        $this->loader->method('loadUpcomingEvents')->willReturn($events);
        $this->loader->method('loadGroups')->willReturn([]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadPosts')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        // First page
        $ctx = new FeedContext(limit: 10);
        $page1 = $this->assembler->assemble($ctx);
        $this->assertNotNull($page1->nextCursor);

        // Second page
        $ctx2 = new FeedContext(cursor: $page1->nextCursor, limit: 10);
        $page2 = $this->assembler->assemble($ctx2);

        // No ID overlap between pages (excluding synthetic items)
        $page1Ids = array_map(fn($i) => $i->id, array_filter($page1->items, fn($i) => !$i->isSynthetic()));
        $page2Ids = array_map(fn($i) => $i->id, array_filter($page2->items, fn($i) => !$i->isSynthetic()));
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    #[Test]
    public function it_sorts_deterministically_golden_file(): void
    {
        // Fixed timestamps for deterministic output
        $base = 1711000000;
        $event = new Event(['eid' => 1, 'title' => 'E1', 'slug' => 'e1', 'status' => 1, 'created_at' => $base]);
        $group = new Group(['gid' => 2, 'name' => 'G1', 'slug' => 'g1', 'type' => 'organization', 'status' => 1, 'created_at' => $base - 100]);
        $this->loader->method('loadUpcomingEvents')->willReturn([$event]);
        $this->loader->method('loadGroups')->willReturn([$group]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPosts')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = FeedContext::defaults();
        $response = $this->assembler->assemble($ctx);

        // Communities card (weight 500) sorts before regular items (weight 0)
        $ids = array_map(fn($item) => $item->id, $response->items);
        $commIdx = array_search('communities:global', $ids, true);
        $entityIds = array_filter($ids, fn($id) => !str_starts_with($id, 'communities:'));

        $this->assertSame(0, $commIdx, 'Communities card should be first (weight 500)');
        $this->assertNotEmpty($entityIds);

        // Run twice — same order
        $response2 = $this->assembler->assemble($ctx);
        $ids2 = array_map(fn($item) => $item->id, $response2->items);
        $this->assertSame($ids, $ids2, 'Sort must be deterministic across runs');
    }
}
