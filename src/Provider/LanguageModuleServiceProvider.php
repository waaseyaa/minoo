<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Language\TmGapLog;
use App\Entity\Language\TranslationMemory;
use Waaseyaa\Entity\EntityType;

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
        $this->entityType(new EntityType(
            id: 'translation_memory',
            label: 'Translation Memory',
            class: TranslationMemory::class,
            keys: ['id' => 'tmid', 'uuid' => 'uuid', 'label' => 'source_en'],
            group: 'language',
            _fieldDefinitions: [
                'source_en' => ['type' => 'string', 'label' => 'English Source', 'description' => 'The normalized English source string.', 'weight' => 0],
                'source_hash' => ['type' => 'string', 'label' => 'Source Hash', 'description' => 'Hash of the normalized source for exact lookup.', 'weight' => 1],
                'dialect_code' => ['type' => 'string', 'label' => 'Dialect Code', 'description' => 'References dialect_region.code; null means dialect-agnostic.', 'weight' => 2],
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
                'dialect_code' => ['type' => 'string', 'label' => 'Dialect Code', 'description' => 'Requested dialect (references dialect_region.code), nullable.', 'weight' => 2],
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
    }
}
