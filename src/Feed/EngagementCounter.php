<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Waaseyaa\Entity\EntityTypeManager;

final class EngagementCounter
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    /**
     * Batch-query reaction and comment counts for a set of target entities.
     *
     * @param list<array{type: string, id: int}> $targets
     * @return array<string, array{reactions: int, comments: int}>
     *         Keyed by "type:id", e.g. "event:42"
     */
    public function getCounts(array $targets): array
    {
        if ($targets === []) {
            return [];
        }

        $result = [];

        foreach ($targets as $target) {
            $key = $target['type'] . ':' . $target['id'];
            $result[$key] = ['reactions' => 0, 'comments' => 0];
        }

        $reactionStorage = $this->entityTypeManager->getStorage('reaction');
        $commentStorage = $this->entityTypeManager->getStorage('comment');

        foreach ($targets as $target) {
            $key = $target['type'] . ':' . $target['id'];

            $reactionIds = $reactionStorage->getQuery()
                ->condition('target_type', $target['type'])
                ->condition('target_id', $target['id'])
                ->count()
                ->execute();
            $result[$key]['reactions'] = count($reactionIds);

            $commentIds = $commentStorage->getQuery()
                ->condition('target_type', $target['type'])
                ->condition('target_id', $target['id'])
                ->condition('status', 1)
                ->count()
                ->execute();
            $result[$key]['comments'] = count($commentIds);
        }

        return $result;
    }

    /**
     * Get reaction and comment counts for a single target.
     *
     * @return array{reactions: int, comments: int}
     */
    public function getCountsForTarget(string $targetType, int $targetId): array
    {
        $counts = $this->getCounts([['type' => $targetType, 'id' => $targetId]]);

        return $counts[$targetType . ':' . $targetId] ?? ['reactions' => 0, 'comments' => 0];
    }
}
