<?php

declare(strict_types=1);

namespace App\Access\ElderSupport;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Elder-support requests carry personal contact details (#801). Anyone signed
 * in may create one; only community coordinators and admins may read or triage
 * them. There is no public view.
 */
#[PolicyAttribute(entityType: ['elder_support_request'])]
final class ElderSupportAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'elder_support_request';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        if (in_array($operation, ['view', 'update'], true)
            && in_array('elder_coordinator', $account->getRoles(), true)) {
            return AccessResult::allowed('Coordinator can triage elder-support requests.');
        }

        return AccessResult::forbidden('Elder-support requests are coordinator/admin only.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Signed-in members may submit a request.');
        }

        return AccessResult::forbidden('Sign in to request elder support.');
    }
}
