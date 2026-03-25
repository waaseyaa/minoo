<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\CrosswordPuzzle;
use Minoo\Entity\DailyChallenge;
use Minoo\Entity\GameSession;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'game_session',
            label: 'Game Session',
            class: GameSession::class,
            keys: ['id' => 'gsid', 'uuid' => 'uuid', 'label' => 'mode'],
            group: 'games',
            fieldDefinitions: [
                'mode' => ['type' => 'string', 'label' => 'Mode', 'weight' => 0],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 1],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 5],
                'user_id' => ['type' => 'integer', 'label' => 'User', 'weight' => 6],
                'guesses' => ['type' => 'text_long', 'label' => 'Guesses', 'description' => 'JSON array of letters guessed.', 'weight' => 10],
                'wrong_count' => ['type' => 'integer', 'label' => 'Wrong Count', 'weight' => 11, 'default' => 0],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 15, 'default' => 'in_progress'],
                'daily_date' => ['type' => 'string', 'label' => 'Daily Date', 'weight' => 16],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 17, 'default' => 'easy'],
                'game_type' => ['type' => 'string', 'label' => 'Game Type', 'weight' => 18, 'default' => 'shkoda'],
                'puzzle_id' => ['type' => 'string', 'label' => 'Puzzle ID', 'weight' => 19],
                'grid_state' => ['type' => 'text_long', 'label' => 'Grid State', 'description' => 'JSON crossword grid fill state.', 'weight' => 20],
                'hints_used' => ['type' => 'integer', 'label' => 'Hints Used', 'weight' => 21, 'default' => 0],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'daily_challenge',
            label: 'Daily Challenge',
            class: DailyChallenge::class,
            keys: ['id' => 'date', 'label' => 'date'],
            group: 'games',
            fieldDefinitions: [
                'date' => ['type' => 'string', 'label' => 'Date', 'weight' => 0],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 5],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 10, 'default' => 'english_to_ojibwe'],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 15, 'default' => 'easy'],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'crossword_puzzle',
            label: 'Crossword Puzzle',
            class: CrosswordPuzzle::class,
            keys: ['id' => 'id', 'label' => 'id'],
            group: 'games',
            fieldDefinitions: [
                'grid_size' => ['type' => 'integer', 'label' => 'Grid Size', 'weight' => 0],
                'words' => ['type' => 'text_long', 'label' => 'Words', 'description' => 'JSON array of word placements.', 'weight' => 5],
                'clues' => ['type' => 'text_long', 'label' => 'Clues', 'description' => 'JSON map of word index to clue data.', 'weight' => 10],
                'theme' => ['type' => 'string', 'label' => 'Theme', 'weight' => 15],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 20, 'default' => 'easy'],
            ],
        ));
    }

    public function boot(): void
    {
        $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);

        $dispatcher->addListener(EntityEvents::PRE_SAVE->value, static function (EntityEvent $event): void {
            if ($event->entity->getEntityTypeId() === 'game_session') {
                $event->entity->set('updated_at', time());
            }
        });
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        // Legacy redirect: /games/ishkode → /games/shkoda (#535)
        $router->addRoute(
            'games.ishkode.redirect',
            RouteBuilder::create('/games/ishkode')
                ->controller('Minoo\\Controller\\ShkodaController::redirectLegacy')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // Game page
        $router->addRoute(
            'games.shkoda',
            RouteBuilder::create('/games/shkoda')
                ->controller('Minoo\\Controller\\ShkodaController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // API: get daily challenge
        $router->addRoute(
            'api.games.shkoda.daily',
            RouteBuilder::create('/api/games/shkoda/daily')
                ->controller('Minoo\\Controller\\ShkodaController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: get random word for practice/streak
        $router->addRoute(
            'api.games.shkoda.word',
            RouteBuilder::create('/api/games/shkoda/word')
                ->controller('Minoo\\Controller\\ShkodaController::word')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: submit guess (daily challenge only)
        $router->addRoute(
            'api.games.shkoda.guess',
            RouteBuilder::create('/api/games/shkoda/guess')
                ->controller('Minoo\\Controller\\ShkodaController::guess')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: complete game
        $router->addRoute(
            'api.games.shkoda.complete',
            RouteBuilder::create('/api/games/shkoda/complete')
                ->controller('Minoo\\Controller\\ShkodaController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: player stats (auth required)
        $router->addRoute(
            'api.games.shkoda.stats',
            RouteBuilder::create('/api/games/shkoda/stats')
                ->controller('Minoo\\Controller\\ShkodaController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // --- Crossword routes ---

        $router->addRoute(
            'games.crossword',
            RouteBuilder::create('/games/crossword')
                ->controller('Minoo\\Controller\\CrosswordController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.daily',
            RouteBuilder::create('/api/games/crossword/daily')
                ->controller('Minoo\\Controller\\CrosswordController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.random',
            RouteBuilder::create('/api/games/crossword/random')
                ->controller('Minoo\\Controller\\CrosswordController::random')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.themes',
            RouteBuilder::create('/api/games/crossword/themes')
                ->controller('Minoo\\Controller\\CrosswordController::themes')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.theme',
            RouteBuilder::create('/api/games/crossword/theme/{slug}')
                ->controller('Minoo\\Controller\\CrosswordController::theme')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.check',
            RouteBuilder::create('/api/games/crossword/check')
                ->controller('Minoo\\Controller\\CrosswordController::check')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.complete',
            RouteBuilder::create('/api/games/crossword/complete')
                ->controller('Minoo\\Controller\\CrosswordController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.hint',
            RouteBuilder::create('/api/games/crossword/hint')
                ->controller('Minoo\\Controller\\CrosswordController::hint')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.abandon',
            RouteBuilder::create('/api/games/crossword/abandon')
                ->controller('Minoo\\Controller\\CrosswordController::abandon')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.crossword.stats',
            RouteBuilder::create('/api/games/crossword/stats')
                ->controller('Minoo\\Controller\\CrosswordController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // --- Matcher routes ---

        $router->addRoute(
            'games.matcher',
            RouteBuilder::create('/games/matcher')
                ->controller('Minoo\\Controller\\MatcherController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.daily',
            RouteBuilder::create('/api/games/matcher/daily')
                ->controller('Minoo\\Controller\\MatcherController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.practice',
            RouteBuilder::create('/api/games/matcher/practice')
                ->controller('Minoo\\Controller\\MatcherController::practice')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.match',
            RouteBuilder::create('/api/games/matcher/match')
                ->controller('Minoo\\Controller\\MatcherController::match')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.complete',
            RouteBuilder::create('/api/games/matcher/complete')
                ->controller('Minoo\\Controller\\MatcherController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.matcher.stats',
            RouteBuilder::create('/api/games/matcher/stats')
                ->controller('Minoo\\Controller\\MatcherController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}
