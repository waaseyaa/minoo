<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'post')]
final class PostAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'post';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::allowed('Posts are publicly viewable.'),
            'update' => $this->ownerCheck($entity, $account),
            'delete' => $this->deleteCheck($entity, $account),
            default => AccessResult::neutral('Non-admin cannot perform this operation on posts.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated users may create posts.');
        }

        return AccessResult::neutral('Anonymous users cannot create posts.');
    }

    private function ownerCheck(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        $userId = $entity->get('user_id');

        if ($userId !== null && (int) $userId === (int) $account->id()) {
            return AccessResult::allowed('Owner may edit own post.');
        }

        return AccessResult::neutral('Non-owner cannot edit post.');
    }

    private function deleteCheck(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        $userId = $entity->get('user_id');

        if ($userId !== null && (int) $userId === (int) $account->id()) {
            return AccessResult::allowed('Owner may delete own post.');
        }

        if (in_array('coordinator', $account->getRoles(), true)) {
            return AccessResult::allowed('Coordinator may delete posts.');
        }

        return AccessResult::neutral('Non-owner cannot delete post.');
    }
}
