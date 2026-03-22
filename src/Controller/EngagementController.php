<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Entity\Reaction;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class EngagementController
{
    /** @var list<string> Entity types that can be reaction/comment/follow targets */
    private const ALLOWED_TARGET_TYPES = [
        'event', 'group', 'teaching', 'community', 'post',
        'oral_history', 'dictionary_entry', 'cultural_collection',
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function react(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);

        if (!isset($data['reaction_type'], $data['target_type'], $data['target_id'])) {
            return $this->json(['error' => 'Missing required fields: reaction_type, target_type, target_id'], 422);
        }

        if (!$this->isValidTargetType($data['target_type'])) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $reactionType = trim($data['reaction_type']);
        if (!in_array($reactionType, Reaction::ALLOWED_REACTION_TYPES, true)) {
            return $this->json(['error' => 'Invalid reaction_type'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('reaction');

        try {
            $entity = $storage->create([
                'reaction_type' => $reactionType,
                'user_id' => $account->id(),
                'target_type' => $data['target_type'],
                'target_id' => (int) $data['target_id'],
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        return $this->json(['id' => $entity->id(), 'reaction_type' => $entity->get('reaction_type')], 201);
    }

    public function deleteReaction(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('reaction');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);

        return $this->json(['deleted' => true]);
    }

    public function comment(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);

        if (!isset($data['body'], $data['target_type'], $data['target_id'])) {
            return $this->json(['error' => 'Missing required fields: body, target_type, target_id'], 422);
        }

        if (!$this->isValidTargetType($data['target_type'])) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $body = trim($data['body']);
        if ($body === '' || mb_strlen($body) > 2000) {
            return $this->json(['error' => 'Body must be 1-2000 characters'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('comment');

        try {
            $entity = $storage->create([
                'body' => $body,
                'user_id' => $account->id(),
                'target_type' => $data['target_type'],
                'target_id' => (int) $data['target_id'],
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        return $this->json([
            'id' => $entity->id(),
            'body' => $entity->get('body'),
            'created_at' => $entity->get('created_at'),
        ], 201);
    }

    public function deleteComment(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('comment');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);

        return $this->json(['deleted' => true]);
    }

    public function getComments(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $targetType = $params['target_type'] ?? '';
        if (!$this->isValidTargetType($targetType)) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $targetId = (int) ($params['target_id'] ?? 0);
        $limit = min((int) ($query['limit'] ?? 20), 50);
        $offset = max((int) ($query['offset'] ?? 0), 0);

        $storage = $this->entityTypeManager->getStorage('comment');
        $ids = $storage->getQuery()
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->condition('status', 1)
            ->sort('created_at', 'DESC')
            ->range($offset, $limit)
            ->execute();

        $comments = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $items = array_map(fn($c) => [
            'id' => $c->id(),
            'body' => $c->get('body'),
            'user_id' => $c->get('user_id'),
            'created_at' => $c->get('created_at'),
        ], $comments);

        return $this->json(['comments' => $items]);
    }

    public function follow(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);

        if (!isset($data['target_type'], $data['target_id'])) {
            return $this->json(['error' => 'Missing required fields: target_type, target_id'], 422);
        }

        if (!$this->isValidTargetType($data['target_type'])) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('follow');

        try {
            $entity = $storage->create([
                'user_id' => $account->id(),
                'target_type' => $data['target_type'],
                'target_id' => (int) $data['target_id'],
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        return $this->json(['id' => $entity->id()], 201);
    }

    public function deleteFollow(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('follow');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);

        return $this->json(['deleted' => true]);
    }

    public function createPost(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);

        if (!isset($data['body'], $data['community_id'])) {
            return $this->json(['error' => 'Missing required fields: body, community_id'], 422);
        }

        $body = trim($data['body']);
        if ($body === '' || mb_strlen($body) > 5000) {
            return $this->json(['error' => 'Body must be 1-5000 characters'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('post');

        try {
            $entity = $storage->create([
                'body' => $body,
                'user_id' => $account->id(),
                'community_id' => (int) $data['community_id'],
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        return $this->json([
            'id' => $entity->id(),
            'body' => $entity->get('body'),
            'created_at' => $entity->get('created_at'),
        ], 201);
    }

    public function deletePost(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('post');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);

        return $this->json(['deleted' => true]);
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

    private function isValidTargetType(string $type): bool
    {
        return in_array($type, self::ALLOWED_TARGET_TYPES, true);
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
