<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['game_session', 'daily_challenge', 'crossword_puzzle'])]
final class GameAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['game_session', 'daily_challenge', 'crossword_puzzle'];

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
            'update' => $this->canModifySession($entity, $account),
            'delete' => $this->canModifySession($entity, $account),
            default => AccessResult::neutral('Cannot modify game data.'),
        };
    }

    private function canModifySession(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        if ($entity->getEntityTypeId() !== 'game_session') {
            return AccessResult::neutral('Only game sessions can be modified.');
        }

        // Anonymous sessions (user_id is null) can be updated by anyone with the token
        if ($entity->get('user_id') === null) {
            return AccessResult::allowed('Anonymous session update.');
        }

        // Authenticated users can update their own sessions
        if ($account->isAuthenticated() && (int) $entity->get('user_id') === (int) $account->id()) {
            return AccessResult::allowed('Own session.');
        }

        return AccessResult::neutral('Cannot modify other users\' sessions.');
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
