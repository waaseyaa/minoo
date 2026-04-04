<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Event extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;

    protected string $entityTypeId = 'event';

    protected array $entityKeys = [
        'id' => 'eid',
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
