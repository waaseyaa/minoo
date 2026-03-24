<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['game_session', 'daily_challenge'])]
final class GameAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['game_session', 'daily_challenge'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::ENTITY_TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::allowed('Game data is publicly viewable.'),
            default => AccessResult::neutral('Cannot modify game data.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        // Anyone can create game sessions (anonymous play)
        if ($entityTypeId === 'game_session') {
            return AccessResult::allowed('Public game play.');
        }

        return AccessResult::neutral('Only admins can create daily challenges.');
    }
}
