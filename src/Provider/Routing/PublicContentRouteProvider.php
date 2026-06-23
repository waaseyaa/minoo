<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Public content routes. Events (#819) and groups (#821) restored for the
 * relaunch; businesses, teachings, and crisis/OG image routes remain removed.
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
        // --- Groups (#821) ---
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

        // =====================================================================
        // --- Chat: cite-only language assistant (#822) ---
        // =====================================================================

        $router->addRoute(
            'chat.index',
            RouteBuilder::create('/chat')
                ->controller('App\\Http\\Controller\\Chat\\ChatController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Language ---
        // =====================================================================

        // Corpus audio: served from MINOO_CORPUS_PATH (never committed), gated to
        // consent_public + published example_sentence rows (Phase 4).
        $router->addRoute(
            'corpus.audio',
            RouteBuilder::create('/media/corpus/audio/{id}')
                ->controller('App\\Http\\Controller\\Language\\CorpusAudioController::audio')
                ->allowAll()
                ->methods('GET')
                ->requirement('id', '[a-z0-9][a-z0-9-]*')
                ->build(),
        );

        // Corpus images: whiteboard thumbnail + illustrative context image, same
        // consent gate and source directory as the audio (#852).
        $router->addRoute(
            'corpus.thumb',
            RouteBuilder::create('/media/corpus/thumb/{id}')
                ->controller('App\\Http\\Controller\\Language\\CorpusImageController::thumb')
                ->allowAll()
                ->methods('GET')
                ->requirement('id', '[a-z0-9][a-z0-9-]*')
                ->build(),
        );

        $router->addRoute(
            'corpus.context',
            RouteBuilder::create('/media/corpus/context/{id}')
                ->controller('App\\Http\\Controller\\Language\\CorpusImageController::context')
                ->allowAll()
                ->methods('GET')
                ->requirement('id', '[a-z0-9][a-z0-9-]*')
                ->build(),
        );

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
