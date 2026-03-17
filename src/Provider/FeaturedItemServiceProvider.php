<?php
declare(strict_types=1);
namespace Minoo\Provider;
use Minoo\Entity\FeaturedItem;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class FeaturedItemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'featured_item',
            label: 'Featured Item',
            class: FeaturedItem::class,
            keys: ['id' => 'fid', 'label' => 'headline'],
            group: 'editorial',
            fieldDefinitions: [
                'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'description' => 'Referenced entity type (event, teaching, group, resource_person).', 'weight' => 1],
                'entity_id' => ['type' => 'integer', 'label' => 'Entity ID', 'description' => 'Referenced entity ID.', 'weight' => 2],
                'headline' => ['type' => 'string', 'label' => 'Headline', 'description' => 'Display headline (overrides entity title when set).', 'weight' => 3],
                'subheadline' => ['type' => 'string', 'label' => 'Subheadline', 'description' => 'Optional subtitle or context line.', 'weight' => 4],
                'weight' => ['type' => 'integer', 'label' => 'Weight', 'description' => 'Sort order (higher = more prominent).', 'default' => 0, 'weight' => 10],
                'starts_at' => ['type' => 'datetime', 'label' => 'Starts At', 'description' => 'When this item begins appearing.', 'weight' => 20],
                'ends_at' => ['type' => 'datetime', 'label' => 'Ends At', 'description' => 'When this item stops appearing.', 'weight' => 21],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'default' => 1, 'weight' => 30],
            ],
        ));
    }
}
