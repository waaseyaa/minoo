<?php

declare(strict_types=1);

namespace App\Access\Account;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * A member's saved words are private to them (#806). Owners may view/update/
 * delete their own; anyone signed in may create. Admins may view for support.
 */
#[PolicyAttribute(entityType: ['saved_word'])]
final class SavedWordAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'saved_word';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content') && $operation === 'view') {
            return AccessResult::allowed('Admin support view.');
        }

        $ownerId = $entity->get('user_id');
        if ($account->isAuthenticated() && $ownerId !== null && (int) $ownerId === (int) $account->id()) {
            return AccessResult::allowed('Owner of the saved word.');
        }

        return AccessResult::forbidden('Saved words are private to their owner.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Signed-in members may save words.');
        }

        return AccessResult::forbidden('Sign in to save words.');
    }
}
