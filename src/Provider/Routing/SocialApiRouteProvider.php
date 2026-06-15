<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Social spine write API (#813): reactions, comments, follows, posts.
 *
 * Consent by participation — every mutating route requires authentication, so a
 * member only ever acts for themselves. Reading comments is public.
 * (The cut chat + messaging routes are NOT restored here.)
 */
final class SocialApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $ctrl = 'App\\Http\\Controller\\Social\\EngagementController';

        $router->addRoute(
            'engagement.react',
            RouteBuilder::create('/api/engagement/react')
                ->controller($ctrl . '::react')->requireAuthentication()->methods('POST')->build(),
        );
        $router->addRoute(
            'engagement.deleteReaction',
            RouteBuilder::create('/api/engagement/react/{id}')
                ->controller($ctrl . '::deleteReaction')->requireAuthentication()->methods('DELETE')->requirement('id', '\\d+')->build(),
        );
        $router->addRoute(
            'engagement.comment',
            RouteBuilder::create('/api/engagement/comment')
                ->controller($ctrl . '::comment')->requireAuthentication()->methods('POST')->build(),
        );
        $router->addRoute(
            'engagement.deleteComment',
            RouteBuilder::create('/api/engagement/comment/{id}')
                ->controller($ctrl . '::deleteComment')->requireAuthentication()->methods('DELETE')->requirement('id', '\\d+')->build(),
        );
        $router->addRoute(
            'engagement.getComments',
            RouteBuilder::create('/api/engagement/comments/{target_type}/{target_id}')
                ->controller($ctrl . '::getComments')->allowAll()->methods('GET')->requirement('target_id', '\\d+')->build(),
        );
        $router->addRoute(
            'engagement.follow',
            RouteBuilder::create('/api/engagement/follow')
                ->controller($ctrl . '::follow')->requireAuthentication()->methods('POST')->build(),
        );
        $router->addRoute(
            'engagement.deleteFollow',
            RouteBuilder::create('/api/engagement/follow/{id}')
                ->controller($ctrl . '::deleteFollow')->requireAuthentication()->methods('DELETE')->requirement('id', '\\d+')->build(),
        );
        $router->addRoute(
            'engagement.createPost',
            RouteBuilder::create('/api/engagement/post')
                ->controller($ctrl . '::createPost')->requireAuthentication()->methods('POST')->build(),
        );
        $router->addRoute(
            'engagement.deletePost',
            RouteBuilder::create('/api/engagement/post/{id}')
                ->controller($ctrl . '::deletePost')->requireAuthentication()->methods('DELETE')->requirement('id', '\\d+')->build(),
        );
    }
}
