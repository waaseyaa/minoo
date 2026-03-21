<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Entity\Event;
use Minoo\Entity\Group;
use Minoo\Entity\ResourcePerson;
use Minoo\Feed\FeedItem;
use Minoo\Feed\FeedItemFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedItemFactory::class)]
final class FeedItemFactoryTest extends TestCase
{
    private FeedItemFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FeedItemFactory();
    }

    #[Test]
    public function it_creates_event_item(): void
    {
        $entity = new Event([
            'eid' => 42,
            'title' => 'Language Circle',
            'slug' => 'language-circle',
            'starts_at' => '2026-03-22T18:00:00',
            'location' => 'Community Centre',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('event', $entity, typeSlot: 1);

        $this->assertSame('event:42', $item->id);
        $this->assertSame('event', $item->type);
        $this->assertSame('Language Circle', $item->title);
        $this->assertSame('/events/language-circle', $item->url);
        $this->assertSame('Event', $item->badge);
        $this->assertSame(0, $item->weight);
        $this->assertSame('Community Centre', $item->meta);
        $this->assertNotEmpty($item->sortKey);
    }

    #[Test]
    public function it_creates_group_item(): void
    {
        $entity = new Group([
            'gid' => 7,
            'name' => 'Youth Council',
            'slug' => 'youth-council',
            'type' => 'organization',
            'description' => 'Community leadership for youth voices in Sagamok',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('group', $entity, typeSlot: 2);

        $this->assertSame('group:7', $item->id);
        $this->assertSame('group', $item->type);
        $this->assertSame('/groups/youth-council', $item->url);
        $this->assertSame('Group', $item->badge);
    }

    #[Test]
    public function it_creates_business_item(): void
    {
        $entity = new Group([
            'gid' => 10,
            'name' => 'Eagle Feather Crafts',
            'slug' => 'eagle-feather-crafts',
            'type' => 'business',
            'description' => 'Traditional crafts & gifts',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('business', $entity, typeSlot: 3);

        $this->assertSame('business:10', $item->id);
        $this->assertSame('business', $item->type);
        $this->assertSame('/businesses/eagle-feather-crafts', $item->url);
        $this->assertSame('Business', $item->badge);
    }

    #[Test]
    public function it_creates_person_item(): void
    {
        $entity = new ResourcePerson([
            'rpid' => 5,
            'name' => 'Mary Toulouse',
            'slug' => 'mary-toulouse',
            'role' => 'Knowledge Keeper',
            'community' => 'Sagamok Anishnawbek',
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('person', $entity, typeSlot: 4);

        $this->assertSame('person:5', $item->id);
        $this->assertSame('person', $item->type);
        $this->assertSame('/people/mary-toulouse', $item->url);
        $this->assertSame('Person', $item->badge);
        $this->assertSame('Sagamok Anishnawbek', $item->communityName);
        $this->assertSame('Knowledge Keeper', $item->meta);
    }

    #[Test]
    public function it_computes_distance_when_location_provided(): void
    {
        $entity = new Event([
            'eid' => 1,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('event', $entity, typeSlot: 0, lat: 46.5, lon: -81.2, entityLat: 46.6, entityLon: -81.3);

        $this->assertNotNull($item->distance);
        $this->assertGreaterThan(0.0, $item->distance);
    }

    #[Test]
    public function it_creates_welcome_synthetic(): void
    {
        $item = $this->factory->createWelcome();

        $this->assertSame('welcome:global', $item->id);
        $this->assertSame('welcome', $item->type);
        $this->assertSame(999, $item->weight);
        $this->assertSame('/about', $item->url);
        $this->assertTrue($item->isSynthetic());
    }

    #[Test]
    public function it_creates_communities_synthetic(): void
    {
        $communities = [
            ['name' => 'Sagamok', 'slug' => 'sagamok-anishnawbek'],
            ['name' => 'Espanola', 'slug' => 'espanola'],
        ];

        $item = $this->factory->createCommunities($communities);

        $this->assertSame('communities:global', $item->id);
        $this->assertSame('communities', $item->type);
        $this->assertSame(500, $item->weight);
        $this->assertSame($communities, $item->payload['communities']);
    }

    #[Test]
    public function it_truncates_long_descriptions(): void
    {
        $longDesc = str_repeat('A', 100);
        $entity = new Group([
            'gid' => 1,
            'name' => 'Test',
            'slug' => 'test',
            'type' => 'organization',
            'description' => $longDesc,
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('group', $entity, typeSlot: 0);

        $this->assertLessThanOrEqual(63, mb_strlen($item->meta ?? ''));
    }
}
