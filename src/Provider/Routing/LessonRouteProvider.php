<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Lesson (course) surface routes.
 *
 * Every route is admin-gated (requireAuthentication + an in-controller
 * "administer content" check) because the lesson renders consent-gated Elder
 * corpus content. This stays gated until written consent records exist; see
 * LessonController.
 */
final class LessonRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'lesson.index',
            RouteBuilder::create('/lesson')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::index')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'lesson.one',
            RouteBuilder::create('/lesson/1')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::lessonOne')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'lesson.media',
            RouteBuilder::create('/lesson/media/{kind}/{id}')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::media')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('kind', 'thumb|audio')
                ->requirement('id', 'sb-[0-9]+')
                ->build(),
        );
    }
}
