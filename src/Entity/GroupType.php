<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class GroupType extends ConfigEntityBase
{
    protected string $entityTypeId = 'group_type';

    protected array $entityKeys = [
        'id' => 'type',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
    ) {
        if (!array_key_exists('description', $values)) {
            $values['description'] = '';
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
        );
    }
}
