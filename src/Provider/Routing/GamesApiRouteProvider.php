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

final class GamesApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
                            // =====================================================================
                            // --- Games ---
                            // =====================================================================

                            // Legacy redirect: /games/ishkode -> /games/shkoda (#535)
                            $router->addRoute(
                                'games.ishkode.redirect',
                                RouteBuilder::create('/games/ishkode')
                                    ->controller('App\\Controller\\ShkodaController::redirectLegacy')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // Game page
                            $router->addRoute(
                                'games.shkoda',
                                RouteBuilder::create('/games/shkoda')
                                    ->controller('App\\Controller\\ShkodaController::page')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // API: get daily challenge
                            $router->addRoute(
                                'api.games.shkoda.daily',
                                RouteBuilder::create('/api/games/shkoda/daily')
                                    ->controller('App\\Controller\\ShkodaController::daily')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // API: get random word for practice/streak
                            $router->addRoute(
                                'api.games.shkoda.word',
                                RouteBuilder::create('/api/games/shkoda/word')
                                    ->controller('App\\Controller\\ShkodaController::word')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // API: submit guess (daily challenge only)
                            $router->addRoute(
                                'api.games.shkoda.guess',
                                RouteBuilder::create('/api/games/shkoda/guess')
                                    ->controller('App\\Controller\\ShkodaController::guess')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            // API: complete game
                            $router->addRoute(
                                'api.games.shkoda.complete',
                                RouteBuilder::create('/api/games/shkoda/complete')
                                    ->controller('App\\Controller\\ShkodaController::complete')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            // API: player stats (auth required)
                            $router->addRoute(
                                'api.games.shkoda.stats',
                                RouteBuilder::create('/api/games/shkoda/stats')
                                    ->controller('App\\Controller\\ShkodaController::stats')
                                    ->requireAuthentication()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // --- Crossword routes ---

                            $router->addRoute(
                                'games.crossword',
                                RouteBuilder::create('/games/crossword')
                                    ->controller('App\\Controller\\CrosswordController::page')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.daily',
                                RouteBuilder::create('/api/games/crossword/daily')
                                    ->controller('App\\Controller\\CrosswordController::daily')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.random',
                                RouteBuilder::create('/api/games/crossword/random')
                                    ->controller('App\\Controller\\CrosswordController::random')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.themes',
                                RouteBuilder::create('/api/games/crossword/themes')
                                    ->controller('App\\Controller\\CrosswordController::themes')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.theme',
                                RouteBuilder::create('/api/games/crossword/theme/{slug}')
                                    ->controller('App\\Controller\\CrosswordController::theme')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.check',
                                RouteBuilder::create('/api/games/crossword/check')
                                    ->controller('App\\Controller\\CrosswordController::check')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.complete',
                                RouteBuilder::create('/api/games/crossword/complete')
                                    ->controller('App\\Controller\\CrosswordController::complete')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.hint',
                                RouteBuilder::create('/api/games/crossword/hint')
                                    ->controller('App\\Controller\\CrosswordController::hint')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.abandon',
                                RouteBuilder::create('/api/games/crossword/abandon')
                                    ->controller('App\\Controller\\CrosswordController::abandon')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.crossword.stats',
                                RouteBuilder::create('/api/games/crossword/stats')
                                    ->controller('App\\Controller\\CrosswordController::stats')
                                    ->requireAuthentication()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // --- Matcher routes ---

                            $router->addRoute(
                                'games.matcher',
                                RouteBuilder::create('/games/matcher')
                                    ->controller('App\\Controller\\MatcherController::page')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.matcher.daily',
                                RouteBuilder::create('/api/games/matcher/daily')
                                    ->controller('App\\Controller\\MatcherController::daily')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.matcher.practice',
                                RouteBuilder::create('/api/games/matcher/practice')
                                    ->controller('App\\Controller\\MatcherController::practice')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.matcher.match',
                                RouteBuilder::create('/api/games/matcher/match')
                                    ->controller('App\\Controller\\MatcherController::match')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.matcher.complete',
                                RouteBuilder::create('/api/games/matcher/complete')
                                    ->controller('App\\Controller\\MatcherController::complete')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.matcher.stats',
                                RouteBuilder::create('/api/games/matcher/stats')
                                    ->controller('App\\Controller\\MatcherController::stats')
                                    ->requireAuthentication()
                                    ->methods('GET')
                                    ->build(),
                            );

                            // --- Agim routes ---

                            $router->addRoute(
                                'games.agim',
                                RouteBuilder::create('/games/agim')
                                    ->controller('App\\Controller\\AgimController::page')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.agim.start',
                                RouteBuilder::create('/api/games/agim/start')
                                    ->controller('App\\Controller\\AgimController::start')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.agim.prompt',
                                RouteBuilder::create('/api/games/agim/prompt')
                                    ->controller('App\\Controller\\AgimController::prompt')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.agim.answer',
                                RouteBuilder::create('/api/games/agim/answer')
                                    ->controller('App\\Controller\\AgimController::answer')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.agim.complete',
                                RouteBuilder::create('/api/games/agim/complete')
                                    ->controller('App\\Controller\\AgimController::complete')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.agim.stats',
                                RouteBuilder::create('/api/games/agim/stats')
                                    ->controller('App\\Controller\\AgimController::stats')
                                    ->requireAuthentication()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'games.guess_price',
                                RouteBuilder::create('/games/guess-price')
                                    ->controller('App\\Controller\\GuessPriceController::page')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'games.guess_price.trailing_redirect',
                                RouteBuilder::create('/games/guess-price/')
                                    ->controller(static fn (): Response => new RedirectResponse('/games/guess-price', Response::HTTP_PERMANENTLY_REDIRECT))
                                    ->allowAll()
                                    ->methods('GET', 'HEAD')
                                    ->build(),
                            );

                            // --- Journey routes ---

                            $router->addRoute(
                                'games.journey',
                                RouteBuilder::create('/games/journey')
                                    ->controller('App\\Controller\\JourneyController::page')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.journey.scenes',
                                RouteBuilder::create('/api/games/journey/scenes')
                                    ->controller('App\\Controller\\JourneyController::scenes')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.journey.scene',
                                RouteBuilder::create('/api/games/journey/scene/{slug}')
                                    ->controller('App\\Controller\\JourneyController::scene')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.journey.tap',
                                RouteBuilder::create('/api/games/journey/tap')
                                    ->controller('App\\Controller\\JourneyController::tap')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.journey.hint',
                                RouteBuilder::create('/api/games/journey/hint')
                                    ->controller('App\\Controller\\JourneyController::hint')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.journey.complete',
                                RouteBuilder::create('/api/games/journey/complete')
                                    ->controller('App\\Controller\\JourneyController::complete')
                                    ->allowAll()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'api.games.journey.stats',
                                RouteBuilder::create('/api/games/journey/stats')
                                    ->controller('App\\Controller\\JourneyController::stats')
                                    ->requireAuthentication()
                                    ->methods('GET')
                                    ->build(),
                            );
    }
}
