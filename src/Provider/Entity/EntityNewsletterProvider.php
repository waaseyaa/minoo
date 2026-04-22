<?php

declare(strict_types=1);

namespace App\Provider\Entity;

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

final class EntityNewsletterProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
                    // =====================================================================
                    // --- Newsletter ---
                    // =====================================================================

                    $this->entityType(new EntityType(
                        id: 'newsletter_edition',
                        label: 'Newsletter Edition',
                        class: NewsletterEdition::class,
                        keys: ['id' => 'neid', 'uuid' => 'uuid', 'label' => 'headline'],
                        group: 'newsletter',
                        fieldDefinitions: [
                            'community_id' => ['type' => 'string', 'label' => 'Community ID', 'description' => 'Null = regional issue.'],
                            'volume' => ['type' => 'integer', 'label' => 'Volume', 'default' => 1],
                            'issue_number' => ['type' => 'integer', 'label' => 'Issue Number', 'default' => 1],
                            'publish_date' => ['type' => 'string', 'label' => 'Publish Date'],
                            'status' => ['type' => 'string', 'label' => 'Status', 'default' => 'draft'],
                            'pdf_path' => ['type' => 'string', 'label' => 'PDF Path'],
                            'pdf_hash' => ['type' => 'string', 'label' => 'PDF SHA256'],
                            'sent_at' => ['type' => 'datetime', 'label' => 'Sent At'],
                            'created_by' => ['type' => 'integer', 'label' => 'Created By'],
                            'approved_by' => ['type' => 'integer', 'label' => 'Approved By'],
                            'approved_at' => ['type' => 'datetime', 'label' => 'Approved At'],
                            'headline' => ['type' => 'string', 'label' => 'Headline'],
                        ],
                    ));

                    $this->entityType(new EntityType(
                        id: 'newsletter_item',
                        label: 'Newsletter Item',
                        class: NewsletterItem::class,
                        keys: ['id' => 'nitid', 'uuid' => 'uuid', 'label' => 'editor_blurb'],
                        group: 'newsletter',
                        fieldDefinitions: [
                            'edition_id' => ['type' => 'integer', 'label' => 'Edition ID'],
                            'position' => ['type' => 'integer', 'label' => 'Position', 'default' => 0],
                            'section' => ['type' => 'string', 'label' => 'Section'],
                            'source_type' => ['type' => 'string', 'label' => 'Source Type'],
                            'source_id' => ['type' => 'integer', 'label' => 'Source ID'],
                            'inline_title' => ['type' => 'string', 'label' => 'Inline Title'],
                            'inline_body' => ['type' => 'text_long', 'label' => 'Inline Body'],
                            'editor_blurb' => ['type' => 'string', 'label' => 'Editor Blurb'],
                            'included' => ['type' => 'boolean', 'label' => 'Included', 'default' => 1],
                        ],
                    ));

                    $this->entityType(new EntityType(
                        id: 'newsletter_submission',
                        label: 'Newsletter Submission',
                        class: NewsletterSubmission::class,
                        keys: ['id' => 'nsuid', 'uuid' => 'uuid', 'label' => 'title'],
                        group: 'newsletter',
                        fieldDefinitions: [
                            'community_id' => ['type' => 'string', 'label' => 'Community ID'],
                            'submitted_by' => ['type' => 'integer', 'label' => 'Submitted By'],
                            'submitted_at' => ['type' => 'datetime', 'label' => 'Submitted At'],
                            'category' => ['type' => 'string', 'label' => 'Category'],
                            'title' => ['type' => 'string', 'label' => 'Title'],
                            'body' => ['type' => 'text_long', 'label' => 'Body'],
                            'status' => ['type' => 'string', 'label' => 'Status', 'default' => 'submitted'],
                            'approved_by' => ['type' => 'integer', 'label' => 'Approved By'],
                            'approved_at' => ['type' => 'datetime', 'label' => 'Approved At'],
                            'included_in_edition_id' => ['type' => 'integer', 'label' => 'Included In Edition ID'],
                        ],
                    ));

                    $this->singleton(EditionLifecycle::class, function () {
                        return new EditionLifecycle();
                    });

                    $this->singleton(NewsletterAssembler::class, function () {
                        $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
                        return new NewsletterAssembler(
                            entityTypeManager: $this->resolve(EntityTypeManager::class),
                            lifecycle: $this->resolve(EditionLifecycle::class),
                            quotas: SectionQuota::fromConfig($config['sections']),
                        );
                    });

                    $this->singleton(RenderTokenStore::class, function () {
                        $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
                        $dir = dirname(__DIR__, 3) . '/' . $config['storage_dir'] . '/render-tokens';
                        return new RenderTokenStore(
                            storageDir: $dir,
                            ttlSeconds: 60,
                        );
                    });

                    $this->singleton(NewsletterRenderer::class, function () {
                        $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
                        $rootDir = dirname(__DIR__, 3);
                        return new NewsletterRenderer(
                            tokenStore: $this->resolve(RenderTokenStore::class),
                            storageDir: $rootDir . '/' . $config['storage_dir'],
                            baseUrl: $_ENV['APP_URL'] ?? 'http://localhost:8081',
                            nodeBinary: 'node',
                            scriptPath: $rootDir . '/bin/render-pdf.js',
                            timeoutSeconds: $config['pdf']['timeout_seconds'] ?? 60,
                        );
                    });

                    $this->singleton(NewsletterDispatcher::class, function () {
                        $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
                        $mailConfig = $this->config['mail'] ?? [];

                        $mailer = new NewsletterMailer(
                            apiKey: (string) ($mailConfig['sendgrid_api_key'] ?? ''),
                            fromAddress: (string) ($mailConfig['from_address'] ?? ''),
                            fromName: (string) ($mailConfig['from_name'] ?? 'Minoo Newsroom'),
                        );
                        return new NewsletterDispatcher(
                            mailService: $mailer,
                            communityConfig: $config['communities'] ?? [],
                            defaultCommunity: $config['default_community'] ?? 'manitoulin-regional',
                        );
                    });
                
            
    }
}
