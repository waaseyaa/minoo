<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Static pages + game short links.
 *
 * Carried over from the retired NewsletterApiRouteProvider (2026-06
 * language-platform slimming). Cut: newsletter routes, guess-price,
 * elders, get-involved, messages, volunteer.
 */
final class StaticPagesRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'static.about',
            RouteBuilder::create('/about')
                ->controller('App\Http\Controller\Site\StaticPageController::about')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.agim.short',
            RouteBuilder::create('/agim')
                ->controller('App\Http\Controller\Games\AgimController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.crossword.short',
            RouteBuilder::create('/crossword')
                ->controller('App\Http\Controller\Games\CrosswordController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.data_sovereignty',
            RouteBuilder::create('/data-sovereignty')
                ->controller('App\Http\Controller\Site\StaticPageController::dataSovereignty')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.games',
            RouteBuilder::create('/games')
                ->controller('App\Http\Controller\Site\StaticPageController::games')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.games.trailing_redirect',
            RouteBuilder::create('/games/')
                ->controller(static fn (): Response => new RedirectResponse('/games', Response::HTTP_PERMANENTLY_REDIRECT))
                ->allowAll()
                ->methods('GET', 'HEAD')
                ->build(),
        );

        $router->addRoute(
            'static.journey',
            RouteBuilder::create('/journey')
                ->controller('App\Http\Controller\Site\StaticPageController::journey')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.legal',
            RouteBuilder::create('/legal')
                ->controller('App\Http\Controller\Site\StaticPageController::legal')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.legal.section',
            RouteBuilder::create('/legal/{section}')
                ->controller('App\Http\Controller\Site\StaticPageController::legal')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.matcher',
            RouteBuilder::create('/matcher')
                ->controller('App\Http\Controller\Site\StaticPageController::matcher')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.search',
            RouteBuilder::create('/search')
                ->controller('App\Http\Controller\Site\StaticPageController::search')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.shkoda.short',
            RouteBuilder::create('/shkoda')
                ->controller('App\Http\Controller\Games\ShkodaController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.studio',
            RouteBuilder::create('/studio')
                ->controller('App\Http\Controller\Site\StaticPageController::studio')
                ->allowAll()->render()->methods('GET')->build(),
        );

        // SEO: XML sitemap (#807). Served by the app (no static file).
        $router->addRoute(
            'seo.sitemap',
            RouteBuilder::create('/sitemap.xml')
                ->controller('App\Http\Controller\Site\SitemapController::xml')
                ->allowAll()->methods('GET')->build(),
        );
    }
}
