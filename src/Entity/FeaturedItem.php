<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class FeaturedItem extends ContentEntityBase
{
    protected string $entityTypeId = 'featured_item';

    protected array $entityKeys = [
        'id' => 'fid',
        'uuid' => 'uuid',
        'label' => 'headline',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
