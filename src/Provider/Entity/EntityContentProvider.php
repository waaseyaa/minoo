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

final class EntityContentProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
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
                        dirname(__DIR__, 3) . '/storage/uploads',
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
    }
}
