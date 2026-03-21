<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\OralHistory;
use Minoo\Entity\OralHistoryCollection;
use Minoo\Entity\OralHistoryType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class OralHistoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'oral_history',
            label: 'Oral History',
            class: OralHistory::class,
            keys: ['id' => 'ohid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            group: 'knowledge',
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
                'summary' => [
                    'type' => 'text_long',
                    'label' => 'Summary',
                    'description' => 'Brief description of the oral history.',
                    'weight' => 3,
                ],
                'content' => [
                    'type' => 'text_long',
                    'label' => 'Content',
                    'description' => 'Full oral history transcript or narrative.',
                    'weight' => 5,
                ],
                'audio_url' => [
                    'type' => 'string',
                    'label' => 'Audio URL',
                    'description' => 'URL to audio recording.',
                    'weight' => 6,
                ],
                'duration_seconds' => [
                    'type' => 'integer',
                    'label' => 'Duration (seconds)',
                    'weight' => 7,
                ],
                'recorded_date' => [
                    'type' => 'string',
                    'label' => 'Date Recorded',
                    'weight' => 8,
                ],
                'collection_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Collection',
                    'settings' => ['target_type' => 'oral_history_collection'],
                    'weight' => 9,
                ],
                'story_order' => [
                    'type' => 'integer',
                    'label' => 'Story Order',
                    'description' => 'Sort weight within collection.',
                    'weight' => 10,
                ],
                'contributor_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Contributor',
                    'settings' => ['target_type' => 'contributor'],
                    'weight' => 11,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'cultural_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Cultural Group',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 13,
                ],
                'protocol_level' => [
                    'type' => 'string',
                    'label' => 'Protocol Level',
                    'description' => 'Access protocol: open, community, restricted.',
                    'default_value' => 'open',
                    'weight' => 20,
                ],
                'is_living_record' => [
                    'type' => 'boolean',
                    'label' => 'Living Record',
                    'description' => 'Whether this story can be updated by community members.',
                    'weight' => 21,
                    'default' => 0,
                ],
                'tags' => [
                    'type' => 'entity_reference',
                    'label' => 'Tags',
                    'description' => 'Cross-cutting topic tags.',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 25,
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
            id: 'oral_history_type',
            label: 'Oral History Type',
            class: OralHistoryType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'knowledge',
        ));

        $this->entityType(new EntityType(
            id: 'oral_history_collection',
            label: 'Oral History Collection',
            class: OralHistoryCollection::class,
            keys: ['id' => 'ohcid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'knowledge',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 5,
                ],
                'curator_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Curator',
                    'settings' => ['target_type' => 'contributor'],
                    'weight' => 10,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'protocol_level' => [
                    'type' => 'string',
                    'label' => 'Protocol Level',
                    'description' => 'Access protocol: open, community, restricted.',
                    'default_value' => 'open',
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
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'oral_histories.list',
            RouteBuilder::create('/oral-histories')
                ->controller('Minoo\\Controller\\OralHistoryController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'oral_histories.collection',
            RouteBuilder::create('/oral-histories/collections/{slug}')
                ->controller('Minoo\\Controller\\OralHistoryController::collection')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'oral_histories.show',
            RouteBuilder::create('/oral-histories/{slug}')
                ->controller('Minoo\\Controller\\OralHistoryController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
