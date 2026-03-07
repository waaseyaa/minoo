<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'resource_person')]
final class PeopleAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'resource_person';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => (int) $entity->get('status') === 1 && $account->hasPermission('access content')
                ? AccessResult::allowed('Published and user has access content.')
                : AccessResult::neutral('Cannot view unpublished people content.'),
            default => AccessResult::neutral('Non-admin cannot modify people content.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create people content.');
    }
}
