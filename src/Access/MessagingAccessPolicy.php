<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: ['message_thread', 'thread_participant', 'thread_message'])]
final class MessagingAccessPolicy implements AccessPolicyInterface
{
    /** @var list<string> */
    private const TYPES = ['message_thread', 'thread_participant', 'thread_message'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::neutral('Messaging visibility is participant-scoped in controller checks.'),
            'delete' => $this->deleteAccess($entity, $account),
            'update' => $this->ownerCheck($entity, $account),
            default => AccessResult::neutral('Operation not explicitly granted by messaging policy.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated users may create messaging entities.');
        }

        return AccessResult::neutral('Anonymous users cannot create messaging entities.');
    }

    private function deleteAccess(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        if ($entity->getEntityTypeId() === 'thread_participant') {
            return $this->threadParticipantDeleteAccess($entity, $account);
        }

        return $this->ownerCheck($entity, $account);
    }

    /**
     * Thread creators may remove any participant row; users may delete their own row (leave).
     * `thread_creator_id` is denormalized from `message_thread.created_by` at join time.
     */
    private function threadParticipantDeleteAccess(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        $userId = $entity->get('user_id');
        if ($userId !== null && (int) $userId === (int) $account->id()) {
            return AccessResult::allowed('Participant may remove own membership.');
        }

        $threadCreatorId = $entity->get('thread_creator_id');
        if ($threadCreatorId !== null && (int) $threadCreatorId === (int) $account->id()) {
            return AccessResult::allowed('Thread creator may remove participants.');
        }

        return AccessResult::neutral('Only thread creator or the participant may delete this membership.');
    }

    private function ownerCheck(EntityInterface $entity, AccountInterface $account): AccessResult
    {
        $type = $entity->getEntityTypeId();

        $ownerField = match ($type) {
            'message_thread' => 'created_by',
            'thread_participant' => 'user_id',
            'thread_message' => 'sender_id',
            default => null,
        };

        if ($ownerField === null) {
            return AccessResult::neutral('Entity type does not define an ownership field.');
        }

        $ownerId = $entity->get($ownerField);
        if ($ownerId !== null && (int) $ownerId === (int) $account->id()) {
            return AccessResult::allowed('Owner may perform this operation.');
        }

        return AccessResult::neutral('Only owner may perform this operation.');
    }
}
