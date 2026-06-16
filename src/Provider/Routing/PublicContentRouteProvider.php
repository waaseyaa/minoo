<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Public content routes. Events restored for the relaunch (#819); groups,
 * businesses, teachings, and crisis/OG image routes remain removed.
 */
final class PublicContentRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Events (#819) ---
        // =====================================================================

        $router->addRoute(
            'events.list',
            RouteBuilder::create('/events')
                ->controller('App\\Http\\Controller\\Events\\EventController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'events.show',
            RouteBuilder::create('/events/{slug}')
                ->controller('App\\Http\\Controller\\Events\\EventController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

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
