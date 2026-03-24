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
        // Game page
        $router->addRoute(
            'games.ishkode',
            RouteBuilder::create('/games/ishkode')
                ->controller('Minoo\\Controller\\IshkodeController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // API: get daily challenge
        $router->addRoute(
            'api.games.ishkode.daily',
            RouteBuilder::create('/api/games/ishkode/daily')
                ->controller('Minoo\\Controller\\IshkodeController::daily')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: get random word for practice/streak
        $router->addRoute(
            'api.games.ishkode.word',
            RouteBuilder::create('/api/games/ishkode/word')
                ->controller('Minoo\\Controller\\IshkodeController::word')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // API: submit guess (daily challenge only)
        $router->addRoute(
            'api.games.ishkode.guess',
            RouteBuilder::create('/api/games/ishkode/guess')
                ->controller('Minoo\\Controller\\IshkodeController::guess')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: complete game
        $router->addRoute(
            'api.games.ishkode.complete',
            RouteBuilder::create('/api/games/ishkode/complete')
                ->controller('Minoo\\Controller\\IshkodeController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // API: player stats (auth required)
        $router->addRoute(
            'api.games.ishkode.stats',
            RouteBuilder::create('/api/games/ishkode/stats')
                ->controller('Minoo\\Controller\\IshkodeController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}
