<?php
declare(strict_types=1);
namespace Minoo\Entity;
use Waaseyaa\Entity\ConfigEntityBase;

final class FeaturedItem extends ConfigEntityBase
{
    protected string $entityTypeId = 'featured_item';
    protected array $entityKeys = ['id' => 'fid', 'label' => 'headline'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('weight', $values)) { $values['weight'] = 0; }
        if (!array_key_exists('status', $values)) { $values['status'] = 1; }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
