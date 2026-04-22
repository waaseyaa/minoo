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

final class PublicCommunityRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
                            // =====================================================================
                            // --- People ---
                            // =====================================================================

                            $router->addRoute(
                                'people.list',
                                RouteBuilder::create('/people')
                                    ->controller('App\Controller\PeopleController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'people.show',
                                RouteBuilder::create('/people/{slug}')
                                    ->controller('App\Controller\PeopleController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Elder Support ---
                            // =====================================================================

                            $router->addRoute(
                                'elders.request.form',
                                RouteBuilder::create('/elders/request')
                                    ->controller('App\Controller\ElderSupportController::requestForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elders.request.submit',
                                RouteBuilder::create('/elders/request')
                                    ->controller('App\Controller\ElderSupportController::submitRequest')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elders.request.detail',
                                RouteBuilder::create('/elders/request/{uuid}')
                                    ->controller('App\Controller\ElderSupportController::requestDetail')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elders.volunteer.form',
                                RouteBuilder::create('/elders/volunteer')
                                    ->controller('App\Controller\VolunteerController::signupForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elders.volunteer.submit',
                                RouteBuilder::create('/elders/volunteer')
                                    ->controller('App\Controller\VolunteerController::submitSignup')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elders.volunteer.detail',
                                RouteBuilder::create('/elders/volunteer/{uuid}')
                                    ->controller('App\Controller\VolunteerController::signupDetail')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.assign',
                                RouteBuilder::create('/elders/request/{esrid}/assign')
                                    ->controller('App\Controller\ElderSupportWorkflowController::assignVolunteer')
                                    ->requireRole('elder_coordinator')
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.start',
                                RouteBuilder::create('/elders/request/{esrid}/start')
                                    ->controller('App\Controller\ElderSupportWorkflowController::startRequest')
                                    ->requireRole('volunteer')
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.complete',
                                RouteBuilder::create('/elders/request/{esrid}/complete')
                                    ->controller('App\Controller\ElderSupportWorkflowController::completeRequest')
                                    ->requireRole('volunteer')
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.confirm',
                                RouteBuilder::create('/elders/request/{esrid}/confirm')
                                    ->controller('App\Controller\ElderSupportWorkflowController::confirmRequest')
                                    ->requireRole('elder_coordinator')
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.decline',
                                RouteBuilder::create('/elders/request/{esrid}/decline')
                                    ->controller('App\Controller\ElderSupportWorkflowController::declineRequest')
                                    ->requireRole('volunteer')
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.reassign',
                                RouteBuilder::create('/elders/request/{esrid}/reassign')
                                    ->controller('App\Controller\ElderSupportWorkflowController::reassignVolunteer')
                                    ->requireRole('elder_coordinator')
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'elder.cancel',
                                RouteBuilder::create('/elders/request/{esrid}/cancel')
                                    ->controller('App\Controller\ElderSupportWorkflowController::cancelRequest')
                                    ->requireRole('elder_coordinator')
                                    ->methods('POST')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Community ---
                            // =====================================================================

                            $router->addRoute(
                                'communities.list',
                                RouteBuilder::create('/communities')
                                    ->controller('App\\Controller\\CommunityController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'communities.crisis_incident',
                                RouteBuilder::create('/communities/{slug}/{incident}')
                                    ->controller('App\\Controller\\CommunityController::crisisIncident')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->requirement('incident', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'communities.show',
                                RouteBuilder::create('/communities/{slug}')
                                    ->controller('App\\Controller\\CommunityController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'communities.autocomplete',
                                RouteBuilder::create('/api/communities/autocomplete')
                                    ->controller('App\\Controller\\CommunityController::autocomplete')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'location.current',
                                RouteBuilder::create('/api/location/current')
                                    ->controller('App\\Controller\\LocationController::current')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'location.set',
                                RouteBuilder::create('/api/location/set')
                                    ->controller('App\\Controller\\LocationController::set')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'location.update',
                                RouteBuilder::create('/api/location/update')
                                    ->controller('App\\Controller\\LocationController::update')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Oral History ---
                            // =====================================================================

                            $router->addRoute(
                                'oral_histories.list',
                                RouteBuilder::create('/oral-histories')
                                    ->controller('App\\Controller\\OralHistoryController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'oral_histories.collection',
                                RouteBuilder::create('/oral-histories/collections/{slug}')
                                    ->controller('App\\Controller\\OralHistoryController::collection')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'oral_histories.show',
                                RouteBuilder::create('/oral-histories/{slug}')
                                    ->controller('App\\Controller\\OralHistoryController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Contributors ---
                            // =====================================================================

                            $router->addRoute(
                                'contributors.list',
                                RouteBuilder::create('/contributors')
                                    ->controller('App\\Controller\\ContributorController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'contributors.show',
                                RouteBuilder::create('/contributors/{slug}')
                                    ->controller('App\\Controller\\ContributorController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );
    }
}
