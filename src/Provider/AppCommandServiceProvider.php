<?php

declare(strict_types=1);

namespace App\Provider;

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
use App\Support\Cli\CrisisOgAssetsHandler;
use App\Support\Cli\GenealogyDemoSeedHandler;
use App\Support\Cli\MailTestHandler;
use App\Support\Cli\MessagingDigestHandler;
use App\Support\MessageDigestCommand;
use App\Support\NewsletterMailer;
use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use App\Support\NorthCloudCommunityDictionaryClient;
use App\Twig\AccountDisplayTwigExtension;
use App\Twig\DateTwigExtension;
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
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
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

class AppCommandServiceProvider extends AppCoreServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        parent::register();

        $this->singleton(MailTestHandler::class, function (): MailTestHandler {
            [$configured, $fromAddress] = $this->mailConfigSnapshot();
            return new MailTestHandler(
                $this->resolve(MailerInterface::class),
                $configured,
                $fromAddress,
            );
        });

        $this->singleton(MessageDigestCommand::class, function (): MessageDigestCommand {
            [$configured, $fromAddress] = $this->mailConfigSnapshot();
            $messagingConfig = is_array($this->config['messaging'] ?? null)
                ? $this->config['messaging']
                : [];
            return new MessageDigestCommand(
                $this->resolve(EntityTypeManager::class),
                $this->resolve(MailerInterface::class),
                $configured,
                $messagingConfig,
                $fromAddress,
            );
        });

        $this->singleton(MessagingDigestHandler::class, function (): MessagingDigestHandler {
            return new MessagingDigestHandler($this->resolve(MessageDigestCommand::class));
        });

        $this->singleton(GenealogyDemoSeedHandler::class, function (): GenealogyDemoSeedHandler {
            return new GenealogyDemoSeedHandler($this->resolve(EntityTypeManager::class));
        });

        $this->singleton(CrisisOgAssetsHandler::class, function (): CrisisOgAssetsHandler {
            return new CrisisOgAssetsHandler(
                $this->resolve(\App\Support\CrisisIncidentResolver::class),
                $this->resolve(\App\Support\CrisisOgImageService::class),
            );
        });
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'mail:test',
            description: 'Send a test email to verify SendGrid configuration',
            arguments: [
                new ArgumentDefinition(
                    name: 'email',
                    mode: ArgumentMode::Required,
                    description: 'The address to send the test email to',
                ),
            ],
            handler: [MailTestHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'messaging:digest',
            description: 'Send email digests for unread thread messages (cron)',
            handler: [MessagingDigestHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'genealogy:demo-seed',
            description: 'Create demo genealogy tree, persons, family, and edges for local SSR testing',
            handler: [GenealogyDemoSeedHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'crisis:og-assets',
            description: 'Build or regenerate crisis Open Graph PNGs under public/ (managed /og/crisis/*.png only).',
            arguments: [
                new ArgumentDefinition(
                    name: 'operation',
                    mode: ArgumentMode::Optional,
                    description: 'build|regenerate',
                    default: 'build',
                ),
                new ArgumentDefinition(
                    name: 'community_slug',
                    mode: ArgumentMode::Optional,
                    description: 'With regenerate: community slug',
                ),
                new ArgumentDefinition(
                    name: 'incident_slug',
                    mode: ArgumentMode::Optional,
                    description: 'With regenerate: incident slug',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Print actions without writing files',
                ),
                new OptionDefinition(
                    name: 'only',
                    mode: OptionMode::Required,
                    description: 'Limit build to community_slug/incident_slug',
                    default: '',
                ),
                new OptionDefinition(
                    name: 'force',
                    mode: OptionMode::None,
                    description: 'Overwrite existing managed PNG',
                ),
                new OptionDefinition(
                    name: 'draft',
                    mode: OptionMode::None,
                    description: 'Include draft incidents in resolver',
                ),
            ],
            handler: [CrisisOgAssetsHandler::class, 'execute'],
        );
    }

    /**
     * @return array{0: bool, 1: string} [configured, fromAddress]
     */
    private function mailConfigSnapshot(): array
    {
        $mailConfig = is_array($this->config['mail'] ?? null) ? $this->config['mail'] : [];
        $fromAddress = trim((string) ($mailConfig['from_address'] ?? ''));
        $configured = trim((string) ($mailConfig['sendgrid_api_key'] ?? '')) !== ''
            && $fromAddress !== '';

        return [$configured, $fromAddress];
    }
}
