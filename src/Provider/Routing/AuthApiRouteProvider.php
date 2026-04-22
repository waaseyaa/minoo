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

final class AuthApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
                            // =====================================================================
                            // --- Auth ---
                            // =====================================================================

                            $router->addRoute(
                                'auth.login_form',
                                RouteBuilder::create('/login')
                                    ->controller('App\Controller\AuthController::loginForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.login_submit',
                                RouteBuilder::create('/login')
                                    ->controller('App\Controller\AuthController::submitLogin')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.register_form',
                                RouteBuilder::create('/register')
                                    ->controller('App\Controller\AuthController::registerForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.register_submit',
                                RouteBuilder::create('/register')
                                    ->controller('App\Controller\AuthController::submitRegister')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.logout',
                                RouteBuilder::create('/logout')
                                    ->controller('App\Controller\AuthController::logout')
                                    ->allowAll()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.forgot_password_form',
                                RouteBuilder::create('/forgot-password')
                                    ->controller('App\Controller\AuthController::forgotPasswordForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.forgot_password_submit',
                                RouteBuilder::create('/forgot-password')
                                    ->controller('App\Controller\AuthController::submitForgotPassword')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.reset_password_form',
                                RouteBuilder::create('/reset-password')
                                    ->controller('App\Controller\AuthController::resetPasswordForm')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.reset_password_submit',
                                RouteBuilder::create('/reset-password')
                                    ->controller('App\Controller\AuthController::submitResetPassword')
                                    ->allowAll()
                                    ->render()
                                    ->methods('POST')
                                    ->build(),
                            );

                            $router->addRoute(
                                'auth.verify_email',
                                RouteBuilder::create('/verify-email')
                                    ->controller('App\Controller\AuthController::verifyEmail')
                                    ->allowAll()
                                    ->render()
                                    ->methods('GET')
                                    ->build(),
                            );
    }
}
