<?php

declare(strict_types=1);

namespace App\Entity\ElderSupport;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * A request for elder support, triaged by community coordinators (#801).
 *
 * Restored against the dormant `elder_support_request` table left by the
 * language-platform slimming. Requests hold personal contact details, so the
 * access policy keeps them coordinator/admin-only to read; the workflow stays
 * authenticated + gated (not surfaced in public nav) until a staffed
 * coordinator program is in place.
 */
final class ElderSupportRequest extends ContentEntityBase
{
    protected string $entityTypeId = 'elder_support_request';

    protected array $entityKeys = [
        'id' => 'esrid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 'open';
        }
        if (!array_key_exists('assigned_to', $values)) {
            $values['assigned_to'] = null;
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
