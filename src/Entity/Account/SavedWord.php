<?php

declare(strict_types=1);

namespace App\Entity\Account;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * A dictionary entry a member has saved to their personal word list (#806).
 * Stores the owning user_id + dictionary_entry_id plus a denormalised snapshot
 * (word/slug/definition) so "My words" renders without reloading every entry.
 */
final class SavedWord extends ContentEntityBase
{
    protected string $entityTypeId = 'saved_word';

    protected array $entityKeys = [
        'id' => 'swid',
        'uuid' => 'uuid',
        'label' => 'word',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
