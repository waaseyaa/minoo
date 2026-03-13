<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\ThemeServiceProvider;

final class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function boot(): void
    {
        $twig = ThemeServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return;
        }

        $config = $this->loadConfig();
        $twig->addGlobal('chat_enabled', $config['enabled'] ?? false);
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'chat.send',
            RouteBuilder::create('/api/chat')
                ->controller('Minoo\Controller\ChatController::send')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/config/ai-chat.php';

        if (!file_exists($path)) {
            return ['enabled' => false];
        }

        return require $path;
    }
}
