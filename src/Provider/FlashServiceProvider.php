<?php

declare(strict_types=1);

namespace App\Provider;

use App\Twig\AccountDisplayTwigExtension;
use App\Twig\DateTwigExtension;
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

        $twig->addExtension(new DateTwigExtension());
        $twig->addExtension(new AccountDisplayTwigExtension());
    }
}
