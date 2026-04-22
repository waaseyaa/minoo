<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\WaaseyaaRouter;

class AppRoutesServiceProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
    }
}
