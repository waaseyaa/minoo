<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Anokii admin shell routes (#851).
 *
 * Mounts the role-gated Anokii workspace at /admin/anokii. These routes carry
 * an explicit priority (>= 100) so they win over the priority-0 `admin_spa`
 * `/admin/{path}` catch-all (registered by {@see AdminRouteProvider}) — without
 * it, GET /admin/anokii would fall through to the Vue admin SPA. This provider
 * is therefore merged BEFORE AdminRouteProvider in {@see \App\Provider\MinooRoutingStackProvider}.
 *
 * Gating uses Minoo's own roles (`admin`, `elder_coordinator`), mirroring the
 * /staff/* idiom — NOT Anokii's single-admin DashboardGate. The `{sub}` route
 * is a forward-looking catch-all for future tabs (Transcribe / Ingest / Curate);
 * for now every path renders the same landing shell.
 */
final class AnokiiRouteProvider extends AppCoreServiceProvider
{
    /** Beats the priority-0 admin_spa /admin/{path} catch-all. */
    private const int ROUTE_PRIORITY = 100;

    /** Roles permitted into the Anokii workspace (Minoo staff roles). */
    private const string STAFF_ROLES = 'admin,elder_coordinator';

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'anokii.home',
            RouteBuilder::create('/admin/anokii')
                ->controller('App\Http\Controller\Anokii\AnokiiAdminController::index')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::ROUTE_PRIORITY)
                ->render()
                ->methods('GET')
                ->build(),
        );

        // Forward-looking: future workspace tabs live under /admin/anokii/{sub}.
        // All currently resolve to the same landing shell. The requirement keeps
        // this off the admin-surface `_surface` API namespace.
        $router->addRoute(
            'anokii.section',
            RouteBuilder::create('/admin/anokii/{sub}')
                ->controller('App\Http\Controller\Anokii\AnokiiAdminController::index')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::ROUTE_PRIORITY)
                ->render()
                ->methods('GET')
                ->requirement('sub', '[a-z][a-z0-9-]*')
                ->build(),
        );
    }
}
