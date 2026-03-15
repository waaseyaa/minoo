<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Event;
use Minoo\Provider\EventServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $event = new Event(['title' => 'Spring Powwow', 'type' => 'powwow', 'starts_at' => '2026-06-21 10:00:00']);

        $this->assertSame('Spring Powwow', $event->get('title'));
        $this->assertSame('powwow', $event->bundle());
        $this->assertSame('2026-06-21 10:00:00', $event->get('starts_at'));
        $this->assertSame(1, $event->get('status'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $event = new Event(['title' => 'Test', 'type' => 'gathering', 'starts_at' => '2026-01-01']);

        $this->assertSame('event', $event->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_fields(): void
    {
        $event = new Event([
            'title' => 'Ceremony',
            'type' => 'ceremony',
            'starts_at' => '2026-06-21 10:00:00',
            'ends_at' => '2026-06-21 18:00:00',
            'location' => 'Mille Lacs',
            'description' => 'Annual ceremony.',
        ]);

        $this->assertSame('Mille Lacs', $event->get('location'));
        $this->assertSame('2026-06-21 18:00:00', $event->get('ends_at'));
        $this->assertSame('Annual ceremony.', $event->get('description'));
    }

    #[Test]
    public function it_defines_community_id_field(): void
    {
        $provider = new EventServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();
        $eventType = array_values(array_filter($types, fn($t) => $t->id() === 'event'))[0];
        $fields = $eventType->getFieldDefinitions();

        $this->assertArrayHasKey('community_id', $fields);
        $this->assertSame('entity_reference', $fields['community_id']['type']);
        $this->assertSame('community', $fields['community_id']['settings']['target_type']);
    }
}
