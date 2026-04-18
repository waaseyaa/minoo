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
use App\Entity\Group;
use App\Entity\GroupType;
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
use App\Support\Command\MailTestCommand;
use App\Support\Command\MessagingDigestCommand;
use App\Support\MessageDigestCommand;
use App\Support\NewsletterMailer;
use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use App\Support\NorthCloudCommunityDictionaryClient;
use App\Twig\AccountDisplayTwigExtension;
use App\Twig\DateTwigExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
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

final class AppServiceProvider extends ServiceProvider
{
    private ?NorthCloudSearchProvider $searchProvider = null;

    public function register(): void
    {
        // =====================================================================
        // --- I18n ---
        // =====================================================================

        $this->singleton(LanguageManagerInterface::class, function (): LanguageManagerInterface {
            return new LanguageManager([
                new Language('en', 'English', isDefault: true),
                new Language('oj', 'Anishinaabemowin'),
            ]);
        });

        $this->singleton(TranslatorInterface::class, function (): TranslatorInterface {
            $langPath = dirname(__DIR__, 2) . '/resources/lang';
            /** @var LanguageManagerInterface $manager */
            $manager = $this->resolve(LanguageManagerInterface::class);
            return new Translator($langPath, $manager);
        });

        $this->singleton(UrlPrefixNegotiator::class, fn() => new UrlPrefixNegotiator());

        // =====================================================================
        // --- Rate limiting ---
        // =====================================================================

        $this->singleton(\App\Contract\RateLimiterInterface::class, function (): \App\Contract\RateLimiterInterface {
            if (getenv('APP_ENV') === 'testing') {
                return new \App\Support\NullRateLimiter();
            }
            $dbPath = getenv('WAASEYAA_DB') ?: dirname(__DIR__, 2) . '/storage/waaseyaa.sqlite';
            return new \App\Support\SqliteRateLimiter($dbPath);
        });

        // =====================================================================
        // --- Events ---
        // =====================================================================

        $this->singleton(
            \App\Domain\Events\Service\EventFeedRanker::class,
            fn(): \App\Domain\Events\Service\EventFeedRanker => new \App\Domain\Events\Service\EventFeedRanker(),
        );

        $this->singleton(
            \App\Domain\Events\Service\EventFeedBuilder::class,
            fn(): \App\Domain\Events\Service\EventFeedBuilder => new \App\Domain\Events\Service\EventFeedBuilder(
                $this->resolve(EntityTypeManager::class),
                $this->resolve(\App\Domain\Events\Service\EventFeedRanker::class),
            ),
        );

        $this->entityType(new EntityType(
            id: 'event',
            label: 'Event',
            class: Event::class,
            keys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            group: 'events',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'type' => [
                    'type' => 'string',
                    'label' => 'Type',
                    'weight' => -1,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'description' => 'Rich text event description.',
                    'weight' => 5,
                ],
                'location' => [
                    'type' => 'string',
                    'label' => 'Location',
                    'description' => 'Physical location or "online".',
                    'weight' => 10,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'starts_at' => [
                    'type' => 'datetime',
                    'label' => 'Starts At',
                    'weight' => 15,
                ],
                'ends_at' => [
                    'type' => 'datetime',
                    'label' => 'Ends At',
                    'description' => 'Leave empty for open-ended events.',
                    'weight' => 16,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Featured Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'source_url' => [
                    'type' => 'string',
                    'label' => 'Source URL',
                    'description' => 'Canonical URL of the original content (for NC deduplication).',
                    'weight' => 50,
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
                    'weight' => 95,
                ],
                'verified_at' => [
                    'type' => 'datetime',
                    'label' => 'Verified At',
                    'description' => 'When this record was last verified.',
                    'weight' => 96,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'event_type',
            label: 'Event Type',
            class: EventType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'events',
        ));

        // =====================================================================
        // --- Groups ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'group',
            label: 'Community Group',
            class: Group::class,
            keys: ['id' => 'gid', 'uuid' => 'uuid', 'label' => 'name', 'bundle' => 'type'],
            group: 'community',
            fieldDefinitions: [
                'name' => [
                    'type' => 'string',
                    'label' => 'Name',
                    'weight' => 0,
                ],
                'type' => [
                    'type' => 'string',
                    'label' => 'Type',
                    'weight' => -1,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 5,
                ],
                'url' => [
                    'type' => 'uri',
                    'label' => 'Website',
                    'description' => 'External website URL.',
                    'weight' => 10,
                ],
                'region' => [
                    'type' => 'string',
                    'label' => 'Region',
                    'description' => 'Geographic region.',
                    'weight' => 15,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 16,
                ],
                'phone' => [
                    'type' => 'string',
                    'label' => 'Phone',
                    'description' => 'Business phone number in E.164 format.',
                    'weight' => 17,
                ],
                'email' => [
                    'type' => 'string',
                    'label' => 'Email',
                    'weight' => 18,
                ],
                'address' => [
                    'type' => 'string',
                    'label' => 'Address',
                    'description' => 'Physical address.',
                    'weight' => 19,
                ],
                'booking_url' => [
                    'type' => 'uri',
                    'label' => 'Booking URL',
                    'description' => 'External booking link.',
                    'weight' => 20,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 21,
                ],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
                    'weight' => 95,
                ],
                'verified_at' => [
                    'type' => 'datetime',
                    'label' => 'Verified At',
                    'description' => 'When this record was last verified.',
                    'weight' => 96,
                ],
                'social_posts' => [
                    'type' => 'text_long',
                    'label' => 'Social Posts',
                    'description' => 'JSON array of recent social media posts.',
                    'weight' => 97,
                ],
                'latitude' => [
                    'type' => 'float',
                    'label' => 'Latitude',
                    'description' => 'Geocoded from address, or community fallback.',
                    'weight' => 98,
                ],
                'longitude' => [
                    'type' => 'float',
                    'label' => 'Longitude',
                    'description' => 'Geocoded from address, or community fallback.',
                    'weight' => 99,
                ],
                'coordinate_source' => [
                    'type' => 'string',
                    'label' => 'Coordinate Source',
                    'description' => 'How coordinates were obtained: address or community.',
                    'weight' => 100,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'group_type',
            label: 'Group Type',
            class: GroupType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'community',
        ));

        // =====================================================================
        // --- Cultural Groups ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'cultural_group',
            label: 'Cultural Group',
            class: CulturalGroup::class,
            keys: ['id' => 'cgid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'community',
            fieldDefinitions: [
                'name' => [
                    'type' => 'string',
                    'label' => 'Name',
                    'weight' => 0,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'parent_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Parent Group',
                    'description' => 'Self-referential hierarchy.',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 5,
                ],
                'depth_label' => [
                    'type' => 'string',
                    'label' => 'Depth Label',
                    'description' => 'Free-text depth descriptor (nation, tribe, band, clan).',
                    'weight' => 6,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 10,
                ],
                'metadata' => [
                    'type' => 'text',
                    'label' => 'Metadata',
                    'description' => 'JSON blob for extensible properties.',
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'label' => 'Sort Order',
                    'description' => 'Manual ordering within siblings.',
                    'weight' => 25,
                    'default' => 0,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        // =====================================================================
        // --- Teachings ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: Teaching::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            group: 'knowledge',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'type' => [
                    'type' => 'string',
                    'label' => 'Type',
                    'weight' => -1,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'content' => [
                    'type' => 'text_long',
                    'label' => 'Content',
                    'description' => 'Full teaching content.',
                    'weight' => 5,
                ],
                'cultural_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Cultural Group',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 10,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'tags' => [
                    'type' => 'entity_reference',
                    'label' => 'Tags',
                    'description' => 'Cross-cutting topic tags.',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'source_url' => [
                    'type' => 'string',
                    'label' => 'Source URL',
                    'description' => 'Canonical URL of the original content (for NC deduplication).',
                    'weight' => 50,
                ],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'teaching_type',
            label: 'Teaching Type',
            class: TeachingType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'knowledge',
        ));

        // =====================================================================
        // --- Cultural Collections ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'cultural_collection',
            label: 'Cultural Collection',
            class: CulturalCollection::class,
            keys: ['id' => 'ccid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'knowledge',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'description' => 'Cultural context and significance.',
                    'weight' => 5,
                ],
                'gallery' => [
                    'type' => 'entity_reference',
                    'label' => 'Gallery',
                    'description' => 'Gallery category (taxonomy term).',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 10,
                ],
                'source_url' => [
                    'type' => 'uri',
                    'label' => 'Source URL',
                    'description' => 'Original URL from ojibwe.lib.umn.edu.',
                    'weight' => 15,
                ],
                'source_attribution' => [
                    'type' => 'string',
                    'label' => 'Source Attribution',
                    'weight' => 16,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Primary Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        // =====================================================================
        // --- Language ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'dictionary_entry',
            label: 'Dictionary Entry',
            class: DictionaryEntry::class,
            keys: ['id' => 'deid', 'uuid' => 'uuid', 'label' => 'word'],
            group: 'language',
            fieldDefinitions: [
                'word' => ['type' => 'string', 'label' => 'Word', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'definition' => ['type' => 'string', 'label' => 'Definition', 'weight' => 5],
                'part_of_speech' => ['type' => 'string', 'label' => 'Part of Speech', 'description' => 'Code: ni, na, vai, vti, vta, vii, nad, nid, etc.', 'weight' => 6],
                'stem' => ['type' => 'string', 'label' => 'Stem', 'description' => 'Root stem (e.g., /jiimaan-/).', 'weight' => 7],
                'inflected_forms' => ['type' => 'text', 'label' => 'Inflected Forms', 'description' => 'JSON array of form/label pairs.', 'weight' => 8],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'description' => 'ISO-style code (e.g., oj, oj-sw, oj-nw).', 'weight' => 9, 'default' => 'oj'],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'weight' => 15],
                'attribution_source' => ['type' => 'string', 'label' => 'Attribution Source', 'description' => 'Source identifier (e.g., ojibwe-peoples-dictionary).', 'weight' => 16],
                'attribution_url' => ['type' => 'uri', 'label' => 'Attribution URL', 'description' => 'URL of the authoritative source.', 'weight' => 17],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this content may be shown on public pages.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'description' => 'Whether this content may be used for AI training. Default: no.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'example_sentence',
            label: 'Example Sentence',
            class: ExampleSentence::class,
            keys: ['id' => 'esid', 'uuid' => 'uuid', 'label' => 'ojibwe_text'],
            group: 'language',
            fieldDefinitions: [
                'ojibwe_text' => ['type' => 'string', 'label' => 'Ojibwe Text', 'weight' => 0],
                'english_text' => ['type' => 'string', 'label' => 'English Translation', 'weight' => 5],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 10],
                'contributor_id' => ['type' => 'entity_reference', 'label' => 'Contributor', 'settings' => ['target_type' => 'contributor'], 'weight' => 15],
                'audio_url' => ['type' => 'uri', 'label' => 'Audio URL', 'weight' => 20],
                'source_sentence_id' => ['type' => 'string', 'label' => 'Source Sentence ID', 'description' => 'Unique ID from source for dedup across re-crawls.', 'weight' => 22],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'weight' => 25, 'default' => 'oj'],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'word_part',
            label: 'Word Part',
            class: WordPart::class,
            keys: ['id' => 'wpid', 'uuid' => 'uuid', 'label' => 'form'],
            group: 'language',
            fieldDefinitions: [
                'form' => ['type' => 'string', 'label' => 'Form', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'type' => ['type' => 'string', 'label' => 'Type', 'description' => 'initial, medial, or final.', 'weight' => 5],
                'definition' => ['type' => 'string', 'label' => 'Definition', 'weight' => 10],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'weight' => 15],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'speaker',
            label: 'Speaker',
            class: Speaker::class,
            keys: ['id' => 'spid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'language',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'code' => ['type' => 'string', 'label' => 'Code', 'weight' => 1],
                'bio' => ['type' => 'text', 'label' => 'Biography', 'weight' => 5],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 6],
                'consent_public_display' => ['type' => 'boolean', 'label' => 'Public Display Consent', 'description' => 'Whether this speaker may be shown on public pages.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'description' => 'Whether this speaker data may be used for AI training. Default: no.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->singleton(MapperRegistry::class, function (): MapperRegistry {
            $registry = new MapperRegistry();
            $registry->register(new NcArticleToTeachingMapper());
            $registry->register(new NcArticleToEventMapper());

            return $registry;
        });

        $this->singleton(PackageNorthCloudClient::class, function (): PackageNorthCloudClient {
            $ncConfig = $this->config['northcloud'] ?? [];
            $baseUrl = rtrim((string) ($ncConfig['base_url'] ?? 'https://api.northcloud.one'), '/');
            $timeout = (int) ($ncConfig['timeout'] ?? 5);
            $apiToken = (string) ($ncConfig['api_token'] ?? '');

            return new PackageNorthCloudClient(
                baseUrl: $baseUrl,
                timeout: $timeout,
                apiToken: $apiToken,
                allowInsecure: $this->shouldAllowInsecureNorthCloudUrl($baseUrl),
            );
        });

        $this->singleton(
            NorthCloudCommunityDictionaryClientInterface::class,
            function (): NorthCloudCommunityDictionaryClientInterface {
                return new NorthCloudCommunityDictionaryClient(
                    client: $this->resolve(PackageNorthCloudClient::class),
                );
            },
        );

        $this->singleton(NcSyncService::class, function (): NcSyncService {
            return new NcSyncService(
                $this->resolve(PackageNorthCloudClient::class),
                $this->resolve(EntityTypeManager::class),
                $this->resolve(MapperRegistry::class),
            );
        });

        // MCP auth: bind BearerTokenAuth with tokens from config.
        // Tokens map bearer token string → AccountInterface. Empty by default
        // (all requests return 401); populate via config/waaseyaa.php mcp.tokens.
        $this->singleton(McpAuthInterface::class, function (): McpAuthInterface {
            $tokens = (array) ($this->config['mcp']['tokens'] ?? []);
            return new BearerTokenAuth($tokens);
        });

        // MCP tool registry and executor: no tools registered by default.
        // Add tool definitions and execution logic when MCP tools are wired up.
        $this->singleton(ToolRegistryInterface::class, fn(): ToolRegistryInterface => new class implements ToolRegistryInterface {
            public function getTools(): array { return []; }
            public function getTool(string $name): ?\Waaseyaa\AI\Schema\Mcp\McpToolDefinition { return null; }
        });

        $this->singleton(ToolExecutorInterface::class, fn(): ToolExecutorInterface => new class implements ToolExecutorInterface {
            public function execute(string $toolName, array $arguments): array {
                return ['content' => [['type' => 'text', 'text' => "Unknown tool: {$toolName}"]], 'isError' => true];
            }
        });

        // =====================================================================
        // --- Ingestion ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'ingest_log',
            label: 'Ingestion Log',
            class: IngestLog::class,
            keys: ['id' => 'ilid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'ingestion',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'status' => [
                    'type' => 'string',
                    'label' => 'Status',
                    'description' => 'pending_review, approved, rejected, or failed.',
                    'weight' => 1,
                    'default' => 'pending_review',
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Origin identifier (e.g. northcloud, ojibwe_lib).',
                    'weight' => 2,
                ],
                'entity_type_target' => [
                    'type' => 'string',
                    'label' => 'Target Entity Type',
                    'description' => 'Entity type machine name for the parsed content.',
                    'weight' => 3,
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'label' => 'Created Entity ID',
                    'description' => 'ID of the entity created after approval.',
                    'weight' => 4,
                ],
                'payload_raw' => [
                    'type' => 'text',
                    'label' => 'Raw Payload',
                    'description' => 'Original payload JSON from source.',
                    'weight' => 10,
                ],
                'payload_parsed' => [
                    'type' => 'text',
                    'label' => 'Parsed Payload',
                    'description' => 'Mapped/transformed fields JSON.',
                    'weight' => 11,
                ],
                'error_message' => [
                    'type' => 'text',
                    'label' => 'Error Message',
                    'description' => 'Error details if status is failed.',
                    'weight' => 12,
                ],
                'reviewed_by' => [
                    'type' => 'entity_reference',
                    'label' => 'Reviewed By',
                    'settings' => ['target_type' => 'user'],
                    'weight' => 20,
                ],
                'reviewed_at' => [
                    'type' => 'timestamp',
                    'label' => 'Reviewed At',
                    'weight' => 21,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        // =====================================================================
        // --- Search ---
        // =====================================================================

        $searchConfig = $this->config['search'] ?? [];

        $this->searchProvider = new NorthCloudSearchProvider(
            client: new PackageNorthCloudClient(
                baseUrl: (string) ($searchConfig['base_url'] ?? 'https://northcloud.one'),
                timeout: (int) ($searchConfig['timeout'] ?? 5),
                allowInsecure: $this->shouldAllowInsecureNorthCloudUrl((string) ($searchConfig['base_url'] ?? 'https://northcloud.one')),
            ),
            cacheTtl: (int) ($searchConfig['cache_ttl'] ?? 60),
        );

        $this->singleton(SearchProviderInterface::class, fn(): SearchProviderInterface => $this->searchProvider);

        // =====================================================================
        // --- People ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'resource_person',
            label: 'Resource Person',
            class: ResourcePerson::class,
            keys: ['id' => 'rpid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 5],
                'community' => ['type' => 'string', 'label' => 'Community', 'description' => 'Community affiliation (e.g. Sagamok Anishnawbek).', 'weight' => 10],
                'roles' => ['type' => 'entity_reference', 'label' => 'Roles', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_roles'], 'cardinality' => -1, 'weight' => 15],
                'offerings' => ['type' => 'entity_reference', 'label' => 'Offerings', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_offerings'], 'cardinality' => -1, 'weight' => 16],
                'email' => ['type' => 'string', 'label' => 'Email', 'weight' => 20],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 21],
                'business_name' => ['type' => 'string', 'label' => 'Business Name', 'weight' => 25],
                'website' => ['type' => 'string', 'label' => 'Website', 'weight' => 26],
                'linked_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Linked Business',
                    'description' => 'The business group this person is associated with.',
                    'settings' => ['target_type' => 'group'],
                    'weight' => 27,
                ],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 28],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
                    'weight' => 95,
                ],
                'verified_at' => [
                    'type' => 'datetime',
                    'label' => 'Verified At',
                    'description' => 'When this record was last verified.',
                    'weight' => 96,
                ],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Elder Support ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'elder_support_request',
            label: 'Elder Support Request',
            class: ElderSupportRequest::class,
            keys: ['id' => 'esrid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'elders',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 1],
                'community' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 5],
                'type' => ['type' => 'string', 'label' => 'Request Type', 'weight' => 10],
                'notes' => ['type' => 'text_long', 'label' => 'Notes', 'weight' => 15],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 20, 'default' => 'open'],
                'assigned_volunteer' => [
                    'type' => 'integer',
                    'label' => 'Assigned Volunteer',
                    'description' => 'ID of the assigned volunteer entity.',
                    'weight' => 25,
                ],
                'assigned_at' => [
                    'type' => 'timestamp',
                    'label' => 'Assigned At',
                    'weight' => 26,
                ],
                'completion_notes' => ['type' => 'text_long', 'label' => 'Completion Notes', 'weight' => 28],
                'cancelled_reason' => ['type' => 'text_long', 'label' => 'Cancellation Reason', 'weight' => 30],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'volunteer',
            label: 'Volunteer',
            class: Volunteer::class,
            keys: ['id' => 'vid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'elders',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 1],
                'community' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 3],
                'availability' => ['type' => 'string', 'label' => 'Availability', 'weight' => 5],
                'skills' => ['type' => 'entity_reference', 'label' => 'Skills', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'volunteer_skills'], 'cardinality' => -1, 'weight' => 10],
                'max_travel_km' => ['type' => 'integer', 'label' => 'Max Travel (km)', 'weight' => 12],
                'account_id' => ['type' => 'integer', 'label' => 'Account ID', 'weight' => 35],
                'notes' => ['type' => 'text_long', 'label' => 'Notes', 'weight' => 15],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 20, 'default' => 'active'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Community ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'communities',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'community_type' => ['type' => 'string', 'label' => 'Community Type', 'weight' => 5],
                'municipality_type' => ['type' => 'string', 'label' => 'Municipality Type', 'weight' => 6],
                'province' => ['type' => 'string', 'label' => 'Province', 'weight' => 10],
                'region' => ['type' => 'string', 'label' => 'Region', 'weight' => 11],
                'latitude' => ['type' => 'float', 'label' => 'Latitude', 'weight' => 15],
                'longitude' => ['type' => 'float', 'label' => 'Longitude', 'weight' => 16],
                'population' => ['type' => 'integer', 'label' => 'Population', 'weight' => 20],
                'population_year' => ['type' => 'integer', 'label' => 'Population Year', 'weight' => 21],
                'nation' => ['type' => 'string', 'label' => 'Nation/Linguistic Group', 'weight' => 25],
                'treaty' => ['type' => 'string', 'label' => 'Treaty', 'weight' => 26],
                'reserve_name' => ['type' => 'string', 'label' => 'Reserve Name', 'weight' => 27],
                'language_group' => ['type' => 'string', 'label' => 'Language Group', 'weight' => 30],
                'website' => ['type' => 'string', 'label' => 'Website', 'weight' => 35],
                'inac_id' => ['type' => 'string', 'label' => 'INAC Band Number', 'weight' => 40],
                'statcan_csd' => ['type' => 'string', 'label' => 'StatsCan CSD Code', 'weight' => 41],
                'nc_id' => ['type' => 'string', 'label' => 'NorthCloud ID', 'weight' => 42],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 50, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 60],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 61],
            ],
        ));

