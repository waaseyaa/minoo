<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class FeaturedItem extends ContentEntityBase
{
    protected string $entityTypeId = 'featured_item';

    protected array $entityKeys = [
        'id' => 'fid',
        'uuid' => 'uuid',
        'label' => 'headline',
    ];
}
