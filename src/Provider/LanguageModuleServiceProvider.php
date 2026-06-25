<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Language\TmBacklog;
use App\Entity\Language\TmGapLog;
use App\Entity\Language\TranslationMemory;
use App\Language\Asr\AsrClient;
use App\Language\Asr\UnavailableAsrClient;
use App\Language\DialectCodeProvider;
use App\Language\TranslationMemoryService;
use App\Seed\TranslationBacklogSeeder;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The Anokii language module's wiring (issue #890), following the config-gated
 * module-provider seam (the package CoIntelligenceServiceProvider pattern).
 *
 * This is the self-contained home for the language capability: it owns the
 * module's data model and, in later milestone issues, its services
 * (DialectCodeProvider, the translation-memory lookup, the gated AsrClient) and
 * its public surface (the /api/lang routes, mounted gated on
 * DistributionConfig::moduleEnabled('language')). Keeping it as one provider
 * makes the eventual extraction to a waaseyaa/anokii-language package cheap.
 *
 * Entities are registered unconditionally (like the package CoIntelligence
 * provider registers its graph) so the schema is consistent regardless of the
 * module flag; only the public surfaces get gated. The :memory: test kernel
 * auto-creates these tables from the entity definitions; production gets them
 * from migrations/20260624_120000_create_translation_memory_tables.php.
 */
