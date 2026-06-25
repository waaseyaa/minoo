<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Entity\Ingestion\IngestLog;
use App\Entity\Language\DictionaryEntry;
use App\Entity\Language\ExampleSentence;
use App\Entity\Language\Speaker;
use App\Entity\Language\WordPart;
use App\Infrastructure\Mcp\MinooNoopToolRegistry;
use App\Infrastructure\Mcp\MinooUnknownToolExecutor;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\I18n\Language;
use Waaseyaa\I18n\LanguageManager;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\Translator;
use Waaseyaa\I18n\TranslatorInterface;
use Waaseyaa\Mcp\Auth\BearerTokenAuth;
use Waaseyaa\Mcp\Auth\McpAuthInterface;
use Waaseyaa\Mcp\Bridge\ToolExecutorInterface;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;
use Waaseyaa\Routing\Language\UrlPrefixNegotiator;

/**
 * Foundation services + the language entity domain.
 *
 * Language-platform slimming (2026-06): events, teachings, cultural
 * groups/collections, NorthCloud sync, crisis/OG image services, and the
 * NorthCloud search override are gone. Search falls through to the
 * framework's local FTS5 provider; the dictionary search at
 * /language/search queries entity storage directly.
 */
final class EntityFoundationProvider extends AppCoreServiceProvider
{
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
                'notes' => ['type' => 'text', 'label' => 'Notes', 'description' => 'Transcriber notes (not displayed publicly).', 'weight' => 6],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 10],
                'contributor_id' => ['type' => 'entity_reference', 'label' => 'Contributor', 'settings' => ['target_type' => 'contributor'], 'weight' => 15],
                'speaker_id' => ['type' => 'entity_reference', 'label' => 'Speaker', 'settings' => ['target_type' => 'speaker'], 'weight' => 16],
                'audio_url' => ['type' => 'uri', 'label' => 'Audio URL', 'weight' => 20],
                'video_url' => ['type' => 'uri', 'label' => 'Video URL', 'description' => 'Web-optimized teaching reel (H.264/AAC), served from MINOO_CORPUS_PATH via the consent gate with HTTP Range support.', 'weight' => 21],
                'thumbnail_url' => ['type' => 'uri', 'label' => 'Thumbnail URL', 'description' => 'Whiteboard keyframe, served from MINOO_CORPUS_PATH via the consent gate.', 'weight' => 22],
                'context_image_url' => ['type' => 'uri', 'label' => 'Context Image URL', 'description' => 'Illustrative image (cached locally), served via the consent gate.', 'weight' => 33],
                'context_image_credit' => ['type' => 'string', 'label' => 'Context Image Credit', 'description' => 'Attribution string for the context image.', 'weight' => 34],
                'context_image_source' => ['type' => 'uri', 'label' => 'Context Image Source', 'description' => 'Original URL the context image was sourced from.', 'weight' => 35],
                'context_image_article' => ['type' => 'uri', 'label' => 'Context Image Article', 'description' => 'Source article the context image illustrates.', 'weight' => 36],
                'source_sentence_id' => ['type' => 'string', 'label' => 'Source Sentence ID', 'description' => 'Unique ID from source for dedup across re-crawls.', 'weight' => 22],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'description' => 'Where this sentence was originally published (e.g. source video).', 'weight' => 23],
                'source_date' => ['type' => 'string', 'label' => 'Source Date', 'description' => 'Publication date of the original source (YYYY-MM-DD).', 'weight' => 24],
                'provenance' => ['type' => 'text', 'label' => 'Provenance', 'description' => 'Full provenance record JSON (source, media paths, credits).', 'weight' => 26],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'weight' => 25, 'default' => 'oj'],
                // BCP 47 provenance tag for the utterance, e.g. oj-x-sagamok for
                // Steven's Sagamok corpus (#898). Empty until a tagged ingest sets
                // it; the dialect grouping is derived from it, never stored. No
                // corpus data is loaded here (Phase 0 consent gate holds).
                'language_tag' => ['type' => 'string', 'label' => 'Language Tag', 'description' => 'BCP 47 community provenance tag (oj-x-<community>); empty when unset.', 'weight' => 27],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this sentence may be shown on public pages.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'description' => 'Whether this sentence may be used for AI training. Default: no.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                // Anokii workspace pipeline stage (#876). Drives the workspace UI:
                // ingested -> drafted -> transcribed -> curated -> published. Stored
                // in the _data JSON blob (no migration); legacy rows are resolved
                // on the fly by App\Anokii\Pipeline\PipelineStageResolver.
                'pipeline_status' => ['type' => 'string', 'label' => 'Pipeline Stage', 'description' => 'Anokii workspace stage: ingested|drafted|transcribed|curated|published.', 'weight' => 31],
                // Anokii curation: the lesson this utterance was added to (#878).
                // Lessons are static config keyed by slug; this records the
                // association set in the Curate tab. Lives in the _data blob.
                'lesson_slug' => ['type' => 'string', 'label' => 'Lesson', 'description' => 'Slug of the lesson this utterance was curated into (e.g. the-kitchen).', 'weight' => 32],
                // Dynamic lesson presentation (#912): a lesson renders its assigned,
                // published+curated rows grouped by lesson_group and ordered by
                // lesson_weight. Both live in the _data blob.
                'lesson_group' => ['type' => 'string', 'label' => 'Lesson Section', 'description' => 'Section heading within the lesson (e.g. Utensils). Empty groups under a default heading.', 'weight' => 33],
                'lesson_weight' => ['type' => 'integer', 'label' => 'Lesson Order', 'description' => 'Sort order within the lesson; lower first.', 'weight' => 34, 'default' => 0],
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
                'dialect_region_id' => ['type' => 'entity_reference', 'label' => 'Dialect Region', 'settings' => ['target_type' => 'dialect_region'], 'weight' => 10],
                'community' => ['type' => 'string', 'label' => 'Community', 'description' => 'Home community, free text as the speaker states it.', 'weight' => 11],
                'consent_public_display' => ['type' => 'boolean', 'label' => 'Public Display Consent', 'description' => 'Whether this speaker may be shown on public pages.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'description' => 'Whether this speaker data may be used for AI training. Default: no.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

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
    }
}
