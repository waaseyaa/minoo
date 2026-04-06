<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Api\JsonResponseTrait;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Symfony\Component\HttpFoundation\Response;

final class BlockController
{
    use JsonResponseTrait;
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->blockStorage();
        $ids = $storage->getQuery()
            ->condition('blocker_id', (int) $account->id())
            ->sort('created_at', 'DESC')
            ->execute();

        $blocks = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $payload = array_map(static fn($block): array => [
            'id' => (int) $block->id(),
            'blocker_id' => (int) $block->get('blocker_id'),
            'blocked_id' => (int) $block->get('blocked_id'),
            'created_at' => (int) $block->get('created_at'),
        ], $blocks);

        return $this->json(['blocks' => $payload]);
    }

    public function store(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);
        $blockedId = (int) ($data['blocked_id'] ?? 0);
        $blockerId = (int) $account->id();

        if ($blockedId <= 0) {
            return $this->json(['error' => 'blocked_id is required'], 422);
        }

        if ($blockerId === $blockedId) {
            return $this->json(['error' => 'Cannot block yourself'], 422);
        }

        $storage = $this->blockStorage();

        // Check for duplicate block.
        $existing = $storage->getQuery()
            ->condition('blocker_id', $blockerId)
            ->condition('blocked_id', $blockedId)
            ->range(0, 1)
            ->execute();

        if ($existing !== []) {
            return $this->json(['error' => 'User is already blocked'], 409);
        }

        try {
            $block = $storage->create([
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'created_at' => time(),
            ]);
            $storage->save($block);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid block payload'], 422);
        }

        return $this->json(['id' => (int) $block->id()], 201);
    }

    public function delete(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $blockedUserId = (int) ($params['user_id'] ?? 0);
        $blockerId = (int) $account->id();

        $storage = $this->blockStorage();
        $ids = $storage->getQuery()
            ->condition('blocker_id', $blockerId)
            ->condition('blocked_id', $blockedUserId)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return $this->json(['error' => 'Block not found'], 404);
        }

        $block = $storage->load((int) reset($ids));
        if ($block === null) {
            return $this->json(['error' => 'Block not found'], 404);
        }

        $storage->delete([$block]);

        return $this->json(['removed' => true]);
    }

    private function blockStorage(): EntityStorageInterface
    {
        return $this->entityTypeManager->getStorage('user_block');
    }

}