final class LanguageModuleServiceProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // The single seam for valid dialect codes. Bound here so the module's
        // services and (issue 6) the /api/lang dialect parameter resolve one
        // contract; the backing can move to a taxonomy package later without
        // touching callers.
        $this->singleton(DialectCodeProvider::class, static fn (): DialectCodeProvider => new DialectCodeProvider());

        // The translation-memory lookup (exact -> fuzzy -> gap-log), resolved by
        // the /api/lang controller. Reads are consent-gated at the entity layer.
        $this->singleton(
            TranslationMemoryService::class,
            fn (): TranslationMemoryService => new TranslationMemoryService(
                $this->resolve(EntityTypeManager::class),
                $this->resolve(DialectCodeProvider::class),
            ),
        );

        // The ASR seam to the separate Python/GPU worker. Fail-closed by default:
        // no transcription until the Phase 0 consent agreement exists and a real
        // worker-backed client replaces this binding. No public ASR surface (D8).
        $this->singleton(AsrClient::class, static fn (): AsrClient => new UnavailableAsrClient());

        // The idempotent backlog seeder (#906): upserts the committed
        // demand-ranked English seed into tm_backlog. Used by the dev console
        // command and the prod reflection script.
        $this->singleton(
            TranslationBacklogSeeder::class,
            fn (): TranslationBacklogSeeder => new TranslationBacklogSeeder($this->resolve(EntityTypeManager::class)),
        );

        $this->entityType(new EntityType(
            id: 'translation_memory',
            label: 'Translation Memory',
            class: TranslationMemory::class,
            keys: ['id' => 'tmid', 'uuid' => 'uuid', 'label' => 'source_en'],
            group: 'language',
            _fieldDefinitions: [
                'source_en' => ['type' => 'string', 'label' => 'English Source', 'description' => 'The normalized English source string.', 'weight' => 0],
                'source_hash' => ['type' => 'string', 'label' => 'Source Hash', 'description' => 'Hash of the normalized source for exact lookup.', 'weight' => 1],
                'language_tag' => ['type' => 'string', 'label' => 'Language Tag', 'description' => 'Full BCP 47 tag, e.g. oj-x-sagamok. Community granularity is retained here; the dialect grouping is derived, never stored. Empty or oj means tag-agnostic.', 'weight' => 2],
                'translation' => ['type' => 'string', 'label' => 'Translation', 'description' => 'Anishinaabemowin translation, stored plain (not JSON-wrapped).', 'weight' => 5],
                'confidence' => ['type' => 'integer', 'label' => 'Confidence', 'description' => 'Confidence score 0 to 100.', 'weight' => 6, 'default' => 0],
                'needs_speaker_review' => ['type' => 'boolean', 'label' => 'Needs Speaker Review', 'weight' => 7, 'default' => 1],
                'match_origin' => ['type' => 'string', 'label' => 'Match Origin', 'description' => 'How the row was created: seed|imported|speaker|fuzzy_promoted.', 'weight' => 8],
                'speaker_id' => ['type' => 'entity_reference', 'label' => 'Speaker', 'settings' => ['target_type' => 'speaker'], 'weight' => 10],
                'contributor_id' => ['type' => 'entity_reference', 'label' => 'Contributor', 'settings' => ['target_type' => 'contributor'], 'weight' => 11],
                'attribution_source' => ['type' => 'string', 'label' => 'Attribution Source', 'weight' => 16],
                'attribution_url' => ['type' => 'uri', 'label' => 'Attribution URL', 'weight' => 17],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'description' => 'Where the English seed came from.', 'weight' => 18],
                'provenance' => ['type' => 'text', 'label' => 'Provenance', 'description' => 'Full provenance record JSON.', 'weight' => 20],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'weight' => 25, 'default' => 'oj'],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this translation may be served publicly.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'tm_gap_log',
            label: 'Translation Gap Log',
            class: TmGapLog::class,
            keys: ['id' => 'glid', 'uuid' => 'uuid', 'label' => 'source_en'],
            group: 'language',
            _fieldDefinitions: [
                'source_en' => ['type' => 'string', 'label' => 'English Source', 'description' => 'The requested English string, normalized.', 'weight' => 0],
                'source_hash' => ['type' => 'string', 'label' => 'Source Hash', 'description' => 'Dedupe key.', 'weight' => 1],
                'language_tag' => ['type' => 'string', 'label' => 'Language Tag', 'description' => 'Requested BCP 47 tag (e.g. oj-x-sagamok), empty when tag-agnostic.', 'weight' => 2],
                'lookup_type' => ['type' => 'string', 'label' => 'Lookup Type', 'description' => 'exact_miss or fuzzy_below_threshold.', 'weight' => 3],
                'best_fuzzy_score' => ['type' => 'integer', 'label' => 'Best Fuzzy Score', 'description' => 'Best similarity seen 0 to 100, nullable.', 'weight' => 4],
                'request_count' => ['type' => 'integer', 'label' => 'Request Count', 'description' => 'Incremented on repeat misses (the gap frequency).', 'weight' => 5, 'default' => 1],
                'last_requested_at' => ['type' => 'timestamp', 'label' => 'Last Requested', 'weight' => 6],
                'status' => ['type' => 'string', 'label' => 'Status', 'description' => 'open | queued_for_speaker | resolved.', 'weight' => 7, 'default' => 'open'],
                'resolved_tm_id' => ['type' => 'integer', 'label' => 'Resolved Translation', 'description' => 'The translation_memory row that closed the gap, nullable.', 'weight' => 8],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // The demand-ranked English backlog (#906): the ungated worklist seeded
        // from the public website recon. Registered unconditionally so the schema
        // is consistent; admin-only via LanguageAccessPolicy (string status never
        // satisfies the integer status-1 view gate). A row graduates into
        // translation_memory once a speaker translates it.
        $this->entityType(new EntityType(
            id: 'tm_backlog',
            label: 'Translation Backlog',
            class: TmBacklog::class,
            keys: ['id' => 'tbid', 'uuid' => 'uuid', 'label' => 'english_text'],
            group: 'language',
            _fieldDefinitions: [
                'english_text' => ['type' => 'string', 'label' => 'English Text', 'description' => 'The English surface form awaiting translation.', 'weight' => 0],
                'concept_key' => ['type' => 'string', 'label' => 'Concept Key', 'description' => 'Clustered key grouping surface variants (Contact / Contact Us).', 'weight' => 1],
                'dedupe_key' => ['type' => 'string', 'label' => 'Dedupe Key', 'description' => 'sha256 of (english_text, target_tag); the upsert uniqueness key.', 'weight' => 2],
                'demand_sites' => ['type' => 'integer', 'label' => 'Demand (distinct sites)', 'description' => 'Number of distinct sites the string appeared on (primary rank).', 'weight' => 3, 'default' => 0],
                'demand_total' => ['type' => 'integer', 'label' => 'Demand (total)', 'description' => 'Total occurrences across all sites (secondary rank).', 'weight' => 4, 'default' => 0],
                'category' => ['type' => 'string', 'label' => 'Category', 'description' => 'governance-nav | global-ui | other.', 'weight' => 5],
                'target_tag' => ['type' => 'string', 'label' => 'Target Tag', 'description' => 'First translation target (default oj-x-sagamok, Sagamok is the core).', 'weight' => 6, 'default' => 'oj-x-sagamok'],
                'status' => ['type' => 'string', 'label' => 'Status', 'description' => 'awaiting_translation | translated. String status keeps the backlog admin-only.', 'weight' => 7, 'default' => 'awaiting_translation'],
                'provenance' => ['type' => 'string', 'label' => 'Provenance', 'description' => 'Where the demand came from, e.g. seed-crawl-2026-06-25.', 'weight' => 8],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }
}
