<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Event;
use Minoo\Entity\EventType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'event',
            label: 'Event',
            class: Event::class,
            keys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            group: 'events',
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'description' => 'Rich text event description.',
                    'weight' => 5,
                ],
                'location' => [
                    'type' => 'string',
                    'label' => 'Location',
                    'description' => 'Physical location or "online".',
                    'weight' => 10,
                ],
                'starts_at' => [
                    'type' => 'datetime',
                    'label' => 'Starts At',
                    'weight' => 15,
                ],
                'ends_at' => [
                    'type' => 'datetime',
                    'label' => 'Ends At',
                    'description' => 'Leave empty for open-ended events.',
                    'weight' => 16,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Featured Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'event_type',
            label: 'Event Type',
            class: EventType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'events',
        ));
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'events.list',
            RouteBuilder::create('/events')
                ->controller('Minoo\\Controller\\EventController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'events.show',
            RouteBuilder::create('/events/{slug}')
                ->controller('Minoo\\Controller\\EventController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
