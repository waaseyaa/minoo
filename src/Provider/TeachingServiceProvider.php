<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Teaching;
use Minoo\Entity\TeachingType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class TeachingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: Teaching::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
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
                'content' => [
                    'type' => 'text_long',
                    'label' => 'Content',
                    'description' => 'Full teaching content.',
                    'weight' => 5,
                ],
                'cultural_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Cultural Group',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 10,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'tags' => [
                    'type' => 'entity_reference',
                    'label' => 'Tags',
                    'description' => 'Cross-cutting topic tags.',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
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
            id: 'teaching_type',
            label: 'Teaching Type',
            class: TeachingType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'knowledge',
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'teachings.list',
            RouteBuilder::create('/teachings')
                ->controller('Minoo\\Controller\\TeachingController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'teachings.show',
            RouteBuilder::create('/teachings/{slug}')
                ->controller('Minoo\\Controller\\TeachingController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
