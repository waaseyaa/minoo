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

final class EntityFeedProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
                    // =====================================================================
                    // --- Feed ---
                    // =====================================================================

                    $this->singleton(EntityLoaderService::class, fn(): EntityLoaderService => new EntityLoaderService(
                        $this->resolve(EntityTypeManager::class),
                    ));

                    $this->singleton(FeedItemFactory::class, fn(): FeedItemFactory => new FeedItemFactory());

                    $this->singleton(FeedAssemblerInterface::class, fn(): FeedAssemblerInterface => new FeedAssembler(
                        $this->resolve(EntityLoaderService::class),
                        $this->resolve(FeedItemFactory::class),
                        null,
                        $this->resolve(FeedScorer::class),
                    ));

                    // =====================================================================
                    // --- Feed Scoring ---
                    // =====================================================================

                    $feedScoringConfig = is_file(dirname(__DIR__, 3) . '/config/feed_scoring.php')
                        ? require dirname(__DIR__, 3) . '/config/feed_scoring.php'
                        : [];

                    $this->singleton(DecayCalculator::class, fn(): DecayCalculator => new DecayCalculator(
                        halfLifeHours: (float) ($feedScoringConfig['decay_half_life_hours'] ?? 96),
                    ));

                    $this->singleton(AffinityCache::class, fn(): AffinityCache => new AffinityCache(
                        new MemoryBackend(),
                    ));

                    $interactionWeights = $feedScoringConfig['interaction_weights'] ?? [];
                    $this->singleton(EngagementCalculator::class, fn(): EngagementCalculator => new EngagementCalculator(
                        entityTypeManager: $this->resolve(EntityTypeManager::class),
                        reactionWeight: (float) ($interactionWeights['reaction'] ?? 1.0),
                        commentWeight: (float) ($interactionWeights['comment'] ?? 3.0),
                    ));

                    $affinityConfig = $feedScoringConfig['affinity_signals'] ?? [];
                    $this->singleton(AffinityCalculator::class, fn(): AffinityCalculator => new AffinityCalculator(
                        entityTypeManager: $this->resolve(EntityTypeManager::class),
                        cache: $this->resolve(AffinityCache::class),
                        baseAffinity: (float) ($feedScoringConfig['base_affinity'] ?? 1.0),
                        followPoints: (float) ($affinityConfig['follows_source'] ?? 4.0),
                        sameCommunityPoints: (float) ($affinityConfig['same_community'] ?? 3.0),
                        reactionPoints: (float) ($affinityConfig['reaction_points'] ?? 1.0),
                        reactionMax: (float) ($affinityConfig['reaction_max'] ?? 5.0),
                        commentPoints: (float) ($affinityConfig['comment_points'] ?? 2.0),
                        commentMax: (float) ($affinityConfig['comment_max'] ?? 6.0),
                        geoCloseKm: (float) ($affinityConfig['geo_close_km'] ?? 50),
                        geoClosePoints: (float) ($affinityConfig['geo_close_points'] ?? 2.0),
                        geoMidKm: (float) ($affinityConfig['geo_mid_km'] ?? 150),
                        geoMidPoints: (float) ($affinityConfig['geo_mid_points'] ?? 1.0),
                        lookbackDays: (int) ($feedScoringConfig['lookback_days'] ?? 30),
                    ));

                    $diversity = $feedScoringConfig['diversity'] ?? [];
                    $this->singleton(DiversityReranker::class, fn(): DiversityReranker => new DiversityReranker(
                        maxConsecutiveType: (int) ($diversity['max_consecutive_type'] ?? 2),
                        maxConsecutiveCommunity: (int) ($diversity['max_consecutive_community'] ?? 2),
                        postGuaranteeSlot: (int) ($diversity['post_guarantee_slot'] ?? 3),
                    ));

                    $this->singleton(FeedScorer::class, fn(): FeedScorer => new FeedScorer(
                        affinity: $this->resolve(AffinityCalculator::class),
                        engagement: $this->resolve(EngagementCalculator::class),
                        decay: $this->resolve(DecayCalculator::class),
                        reranker: $this->resolve(DiversityReranker::class),
                        featuredBoost: (float) ($feedScoringConfig['featured_boost'] ?? 100.0),
                    ));
    }
}
