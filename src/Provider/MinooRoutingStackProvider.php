<?php

declare(strict_types=1);

namespace App\Provider;

use App\Provider\Routing\AdminRouteProvider;
use App\Provider\Routing\AnokiiRouteProvider;
use App\Provider\Routing\AuthApiRouteProvider;
use App\Provider\Routing\GamesApiRouteProvider;
use App\Provider\Routing\LessonRouteProvider;
use App\Provider\Routing\PublicAccountRouteProvider;
use App\Provider\Routing\PublicContentRouteProvider;
use App\Provider\Routing\PublicHomeFeedRouteProvider;
use App\Provider\Routing\SocialApiRouteProvider;
use App\Provider\Routing\StaticPagesRouteProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Registers all Minoo HTTP routes (single composer entry).
 *
 * Language-platform slimming (2026-06): community, social (engagement,
 * messaging, chat), and newsletter route providers de-registered.
 */
final class MinooRoutingStackProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        foreach ([
            new PublicContentRouteProvider(),
            new PublicAccountRouteProvider(),
            new PublicHomeFeedRouteProvider(),
            new AuthApiRouteProvider(),
            new GamesApiRouteProvider(),
            new LessonRouteProvider(),
            new StaticPagesRouteProvider(),
            // Anokii shell must register before the admin-surface SPA catch-all
            // so its priority-100 /admin/anokii routes win over admin_spa.
            new AnokiiRouteProvider(),
            new AdminRouteProvider(),
            new SocialApiRouteProvider(),
        ] as $child) {
            // mergeChildProvider() forwards both kernel context and the kernel-services
            // resolver introduced in alpha.171 (replaces the older setKernelResolver path).
            $this->mergeChildProvider($child);
            $child->routes($router, $entityTypeManager);
        }
    }
}
