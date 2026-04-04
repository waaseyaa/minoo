<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class OralHistory extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;

    protected string $entityTypeId = 'oral_history';

    protected array $entityKeys = [
        'id' => 'ohid',
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
        if (!array_key_exists('protocol_level', $values)) {
            $values['protocol_level'] = 'open';
        }
        if (!array_key_exists('is_living_record', $values)) {
            $values['is_living_record'] = 0;
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
