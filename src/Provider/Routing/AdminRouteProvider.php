<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\Bootstrap\AccessPolicyRegistry;
use Waaseyaa\Foundation\Kernel\Bootstrap\KernelPolicyDependencyResolver;
use Waaseyaa\Foundation\Kernel\Bootstrap\ManifestBootstrapper;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AdminRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Language-platform slimming (2026-06): volunteer/coordinator
        // dashboards and the newsletter admin API removed.

        // =====================================================================
        // --- Staff tools (SSR) - under /staff and /api/staff (not /admin/*) ---
        // --- so they never collide with the admin-surface /admin/{path} SPA. ---
        // =====================================================================

        $router->addRoute(
            'staff.ingestion',
            RouteBuilder::create('/staff/ingestion')
                ->controller('App\Http\Controller\Ingestion\IngestionDashboardController::index')
                // Match /staff/users: site admins use role `admin`, not the flat `permissions`
                // array (Waaseyaa only auto-grants all perms for role `administrator`).
                ->requireRole('admin,elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.nc_sync_status',
            RouteBuilder::create('/api/staff/nc-sync-status')
                ->controller('App\Http\Controller\Ingestion\IngestionApiController::status')
                ->requireRole('admin,elder_coordinator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.envelope',
            RouteBuilder::create('/api/staff/ingestion/envelope')
                ->controller('App\Http\Controller\Ingestion\IngestionApiController::ingestEnvelope')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.approve',
            RouteBuilder::create('/api/staff/ingestion/{id}/approve')
                ->controller('App\Http\Controller\Ingestion\IngestionApiController::approve')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.reject',
            RouteBuilder::create('/api/staff/ingestion/{id}/reject')
                ->controller('App\Http\Controller\Ingestion\IngestionApiController::reject')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.materialize',
            RouteBuilder::create('/api/staff/ingestion/{id}/materialize')
                ->controller('App\Http\Controller\Ingestion\IngestionApiController::materialize')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'legacy.staff.ingestion',
            RouteBuilder::create('/admin/ingestion')
                ->controller(static fn (): Response => new RedirectResponse('/staff/ingestion', Response::HTTP_MOVED_PERMANENTLY))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'legacy.staff.users',
            RouteBuilder::create('/admin/users')
                ->controller(static fn (): Response => new RedirectResponse('/staff/users', Response::HTTP_MOVED_PERMANENTLY))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Role Management ---
        // =====================================================================

        $router->addRoute(
            'staff.users',
            RouteBuilder::create('/staff/users')
                ->controller('App\Http\Controller\Dashboard\RoleManagementController::adminList')
                ->requireRole('admin')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.users.roles',
            RouteBuilder::create('/api/users/{uid}/roles')
                ->controller('App\Http\Controller\Dashboard\RoleManagementController::changeRole')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Admin ---
        // =====================================================================

        // Newsletter admin API + builder SPA removed in the 2026-06 slimming.

        // AdminSurface generic CRUD (static call - _surface API routes only)
        // Package `AdminSurfaceServiceProvider` also registers these from the manifest;
        // strip its routes so Minoo can register the same names with a custom host.

        if ($entityTypeManager !== null) {
            self::removePackageAdminSurfaceRoutes($router);

            // alpha.180 tightened SchemaPresenter::present() and ResourceSerializer::serialize()
            // to require both $accessHandler and $account, or neither (PARTIAL_ACCESS_CONTEXT
            // guard). The host populates $currentAccount via resolveSession() at request time,
            // so we must also pass a real EntityAccessHandler at construction. The framework's
            // kernel does not expose its handler to providers via KernelServices, so replay
            // the same discovery here (idempotent - same policies as the kernel uses).
            // alpha.189+ ships policies with service dependencies (e.g.
            // ClassificationFieldAccessPolicy), so the replay needs the
            // kernel-services-backed resolver, not the null default.
            $logger = $this->resolve(LoggerInterface::class);
            $manifest = (new ManifestBootstrapper())->boot(dirname(__DIR__, 3));
            $resolver = $this->kernelServices !== null
                ? new KernelPolicyDependencyResolver($this->kernelServices)
                : null;
            $accessHandler = $resolver !== null
                ? (new AccessPolicyRegistry($logger, $resolver))->discover($manifest)
                : (new AccessPolicyRegistry($logger))->discover($manifest);

            $host = new GenericAdminSurfaceHost(
                entityTypeManager: $entityTypeManager,
                accessHandler: $accessHandler,
                schemaPresenter: new SchemaPresenter(),
                tenantId: 'minoo',
                tenantName: 'Minoo',
                readOnlyTypes: ['ingest_log'],
            );

            AdminSurfaceServiceProvider::registerRoutes($router, $host);
        }

        // Re-add admin_spa catch-all after app-specific admin routes so they
        // match first (the newsletter admin routes it once deferred to are gone).
        // The framework's AdminSurfaceServiceProvider registers admin_spa in its
        // own routes() which runs earlier; remove then add so WaaseyaaRouter rejects
        // duplicate route names.
        $router->removeRoute('admin_spa');
        $projectRoot = dirname(__DIR__, 3);
        $vendorDistDir = $projectRoot . '/vendor/waaseyaa/admin-surface/dist';
        $vendorDistContent = is_file($vendorDistDir . '/index.html')
            ? file_get_contents($vendorDistDir . '/index.html')
            : null;

        $router->addRoute('admin_spa', RouteBuilder::create('/admin/{path}')
            ->methods('GET')
            ->allowAll()
            ->controller(static function (mixed $request = null, string $path = '') use ($projectRoot, $vendorDistDir, $vendorDistContent): Response {
                if ($path !== '' && !str_contains($path, '..')) {
                    $publicAsset = $projectRoot . '/public/admin/' . $path;
                    if (is_file($publicAsset)) {
                        return AdminSurfaceServiceProvider::serveStaticFile($publicAsset);
                    }
                    $vendorAsset = $vendorDistDir . '/' . $path;
                    if (is_file($vendorAsset)) {
                        return AdminSurfaceServiceProvider::serveStaticFile($vendorAsset);
                    }
                }
                $html = AdminSurfaceServiceProvider::resolveAdminIndex($projectRoot, $vendorDistContent);
                if ($html !== null) {
                    return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
                }
                return new Response('Admin interface not available.', 404);
            })
            ->requirement('path', '(?!_surface(/|$)).*')
            ->default('path', '')
            ->build());
    }

    private static function removePackageAdminSurfaceRoutes(WaaseyaaRouter $router): void
    {
        foreach (array_keys($router->getRouteCollection()->all()) as $name) {
            if (str_starts_with($name, 'admin_surface.') || $name === 'admin_spa') {
                $router->removeRoute($name);
            }
        }
    }
}
