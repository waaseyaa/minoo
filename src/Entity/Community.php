<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Community extends ContentEntityBase
{
    protected string $entityTypeId = 'community';

    protected array $entityKeys = [
        'id' => 'cid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
