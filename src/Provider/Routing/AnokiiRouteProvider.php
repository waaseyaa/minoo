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

    /** Above ROUTE_PRIORITY so literal tab routes win over the /{sub} catch-all. */
    private const int TAB_PRIORITY = 110;

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

        // Transcribe tab (#853): dashboard + autosave API. Higher priority than
        // the /{sub} catch-all below so these literal paths win.
        $router->addRoute(
            'anokii.transcribe',
            RouteBuilder::create('/admin/anokii/transcribe')
                ->controller('App\Http\Controller\Anokii\TranscribeController::index')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anokii.transcribe.save',
            RouteBuilder::create('/admin/anokii/transcribe/save')
                ->controller('App\Http\Controller\Anokii\TranscribeController::save')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('POST')
                ->build(),
        );

        // Curate tab (#855): promote utterances into dictionary_entry / word_part.
        $router->addRoute(
            'anokii.curate',
            RouteBuilder::create('/admin/anokii/curate')
                ->controller('App\Http\Controller\Anokii\CurateController::index')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anokii.curate.promote',
            RouteBuilder::create('/admin/anokii/curate/promote')
                ->controller('App\Http\Controller\Anokii\CurateController::promote')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'anokii.curate.publish',
            RouteBuilder::create('/admin/anokii/curate/publish')
                ->controller('App\Http\Controller\Anokii\CurateController::publish')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'anokii.curate.lesson',
            RouteBuilder::create('/admin/anokii/curate/lesson')
                ->controller('App\Http\Controller\Anokii\CurateController::lesson')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('POST')
                ->build(),
        );

        // Ingest tab (#877): drag-and-drop multi-reel upload + async processing.
        $router->addRoute(
            'anokii.ingest',
            RouteBuilder::create('/admin/anokii/ingest')
                ->controller('App\Http\Controller\Anokii\IngestController::index')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anokii.ingest.upload',
            RouteBuilder::create('/admin/anokii/ingest/upload')
                ->controller('App\Http\Controller\Anokii\IngestController::upload')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'anokii.ingest.status',
            RouteBuilder::create('/admin/anokii/ingest/status')
                ->controller('App\Http\Controller\Anokii\IngestController::status')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anokii.ingest.url',
            RouteBuilder::create('/admin/anokii/ingest/url')
                ->controller('App\Http\Controller\Anokii\IngestController::url')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->methods('POST')
                ->build(),
        );

        // Staff-gated corpus media: serves unreviewed draft reels (status 0) that
        // the consent-gated public routes correctly hide. requireRole is the gate.
        $router->addRoute(
            'anokii.media',
            RouteBuilder::create('/admin/anokii/media/{kind}/{id}')
                ->controller('App\Http\Controller\Anokii\AnokiiMediaController::media')
                ->requireRole(self::STAFF_ROLES)
                ->priority(self::TAB_PRIORITY)
                ->render()
                ->methods('GET')
                ->requirement('kind', 'video|thumb|audio')
                ->requirement('id', '[a-z0-9-]+')
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
