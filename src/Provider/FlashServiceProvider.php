<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Service\FlashMessageService;
use Minoo\Twig\FlashTwigExtension;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\SSR\ThemeServiceProvider;

final class FlashServiceProvider extends ServiceProvider
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

        $twig->addExtension(new FlashTwigExtension(new FlashMessageService()));
    }
}
