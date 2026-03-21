<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Contributor extends ContentEntityBase
{
    protected string $entityTypeId = 'contributor';

    protected array $entityKeys = [
        'id' => 'coid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('consent_public', $values)) {
            $values['consent_public'] = 0;
        }
        if (!array_key_exists('consent_record', $values)) {
            $values['consent_record'] = 0;
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
