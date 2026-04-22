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

final class PublicContentRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
                            // =====================================================================
                            // --- Events ---
                            // =====================================================================

                            $router->addRoute(
                                'events.list',
                                RouteBuilder::create('/events')
                                    ->controller('App\\Controller\\EventController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'events.ics',
                                RouteBuilder::create('/events/{slug}.ics')
                                    ->controller('App\\Controller\\EventController::ics')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'events.show',
                                RouteBuilder::create('/events/{slug}')
                                    ->controller('App\\Controller\\EventController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'og.event.png',
                                RouteBuilder::create('/og/event/{slug}.png')
                                    ->controller('App\\Controller\\OpenGraphController::eventPng')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Groups ---
                            // =====================================================================

                            $router->addRoute(
                                'groups.list',
                                RouteBuilder::create('/groups')
                                    ->controller('App\\Controller\\GroupController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'groups.show',
                                RouteBuilder::create('/groups/{slug}')
                                    ->controller('App\\Controller\\GroupController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'businesses.list',
                                RouteBuilder::create('/businesses')
                                    ->controller('App\\Controller\\BusinessController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'businesses.show',
                                RouteBuilder::create('/businesses/{slug}')
                                    ->controller('App\\Controller\\BusinessController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'og.business.png',
                                RouteBuilder::create('/og/business/{slug}.png')
                                    ->controller('App\\Controller\\OpenGraphController::businessPng')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Teachings ---
                            // =====================================================================

                            $router->addRoute(
                                'teachings.list',
                                RouteBuilder::create('/teachings')
                                    ->controller('App\\Controller\\TeachingController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'teachings.show',
                                RouteBuilder::create('/teachings/{slug}')
                                    ->controller('App\\Controller\\TeachingController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'og.teaching.png',
                                RouteBuilder::create('/og/teaching/{slug}.png')
                                    ->controller('App\\Controller\\OpenGraphController::teachingPng')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            $router->addRoute(
                                'og.crisis.sagamok_spanish_river_flood.png',
                                RouteBuilder::create('/og/crisis/sagamok-spanish-river-flood.png')
                                    ->controller('App\\Controller\\OpenGraphController::sagamokSpanishRiverFloodPng')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'og.crisis.incident.png',
                                RouteBuilder::create('/og/crisis/{community_slug}/{incident_slug}.png')
                                    ->controller('App\\Controller\\OpenGraphController::crisisIncidentPng')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->requirement('community_slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->requirement('incident_slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );

                            // =====================================================================
                            // --- Language ---
                            // =====================================================================

                            $router->addRoute(
                                'language.list',
                                RouteBuilder::create('/language')
                                    ->controller('App\\Controller\\LanguageController::list')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'language.search',
                                RouteBuilder::create('/language/search')
                                    ->controller('App\\Controller\\LanguageController::search')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'language.show',
                                RouteBuilder::create('/language/{slug}')
                                    ->controller('App\\Controller\\LanguageController::show')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                                    ->build(),
                            );
    }
}
