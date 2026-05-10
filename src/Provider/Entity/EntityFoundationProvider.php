<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Entity\Events\Event;
use App\Entity\Events\EventType;
use App\Entity\Groups\CulturalGroup;
use App\Entity\Ingestion\IngestLog;
use App\Entity\Language\DictionaryEntry;
use App\Entity\Language\ExampleSentence;
use App\Entity\Language\Speaker;
use App\Entity\Language\WordPart;
use App\Entity\Teachings\CulturalCollection;
use App\Entity\Teachings\Teaching;
use App\Entity\Teachings\TeachingType;
use App\Infrastructure\Mcp\MinooNoopToolRegistry;
use App\Infrastructure\Mcp\MinooUnknownToolExecutor;
use App\Infrastructure\NorthCloud\NorthCloudCommunityDictionaryClient;
use App\Infrastructure\NorthCloud\NorthCloudCommunityDictionaryClientInterface;
use App\Ingestion\EntityMapper\NcArticleToEventMapper;
use App\Ingestion\EntityMapper\NcArticleToTeachingMapper;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\I18n\Language;
use Waaseyaa\I18n\LanguageManager;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\Translator;
use Waaseyaa\I18n\TranslatorInterface;
use Waaseyaa\Mcp\Auth\BearerTokenAuth;
use Waaseyaa\Mcp\Auth\McpAuthInterface;
use Waaseyaa\Mcp\Bridge\ToolExecutorInterface;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;
use Waaseyaa\NorthCloud\Client\NorthCloudClient as PackageNorthCloudClient;
use Waaseyaa\NorthCloud\Search\NorthCloudSearchProvider;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Waaseyaa\NorthCloud\Sync\NcSyncService;
use Waaseyaa\Routing\Language\UrlPrefixNegotiator;
use Waaseyaa\Search\SearchProviderInterface;

final class EntityFoundationProvider extends AppCoreServiceProvider
{
    private ?NorthCloudSearchProvider $searchProvider = null;

    private function shouldAllowInsecureNorthCloudUrl(string $baseUrl): bool
    {
        if (!str_starts_with($baseUrl, 'http://')) {
            return false;
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

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
            $langPath = dirname(__DIR__, 3) . '/resources/lang';
            /** @var LanguageManagerInterface $manager */
            $manager = $this->resolve(LanguageManagerInterface::class);
            return new Translator($langPath, $manager);
        });

        $this->singleton(UrlPrefixNegotiator::class, fn () => new UrlPrefixNegotiator());

        $this->singleton(\App\Infrastructure\OpenGraph\OgImageRenderer::class, function (): \App\Infrastructure\OpenGraph\OgImageRenderer {
            return new \App\Infrastructure\OpenGraph\OgImageRenderer(dirname(__DIR__, 3));
        });

        $this->singleton(\App\Infrastructure\Crisis\CrisisIncidentResolver::class, function (): \App\Infrastructure\Crisis\CrisisIncidentResolver {
            return new \App\Infrastructure\Crisis\CrisisIncidentResolver(dirname(__DIR__, 3));
        });

        $this->singleton(\App\Infrastructure\OpenGraph\CrisisOgImageService::class, function (): \App\Infrastructure\OpenGraph\CrisisOgImageService {
            return new \App\Infrastructure\OpenGraph\CrisisOgImageService(
                dirname(__DIR__, 3),
                $this->resolve(EntityTypeManager::class),
                $this->resolve(\App\Infrastructure\OpenGraph\OgImageRenderer::class),
                $this->resolve(TranslatorInterface::class),
                $this->resolve(\App\Infrastructure\Crisis\CrisisIncidentResolver::class),
                new \Waaseyaa\Foundation\Log\NullLogger(),
            );
        });

        // =====================================================================
        // --- Rate limiting ---
        // =====================================================================

        $this->singleton(\App\Infrastructure\RateLimit\RateLimiterInterface::class, function (): \App\Infrastructure\RateLimit\RateLimiterInterface {
            if (getenv('APP_ENV') === 'testing') {
                return new \App\Infrastructure\RateLimit\NullRateLimiter();
            }
            $dbPath = getenv('WAASEYAA_DB') ?: dirname(__DIR__, 3) . '/storage/waaseyaa.sqlite';
            return new \App\Infrastructure\RateLimit\SqliteRateLimiter($dbPath);
        });

        // =====================================================================
        // --- Events ---
        // =====================================================================

        $this->singleton(
            \App\Domain\Events\Service\EventFeedRanker::class,
            fn (): \App\Domain\Events\Service\EventFeedRanker => new \App\Domain\Events\Service\EventFeedRanker(),
        );

        $this->singleton(
            \App\Domain\Events\Service\EventFeedBuilder::class,
            fn (): \App\Domain\Events\Service\EventFeedBuilder => new \App\Domain\Events\Service\EventFeedBuilder(
                $this->resolve(EntityTypeManager::class),
                $this->resolve(\App\Domain\Events\Service\EventFeedRanker::class),
            ),
        );

        $this->entityType(new EntityType(
            id: 'event',
            label: 'Event',
            class: Event::class,
            keys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            tenancy: ['scope' => 'community'],
            group: 'events',
            _fieldDefinitions: [
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
        // --- Cultural Groups ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'cultural_group',
            label: 'Cultural Group',
            class: CulturalGroup::class,
            keys: ['id' => 'cgid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'community',
            _fieldDefinitions: [
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
            tenancy: ['scope' => 'community'],
            group: 'knowledge',
            _fieldDefinitions: [
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
            _fieldDefinitions: [
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
            _fieldDefinitions: [
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
            _fieldDefinitions: [
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
            _fieldDefinitions: [
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
            _fieldDefinitions: [
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

        $this->singleton(ToolRegistryInterface::class, static fn (): ToolRegistryInterface => new MinooNoopToolRegistry());
        $this->singleton(ToolExecutorInterface::class, static fn (): ToolExecutorInterface => new MinooUnknownToolExecutor());

        // =====================================================================
        // --- Ingestion ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'ingest_log',
            label: 'Ingestion Log',
            class: IngestLog::class,
            keys: ['id' => 'ilid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'ingestion',
            _fieldDefinitions: [
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

        $this->singleton(SearchProviderInterface::class, fn (): SearchProviderInterface => $this->searchProvider);
    }
}
