<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterAssembler;
use App\Domain\Newsletter\Service\NewsletterDispatcher;
use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use App\Domain\Newsletter\ValueObject\SectionQuota;
use App\Entity\Community;
use App\Entity\Contributor;
use App\Entity\CrosswordPuzzle;
use App\Entity\CulturalCollection;
use App\Entity\CulturalGroup;
use App\Entity\DailyChallenge;
use App\Entity\DialectRegion;
use App\Entity\DictionaryEntry;
use App\Entity\ElderSupportRequest;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\ExampleSentence;
use App\Entity\FeaturedItem;
use App\Entity\GameSession;
use App\Entity\IngestLog;
use App\Entity\Leader;
use App\Entity\NewsletterEdition;
use App\Entity\NewsletterItem;
use App\Entity\NewsletterSubmission;
use App\Entity\OralHistory;
use App\Entity\OralHistoryCollection;
use App\Entity\OralHistoryType;
use App\Entity\Post;
use App\Entity\ResourcePerson;
use App\Entity\Speaker;
use App\Entity\Teaching;
use App\Entity\TeachingType;
use App\Entity\Volunteer;
use App\Entity\WordPart;
use App\Feed\EntityLoaderService;
use App\Feed\FeedAssembler;
use App\Feed\FeedAssemblerInterface;
use App\Feed\FeedItemFactory;
use App\Feed\Scoring\AffinityCache;
use App\Feed\Scoring\AffinityCalculator;
use App\Feed\Scoring\DecayCalculator;
use App\Feed\Scoring\DiversityReranker;
use App\Feed\Scoring\EngagementCalculator;
use App\Feed\Scoring\FeedScorer;
use App\Ingestion\EntityMapper\NcArticleToEventMapper;
use App\Ingestion\EntityMapper\NcArticleToTeachingMapper;
use App\Support\Command\GenealogyDemoSeedCommand;
use App\Support\Command\MailTestCommand;
use App\Support\Command\MessagingDigestCommand;
use App\Support\MessageDigestCommand;
use App\Support\NewsletterMailer;
use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use App\Support\NorthCloudCommunityDictionaryClient;
use App\Twig\AccountDisplayTwigExtension;
use App\Twig\DateTwigExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\I18n\Language;
use Waaseyaa\I18n\LanguageManager;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\Translator;
use Waaseyaa\I18n\TranslatorInterface;
use Waaseyaa\I18n\Twig\TranslationTwigExtension;
use Waaseyaa\Mail\MailerInterface;
use Waaseyaa\Media\UploadHandler;
use Waaseyaa\NorthCloud\Client\NorthCloudClient as PackageNorthCloudClient;
use Waaseyaa\NorthCloud\Command\NcSyncCommand;
use Waaseyaa\NorthCloud\Search\NorthCloudSearchProvider;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Waaseyaa\NorthCloud\Sync\NcSyncService;
use Waaseyaa\Routing\Language\UrlPrefixNegotiator;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\Twig\SearchTwigExtension;
use Waaseyaa\Mcp\Auth\BearerTokenAuth;
use Waaseyaa\Mcp\Auth\McpAuthInterface;
use Waaseyaa\Mcp\Bridge\ToolExecutorInterface;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;
use Waaseyaa\SSR\SsrServiceProvider;
use Waaseyaa\SSR\ThemeServiceProvider;

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

                    if ($entityTypeManager !== null) {
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
                    // own routes() which runs before AppServiceProvider. Re-adding with the
                    // same name moves it to the end of Symfony's RouteCollection.
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
}
