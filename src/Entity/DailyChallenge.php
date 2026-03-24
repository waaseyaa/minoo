<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class DailyChallenge extends ConfigEntityBase
{
    protected string $entityTypeId = 'daily_challenge';

    protected array $entityKeys = ['id' => 'date', 'label' => 'date'];

    private const VALID_DIRECTIONS = ['ojibwe_to_english', 'english_to_ojibwe'];
    private const VALID_TIERS = ['easy', 'medium', 'hard'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['date', 'dictionary_entry_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!array_key_exists('direction', $values)) {
            $values['direction'] = 'english_to_ojibwe';
        }
        if (!in_array($values['direction'], self::VALID_DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid direction: {$values['direction']}");
        }

        if (!array_key_exists('difficulty_tier', $values)) {
            $values['difficulty_tier'] = 'easy';
        }
        if (!in_array($values['difficulty_tier'], self::VALID_TIERS, true)) {
            throw new \InvalidArgumentException("Invalid difficulty_tier: {$values['difficulty_tier']}");
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
