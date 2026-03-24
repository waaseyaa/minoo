<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\DailyChallenge;
use Minoo\Entity\GameSession;
use Waaseyaa\Entity\EntityType;
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
    }
}
