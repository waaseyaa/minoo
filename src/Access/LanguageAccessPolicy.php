<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region', 'speaker'])]
final class LanguageAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region', 'speaker'];

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
            'view' => (int) $entity->get('status') === 1
                ? AccessResult::allowed('Published content is publicly viewable.')
                : AccessResult::neutral('Cannot view unpublished language content.'),
            default => AccessResult::neutral('Non-admin cannot modify language content.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create language content.');
    }
}
