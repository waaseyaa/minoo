<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Comment extends ContentEntityBase
{
    protected string $entityTypeId = 'comment';

    protected array $entityKeys = [
        'id' => 'cid',
        'uuid' => 'uuid',
        'label' => 'body',
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

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
