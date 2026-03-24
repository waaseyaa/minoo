<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Waaseyaa\Entity\EntityTypeManager;

/**
 * Computes user-source affinity scores based on past engagement.
 *
 * Affinity = sum of weighted interactions (reactions, comments, follows)
 * between a user and a content source (user, community, entity).
 */
final class AffinityCalculator
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly AffinityCache $cache,
        private readonly float $reactionAffinity = 1.0,
        private readonly float $commentAffinity = 2.0,
        private readonly float $followAffinity = 5.0,
    ) {}

    /**
     * Compute affinity scores between a user and multiple source keys.
     *
     * @param int $userId
     * @param list<string> $sourceKeys e.g. ["user:42", "community:7"]
     * @return array<string, float> sourceKey => affinity score (default 1.0)
     */
    public function computeBatch(int $userId, array $sourceKeys): array
    {
        $cached = $this->cache->get($userId);
        if ($cached !== null) {
            // Return cached values, defaulting missing keys to 1.0
            $result = [];
            foreach ($sourceKeys as $key) {
                $result[$key] = $cached[$key] ?? 1.0;
            }
            return $result;
        }

        $scores = $this->computeFromStorage($userId, $sourceKeys);
        $this->cache->set($userId, $scores);

        return $scores;
    }

    /**
     * @param list<string> $sourceKeys
     * @return array<string, float>
     */
    private function computeFromStorage(int $userId, array $sourceKeys): array
    {
        $result = [];
        foreach ($sourceKeys as $key) {
            $result[$key] = 1.0; // Base affinity
        }

        // Count reactions by user toward each source's content
        if ($this->entityTypeManager->hasDefinition('reaction')) {
            try {
                $storage = $this->entityTypeManager->getStorage('reaction');
                $ids = $storage->getQuery()
                    ->condition('user_id', $userId)
                    ->execute();

                if ($ids !== []) {
                    $reactions = array_values($storage->loadMultiple($ids));
                    foreach ($reactions as $reaction) {
                        $targetType = (string) ($reaction->get('target_type') ?? '');
                        $targetId = (string) ($reaction->get('target_id') ?? '');
                        $sourceKey = $this->resolveSourceFromTarget($targetType, $targetId);
                        if ($sourceKey !== null && isset($result[$sourceKey])) {
                            $result[$sourceKey] += $this->reactionAffinity;
                        }
                    }
                }
            } catch (\Throwable) {
                // Silently continue — affinity is best-effort
            }
        }

        // Count comments by user
        if ($this->entityTypeManager->hasDefinition('comment')) {
            try {
                $storage = $this->entityTypeManager->getStorage('comment');
                $ids = $storage->getQuery()
                    ->condition('user_id', $userId)
                    ->execute();

                if ($ids !== []) {
                    $comments = array_values($storage->loadMultiple($ids));
                    foreach ($comments as $comment) {
                        $targetType = (string) ($comment->get('target_type') ?? '');
                        $targetId = (string) ($comment->get('target_id') ?? '');
                        $sourceKey = $this->resolveSourceFromTarget($targetType, $targetId);
                        if ($sourceKey !== null && isset($result[$sourceKey])) {
                            $result[$sourceKey] += $this->commentAffinity;
                        }
                    }
                }
            } catch (\Throwable) {
                // Silently continue
            }
        }

        // Count follows by user
        if ($this->entityTypeManager->hasDefinition('follow')) {
            try {
                $storage = $this->entityTypeManager->getStorage('follow');
                $ids = $storage->getQuery()
                    ->condition('user_id', $userId)
                    ->execute();

                if ($ids !== []) {
                    $follows = array_values($storage->loadMultiple($ids));
                    foreach ($follows as $follow) {
                        $targetType = (string) ($follow->get('target_type') ?? '');
                        $targetId = (string) ($follow->get('target_id') ?? '');
                        $key = $targetType . ':' . $targetId;
                        if (isset($result[$key])) {
                            $result[$key] += $this->followAffinity;
                        }
                    }
                }
            } catch (\Throwable) {
                // Silently continue
            }
        }

        return $result;
    }

    /**
     * Resolve a target (type:id) to its source key for affinity tracking.
     * Posts map to user:{user_id}, other types map to {type}:{id}.
     */
    private function resolveSourceFromTarget(string $targetType, string $targetId): ?string
    {
        if ($targetType === '' || $targetId === '') {
            return null;
        }

        if ($targetType === 'post') {
            // Try to resolve post author
            try {
                $storage = $this->entityTypeManager->getStorage('post');
                $post = $storage->load($targetId);
                if ($post !== null) {
                    $authorId = $post->get('user_id');
                    if ($authorId !== null) {
                        return 'user:' . $authorId;
                    }
                }
            } catch (\Throwable) {
                // Fall through
            }
        }

        return $targetType . ':' . $targetId;
    }
}
