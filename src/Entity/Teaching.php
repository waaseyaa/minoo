<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Teaching extends ContentEntityBase
{
    protected string $entityTypeId = 'teaching';

    protected array $entityKeys = [
        'id' => 'tid',
        'uuid' => 'uuid',
        'label' => 'title',
        'bundle' => 'type',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }
        if (!array_key_exists('copyright_status', $values)) {
            $values['copyright_status'] = 'unknown';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
