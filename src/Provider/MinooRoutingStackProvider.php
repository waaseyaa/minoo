<?php

declare(strict_types=1);

namespace App\Provider;

use App\Provider\Routing\AdminRouteProvider;
use App\Provider\Routing\AuthApiRouteProvider;
use App\Provider\Routing\GamesApiRouteProvider;
use App\Provider\Routing\NewsletterApiRouteProvider;
use App\Provider\Routing\PublicAccountRouteProvider;
use App\Provider\Routing\PublicCommunityRouteProvider;
use App\Provider\Routing\PublicContentRouteProvider;
use App\Provider\Routing\PublicHomeFeedRouteProvider;
use App\Provider\Routing\SocialApiRouteProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Registers all Minoo HTTP routes (single composer entry).
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
            new PublicCommunityRouteProvider(),
            new PublicAccountRouteProvider(),
            new PublicHomeFeedRouteProvider(),
            new AuthApiRouteProvider(),
            new SocialApiRouteProvider(),
            new GamesApiRouteProvider(),
            new NewsletterApiRouteProvider(),
            new AdminRouteProvider(),
        ] as $child) {
            $child->setKernelContext($this->projectRoot, $this->config, $this->manifestFormatters);
            if ($this->kernelResolver !== null) {
                $child->setKernelResolver($this->kernelResolver);
            }
            $child->routes($router, $entityTypeManager);
        }
    }
}
