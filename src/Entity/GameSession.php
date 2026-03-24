<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class GameSession extends ContentEntityBase
{
    protected string $entityTypeId = 'game_session';

    protected array $entityKeys = [
        'id' => 'gsid',
        'uuid' => 'uuid',
        'label' => 'mode',
    ];

    private const VALID_MODES = ['daily', 'practice', 'streak'];
    private const VALID_DIRECTIONS = ['ojibwe_to_english', 'english_to_ojibwe'];
    private const VALID_STATUSES = ['in_progress', 'won', 'lost'];
    private const VALID_TIERS = ['easy', 'medium', 'hard'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['mode', 'direction', 'dictionary_entry_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!in_array($values['mode'], self::VALID_MODES, true)) {
            throw new \InvalidArgumentException("Invalid mode: {$values['mode']}");
        }
        if (!in_array($values['direction'], self::VALID_DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid direction: {$values['direction']}");
        }
        if (isset($values['status']) && !in_array($values['status'], self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$values['status']}");
        }
        if (isset($values['difficulty_tier']) && !in_array($values['difficulty_tier'], self::VALID_TIERS, true)) {
            throw new \InvalidArgumentException("Invalid difficulty_tier: {$values['difficulty_tier']}");
        }

        if (!array_key_exists('user_id', $values)) {
            $values['user_id'] = null;
        }
        if (!array_key_exists('guesses', $values)) {
            $values['guesses'] = '[]';
        }
        if (!array_key_exists('wrong_count', $values)) {
            $values['wrong_count'] = 0;
        }
        if (!array_key_exists('status', $values)) {
            $values['status'] = 'in_progress';
        }
        if (!array_key_exists('daily_date', $values)) {
            $values['daily_date'] = null;
        }
        if (!array_key_exists('difficulty_tier', $values)) {
            $values['difficulty_tier'] = 'easy';
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
