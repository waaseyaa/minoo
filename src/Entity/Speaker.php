<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Speaker extends ContentEntityBase
{
    protected string $entityTypeId = 'speaker';

    protected array $entityKeys = [
        'id' => 'spid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('consent_public_display', $values)) {
            $values['consent_public_display'] = 1;
        }
        if (!array_key_exists('consent_ai_training', $values)) {
            $values['consent_ai_training'] = 0;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
