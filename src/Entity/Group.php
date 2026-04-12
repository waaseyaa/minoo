<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Group extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;

    protected string $entityTypeId = 'group';

    protected array $entityKeys = [
        'id' => 'gid',
        'uuid' => 'uuid',
        'label' => 'name',
        'bundle' => 'type',
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
        if (!array_key_exists('copyright_status', $values)) {
            $values['copyright_status'] = 'unknown';
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
