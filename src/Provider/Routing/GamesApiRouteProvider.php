<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GamesApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Games ---
        // =====================================================================

        // Legacy redirect: /games/ishkode -> /games/shkoda (#535)
        $router->addRoute(
            'games.ishkode.redirect',
            RouteBuilder::create('/games/ishkode')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::redirectLegacy')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // Game page
        $router->addRoute(
            'games.shkoda',
            RouteBuilder::create('/games/shkoda')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // API: get daily challenge
        $router->addRoute(
            'api.games.shkoda.daily',
            RouteBuilder::create('/api/games/shkoda/daily')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: get random word for practice/streak
        $router->addRoute(
            'api.games.shkoda.word',
            RouteBuilder::create('/api/games/shkoda/word')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::word')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: submit guess (daily challenge only)
        $router->addRoute(
            'api.games.shkoda.guess',
            RouteBuilder::create('/api/games/shkoda/guess')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::guess')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: complete game
        $router->addRoute(
            'api.games.shkoda.complete',
            RouteBuilder::create('/api/games/shkoda/complete')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: player stats (auth required)
        $router->addRoute(
            'api.games.shkoda.stats',
            RouteBuilder::create('/api/games/shkoda/stats')
                ->controller('App\\Http\\Controller\\Games\\ShkodaController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // --- Crossword routes ---

        $router->addRoute(
            'games.crossword',
            RouteBuilder::create('/games/crossword')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.daily',
            RouteBuilder::create('/api/games/crossword/daily')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.random',
            RouteBuilder::create('/api/games/crossword/random')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::random')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.themes',
            RouteBuilder::create('/api/games/crossword/themes')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::themes')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.theme',
            RouteBuilder::create('/api/games/crossword/theme/{slug}')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::theme')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.check',
            RouteBuilder::create('/api/games/crossword/check')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::check')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.complete',
            RouteBuilder::create('/api/games/crossword/complete')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.hint',
            RouteBuilder::create('/api/games/crossword/hint')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::hint')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.abandon',
            RouteBuilder::create('/api/games/crossword/abandon')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::abandon')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.stats',
            RouteBuilder::create('/api/games/crossword/stats')
                ->controller('App\\Http\\Controller\\Games\\CrosswordController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // --- Matcher routes ---

        $router->addRoute(
            'games.matcher',
            RouteBuilder::create('/games/matcher')
                ->controller('App\\Http\\Controller\\Games\\MatcherController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.daily',
            RouteBuilder::create('/api/games/matcher/daily')
                ->controller('App\\Http\\Controller\\Games\\MatcherController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.practice',
            RouteBuilder::create('/api/games/matcher/practice')
                ->controller('App\\Http\\Controller\\Games\\MatcherController::practice')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.match',
            RouteBuilder::create('/api/games/matcher/match')
                ->controller('App\\Http\\Controller\\Games\\MatcherController::match')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.complete',
            RouteBuilder::create('/api/games/matcher/complete')
                ->controller('App\\Http\\Controller\\Games\\MatcherController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.stats',
            RouteBuilder::create('/api/games/matcher/stats')
                ->controller('App\\Http\\Controller\\Games\\MatcherController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // --- Agim routes ---

        $router->addRoute(
            'games.agim',
            RouteBuilder::create('/games/agim')
                ->controller('App\\Http\\Controller\\Games\\AgimController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.start',
            RouteBuilder::create('/api/games/agim/start')
                ->controller('App\\Http\\Controller\\Games\\AgimController::start')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.prompt',
            RouteBuilder::create('/api/games/agim/prompt')
                ->controller('App\\Http\\Controller\\Games\\AgimController::prompt')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.answer',
            RouteBuilder::create('/api/games/agim/answer')
                ->controller('App\\Http\\Controller\\Games\\AgimController::answer')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.complete',
            RouteBuilder::create('/api/games/agim/complete')
                ->controller('App\\Http\\Controller\\Games\\AgimController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.stats',
            RouteBuilder::create('/api/games/agim/stats')
                ->controller('App\\Http\\Controller\\Games\\AgimController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'games.guess_price',
            RouteBuilder::create('/games/guess-price')
                ->controller('App\\Http\\Controller\\Games\\GuessPriceController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'games.guess_price.trailing_redirect',
            RouteBuilder::create('/games/guess-price/')
                ->controller(static fn (): Response => new RedirectResponse('/games/guess-price', Response::HTTP_PERMANENTLY_REDIRECT))
                ->allowAll()
                ->methods('GET', 'HEAD')
                ->build(),
        );

        // --- Journey routes ---

        $router->addRoute(
            'games.journey',
            RouteBuilder::create('/games/journey')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.journey.scenes',
            RouteBuilder::create('/api/games/journey/scenes')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::scenes')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.journey.scene',
            RouteBuilder::create('/api/games/journey/scene/{slug}')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::scene')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.journey.tap',
            RouteBuilder::create('/api/games/journey/tap')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::tap')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.journey.hint',
            RouteBuilder::create('/api/games/journey/hint')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::hint')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.journey.complete',
            RouteBuilder::create('/api/games/journey/complete')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.journey.stats',
            RouteBuilder::create('/api/games/journey/stats')
                ->controller('App\\Http\\Controller\\Games\\JourneyController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}
