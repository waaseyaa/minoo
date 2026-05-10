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
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AdminRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Dashboard ---
        // =====================================================================

        $router->addRoute(
            'dashboard.volunteer',
            RouteBuilder::create('/dashboard/volunteer')
                ->controller('App\Controller\VolunteerDashboardController::index')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.edit',
            RouteBuilder::create('/dashboard/volunteer/edit')
                ->controller('App\Controller\VolunteerDashboardController::editForm')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.edit.submit',
            RouteBuilder::create('/dashboard/volunteer/edit')
                ->controller('App\Controller\VolunteerDashboardController::submitEdit')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.toggle',
            RouteBuilder::create('/dashboard/volunteer/toggle-availability')
                ->controller('App\Controller\VolunteerDashboardController::toggleAvailability')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator',
            RouteBuilder::create('/dashboard/coordinator')
                ->controller('App\Controller\CoordinatorDashboardController::index')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator.applications',
            RouteBuilder::create('/dashboard/coordinator/applications')
                ->controller('App\Controller\CoordinatorDashboardController::applications')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator.applications.approve',
            RouteBuilder::create('/dashboard/coordinator/applications/{uuid}/approve')
                ->controller('App\Controller\CoordinatorDashboardController::approveApplication')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator.applications.deny',
            RouteBuilder::create('/dashboard/coordinator/applications/{uuid}/deny')
                ->controller('App\Controller\CoordinatorDashboardController::denyApplication')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Staff tools (SSR) — under /staff and /api/staff (not /admin/*) ---
        // --- so they never collide with the admin-surface /admin/{path} SPA. ---
        // =====================================================================

        $router->addRoute(
            'staff.ingestion',
            RouteBuilder::create('/staff/ingestion')
                ->controller('App\Controller\IngestionDashboardController::index')
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
                ->controller('App\Controller\IngestionApiController::status')
                ->requireRole('admin,elder_coordinator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.envelope',
            RouteBuilder::create('/api/staff/ingestion/envelope')
                ->controller('App\Controller\IngestionApiController::ingestEnvelope')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.approve',
            RouteBuilder::create('/api/staff/ingestion/{id}/approve')
                ->controller('App\Controller\IngestionApiController::approve')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.reject',
            RouteBuilder::create('/api/staff/ingestion/{id}/reject')
                ->controller('App\Controller\IngestionApiController::reject')
                ->requireRole('admin,elder_coordinator')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'staff.ingestion.materialize',
            RouteBuilder::create('/api/staff/ingestion/{id}/materialize')
                ->controller('App\Controller\IngestionApiController::materialize')
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
            'dashboard.coordinator.users',
            RouteBuilder::create('/dashboard/coordinator/users')
                ->controller('App\Controller\RoleManagementController::coordinatorList')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'staff.users',
            RouteBuilder::create('/staff/users')
                ->controller('App\Controller\RoleManagementController::adminList')
                ->requireRole('admin')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.users.roles',
            RouteBuilder::create('/api/users/{uid}/roles')
                ->controller('App\Controller\RoleManagementController::changeRole')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Admin ---
        // =====================================================================

        // Newsletter Admin API — registered BEFORE AdminSurface catch-all

        $router->addRoute(
            'newsletter.admin.list',
            RouteBuilder::create('/admin/api/newsletter')
                ->controller('App\\Controller\\NewsletterAdminApiController::listEditions')
                ->requireRole('administrator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.create',
            RouteBuilder::create('/admin/api/newsletter')
                ->controller('App\\Controller\\NewsletterAdminApiController::createEdition')
                ->requireRole('administrator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.entity_search',
            RouteBuilder::create('/admin/api/newsletter/entity-search')
                ->controller('App\\Controller\\NewsletterAdminApiController::entitySearch')
                ->requireRole('administrator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.get',
            RouteBuilder::create('/admin/api/newsletter/{id}')
                ->controller('App\\Controller\\NewsletterAdminApiController::getEdition')
                ->requireRole('administrator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.add_item',
            RouteBuilder::create('/admin/api/newsletter/{id}/items')
                ->controller('App\\Controller\\NewsletterAdminApiController::addItem')
                ->requireRole('administrator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.remove_item',
            RouteBuilder::create('/admin/api/newsletter/{id}/items/{itemId}')
                ->controller('App\\Controller\\NewsletterAdminApiController::removeItem')
                ->requireRole('administrator')
                ->methods('DELETE')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.reorder_item',
            RouteBuilder::create('/admin/api/newsletter/{id}/items/{itemId}/reorder')
                ->controller('App\\Controller\\NewsletterAdminApiController::reorderItem')
                ->requireRole('administrator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.preview_token',
            RouteBuilder::create('/admin/api/newsletter/{id}/preview-token')
                ->controller('App\\Controller\\NewsletterAdminApiController::previewToken')
                ->requireRole('administrator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.generate',
            RouteBuilder::create('/admin/api/newsletter/{id}/generate')
                ->controller('App\\Controller\\NewsletterAdminApiController::generate')
                ->requireRole('administrator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.download',
            RouteBuilder::create('/admin/api/newsletter/{id}/download')
                ->controller('App\\Controller\\NewsletterAdminApiController::download')
                ->requireRole('administrator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.send',
            RouteBuilder::create('/admin/api/newsletter/{id}/send')
                ->controller('App\\Controller\\NewsletterAdminApiController::send')
                ->requireRole('administrator')
                ->methods('POST')
                ->build(),
        );

        // Newsletter Builder SPA — before AdminSurface catch-all

        $router->addRoute(
            'newsletter.admin.spa',
            RouteBuilder::create('/admin/newsletter')
                ->controller('App\\Controller\\NewsletterAdminApiController::spaFallback')
                ->requireRole('administrator')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.admin.spa.catchall',
            RouteBuilder::create('/admin/newsletter/{path}')
                ->controller('App\\Controller\\NewsletterAdminApiController::spaFallback')
                ->requireRole('administrator')
                ->methods('GET')
                ->requirement('path', '.*')
                ->build(),
        );

        // AdminSurface generic CRUD (static call — _surface API routes only)
        // Package `AdminSurfaceServiceProvider` also registers these from the manifest;
        // strip its routes so Minoo can register the same names with a custom host.

        if ($entityTypeManager !== null) {
            self::removePackageAdminSurfaceRoutes($router);

            $host = new GenericAdminSurfaceHost(
                entityTypeManager: $entityTypeManager,
                schemaPresenter: new SchemaPresenter(),
                tenantId: 'minoo',
                tenantName: 'Minoo',
                readOnlyTypes: ['ingest_log'],
            );

            AdminSurfaceServiceProvider::registerRoutes($router, $host);
        }

        // Re-add admin_spa catch-all AFTER newsletter routes so specific
        // /admin/api/newsletter/* and /admin/newsletter/* routes match first.
        // The framework's AdminSurfaceServiceProvider registers admin_spa in its
        // own routes() which runs earlier; remove then add so WaaseyaaRouter rejects
        // duplicate route names.
        $router->removeRoute('admin_spa');
        $projectRoot = dirname(__DIR__, 2);
        $vendorDistDir = dirname(__DIR__, 2) . '/vendor/waaseyaa/admin-surface/dist';
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
