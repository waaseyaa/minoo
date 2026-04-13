<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator;
use Waaseyaa\Bimaaji\Introspection\Admin\AdminIntrospectionProvider;
use Waaseyaa\Bimaaji\Introspection\Entity\EntityIntrospectionProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class BimaajiBridgeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(ApplicationGraphGenerator::class, function (): ApplicationGraphGenerator {
            $etm = $this->resolve(EntityTypeManager::class);

            return new ApplicationGraphGenerator([
                new EntityIntrospectionProvider($etm),
                new AdminIntrospectionProvider($etm),
            ]);
        });
    }
}
