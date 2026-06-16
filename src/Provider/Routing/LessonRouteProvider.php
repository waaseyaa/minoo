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
 * Public since Phase 4: written consent has been granted for the corpus, so the
 * lesson (and its media) are open. The Anishinaabemowin is read verbatim from
 * the community-controlled corpus directory at runtime and never committed.
 */
final class LessonRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'lesson.index',
            RouteBuilder::create('/lesson')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'lesson.one',
            RouteBuilder::create('/lesson/1')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::lessonOne')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'lesson.media',
            RouteBuilder::create('/lesson/media/{kind}/{id}')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::media')
                ->allowAll()
                ->methods('GET')
                ->requirement('kind', 'thumb|audio')
                ->requirement('id', 'sb-[0-9]+')
                ->build(),
        );
    }
}
