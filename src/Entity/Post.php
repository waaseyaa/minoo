<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\Community\HasCommunityInterface;
use Waaseyaa\Entity\Community\HasCommunityTrait;
use Waaseyaa\Entity\ContentEntityBase;

final class Post extends ContentEntityBase implements HasCommunityInterface
{
    use HasCommunityTrait;

    protected string $entityTypeId = 'post';

    protected array $entityKeys = [
        'id' => 'pid',
        'uuid' => 'uuid',
        'label' => 'body',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['user_id', 'body', 'community_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('images', $values)) {
            $values['images'] = '[]';
        }
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
