<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use Anokii\Config\DistributionConfig;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * The public /api/lang language API routes (issue #894).
 *
 * Mounted as a peer to the package /api/chat, gated on
 * DistributionConfig::moduleEnabled('language'): when the language module is off
 * these routes are not registered at all, so the surface comes and goes with the
 * module flag. The routes are public (allowAll); consent gating happens at the
 * entity layer inside the controller's lookup. Composed by
 * {@see \App\Provider\MinooRoutingStackProvider}, which forwards the kernel
 * services resolver so $this->resolve() works here.
 */
final class LanguageApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $config = $this->resolve(DistributionConfig::class);
        if (!$config instanceof DistributionConfig || !$config->moduleEnabled('language')) {
            return;
        }

        $router->addRoute(
            'api.lang.dialects',
            RouteBuilder::create('/api/lang/dialects')
                ->controller('App\Http\Controller\Language\LanguageApiController::dialects')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.lang.translate',
            RouteBuilder::create('/api/lang/translate')
                ->controller('App\Http\Controller\Language\LanguageApiController::translate')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The own-corpus lexicon endpoint (#916). Public read but server-to-server
        // by intent (no CORS header is set); reads only the community corpus, never
        // OPD.
        $router->addRoute(
            'api.lang.lookup',
            RouteBuilder::create('/api/lang/lookup')
                ->controller('App\Http\Controller\Language\LanguageApiController::lookup')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
