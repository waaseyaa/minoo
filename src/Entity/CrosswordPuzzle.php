<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class CrosswordPuzzle extends ConfigEntityBase
{
    protected string $entityTypeId = 'crossword_puzzle';

    protected array $entityKeys = ['id' => 'id', 'label' => 'id'];

    private const VALID_TIERS = ['easy', 'medium', 'hard'];

    /** @param array<string, mixed> $values */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
    ) {
        foreach (['id', 'grid_size', 'words', 'clues'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('theme', $values)) {
            $values['theme'] = null;
        }
        if (!array_key_exists('difficulty_tier', $values)) {
            $values['difficulty_tier'] = 'easy';
        }

        if (!in_array($values['difficulty_tier'], self::VALID_TIERS, true)) {
            throw new \InvalidArgumentException("Invalid difficulty_tier: {$values['difficulty_tier']}");
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
        );
    }
}
