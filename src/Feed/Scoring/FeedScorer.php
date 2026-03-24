<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\EngagementCounter;
use Minoo\Feed\FeedItem;

/**
 * Central orchestrator for feed ranking.
 *
 * Combines affinity, engagement, and time-decay signals into a single score
 * per feed item. Applies diversity reranking after scoring.
 */
final class FeedScorer
{
    public function __construct(
        private readonly AffinityCalculator $affinity,
        private readonly EngagementCalculator $engagement,
        private readonly DecayCalculator $decay,
        private readonly DiversityReranker $reranker,
        private readonly EngagementCounter $engagementCounter,
        private readonly float $featuredBoost = 100.0,
    ) {}

    /**
     * Score, sort, and rerank feed items.
     *
     * @param list<FeedItem> $items Unscored feed items
     * @param int|null $userId Authenticated user ID (null for anonymous)
     * @return list<FeedItem> Scored and reranked items
     */
    public function score(array $items, ?int $userId = null): array
    {
        if ($items === []) {
            return [];
        }

        // Separate synthetic items (welcome, communities) — they get pinned to top
        $synthetic = [];
        $scorable = [];
        foreach ($items as $item) {
            if ($item->isSynthetic()) {
                $synthetic[] = $item;
            } else {
                $scorable[] = $item;
            }
        }

        if ($scorable === []) {
            return $synthetic;
        }

        // 1. Resolve source keys and build target keys for batch operations
        $sourceKeys = [];
        $sourceMap = []; // itemIndex => sourceKey
        $targetKeys = []; // [{type, id}]

        foreach ($scorable as $idx => $item) {
            $sourceKey = $this->resolveSourceKey($item);
            $sourceMap[$idx] = $sourceKey;
            $sourceKeys[$sourceKey] = true;
            $targetKeys[] = ['type' => $item->type, 'id' => (int) $item->id];
        }

        // 2. Batch compute affinity scores
        $affinityScores = [];
        if ($userId !== null) {
            $affinityScores = $this->affinity->computeBatch($userId, array_keys($sourceKeys));
        }

        // 3. Batch compute engagement counts and weights
        $engagementCounts = $this->engagementCounter->getCounts($targetKeys);
        $engagementWeights = $this->engagement->computeBatch($engagementCounts);

        // 4. Compute per-item scores
        $now = new \DateTimeImmutable();
        $scored = [];

        foreach ($scorable as $idx => $item) {
            $decayFactor = $this->decay->compute($item->createdAt, $now);
            $targetKey = $item->type . ':' . $item->id;

            if ($item->weight >= 1000) {
                $score = $this->featuredBoost * $decayFactor;
            } else {
                $sourceKey = $sourceMap[$idx];
                $affinity = $affinityScores[$sourceKey] ?? 1.0;
                $engWeight = $engagementWeights[$targetKey] ?? 1.0;

                $score = $affinity * $engWeight * $decayFactor;
            }

            $counts = $engagementCounts[$targetKey] ?? null;

            $scored[] = new FeedItem(
                id: $item->id,
                type: $item->type,
                title: $item->title,
                url: $item->url,
                badge: $item->badge,
                weight: $item->weight,
                createdAt: $item->createdAt,
                sortKey: sprintf('%020.10f_%s', 1e10 - $score, $item->id),
                entity: $item->entity,
                subtitle: $item->subtitle,
                date: $item->date,
                distance: $item->distance,
                communityName: $item->communityName,
                meta: $item->meta,
                payload: $item->payload,
                reactionCount: $counts !== null ? ($counts['reactions'] ?? 0) : $item->reactionCount,
                commentCount: $counts !== null ? ($counts['comments'] ?? 0) : $item->commentCount,
                userReaction: $item->userReaction,
                relativeTime: $item->relativeTime,
                communitySlug: $item->communitySlug,
                communityInitial: $item->communityInitial,
                authorName: $item->authorName,
                score: $score,
            );
        }

        // 5. Sort by score descending (via sortKey)
        usort($scored, fn(FeedItem $a, FeedItem $b) => strcmp($a->sortKey, $b->sortKey));

        // 6. Diversity reranking
        $scored = $this->reranker->rerank($scored);

        // 7. Pin synthetic items to top
        return array_merge($synthetic, $scored);
    }

    /**
     * Resolve the source key for affinity tracking.
     *
     * post -> user:{user_id}
     * event/teaching -> community:{community_id} (fallback {type}:{id})
     * group/business/person -> {type}:{id}
     */
    private function resolveSourceKey(FeedItem $item): string
    {
        if ($item->type === 'post' && $item->entity !== null) {
            $userId = $item->entity->get('user_id');
            if ($userId !== null) {
                return 'user:' . $userId;
            }
        }

        if (in_array($item->type, ['event', 'teaching'], true) && $item->entity !== null) {
            $communityId = $item->entity->get('community_id');
            if ($communityId !== null) {
                return 'community:' . $communityId;
            }
        }

        return $item->type . ':' . $item->id;
    }
}
