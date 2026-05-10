<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\I18n\Language;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class PublicContentRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Events ---
        // =====================================================================

        $router->addRoute(
            'events.list',
            RouteBuilder::create('/events')
                ->controller('App\\Controller\\EventController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'events.ics',
            RouteBuilder::create('/events/{slug}.ics')
                ->controller('App\\Controller\\EventController::ics')
                ->allowAll()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'events.show',
            RouteBuilder::create('/events/{slug}')
                ->controller('App\\Controller\\EventController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.event.png',
            RouteBuilder::create('/og/event/{slug}.png')
                ->controller('App\\Controller\\OpenGraphController::eventPng')
                ->allowAll()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        // =====================================================================
        // --- Groups ---
        // =====================================================================

        $router->addRoute(
            'groups.list',
            RouteBuilder::create('/groups')
                ->controller('App\\Controller\\GroupController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'groups.show',
            RouteBuilder::create('/groups/{slug}')
                ->controller('App\\Controller\\GroupController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'businesses.list',
            RouteBuilder::create('/businesses')
                ->controller('App\\Controller\\BusinessController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'businesses.show',
            RouteBuilder::create('/businesses/{slug}')
                ->controller('App\\Controller\\BusinessController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.business.png',
            RouteBuilder::create('/og/business/{slug}.png')
                ->controller('App\\Controller\\OpenGraphController::businessPng')
                ->allowAll()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        // =====================================================================
        // --- Teachings ---
        // =====================================================================

        $router->addRoute(
            'teachings.list',
            RouteBuilder::create('/teachings')
                ->controller('App\\Controller\\TeachingController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'teachings.show',
            RouteBuilder::create('/teachings/{slug}')
                ->controller('App\\Controller\\TeachingController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.teaching.png',
            RouteBuilder::create('/og/teaching/{slug}.png')
                ->controller('App\\Controller\\OpenGraphController::teachingPng')
                ->allowAll()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.crisis.sagamok_spanish_river_flood.png',
            RouteBuilder::create('/og/crisis/sagamok-spanish-river-flood.png')
                ->controller('App\\Controller\\OpenGraphController::sagamokSpanishRiverFloodPng')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'og.crisis.incident.png',
            RouteBuilder::create('/og/crisis/{community_slug}/{incident_slug}.png')
                ->controller('App\\Controller\\OpenGraphController::crisisIncidentPng')
                ->allowAll()
                ->methods('GET')
                ->requirement('community_slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->requirement('incident_slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        // =====================================================================
        // --- Language ---
        // =====================================================================

        $router->addRoute(
            'language.list',
            RouteBuilder::create('/language')
                ->controller('App\\Controller\\LanguageController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'language.search',
            RouteBuilder::create('/language/search')
                ->controller('App\\Controller\\LanguageController::search')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'language.show',
            RouteBuilder::create('/language/{slug}')
                ->controller('App\\Controller\\LanguageController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
