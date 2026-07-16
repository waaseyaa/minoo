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
 * Home + the pull-based social feed (#814). The homepage serves anonymous
 * visitors; /feed is the authenticated member feed (cursor pagination, no
 * Mercure). Anonymous hits to /feed redirect home in the controller.
 */
final class PublicHomeFeedRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'home.index',
            RouteBuilder::create('/')
                ->controller('App\\Http\\Controller\\Home\\HomeController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // Legacy alias, retired in the #920 cuts; 301 for old links/bookmarks.
        $router->addRoute(
            'home.alias_redirect',
            RouteBuilder::create('/home')
                ->controller(static fn (): Response => new RedirectResponse('/', Response::HTTP_MOVED_PERMANENTLY))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.index',
            RouteBuilder::create('/feed')
                ->controller('App\\Http\\Controller\\Feed\\FeedController::index')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.api',
            RouteBuilder::create('/api/feed')
                ->controller('App\\Http\\Controller\\Feed\\FeedController::api')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.explore',
            RouteBuilder::create('/explore')
                ->controller('App\\Http\\Controller\\Feed\\FeedController::explore')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
