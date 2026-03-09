<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['elder_support_request', 'volunteer'])]
final class ElderSupportAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'elder_support_request' || $entityTypeId === 'volunteer';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::allowed('Public view of specific entity by ID.'),
            'update' => $this->resolveUpdateAccess($entity, $account),
            default => AccessResult::neutral('Non-admin cannot modify elder support entities.'),
        };
    }

    private function resolveUpdateAccess(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        if (in_array('elder_coordinator', $account->getRoles(), true)) {
            return AccessResult::allowed('Coordinator can update any elder support entity.');
        }

        $assignedVolunteerId = $entity->get('assigned_volunteer');
        if ($account->id() !== 0 && $assignedVolunteerId === $account->id()) {
            return AccessResult::allowed('Assigned volunteer can update their own request.');
        }

        return AccessResult::forbidden('Not authorized to update this elder support entity.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return AccessResult::allowed('Public form submission allowed.');
    }
}
