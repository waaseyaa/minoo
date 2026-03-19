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
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'type' => [
                    'type' => 'string',
                    'label' => 'Type',
                    'weight' => -1,
                ],
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
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
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
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
                    'weight' => 95,
                ],
                'verified_at' => [
                    'type' => 'datetime',
                    'label' => 'Verified At',
                    'description' => 'When this record was last verified.',
                    'weight' => 96,
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

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
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
