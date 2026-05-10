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
                ->controller('App\\Http\\Controller\\Events\\EventController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'events.ics',
            RouteBuilder::create('/events/{slug}.ics')
                ->controller('App\\Http\\Controller\\Events\\EventController::ics')
                ->allowAll()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
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

        $router->addRoute(
            'og.event.png',
            RouteBuilder::create('/og/event/{slug}.png')
                ->controller('App\\Http\\Controller\\OpenGraph\\OpenGraphController::eventPng')
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
                ->controller('App\\Http\\Controller\\Groups\\GroupController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'groups.show',
            RouteBuilder::create('/groups/{slug}')
                ->controller('App\\Http\\Controller\\Groups\\GroupController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'businesses.list',
            RouteBuilder::create('/businesses')
                ->controller('App\\Http\\Controller\\Groups\\BusinessController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'businesses.show',
            RouteBuilder::create('/businesses/{slug}')
                ->controller('App\\Http\\Controller\\Groups\\BusinessController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.business.png',
            RouteBuilder::create('/og/business/{slug}.png')
                ->controller('App\\Http\\Controller\\OpenGraph\\OpenGraphController::businessPng')
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
                ->controller('App\\Http\\Controller\\Teachings\\TeachingController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'teachings.show',
            RouteBuilder::create('/teachings/{slug}')
                ->controller('App\\Http\\Controller\\Teachings\\TeachingController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.teaching.png',
            RouteBuilder::create('/og/teaching/{slug}.png')
                ->controller('App\\Http\\Controller\\OpenGraph\\OpenGraphController::teachingPng')
                ->allowAll()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'og.crisis.sagamok_spanish_river_flood.png',
            RouteBuilder::create('/og/crisis/sagamok-spanish-river-flood.png')
                ->controller('App\\Http\\Controller\\OpenGraph\\OpenGraphController::sagamokSpanishRiverFloodPng')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'og.crisis.incident.png',
            RouteBuilder::create('/og/crisis/{community_slug}/{incident_slug}.png')
                ->controller('App\\Http\\Controller\\OpenGraph\\OpenGraphController::crisisIncidentPng')
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
    }
}
