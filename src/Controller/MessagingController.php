<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\SSR\SsrResponse;

final class MessagingController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function indexThreads(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $participantStorage = $this->participantStorage();
        $threadStorage = $this->threadStorage();
        $messageStorage = $this->messageStorage();

        $participantRows = $this->participantsForUser($participantStorage, (int) $account->id());
        if ($participantRows === []) {
            return $this->json(['threads' => []]);
        }

        $threads = [];
        foreach ($participantRows as $participant) {
            $threadId = (int) $participant->get('thread_id');
            $thread = $threadStorage->load($threadId);
            if ($thread === null) {
                continue;
            }
            $threads[$threadId] = $thread;
        }

        usort($threads, static fn(EntityInterface $a, EntityInterface $b): int => ((int) $b->get('updated_at')) <=> ((int) $a->get('updated_at')));

        $limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $threads = array_slice(array_values($threads), $offset, $limit);

        $payload = [];
        foreach ($threads as $thread) {
            $threadId = (int) $thread->id();
            $latestMessage = $this->latestMessageForThread($messageStorage, $threadId);
            $payload[] = [
                'id' => $threadId,
                'title' => (string) $thread->get('title'),
                'created_by' => (int) $thread->get('created_by'),
                'updated_at' => (int) $thread->get('updated_at'),
                'last_message' => $latestMessage,
            ];
        }

        return $this->json(['threads' => $payload]);
    }

    public function createThread(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $participantIds = $this->normalizeParticipantIds($data['participant_ids'] ?? []);
        $creatorId = (int) $account->id();

        if (!in_array($creatorId, $participantIds, true)) {
            $participantIds[] = $creatorId;
        }

        if (count($participantIds) < 2) {
            return $this->json(['error' => 'At least 2 participants are required'], 422);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $now = time();

        $threadStorage = $this->threadStorage();
        $participantStorage = $this->participantStorage();
        $messageStorage = $this->messageStorage();

        try {
            $thread = $threadStorage->create([
                'title' => $title,
                'created_by' => $creatorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $threadStorage->save($thread);

            foreach ($participantIds as $participantId) {
                $participant = $participantStorage->create([
                    'thread_id' => (int) $thread->id(),
                    'user_id' => $participantId,
                    'thread_creator_id' => $creatorId,
                    'role' => $participantId === $creatorId ? 'owner' : 'member',
                    'joined_at' => $now,
                    'last_read_at' => $participantId === $creatorId ? $now : 0,
                ]);
                $participantStorage->save($participant);
            }

            if ($body !== '') {
                $message = $messageStorage->create([
                    'thread_id' => (int) $thread->id(),
                    'sender_id' => $creatorId,
                    'body' => $body,
                    'created_at' => $now,
                ]);
                $messageStorage->save($message);
            }
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid thread payload'], 422);
        }

        return $this->json(['id' => (int) $thread->id()], 201);
    }

    public function showThread(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $threadId = (int) ($params['id'] ?? 0);
        if (!$this->isParticipant($threadId, (int) $account->id())) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $thread = $this->threadStorage()->load($threadId);
        if ($thread === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $participants = $this->participantsForThread($threadId);
        $participantPayload = array_map(static fn(EntityInterface $participant): array => [
            'user_id' => (int) $participant->get('user_id'),
            'role' => (string) $participant->get('role'),
            'joined_at' => (int) $participant->get('joined_at'),
            'last_read_at' => (int) $participant->get('last_read_at'),
        ], $participants);

        return $this->json([
            'thread' => [
                'id' => (int) $thread->id(),
                'title' => (string) $thread->get('title'),
                'created_by' => (int) $thread->get('created_by'),
                'created_at' => (int) $thread->get('created_at'),
                'updated_at' => (int) $thread->get('updated_at'),
            ],
            'participants' => $participantPayload,
        ]);
    }

    public function indexMessages(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $threadId = (int) ($params['id'] ?? 0);
        if (!$this->isParticipant($threadId, (int) $account->id())) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
        $offset = max(0, (int) ($query['offset'] ?? 0));

        $storage = $this->messageStorage();
        $ids = $storage->getQuery()
            ->condition('thread_id', $threadId)
            ->sort('created_at', 'ASC')
            ->range($offset, $limit)
            ->execute();

        $messages = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $payload = array_map(static fn(EntityInterface $message): array => [
            'id' => (int) $message->id(),
            'thread_id' => (int) $message->get('thread_id'),
            'sender_id' => (int) $message->get('sender_id'),
            'body' => (string) $message->get('body'),
            'created_at' => (int) $message->get('created_at'),
        ], $messages);

        return $this->json(['messages' => $payload]);
    }

    public function createMessage(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $threadId = (int) ($params['id'] ?? 0);
        $userId = (int) $account->id();

        if (!$this->isParticipant($threadId, $userId)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $data = $this->jsonBody($request);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            return $this->json(['error' => 'Body must be 1-2000 characters'], 422);
        }

        $storage = $this->messageStorage();
        $threadStorage = $this->threadStorage();
        $now = time();

        try {
            $message = $storage->create([
                'thread_id' => $threadId,
                'sender_id' => $userId,
                'body' => $body,
                'created_at' => $now,
            ]);
            $storage->save($message);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid message payload'], 422);
        }

        $thread = $threadStorage->load($threadId);
        if ($thread !== null) {
            $thread->set('updated_at', $now);
            // All participants can add messages; thread activity should reflect the latest message.
            $threadStorage->save($thread);
        }

        return $this->json([
            'id' => (int) $message->id(),
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'body' => $body,
            'created_at' => $now,
        ], 201);
    }

    public function addParticipants(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $threadId = (int) ($params['id'] ?? 0);
        if (!$this->isThreadOwner($threadId, (int) $account->id(), $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $data = $this->jsonBody($request);
        $participantIds = $this->normalizeParticipantIds($data['participant_ids'] ?? []);
        if ($participantIds === []) {
            return $this->json(['error' => 'participant_ids is required'], 422);
        }

        $threadStorage = $this->threadStorage();
        $thread = $threadStorage->load($threadId);
        if ($thread === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $threadCreatorId = (int) $thread->get('created_by');

        $participantStorage = $this->participantStorage();
        $existing = $this->participantsForThread($threadId);
        $existingUserIds = [];
        foreach ($existing as $participant) {
            $existingUserIds[(int) $participant->get('user_id')] = true;
        }

        $added = [];
        foreach ($participantIds as $participantId) {
            if (isset($existingUserIds[$participantId])) {
                continue;
            }

            $entity = $participantStorage->create([
                'thread_id' => $threadId,
                'user_id' => $participantId,
                'thread_creator_id' => $threadCreatorId,
                'role' => 'member',
                'joined_at' => time(),
                'last_read_at' => 0,
            ]);
            $participantStorage->save($entity);
            $added[] = $participantId;
        }

        return $this->json(['added_participant_ids' => $added], 201);
    }

    public function removeParticipant(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $threadId = (int) ($params['id'] ?? 0);
        $targetUserId = (int) ($params['user_id'] ?? 0);
        $actorUserId = (int) $account->id();

        if (
            !$this->isThreadOwner($threadId, $actorUserId, $account)
            && $targetUserId !== $actorUserId
            && !$account->hasPermission('administer content')
        ) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $participantStorage = $this->participantStorage();
        $ids = $participantStorage->getQuery()
            ->condition('thread_id', $threadId)
            ->condition('user_id', $targetUserId)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return $this->json(['error' => 'Participant not found'], 404);
        }

        $participant = $participantStorage->load((int) reset($ids));
        if ($participant === null) {
            return $this->json(['error' => 'Participant not found'], 404);
        }

        $participantStorage->delete([$participant]);

        return $this->json(['removed' => true]);
    }

    public function searchUsers(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $term = mb_strtolower(trim((string) ($query['q'] ?? '')));
        if ($term === '') {
            return $this->json(['users' => []]);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->sort('uid', 'DESC')
            ->range(0, 500)
            ->execute();

        $users = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
        $matches = [];

        foreach ($users as $user) {
            $uid = (int) $user->id();
            if ($uid === (int) $account->id()) {
                continue;
            }

            $name = (string) ($user->get('name') ?? '');
            $email = (string) ($user->get('mail') ?? '');
            $haystack = mb_strtolower(trim($name . ' ' . $email));
            if ($term !== '' && !str_contains($haystack, $term)) {
                continue;
            }

            $matches[] = ['id' => $uid, 'name' => $name];
            if (count($matches) >= 20) {
                break;
            }
        }

        return $this->json(['users' => $matches]);
    }

    /** @return array<string, mixed> */
    private function jsonBody(HttpRequest $request): array
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return [];
        }

        try {
            return (array) json_decode((string) $content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<int, mixed> $raw */
    /** @return list<int> */
    private function normalizeParticipantIds(array $raw): array
    {
        $participantIds = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id <= 0) {
                continue;
            }
            $participantIds[$id] = $id;
        }

        return array_values($participantIds);
    }

    private function isParticipant(int $threadId, int $userId): bool
    {
        if ($threadId <= 0 || $userId <= 0) {
            return false;
        }

        $ids = $this->participantStorage()->getQuery()
            ->condition('thread_id', $threadId)
            ->condition('user_id', $userId)
            ->range(0, 1)
            ->execute();

        return $ids !== [];
    }

    private function isThreadOwner(int $threadId, int $userId, AccountInterface $account): bool
    {
        if ($account->hasPermission('administer content')) {
            return true;
        }

        $participantRows = $this->participantsForUser($this->participantStorage(), $userId);
        foreach ($participantRows as $participant) {
            if ((int) $participant->get('thread_id') === $threadId && (string) $participant->get('role') === 'owner') {
                return true;
            }
        }

        return false;
    }

    /** @return list<EntityInterface> */
    private function participantsForThread(int $threadId): array
    {
        $storage = $this->participantStorage();
        $ids = $storage->getQuery()
            ->condition('thread_id', $threadId)
            ->sort('joined_at', 'ASC')
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    /** @return list<EntityInterface> */
    private function participantsForUser(EntityStorageInterface $participantStorage, int $userId): array
    {
        $ids = $participantStorage->getQuery()
            ->condition('user_id', $userId)
            ->sort('joined_at', 'ASC')
            ->execute();

        return $ids !== [] ? array_values($participantStorage->loadMultiple($ids)) : [];
    }

    /** @return array<string, mixed>|null */
    private function latestMessageForThread(EntityStorageInterface $messageStorage, int $threadId): ?array
    {
        $ids = $messageStorage->getQuery()
            ->condition('thread_id', $threadId)
            ->sort('created_at', 'DESC')
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        $message = $messageStorage->load((int) reset($ids));
        if ($message === null) {
            return null;
        }

        return [
            'id' => (int) $message->id(),
            'sender_id' => (int) $message->get('sender_id'),
            'body' => (string) $message->get('body'),
            'created_at' => (int) $message->get('created_at'),
        ];
    }

    private function threadStorage(): EntityStorageInterface
    {
        return $this->entityTypeManager->getStorage('message_thread');
    }

    private function participantStorage(): EntityStorageInterface
    {
        return $this->entityTypeManager->getStorage('thread_participant');
    }

    private function messageStorage(): EntityStorageInterface
    {
        return $this->entityTypeManager->getStorage('thread_message');
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
