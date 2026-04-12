<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Leader;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class LeaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'leader',
            label: 'Leader',
            class: Leader::class,
            keys: ['id' => 'lid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            fieldDefinitions: [
                'name' => [
                    'type' => 'string',
                    'label' => 'Name',
                    'description' => 'Full name of the leader.',
                    'weight' => 1,
                ],
                'role' => [
                    'type' => 'string',
                    'label' => 'Role',
                    'description' => 'Leadership role (e.g. Chief, Councillor).',
                    'weight' => 2,
                ],
                'email' => [
                    'type' => 'string',
                    'label' => 'Email',
                    'description' => 'Contact email address.',
                    'weight' => 3,
                ],
                'phone' => [
                    'type' => 'string',
                    'label' => 'Phone',
                    'description' => 'Contact phone number.',
                    'weight' => 4,
                ],
                'community_id' => [
                    'type' => 'string',
                    'label' => 'Community ID',
                    'description' => 'NorthCloud community nc_id reference.',
                    'weight' => 5,
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
}
