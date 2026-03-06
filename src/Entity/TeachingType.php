<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class TeachingType extends ConfigEntityBase
{
    protected string $entityTypeId = 'teaching_type';

    protected array $entityKeys = [
        'id' => 'type',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('description', $values)) {
            $values['description'] = '';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
