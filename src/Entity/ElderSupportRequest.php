<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class ElderSupportRequest extends ContentEntityBase
{
    protected string $entityTypeId = 'elder_support_request';

    protected array $entityKeys = [
        'id' => 'esrid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 'open';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }
        if (!array_key_exists('assigned_volunteer', $values)) {
            $values['assigned_volunteer'] = null;
        }
        if (!array_key_exists('assigned_at', $values)) {
            $values['assigned_at'] = null;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
