<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Follow extends ContentEntityBase
{
    protected string $entityTypeId = 'follow';

    protected array $entityKeys = [
        'id' => 'fid',
        'uuid' => 'uuid',
        'label' => 'target_type',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
