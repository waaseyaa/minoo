<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

/**
 * Computes engagement weight for feed items based on reaction/comment counts.
 *
 * Uses log-dampened formula: 1 + ln(1 + reactionWeight*reactions + commentWeight*comments)
 */
final class EngagementCalculator
{
    public function __construct(
        private readonly float $reactionWeight = 1.0,
        private readonly float $commentWeight = 2.0,
    ) {}

    /**
     * Compute engagement weight for a single target.
     *
     * @return float >= 1.0 (1.0 = no engagement)
     */
    public function compute(int $reactions, int $comments): float
    {
        $weighted = $this->reactionWeight * $reactions + $this->commentWeight * $comments;

        return 1.0 + log(1.0 + $weighted);
    }

    /**
     * Batch-compute engagement weights for multiple targets.
     *
     * @param array<string, array{reactions: int, comments: int}> $counts Keyed by "type:id"
     * @return array<string, float> Keyed by "type:id"
     */
    public function computeBatch(array $counts): array
    {
        $result = [];
        foreach ($counts as $key => $count) {
            $result[$key] = $this->compute($count['reactions'], $count['comments']);
        }
        return $result;
    }
}
