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
 * Lesson (course) surface routes (#861).
 *
 * Lessons are first-class: an index at /lessons and slugged lesson URLs at
 * /lessons/{slug}. The legacy /lesson and /lesson/1 paths 301 to the new URLs.
 *
 * Public since Phase 4: written consent has been granted for the corpus, so the
 * lessons (and their media) are open. The Anishinaabemowin is read verbatim from
 * the community-controlled corpus directory at runtime and never committed.
 */
final class LessonRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'lessons.index',
            RouteBuilder::create('/lessons')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'lessons.show',
            RouteBuilder::create('/lessons/{slug}')
                ->controller('App\\Http\\Controller\\Lesson\\LessonController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z][a-z0-9-]*')
                ->build(),
        );

        // Legacy paths → the new slugged URLs (301).
        $router->addRoute(
            'lesson.legacy_index',
            RouteBuilder::create('/lesson')
                ->controller(static fn (): Response => new RedirectResponse('/lessons', Response::HTTP_MOVED_PERMANENTLY))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'lesson.legacy_one',
            RouteBuilder::create('/lesson/1')
                ->controller(static fn (): Response => new RedirectResponse('/lessons/the-kitchen', Response::HTTP_MOVED_PERMANENTLY))
                ->allowAll()
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

        // Lesson reel: Range-streamed web mp4, consent-gated (#873).
        $router->addRoute(
            'lessons.media.video',
            RouteBuilder::create('/lessons/media/video/{id}')
                ->controller('App\\Http\\Controller\\Language\\CorpusVideoController::video')
                ->allowAll()
                ->methods('GET')
                ->requirement('id', 'sb-[0-9]+')
                ->build(),
        );
    }
}
