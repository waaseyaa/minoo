<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Waaseyaa\Database\DatabaseInterface;

final class EngagementCalculator
{
    public function __construct(
        private readonly DatabaseInterface $database,
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

        $reactionCounts = $this->countByTarget('reaction', $targetKeys);
        $commentCounts = $this->countByTarget('comment', $targetKeys, statusFilter: true);

        foreach ($targetKeys as $key => $target) {
            $targetKey = $target['type'] . ':' . $target['id'];
            $reactions = $reactionCounts[$targetKey] ?? 0;
            $comments = $commentCounts[$targetKey] ?? 0;

            $result[$key]['reactions'] = $reactions;
            $result[$key]['comments'] = $comments;

            $weightedSum = ($reactions * $this->reactionWeight) + ($comments * $this->commentWeight);
            $result[$key]['weight'] = 1.0 + log(1 + $weightedSum, 2);
        }

        return $result;
    }

    /**
     * @param array<string, array{type: string, id: int}> $targetKeys
     * @return array<string, int>  "type:id" => count
     */
    private function countByTarget(string $table, array $targetKeys, bool $statusFilter = false): array
    {
        $types = [];
        $ids = [];
        foreach ($targetKeys as $target) {
            $types[$target['type']] = true;
            $ids[$target['id']] = true;
        }

        $query = $this->database->select($table, 't')
            ->fields('t', ['target_type', 'target_id'])
            ->condition('t.target_type', array_keys($types), 'IN')
            ->condition('t.target_id', array_keys($ids), 'IN');

        if ($statusFilter) {
            $query->condition('t.status', 1);
        }

        $counts = [];
        foreach ($query->execute() as $row) {
            $key = $row['target_type'] . ':' . $row['target_id'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }
}
