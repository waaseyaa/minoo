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

    public const VALID_GAME_TYPES = ['shkoda', 'crossword', 'matcher'];
    private const VALID_MODES = ['daily', 'practice', 'streak', 'themed'];
    private const VALID_DIRECTIONS = ['ojibwe_to_english', 'english_to_ojibwe'];
    private const VALID_STATUSES = ['in_progress', 'won', 'lost', 'completed', 'abandoned'];
    private const VALID_TIERS = ['easy', 'medium', 'hard'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('game_type', $values)) {
            $values['game_type'] = 'shkoda';
        }

        if (!in_array($values['game_type'], self::VALID_GAME_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid game_type: {$values['game_type']}");
        }

        if (!isset($values['mode'])) {
            throw new \InvalidArgumentException('Missing required field: mode');
        }

        if ($values['game_type'] === 'shkoda') {
            foreach (['direction', 'dictionary_entry_id'] as $field) {
                if (!isset($values[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }
        }

        if (!in_array($values['mode'], self::VALID_MODES, true)) {
            throw new \InvalidArgumentException("Invalid mode: {$values['mode']}");
        }
        if (isset($values['direction']) && !in_array($values['direction'], self::VALID_DIRECTIONS, true)) {
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
        if (!array_key_exists('puzzle_id', $values)) {
            $values['puzzle_id'] = null;
        }
        if (!array_key_exists('grid_state', $values)) {
            $values['grid_state'] = null;
        }
        if (!array_key_exists('hints_used', $values)) {
            $values['hints_used'] = 0;
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
