<?php

declare(strict_types=1);

namespace App\Entity\Language;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * A translation-memory gap-log entry: one English string the lookup could not
 * satisfy (issue #890). Mirrors the ingest_log write pattern (a service builds
 * it, a workflow drives a status lifecycle) but is lookup-miss-scoped, not
 * materialization-scoped. Its `status` is a lifecycle string (open ->
 * queued_for_speaker -> resolved), so it is never publicly viewable: the
 * LanguageAccessPolicy view gate keys on an integer status of 1, which a string
 * status never satisfies, leaving gap data admin-only.
 */
final class TmGapLog extends ContentEntityBase
{
    protected string $entityTypeId = 'tm_gap_log';

    protected array $entityKeys = [
        'id' => 'glid',
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
            'status' => 'open',
            'request_count' => 1,
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