        // =====================================================================
        // --- Featured Items ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'featured_item',
            label: 'Featured Item',
            class: FeaturedItem::class,
            keys: ['id' => 'fid', 'uuid' => 'uuid', 'label' => 'headline'],
            group: 'editorial',
            fieldDefinitions: [
                'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'description' => 'Referenced entity type (event, teaching, group, resource_person).', 'weight' => 1],
                'entity_id' => ['type' => 'integer', 'label' => 'Entity ID', 'description' => 'Referenced entity ID.', 'weight' => 2],
                'headline' => ['type' => 'string', 'label' => 'Headline', 'description' => 'Display headline (overrides entity title when set).', 'weight' => 3],
                'subheadline' => ['type' => 'string', 'label' => 'Subheadline', 'description' => 'Optional subtitle or context line.', 'weight' => 4],
                'weight' => ['type' => 'integer', 'label' => 'Weight', 'description' => 'Sort order (higher = more prominent).', 'default' => 0, 'weight' => 10],
                'starts_at' => ['type' => 'datetime', 'label' => 'Starts At', 'description' => 'When this item begins appearing.', 'weight' => 20],
                'ends_at' => ['type' => 'datetime', 'label' => 'Ends At', 'description' => 'When this item stops appearing.', 'weight' => 21],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'default' => 1, 'weight' => 30],
            ],
        ));

        // =====================================================================
        // --- Dialect Regions ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'dialect_region',
            label: 'Dialect Region',
            class: DialectRegion::class,
            keys: ['id' => 'code', 'label' => 'name'],
            group: 'language',
        ));

        // =====================================================================
        // --- Oral History ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'oral_history',
            label: 'Oral History',
            class: OralHistory::class,
            keys: ['id' => 'ohid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            group: 'knowledge',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'type' => [
                    'type' => 'string',
                    'label' => 'Type',
                    'weight' => -1,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'summary' => [
                    'type' => 'text_long',
                    'label' => 'Summary',
                    'description' => 'Brief description of the oral history.',
                    'weight' => 3,
                ],
                'content' => [
                    'type' => 'text_long',
                    'label' => 'Content',
                    'description' => 'Full oral history transcript or narrative.',
                    'weight' => 5,
                ],
                'audio_url' => [
                    'type' => 'string',
                    'label' => 'Audio URL',
                    'description' => 'URL to audio recording.',
                    'weight' => 6,
                ],
                'duration_seconds' => [
                    'type' => 'integer',
                    'label' => 'Duration (seconds)',
                    'weight' => 7,
                ],
                'recorded_date' => [
                    'type' => 'string',
                    'label' => 'Date Recorded',
                    'weight' => 8,
                ],
                'collection_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Collection',
                    'settings' => ['target_type' => 'oral_history_collection'],
                    'weight' => 9,
                ],
                'story_order' => [
                    'type' => 'integer',
                    'label' => 'Story Order',
                    'description' => 'Sort weight within collection.',
                    'weight' => 10,
                ],
                'contributor_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Contributor',
                    'settings' => ['target_type' => 'contributor'],
                    'weight' => 11,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'cultural_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Cultural Group',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 13,
                ],
                'protocol_level' => [
                    'type' => 'string',
                    'label' => 'Protocol Level',
                    'description' => 'Access protocol: open, community, restricted.',
                    'default_value' => 'open',
                    'weight' => 20,
                ],
                'is_living_record' => [
                    'type' => 'boolean',
                    'label' => 'Living Record',
                    'description' => 'Whether this story can be updated by community members.',
                    'weight' => 21,
                    'default' => 0,
                ],
                'tags' => [
                    'type' => 'entity_reference',
                    'label' => 'Tags',
                    'description' => 'Cross-cutting topic tags.',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 25,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'oral_history_type',
            label: 'Oral History Type',
            class: OralHistoryType::class,
            keys: ['id' => 'type', 'label' => 'name'],
            group: 'knowledge',
        ));

        $this->entityType(new EntityType(
            id: 'oral_history_collection',
            label: 'Oral History Collection',
            class: OralHistoryCollection::class,
            keys: ['id' => 'ohcid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'knowledge',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 5,
                ],
                'curator_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Curator',
                    'settings' => ['target_type' => 'contributor'],
                    'weight' => 10,
                ],
                'community_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Community',
                    'settings' => ['target_type' => 'community'],
                    'weight' => 12,
                ],
                'protocol_level' => [
                    'type' => 'string',
                    'label' => 'Protocol Level',
                    'description' => 'Access protocol: open, community, restricted.',
                    'default_value' => 'open',
                    'weight' => 20,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

        // =====================================================================
        // --- Contributors ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'contributor',
            label: 'Contributor',
            class: Contributor::class,
            keys: ['id' => 'coid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'contributor',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'code' => ['type' => 'string', 'label' => 'Speaker Code', 'description' => 'Abbreviation (e.g., es, nj, gh).', 'weight' => 5],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 10],
                'role' => ['type' => 'string', 'label' => 'Role', 'description' => 'Contributor role: speaker, storyteller, elder, translator.', 'weight' => 12],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 15],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 20],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 25,
                ],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this contributor may be shown on public pages.', 'weight' => 28, 'default' => 0],
                'consent_record' => ['type' => 'boolean', 'label' => 'Recording Consent', 'description' => 'Whether this contributor consents to being recorded.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Engagement ---
        // =====================================================================

        // reaction, comment, follow entity types are registered by framework EngagementServiceProvider.

        $this->entityType(new EntityType(
            id: 'post',
            label: 'Post',
            class: Post::class,
            keys: ['id' => 'pid', 'uuid' => 'uuid', 'label' => 'body'],
            group: 'engagement',
            fieldDefinitions: [
                'body' => [
                    'type' => 'text_long',
                    'label' => 'Body',
                    'weight' => 0,
                ],
                'user_id' => [
                    'type' => 'integer',
                    'label' => 'User ID',
                    'weight' => 1,
                ],
                'community_id' => [
                    'type' => 'integer',
                    'label' => 'Community ID',
                    'weight' => 2,
                ],
                'images' => [
                    'type' => 'text_long',
                    'label' => 'Images',
                    'weight' => 3,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 5,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 10,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 11,
                ],
            ],
        ));

        $this->singleton(UploadHandler::class, fn(): UploadHandler => new UploadHandler(
            dirname(__DIR__, 2) . '/storage/uploads',
        ));

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

        $feedScoringConfig = is_file(dirname(__DIR__, 2) . '/config/feed_scoring.php')
            ? require dirname(__DIR__, 2) . '/config/feed_scoring.php'
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

        // =====================================================================
        // --- Games ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'game_session',
            label: 'Game Session',
            class: GameSession::class,
            keys: ['id' => 'gsid', 'uuid' => 'uuid', 'label' => 'mode'],
            group: 'games',
            fieldDefinitions: [
                'mode' => ['type' => 'string', 'label' => 'Mode', 'weight' => 0],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 1],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 5],
                'user_id' => ['type' => 'integer', 'label' => 'User', 'weight' => 6],
                'guesses' => ['type' => 'text_long', 'label' => 'Guesses', 'description' => 'JSON array of letters guessed.', 'weight' => 10],
                'wrong_count' => ['type' => 'integer', 'label' => 'Wrong Count', 'weight' => 11, 'default' => 0],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 15, 'default' => 'in_progress'],
                'daily_date' => ['type' => 'string', 'label' => 'Daily Date', 'weight' => 16],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 17, 'default' => 'easy'],
                'game_type' => ['type' => 'string', 'label' => 'Game Type', 'weight' => 18, 'default' => 'shkoda'],
                'puzzle_id' => ['type' => 'string', 'label' => 'Puzzle ID', 'weight' => 19],
                'grid_state' => ['type' => 'text_long', 'label' => 'Grid State', 'description' => 'JSON crossword grid fill state.', 'weight' => 20],
                'hints_used' => ['type' => 'integer', 'label' => 'Hints Used', 'weight' => 21, 'default' => 0],
                'found_objects' => ['type' => 'text_long', 'label' => 'Found Objects', 'description' => 'JSON array of found object IDs (Journey game).', 'weight' => 22, 'default' => '[]'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'daily_challenge',
            label: 'Daily Challenge',
            class: DailyChallenge::class,
            keys: ['id' => 'date', 'label' => 'date'],
            group: 'games',
            fieldDefinitions: [
                'date' => ['type' => 'string', 'label' => 'Date', 'weight' => 0],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 5],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 10, 'default' => 'english_to_ojibwe'],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 15, 'default' => 'easy'],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'crossword_puzzle',
            label: 'Crossword Puzzle',
            class: CrosswordPuzzle::class,
            keys: ['id' => 'id', 'label' => 'id'],
            group: 'games',
            fieldDefinitions: [
                'grid_size' => ['type' => 'integer', 'label' => 'Grid Size', 'weight' => 0],
                'words' => ['type' => 'text_long', 'label' => 'Words', 'description' => 'JSON array of word placements.', 'weight' => 5],
                'clues' => ['type' => 'text_long', 'label' => 'Clues', 'description' => 'JSON map of word index to clue data.', 'weight' => 10],
                'theme' => ['type' => 'string', 'label' => 'Theme', 'weight' => 15],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 20, 'default' => 'easy'],
            ],
        ));

        // =====================================================================
        // --- Leaders ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'leader',
            label: 'Leader',
            class: Leader::class,
            keys: ['id' => 'lid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            fieldDefinitions: [
                'name' => [
                    'type' => 'string',
                    'label' => 'Name',
                    'description' => 'Full name of the leader.',
                    'weight' => 1,
                ],
                'role' => [
                    'type' => 'string',
                    'label' => 'Role',
                    'description' => 'Leadership role (e.g. Chief, Councillor).',
                    'weight' => 2,
                ],
                'email' => [
                    'type' => 'string',
                    'label' => 'Email',
                    'description' => 'Contact email address.',
                    'weight' => 3,
                ],
                'phone' => [
                    'type' => 'string',
                    'label' => 'Phone',
                    'description' => 'Contact phone number.',
                    'weight' => 4,
                ],
                'community_id' => [
                    'type' => 'string',
                    'label' => 'Community ID',
                    'description' => 'NorthCloud community nc_id reference.',
                    'weight' => 5,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));

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
            $config = require __DIR__ . '/../../config/newsletter.php';
            return new NewsletterAssembler(
                entityTypeManager: $this->resolve(EntityTypeManager::class),
                lifecycle: $this->resolve(EditionLifecycle::class),
                quotas: SectionQuota::fromConfig($config['sections']),
            );
        });

        $this->singleton(RenderTokenStore::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            $dir = dirname(__DIR__, 2) . '/' . $config['storage_dir'] . '/render-tokens';
            return new RenderTokenStore(
                storageDir: $dir,
                ttlSeconds: 60,
            );
        });

        $this->singleton(NewsletterRenderer::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            $rootDir = dirname(__DIR__, 2);
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
            $config = require __DIR__ . '/../../config/newsletter.php';
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
        // --- Ingestion Dashboard ---
        // =====================================================================

        $router->addRoute(
            'admin.ingestion',
            RouteBuilder::create('/admin/ingestion')
                ->controller('App\Controller\IngestionDashboardController::index')
                ->requirePermission('administer content')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.status',
            RouteBuilder::create('/api/admin/nc-sync-status')
                ->controller('App\Controller\IngestionApiController::status')
                ->requirePermission('administer content')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.envelope',
            RouteBuilder::create('/api/ingestion/envelope')
                ->controller('App\Controller\IngestionApiController::ingestEnvelope')
                ->requirePermission('administer content')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.approve',
            RouteBuilder::create('/api/admin/ingestion/{id}/approve')
                ->controller('App\Controller\IngestionApiController::approve')
                ->requirePermission('administer content')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.reject',
            RouteBuilder::create('/api/admin/ingestion/{id}/reject')
                ->controller('App\Controller\IngestionApiController::reject')
                ->requirePermission('administer content')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.materialize',
            RouteBuilder::create('/api/admin/ingestion/{id}/materialize')
                ->controller('App\Controller\IngestionApiController::materialize')
                ->requirePermission('administer content')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        // =====================================================================
        // --- Account ---
        // =====================================================================

        $router->addRoute(
            'account.home',
            RouteBuilder::create('/account')
                ->controller('App\Controller\AccountHomeController::index')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'account.elder_toggle',
            RouteBuilder::create('/account/elder-toggle')
                ->controller('App\Controller\AccountHomeController::toggleElder')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Chat ---
        // =====================================================================

        $router->addRoute(
            'chat.send',
            RouteBuilder::create('/api/chat')
                ->controller('App\Controller\ChatController::send')
                ->allowAll()
                ->methods('POST')
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
            'admin.users',
            RouteBuilder::create('/admin/users')
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

        // =====================================================================
        // --- Engagement ---
        // =====================================================================

        $router->addRoute(
            'engagement.react',
            RouteBuilder::create('/api/engagement/react')
                ->controller('App\\Controller\\EngagementController::react')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteReaction',
            RouteBuilder::create('/api/engagement/react/{id}')
                ->controller('App\\Controller\\EngagementController::deleteReaction')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.comment',
            RouteBuilder::create('/api/engagement/comment')
                ->controller('App\\Controller\\EngagementController::comment')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteComment',
            RouteBuilder::create('/api/engagement/comment/{id}')
                ->controller('App\\Controller\\EngagementController::deleteComment')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.getComments',
            RouteBuilder::create('/api/engagement/comments/{target_type}/{target_id}')
                ->controller('App\\Controller\\EngagementController::getComments')
                ->allowAll()
                ->methods('GET')
                ->requirement('target_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.follow',
            RouteBuilder::create('/api/engagement/follow')
                ->controller('App\\Controller\\EngagementController::follow')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteFollow',
            RouteBuilder::create('/api/engagement/follow/{id}')
                ->controller('App\\Controller\\EngagementController::deleteFollow')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.createPost',
            RouteBuilder::create('/api/engagement/post')
                ->controller('App\\Controller\\EngagementController::createPost')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deletePost',
            RouteBuilder::create('/api/engagement/post/{id}')
                ->controller('App\\Controller\\EngagementController::deletePost')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        // =====================================================================
        // --- Messaging ---
        // =====================================================================

        $router->addRoute(
            'messaging.threads.index',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('App\\Controller\\MessagingController::indexThreads')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.store',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('App\\Controller\\MessagingController::createThread')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.show',
            RouteBuilder::create('/api/messaging/threads/{id}')
                ->controller('App\\Controller\\MessagingController::showThread')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.index',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('App\\Controller\\MessagingController::indexMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.store',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('App\\Controller\\MessagingController::createMessage')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.store',
            RouteBuilder::create('/api/messaging/threads/{id}/participants')
                ->controller('App\\Controller\\MessagingController::addParticipants')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/participants/{user_id}')
                ->controller('App\\Controller\\MessagingController::removeParticipant')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('user_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.users.search',
            RouteBuilder::create('/api/messaging/users')
                ->controller('App\\Controller\\MessagingController::searchUsers')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.edit',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('App\\Controller\\MessagingController::editMessage')
                ->requireAuthentication()
                ->methods('PATCH')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('App\\Controller\\MessagingController::deleteMessage')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.read',
            RouteBuilder::create('/api/messaging/threads/{id}/read')
                ->controller('App\\Controller\\MessagingController::markRead')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.typing',
            RouteBuilder::create('/api/messaging/threads/{id}/typing')
                ->controller('App\\Controller\\MessagingController::typing')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.unread',
            RouteBuilder::create('/api/messaging/unread-count')
                ->controller('App\\Controller\\MessagingController::unreadCount')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.search',
            RouteBuilder::create('/api/messaging/search')
                ->controller('App\\Controller\\MessagingController::searchMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Homepage ---
        // =====================================================================

        $router->addRoute(
            'home.index',
            RouteBuilder::create('/')
                ->controller('App\\Controller\\HomeController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'home.alias',
            RouteBuilder::create('/home')
                ->controller('App\\Controller\\HomeController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Feed ---
        // =====================================================================

        $router->addRoute(
            'feed.page',
            RouteBuilder::create('/feed')
                ->controller('App\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.api',
            RouteBuilder::create('/api/feed')
                ->controller('App\\Controller\\FeedController::api')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explore.redirect',
            RouteBuilder::create('/explore')
                ->controller('App\\Controller\\FeedController::explore')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

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

        // =====================================================================
        // --- Blocks ---
        // =====================================================================

        $router->addRoute('blocks.index', RouteBuilder::create('/api/blocks')
            ->controller('App\\Controller\\BlockController::index')
            ->requireAuthentication()
            ->methods('GET')
            ->build());

        $router->addRoute('blocks.store', RouteBuilder::create('/api/blocks')
            ->controller('App\\Controller\\BlockController::store')
            ->requireAuthentication()
            ->methods('POST')
            ->build());

        $router->addRoute('blocks.delete', RouteBuilder::create('/api/blocks/{user_id}')
            ->controller('App\\Controller\\BlockController::delete')
            ->requireAuthentication()
            ->methods('DELETE')
            ->requirement('user_id', '\\d+')
            ->build());

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

    public function boot(): void
    {
        // =====================================================================
        // --- I18n: Translation Twig extension ---
        // =====================================================================

        /** @var TranslatorInterface $translator */
        $translator = $this->resolve(TranslatorInterface::class);
        /** @var LanguageManagerInterface $manager */
        $manager = $this->resolve(LanguageManagerInterface::class);

        $extension = new TranslationTwigExtension($translator, $manager);
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig !== null) {
            $twig->addExtension($extension);
        }

        // =====================================================================
        // --- Search: SearchTwigExtension ---
        // =====================================================================

        $twigForSearch = SsrServiceProvider::getTwigEnvironment();
        if ($twigForSearch !== null && $this->searchProvider !== null) {
            $baseTopics = (array) ($this->config['search']['base_topics'] ?? []);
            $twigForSearch->addExtension(new SearchTwigExtension($this->searchProvider, $baseTopics));
        }

        // =====================================================================
        // --- Flash: DateTwigExtension + AccountDisplayTwigExtension ---
        // =====================================================================

        $twigForFlash = ThemeServiceProvider::getTwigEnvironment();
        if ($twigForFlash !== null) {
            $twigForFlash->addExtension(new DateTwigExtension());
            $twigForFlash->addExtension(new AccountDisplayTwigExtension());
        }

        // =====================================================================
        // --- Chat: chat_enabled global ---
        // =====================================================================

        $twigForChat = ThemeServiceProvider::getTwigEnvironment();
        if ($twigForChat !== null) {
            $chatConfig = $this->loadChatConfig();
            $twigForChat->addGlobal('chat_enabled', $chatConfig['enabled'] ?? false);
        }

        // =====================================================================
        // --- Feed Scoring: AffinityCache invalidation on entity events ---
        // =====================================================================

        $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $affinityCache = $this->resolve(AffinityCache::class);

        $invalidate = static function (EntityEvent $event) use ($affinityCache): void {
            $entity = $event->entity;
            if (in_array($entity->getEntityTypeId(), ['reaction', 'comment', 'follow'], true)) {
                $userId = $entity->get('user_id');
                if ($userId !== null) {
                    $affinityCache->invalidate((int) $userId);
                }
            }
        };

        $dispatcher->addListener(EntityEvents::POST_SAVE->value, $invalidate);
        $dispatcher->addListener(EntityEvents::POST_DELETE->value, $invalidate);

        // =====================================================================
        // --- Games: game_session updated_at on PRE_SAVE ---
        // =====================================================================

        $dispatcher->addListener(EntityEvents::PRE_SAVE->value, static function (EntityEvent $event): void {
            if ($event->entity->getEntityTypeId() === 'game_session') {
                $event->entity->set('updated_at', time());
            }
        });
    }

    /**
     * @return list<Command>
     */
    public function commands(
        EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        // --- Mail: MailTestCommand + MessagingDigestCommand ---
        $mailConfig = $this->config['mail'] ?? [];
        $fromAddress = trim((string) ($mailConfig['from_address'] ?? ''));
        $configured = trim((string) ($mailConfig['sendgrid_api_key'] ?? '')) !== ''
            && $fromAddress !== '';

        $messagingConfig = [];
        if (isset($this->config['messaging']) && is_array($this->config['messaging'])) {
            $messagingConfig = $this->config['messaging'];
        }

        $digest = new MessageDigestCommand(
            $entityTypeManager,
            $this->resolve(MailerInterface::class),
            $configured,
            $messagingConfig,
            $fromAddress,
        );

        return [
            new NcSyncCommand(
                $this->resolve(NcSyncService::class),
                dirname(__DIR__, 2) . '/storage/nc-sync-status.json',
            ),
            new MailTestCommand(
                $this->resolve(MailerInterface::class),
                $configured,
                $fromAddress,
            ),
            new MessagingDigestCommand($digest),
        ];
    }

    /** @return array<string, mixed> */
    private function loadChatConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/config/ai-chat.php';

        if (!file_exists($path)) {
            return ['enabled' => false];
        }

        return require $path;
    }

    private function shouldAllowInsecureNorthCloudUrl(string $baseUrl): bool
    {
        if (!str_starts_with($baseUrl, 'http://')) {
            return false;
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
