<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['newsletter_edition', 'newsletter_item', 'newsletter_submission'])]
final class NewsletterAccessPolicy implements AccessPolicyInterface
{
    private const ENTITY_TYPES = ['newsletter_edition', 'newsletter_item', 'newsletter_submission'];
    private const PUBLIC_STATUSES = ['generated', 'sent'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::ENTITY_TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        $type = $entity->getEntityTypeId();

        // Submissions: submitter sees their own, coordinator sees all in community.
        if ($type === 'newsletter_submission') {
            if ($operation === 'view' && (int) $entity->get('submitted_by') === (int) $account->id()) {
                return AccessResult::allowed('Submitter views own submission.');
            }
            if ($account->hasPermission('coordinate community')) {
                return AccessResult::allowed('Coordinator manages submissions.');
            }
            return AccessResult::neutral('Public cannot view submissions.');
        }

        // newsletter_item: inherits access from parent edition. Items are only
        // ever rendered inside an edition page; the parent edition's access
        // check is the real public/private boundary. Short-circuit view here so
        // the parent template can iterate items without per-item denials.
        if ($type === 'newsletter_item' && $operation === 'view') {
            return AccessResult::allowed('Item inherits view access from parent edition.');
        }

        // Editions: public read on generated/sent only.
        if ($operation === 'view' && in_array((string) $entity->get('status'), self::PUBLIC_STATUSES, true)) {
            return AccessResult::allowed('Published edition.');
        }

        if ($account->hasPermission('coordinate community')) {
            return AccessResult::allowed('Coordinator can manage own community newsletter.');
        }

        return AccessResult::neutral('Non-coordinator cannot modify newsletter.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin can create newsletter content.');
        }

        // Logged-in users can create submissions.
        if ($entityTypeId === 'newsletter_submission' && $account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated user can submit.');
        }

        // Coordinators can create editions and items.
        if ($account->hasPermission('coordinate community')) {
            return AccessResult::allowed('Coordinator can create newsletter content.');
        }

        return AccessResult::neutral('Non-coordinator cannot create newsletter content.');
    }
}
