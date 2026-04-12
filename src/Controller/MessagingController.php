<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Api\JsonResponseTrait;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Mercure\MercurePublisher;
use Symfony\Component\HttpFoundation\Response;

final class MessagingController
{
    use JsonResponseTrait;
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ?MercurePublisher $mercurePublisher = null,
    ) {}

    public function editMessage(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $threadId = (int) ($params['id'] ?? 0);
        $messageId = (int) ($params['message_id'] ?? 0);
        $userId = (int) $account->id();

        if (!$this->isParticipant($threadId, $userId)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage = $this->messageStorage();
        $message = $storage->load($messageId);
        if ($message === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $message->get('sender_id') !== $userId) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($message->get('deleted_at') !== null) {
            return $this->json(['error' => 'Message has been deleted'], 410);
        }

        $data = $this->jsonBody($request);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            return $this->json(['error' => 'Body must be 1-2000 characters'], 422);
        }

        $now = time();
        $message->set('body', $body);
        $message->set('edited_at', $now);
        $storage->save($message);

        $this->publishMercure("/threads/{$threadId}", [
            'type' => 'message_edited',
            'id' => $messageId,
            'body' => $body,
            'edited_at' => $now,
        ]);

        return $this->json([
            'id' => (int) $message->id(),
            'body' => $body,
            'edited_at' => $now,
        ]);
    }

    public function deleteMessage(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $threadId = (int) ($params['id'] ?? 0);
        $messageId = (int) ($params['message_id'] ?? 0);
        $userId = (int) $account->id();

        if (!$this->isParticipant($threadId, $userId)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage = $this->messageStorage();
        $message = $storage->load($messageId);
        if ($message === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $message->get('sender_id') !== $userId && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $now = time();
        $message->set('deleted_at', $now);
        $storage->save($message);

        $this->publishMercure("/threads/{$threadId}", [
            'type' => 'message_deleted',
            'id' => $messageId,
        ]);

        return $this->json(['deleted' => true]);
    }

    public function markRead(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $threadId = (int) ($params['id'] ?? 0);
        $userId = (int) $account->id();

        if (!$this->isParticipant($threadId, $userId)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $participantStorage = $this->participantStorage();
        $ids = $participantStorage->getQuery()
            ->condition('thread_id', $threadId)
            ->condition('user_id', $userId)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $participant = $participantStorage->load((int) reset($ids));
        if ($participant === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $now = time();
        $participant->set('last_read_at', $now);
        $participantStorage->save($participant);

        $this->publishMercure("/threads/{$threadId}", [
            'type' => 'read',
            'user_id' => $userId,
            'last_read_at' => $now,
        ]);

        return $this->json(['last_read_at' => $now]);
    }

    public function typing(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $threadId = (int) ($params['id'] ?? 0);
        $userId = (int) $account->id();

        if (!$this->isParticipant($threadId, $userId)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $this->publishMercure("/threads/{$threadId}", [
            'type' => 'typing',
            'user_id' => $userId,
            'display_name' => (string) ($account->get('name') ?? ''),
        ]);

        return $this->json(['typing' => true]);
    }

    public function unreadCount(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $userId = (int) $account->id();
        $participantStorage = $this->participantStorage();
        $messageStorage = $this->messageStorage();

        $participantRows = $this->participantsForUser($participantStorage, $userId);
        $totalUnread = 0;

        foreach ($participantRows as $participant) {
            $totalUnread += $this->countUnreadMessages(
                $messageStorage,
                (int) $participant->get('thread_id'),
                (int) $participant->get('last_read_at'),
                $userId,
            );
        }

        return $this->json(['unread_count' => $totalUnread]);
    }

    public function indexThreads(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

        usort($threads, static fn(EntityInterface $a, EntityInterface $b): int => ((int) $b->get('last_message_at')) <=> ((int) $a->get('last_message_at')));

        $limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $threads = array_slice(array_values($threads), $offset, $limit);

        $lastReadByThread = [];
        foreach ($participantRows as $participant) {
            $lastReadByThread[(int) $participant->get('thread_id')] = (int) $participant->get('last_read_at');
        }

        $payload = [];
        foreach ($threads as $thread) {
            $threadId = (int) $thread->id();
            $latestMessage = $this->latestMessageForThread($messageStorage, $threadId);

            $lastReadAt = $lastReadByThread[$threadId] ?? 0;
            $unreadCount = $this->countUnreadMessages($messageStorage, $threadId, $lastReadAt, (int) $account->id());

            $payload[] = [
                'id' => $threadId,
                'title' => (string) $thread->get('title'),
                'created_by' => (int) $thread->get('created_by'),
                'updated_at' => (int) $thread->get('updated_at'),
                'last_message_at' => (int) $thread->get('last_message_at'),
                'last_message' => $latestMessage,
                'unread_count' => $unreadCount,
            ];
        }

        return $this->json(['threads' => $payload]);
    }

    public function createThread(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

        $blockStorage = $this->entityTypeManager->getStorage('user_block');
        foreach ($participantIds as $participantId) {
            if ($participantId === $creatorId) {
                continue;
            }

            $blocked = $blockStorage->getQuery()
                ->condition('blocker_id', $participantId)
                ->condition('blocked_id', $creatorId)
                ->range(0, 1)
                ->execute();

            if ($blocked !== []) {
                return $this->json(['error' => 'Cannot message a user who has blocked you'], 403);
            }

            $blocking = $blockStorage->getQuery()
                ->condition('blocker_id', $creatorId)
                ->condition('blocked_id', $participantId)
                ->range(0, 1)
                ->execute();

            if ($blocking !== []) {
                return $this->json(['error' => 'Cannot message a user you have blocked'], 403);
            }
        }

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $threadType = count($participantIds) > 2 ? 'group' : 'direct';
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
                'thread_type' => $threadType,
                'last_message_at' => $now,
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

    public function showThread(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

    public function indexMessages(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

        // Batch-load reactions for all messages in this page.
        $reactionsByMessage = [];
        if ($ids !== []) {
            $reactionStorage = $this->entityTypeManager->getStorage('reaction');
            $reactionIds = $reactionStorage->getQuery()
                ->condition('target_type', 'thread_message')
                ->execute();
            if ($reactionIds !== []) {
                $reactions = $reactionStorage->loadMultiple($reactionIds);
                foreach ($reactions as $reaction) {
                    $targetId = (int) $reaction->get('target_id');
                    if (in_array($targetId, $ids, true)) {
                        $reactionsByMessage[$targetId][] = [
                            'id' => (int) $reaction->id(),
                            'user_id' => (int) $reaction->get('user_id'),
                            'reaction_type' => (string) $reaction->get('reaction_type'),
                        ];
                    }
                }
            }
        }

        $payload = array_map(static function (EntityInterface $message) use ($reactionsByMessage): array {
            $deletedAt = $message->get('deleted_at');
            $editedAt = $message->get('edited_at');
            $messageId = (int) $message->id();

            return [
                'id' => $messageId,
                'thread_id' => (int) $message->get('thread_id'),
                'sender_id' => (int) $message->get('sender_id'),
                'body' => $deletedAt !== null ? '' : (string) $message->get('body'),
                'created_at' => (int) $message->get('created_at'),
                'edited_at' => $editedAt !== null ? (int) $editedAt : null,
                'deleted_at' => $deletedAt !== null ? (int) $deletedAt : null,
                'reactions' => $reactionsByMessage[$messageId] ?? [],
            ];
        }, $messages);

        return $this->json(['messages' => $payload]);
    }

    public function createMessage(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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
            $thread->set('last_message_at', $now);
            $threadStorage->save($thread);
        }

        $this->publishMercure("/threads/{$threadId}", [
            'type' => 'message',
            'message' => [
                'id' => (int) $message->id(),
                'thread_id' => $threadId,
                'sender_id' => $userId,
                'body' => $body,
                'created_at' => $now,
            ],
        ]);

        return $this->json([
            'id' => (int) $message->id(),
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'body' => $body,
            'created_at' => $now,
        ], 201);
    }

    public function addParticipants(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

    public function removeParticipant(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

    public function searchUsers(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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

    public function searchMessages(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $term = trim((string) ($query['q'] ?? ''));
        if ($term === '' || mb_strlen($term) < 2) {
            return $this->json(['results' => []]);
        }

        $userId = (int) $account->id();
        $participantStorage = $this->participantStorage();
        $participantRows = $this->participantsForUser($participantStorage, $userId);

        if ($participantRows === []) {
            return $this->json(['results' => []]);
        }

        $threadIds = [];
        foreach ($participantRows as $participant) {
            $threadIds[] = (int) $participant->get('thread_id');
        }

        // Search messages across all user's threads using LIKE on body column.
        $messageStorage = $this->messageStorage();
        $likeTerm = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $allMatches = [];

        foreach ($threadIds as $threadId) {
            $ids = $messageStorage->getQuery()
                ->condition('thread_id', $threadId)
                ->condition('body', $likeTerm, 'LIKE')
                ->sort('created_at', 'DESC')
                ->range(0, 10)
                ->execute();

            if ($ids === []) {
                continue;
            }

            $messages = array_values($messageStorage->loadMultiple($ids));
            foreach ($messages as $message) {
                if ($message->get('deleted_at') !== null) {
                    continue;
                }
                $allMatches[] = [
                    'thread_id' => $threadId,
                    'message_id' => (int) $message->id(),
                    'sender_id' => (int) $message->get('sender_id'),
                    'body' => (string) $message->get('body'),
                    'created_at' => (int) $message->get('created_at'),
                ];
            }
        }

        // Sort by recency and limit.
        usort($allMatches, static fn(array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        $allMatches = array_slice($allMatches, 0, 30);

        // Group by thread.
        $grouped = [];
        $threadStorage = $this->threadStorage();
        foreach ($allMatches as $match) {
            $tid = $match['thread_id'];
            if (!isset($grouped[$tid])) {
                $thread = $threadStorage->load($tid);
                $grouped[$tid] = [
                    'thread_id' => $tid,
                    'thread_title' => $thread !== null ? (string) $thread->get('title') : '',
                    'messages' => [],
                ];
            }
            $grouped[$tid]['messages'][] = $match;
        }

        return $this->json(['results' => array_values($grouped)]);
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

    private function countUnreadMessages(EntityStorageInterface $messageStorage, int $threadId, int $lastReadAt, int $userId): int
    {
        $ids = $messageStorage->getQuery()
            ->condition('thread_id', $threadId)
            ->sort('created_at', 'DESC')
            ->range(0, 100)
            ->execute();

        if ($ids === []) {
            return 0;
        }

        $messages = array_values($messageStorage->loadMultiple($ids));
        $count = 0;
        foreach ($messages as $msg) {
            if ((int) $msg->get('created_at') > $lastReadAt && (int) $msg->get('sender_id') !== $userId) {
                ++$count;
            }
        }

        return $count;
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
    private function publishMercure(string $topic, array $data): void
    {
        $this->mercurePublisher?->publish($topic, $data);
    }

}
