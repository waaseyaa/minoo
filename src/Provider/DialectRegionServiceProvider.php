<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\DialectRegion;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class DialectRegionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'dialect_region',
            label: 'Dialect Region',
            class: DialectRegion::class,
            keys: ['id' => 'code', 'label' => 'name'],
            group: 'language',
        ));
    }
}
