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

final class NewsletterApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
                            // =====================================================================
                            // --- Newsletter ---
                            // =====================================================================

                            $router->addRoute(
                                'newsletter.editor.list',
                                RouteBuilder::create('/coordinator/newsletter')
                                    ->controller('App\Controller\NewsletterEditorController::list')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.new',
                                RouteBuilder::create('/coordinator/newsletter/new')
                                    ->controller('App\Controller\NewsletterEditorController::create')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            // Submission moderation routes are registered BEFORE the generic
                            // /coordinator/newsletter/{id} route so "/submissions" is not
                            // accidentally captured as an edition id.
                            $router->addRoute(
                                'newsletter.editor.submissions',
                                RouteBuilder::create('/coordinator/newsletter/submissions')
                                    ->controller('App\Controller\NewsletterEditorController::submissionsList')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.submission_approve',
                                RouteBuilder::create('/coordinator/newsletter/submissions/{id}/approve')
                                    ->controller('App\Controller\NewsletterEditorController::submissionApprove')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.submission_reject',
                                RouteBuilder::create('/coordinator/newsletter/submissions/{id}/reject')
                                    ->controller('App\Controller\NewsletterEditorController::submissionReject')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.assemble',
                                RouteBuilder::create('/coordinator/newsletter/{id}/assemble')
                                    ->controller('App\Controller\NewsletterEditorController::assemble')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.show',
                                RouteBuilder::create('/coordinator/newsletter/{id}')
                                    ->controller('App\Controller\NewsletterEditorController::show')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.approve',
                                RouteBuilder::create('/coordinator/newsletter/{id}/approve')
                                    ->controller('App\Controller\NewsletterEditorController::approve')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.generate',
                                RouteBuilder::create('/coordinator/newsletter/{id}/generate')
                                    ->controller('App\Controller\NewsletterEditorController::generate')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.editor.send',
                                RouteBuilder::create('/coordinator/newsletter/{id}/send')
                                    ->controller('App\Controller\NewsletterEditorController::send')
                                    ->requireRole('community_coordinator')
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            // Public newsletter surface. Order matters: more specific routes
                            // (print_preview, /newsletter, /newsletter/submit, .pdf, vol-issue)
                            // are registered BEFORE the catch-all /newsletter/{community}.
                            $router->addRoute(
                                'newsletter.print_preview',
                                RouteBuilder::create('/newsletter/_internal/{id}/print')
                                    ->controller('App\Controller\NewsletterController::printPreview')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.public.index',
                                RouteBuilder::create('/newsletter')
                                    ->controller('App\Controller\NewsletterController::index')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.public.submit_form',
                                RouteBuilder::create('/newsletter/submit')
                                    ->controller('App\Controller\NewsletterController::submitForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.public.submit_post',
                                RouteBuilder::create('/newsletter/submit')
                                    ->controller('App\Controller\NewsletterController::submitPost')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.public.pdf',
                                RouteBuilder::create('/newsletter/{community}/{volume}-{issue}.pdf')
                                    ->controller('App\Controller\NewsletterController::downloadPdf')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.public.edition',
                                RouteBuilder::create('/newsletter/{community}/{volume}-{issue}')
                                    ->controller('App\Controller\NewsletterController::showEdition')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'newsletter.public.community',
                                RouteBuilder::create('/newsletter/{community}')
                                    ->controller('App\Controller\NewsletterController::showCommunity')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // =====================================================================
                            // Route lockdown (Phase 1): explicit routes for pages that previously
                            // depended on `RenderController::tryRenderPathTemplate()` fallback.
                            // Preserves URLs and behavior exactly; no new render logic.
                            // =====================================================================

                            $router->addRoute(
                                'static.about',
                                RouteBuilder::create('/about')
                                    ->controller('App\Controller\StaticPageController::about')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'games.agim.short',
                                RouteBuilder::create('/agim')
                                    ->controller('App\Controller\AgimController::page')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'games.guess_price.short',
                                RouteBuilder::create('/guess-price')
                                    ->controller('App\Controller\GuessPriceController::page')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'games.guess_price.short.trailing_redirect',
                                RouteBuilder::create('/guess-price/')
                                    ->controller(static fn (): Response => new RedirectResponse('/guess-price', Response::HTTP_PERMANENTLY_REDIRECT))
                                    ->allowAll()
                                    ->methods('GET', 'HEAD')
                                    ->build(),
                            );

                            $router->addRoute(
                                'games.crossword.short',
                                RouteBuilder::create('/crossword')
                                    ->controller('App\Controller\CrosswordController::page')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.data_sovereignty',
                                RouteBuilder::create('/data-sovereignty')
                                    ->controller('App\Controller\StaticPageController::dataSovereignty')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.elders',
                                RouteBuilder::create('/elders')
                                    ->controller('App\Controller\StaticPageController::elders')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.games',
                                RouteBuilder::create('/games')
                                    ->controller('App\Controller\StaticPageController::games')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.games.trailing_redirect',
                                RouteBuilder::create('/games/')
                                    ->controller(static fn (): Response => new RedirectResponse('/games', Response::HTTP_PERMANENTLY_REDIRECT))
                                    ->allowAll()
                                    ->methods('GET', 'HEAD')
                                    ->build(),
                            );

                            $router->addRoute(
                                'static.get_involved',
                                RouteBuilder::create('/get-involved')
                                    ->controller('App\Controller\StaticPageController::getInvolved')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.how_it_works',
                                RouteBuilder::create('/how-it-works')
                                    ->controller('App\Controller\StaticPageController::howItWorks')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.journey',
                                RouteBuilder::create('/journey')
                                    ->controller('App\Controller\StaticPageController::journey')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.legal',
                                RouteBuilder::create('/legal')
                                    ->controller('App\Controller\StaticPageController::legal')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.legal.section',
                                RouteBuilder::create('/legal/{section}')
                                    ->controller('App\Controller\StaticPageController::legal')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.matcher',
                                RouteBuilder::create('/matcher')
                                    ->controller('App\Controller\StaticPageController::matcher')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.messages',
                                RouteBuilder::create('/messages')
                                    ->controller('App\Controller\StaticPageController::messages')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.safety',
                                RouteBuilder::create('/safety')
                                    ->controller('App\Controller\StaticPageController::safety')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.search',
                                RouteBuilder::create('/search')
                                    ->controller('App\Controller\StaticPageController::search')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'games.shkoda.short',
                                RouteBuilder::create('/shkoda')
                                    ->controller('App\Controller\ShkodaController::page')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.studio',
                                RouteBuilder::create('/studio')
                                    ->controller('App\Controller\StaticPageController::studio')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );

                            $router->addRoute(
                                'static.volunteer',
                                RouteBuilder::create('/volunteer')
                                    ->controller('App\Controller\StaticPageController::volunteer')
                                    ->allowAll()->render()->methods('GET')->build(),
                            );
                        
            
    }
}
