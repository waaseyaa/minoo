<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\FeedItem;

final class FeedScorer
{
    public function __construct(
        private readonly AffinityCalculator $affinity,
        private readonly EngagementCalculator $engagement,
        private readonly DecayCalculator $decay,
        private readonly DiversityReranker $reranker,
        private readonly float $featuredBoost = 100.0,
    ) {}

    /**
     * Score, sort, and diversify feed items.
     *
     * @param FeedItem[] $items
     * @param ?int $userId
     * @param ?int $userCommunityId
     * @param ?array{lat: float, lon: float} $userLocation
     * @param ?array<string, array{lat: float, lon: float, community_id?: int}> $sourceLocations
     * @param array<string, string> $sourceMap itemId => sourceKey
     * @return FeedItem[]
     */
    public function score(
        array $items,
        ?int $userId,
        ?int $userCommunityId,
        ?array $userLocation,
        ?array $sourceLocations,
        array $sourceMap,
    ): array {
        $now = time();
        $synthetics = [];
        $scorable = [];

        foreach ($items as $item) {
            if ($item->isSynthetic()) {
                $synthetics[] = $item;
            } else {
                $scorable[] = $item;
            }
        }

        if ($scorable === []) {
            return $synthetics;
        }

        // 1. Batch-compute affinity
        $sourceKeys = array_unique(array_values($sourceMap));
        $affinityScores = $this->affinity->computeBatch(
            $userId,
            $sourceKeys,
            $userCommunityId,
            $userLocation,
            $sourceLocations,
        );

        // 2. Batch-compute engagement
        $targetKeys = [];
        foreach ($scorable as $item) {
            $targetKeys[$item->id] = $this->parseTargetKey($item);
        }
        $engagementData = $this->engagement->computeBatch($targetKeys);

        // 3. Score each item
        $scored = [];
        foreach ($scorable as $item) {
            $affinity = 1.0;
            if ($affinityScores !== null && isset($sourceMap[$item->id])) {
                $affinity = $affinityScores[$sourceMap[$item->id]] ?? 1.0;
            }

            $engResult = $engagementData[$item->id] ?? ['weight' => 1.0, 'reactions' => 0, 'comments' => 0];
            $decayFactor = $this->decay->compute($item->createdAt->getTimestamp(), $now);

            $isFeatured = $item->weight >= 1000;
            $score = $isFeatured
                ? $this->featuredBoost * $decayFactor
                : $affinity * $engResult['weight'] * $decayFactor;

            $sortKey = sprintf('%010d:%s', (int) (max(0, 10000.0 - $score) * 100000), $item->id);

            $scored[] = new FeedItem(
                id: $item->id,
                type: $item->type,
                title: $item->title,
                url: $item->url,
                badge: $item->badge,
                weight: $item->weight,
                createdAt: $item->createdAt,
                sortKey: $sortKey,
                entity: $item->entity,
                subtitle: $item->subtitle,
                date: $item->date,
                distance: $item->distance,
                communityName: $item->communityName,
                meta: $item->meta,
                payload: $item->payload,
                reactionCount: $engResult['reactions'],
                commentCount: $engResult['comments'],
                userReaction: $item->userReaction,
                relativeTime: $item->relativeTime,
                communitySlug: $item->communitySlug,
                communityInitial: $item->communityInitial,
                authorName: $item->authorName,
                score: $score,
            );
        }

        // 4. Sort by score descending
        usort($scored, static fn(FeedItem $a, FeedItem $b) => ($b->score ?? 0.0) <=> ($a->score ?? 0.0));

        // 5. Apply diversity reranking
        $scored = $this->reranker->rerank($scored);

        // 6. Pin synthetics to top
        return [...$synthetics, ...$scored];
    }

    /**
     * @return array{type: string, id: int}
     */
    private function parseTargetKey(FeedItem $item): array
    {
        $parts = explode(':', $item->id, 2);

        return [
            'type' => $parts[0],
            'id' => (int) ($parts[1] ?? 0),
        ];
    }
}
