<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class DailyChallenge extends ConfigEntityBase
{
    protected string $entityTypeId = 'daily_challenge';

    protected array $entityKeys = ['id' => 'date', 'label' => 'date'];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        if (!array_key_exists('direction', $values)) {
            $values['direction'] = 'english_to_ojibwe';
        }
        if (!array_key_exists('difficulty_tier', $values)) {
            $values['difficulty_tier'] = 'easy';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
