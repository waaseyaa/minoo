<?php

declare(strict_types=1);

namespace App\Feed\Scoring;

use Waaseyaa\Entity\EntityTypeManager;

final class EngagementCalculator
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly float $reactionWeight = 1.0,
        private readonly float $commentWeight = 3.0,
    ) {}

    /**
     * Batch-compute engagement weight + raw counts for feed items.
     *
     * @param array<string, array{type: string, id: int}> $targetKeys  keyed by "type:id"
     * @return array<string, array{weight: float, reactions: int, comments: int}>
     */
    public function computeBatch(array $targetKeys): array
    {
        if ($targetKeys === []) {
            return [];
        }

        $result = [];
        foreach ($targetKeys as $key => $target) {
            $result[$key] = ['weight' => 1.0, 'reactions' => 0, 'comments' => 0];
        }

        // Count reactions and comments per target using entity queries
        foreach ($targetKeys as $key => $target) {
            $targetKey = $target['type'] . ':' . $target['id'];

            $reactions = $this->countForTarget('reaction', $target['type'], $target['id']);
            $comments = $this->countForTarget('comment', $target['type'], $target['id'], statusFilter: true);

            $result[$key]['reactions'] = $reactions;
            $result[$key]['comments'] = $comments;

            $weightedSum = ($reactions * $this->reactionWeight) + ($comments * $this->commentWeight);
            $result[$key]['weight'] = 1.0 + log(1 + $weightedSum, 2);
        }

        return $result;
    }

    private function countForTarget(string $entityType, string $targetType, int $targetId, bool $statusFilter = false): int
    {
        try {
            $query = $this->entityTypeManager->getStorage($entityType)->getQuery()
                ->condition('target_type', $targetType)
                ->condition('target_id', $targetId)
                ->count();

            if ($statusFilter) {
                $query->condition('status', 1);
            }

            $result = $query->execute();

            return (int) ($result[0] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
