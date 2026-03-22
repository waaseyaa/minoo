<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Reaction extends ContentEntityBase
{
    protected string $entityTypeId = 'reaction';

    protected array $entityKeys = [
        'id' => 'rid',
        'uuid' => 'uuid',
        'label' => 'reaction_type',
    ];

    /** @var list<string> */
    public const ALLOWED_REACTION_TYPES = [
        'like',
        'interested',
        'recommend',
        'miigwech',
        'connect',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['user_id', 'target_type', 'target_id', 'reaction_type'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!in_array($values['reaction_type'], self::ALLOWED_REACTION_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid reaction_type '{$values['reaction_type']}'. Allowed: " . implode(', ', self::ALLOWED_REACTION_TYPES),
            );
        }

        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
