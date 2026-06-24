<?php

declare(strict_types=1);

namespace App\Entity\Language;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * A translation-memory entry: one English source string mapped to its
 * Anishinaabemowin translation, tagged with dialect and consent (#888 milestone,
 * issue #890). The lookup contract (exact then fuzzy then gap-log) and the
 * /api/lang surface that reads these land in later issues; this is the data model
 * the language module owns. `translation` is stored plain (not JSON-wrapped) to
 * avoid the dictionary_entry definition gotcha.
 */
final class TranslationMemory extends ContentEntityBase
{
    protected string $entityTypeId = 'translation_memory';

    protected array $entityKeys = [
        'id' => 'tmid',
        'uuid' => 'uuid',
        'label' => 'source_en',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $defaults = [
            'status' => 1,
            'language_code' => 'oj',
            'needs_speaker_review' => 1,
            'confidence' => 0,
            'consent_public' => 1,
            'consent_ai_training' => 0,
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
