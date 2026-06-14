<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Language-platform slimming (2026-06): events, groups, businesses,
 * teachings, and crisis/OG image routes removed.
 */
final class PublicContentRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Language ---
        // =====================================================================

        $router->addRoute(
            'language.list',
            RouteBuilder::create('/language')
                ->controller('App\\Http\\Controller\\Language\\LanguageController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'language.search',
            RouteBuilder::create('/language/search')
                ->controller('App\\Http\\Controller\\Language\\LanguageController::search')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'language.show',
            RouteBuilder::create('/language/{slug}')
                ->controller('App\\Http\\Controller\\Language\\LanguageController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        // =====================================================================
        // --- Community living-map (#797/#798/#799): seven Mamaweswen nations ---
        // Public factual data only; individual people-listings stay gated (#800).
        // =====================================================================

        $router->addRoute(
            'community.list',
            RouteBuilder::create('/communities')
                ->controller('App\\Http\\Controller\\Community\\CommunityController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'community.show',
            RouteBuilder::create('/communities/{slug}')
                ->controller('App\\Http\\Controller\\Community\\CommunityController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
