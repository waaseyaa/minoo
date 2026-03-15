<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Group;
use Minoo\Entity\GroupType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GroupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'group',
            label: 'Community Group',
            class: Group::class,
            keys: ['id' => 'gid', 'uuid' => 'uuid', 'label' => 'name', 'bundle' => 'type'],
            group: 'community',
            fieldDefinitions: [
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
                'url' => [
                    'type' => 'uri',
                    'label' => 'Website',
                    'description' => 'External website URL.',
                    'weight' => 10,
                ],
                'region' => [
                    'type' => 'string',
                    'label' => 'Region',
                    'description' => 'Geographic region.',
                    'weight' => 15,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 16,
                ],
                'phone' => [
                    'type' => 'string',
                    'label' => 'Phone',
                    'description' => 'Business phone number in E.164 format.',
                    'weight' => 17,
                ],
                'email' => [
                    'type' => 'string',
                    'label' => 'Email',
                    'weight' => 18,
                ],
                'address' => [
                    'type' => 'string',
                    'label' => 'Address',
                    'description' => 'Physical address.',
                    'weight' => 19,
                ],
                'booking_url' => [
                    'type' => 'uri',
                    'label' => 'Booking URL',
                    'description' => 'External booking link.',
                    'weight' => 20,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 21,
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
            id: 'group_type',
            label: 'Group Type',
            class: GroupType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'community',
        ));
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'groups.list',
            RouteBuilder::create('/groups')
                ->controller('Minoo\\Controller\\GroupController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'groups.show',
            RouteBuilder::create('/groups/{slug}')
                ->controller('Minoo\\Controller\\GroupController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
