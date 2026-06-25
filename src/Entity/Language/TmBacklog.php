<?php

declare(strict_types=1);

namespace App\Entity\Language;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * A translation-backlog entry: one English surface string awaiting an
 * Anishinaabemowin translation, with its cross-site demand signal (issue #906).
 *
 * This is the ungated, English-only worklist seeded from the public website
 * recon (provenance `seed-crawl-2026-06-25`). It is distinct from its two
 * neighbours on purpose: {@see TranslationMemory} *holds* an actual translation
 * keyed on a community `language_tag`, and {@see TmGapLog} is shaped for organic
 * runtime lookup misses (`lookup_type`, `best_fuzzy_score`). Neither cleanly
 * holds "English demand awaiting translation", so this is its own lightweight
 * type. A backlog row graduates INTO `translation_memory` once a speaker
 * supplies the translation for its `target_tag`.
 *
 * `status` is the lifecycle string `awaiting_translation` (later: `translated`),
 * so the LanguageAccessPolicy integer-status-1 view gate never opens it - the
 * backlog stays admin-only, the same mechanism that keeps tm_gap_log admin-only.
 * It is never exposed on /api/lang or any public route.
 */
final class TmBacklog extends ContentEntityBase
{
    protected string $entityTypeId = 'tm_backlog';

    protected array $entityKeys = [
        'id' => 'tbid',
        'uuid' => 'uuid',
        'label' => 'english_text',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $defaults = [
            'target_tag' => 'oj-x-sagamok',
            'status' => 'awaiting_translation',
            'demand_sites' => 0,
            'demand_total' => 0,
            'created_at' => 0,
            'updated_at' => 0,
        ];
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $values)) {
                $values[$key] = $value;
            }
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
