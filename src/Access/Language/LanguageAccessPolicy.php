<?php

declare(strict_types=1);

namespace App\Access\Language;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region', 'speaker', 'translation_memory', 'tm_gap_log'])]
final class LanguageAccessPolicy implements AccessPolicyInterface
{
    // translation_memory is published + consent-gated like the other content
    // (public when status is 1 and consent_public is on). tm_gap_log carries a
    // lifecycle string status (open|queued_for_speaker|resolved), so the integer
    // status-1 view gate below never opens it: gap data stays admin-only.
    private const ENTITY_TYPES = ['dictionary_entry', 'example_sentence', 'word_part', 'dialect_region', 'speaker', 'translation_memory', 'tm_gap_log'];

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
            'view' => (int) $entity->get('status') === 1 && $this->hasPublicConsent($entity)
                ? AccessResult::allowed('Published content with public consent is viewable.')
                : AccessResult::neutral('Cannot view unpublished or consent-gated language content.'),
            default => AccessResult::neutral('Non-admin cannot modify language content.'),
        };
    }

    /**
     * Consent gate: a row whose consent flag is explicitly 0 is never
     * publicly viewable, independent of its published status. Rows from
     * before the consent fields existed read null and stay viewable.
     */
    private function hasPublicConsent(EntityInterface $entity): bool
    {
        $flag = $entity->getEntityTypeId() === 'speaker'
            ? $entity->get('consent_public_display')
            : $entity->get('consent_public');

        return $flag === null || (int) $flag === 1;
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return AccessResult::neutral('Non-admin cannot create language content.');
    }
}
