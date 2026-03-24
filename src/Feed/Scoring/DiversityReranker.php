<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\FeedItem;

/**
 * Reranks scored feed items to ensure content diversity.
 *
 * Prevents consecutive items of the same type or community from clustering.
 * Uses a sliding window approach: if the current item matches the previous
 * item's type AND community, try to swap it with the next different item.
 */
final class DiversityReranker
{
    public function __construct(
        private readonly int $windowSize = 3,
    ) {}

    /**
     * Rerank items to improve diversity.
     *
     * @param list<FeedItem> $items Already sorted by score descending
     * @return list<FeedItem>
     */
    public function rerank(array $items): array
    {
        $count = count($items);
        if ($count <= 2) {
            return $items;
        }

        for ($i = 1; $i < $count; $i++) {
            $currentType = $items[$i]->type;
            $currentCommunity = $items[$i]->communitySlug;
            $prevType = $items[$i - 1]->type;
            $prevCommunity = $items[$i - 1]->communitySlug;

            // Only swap if both type AND community match the previous item
            if ($currentType === $prevType && $currentCommunity === $prevCommunity) {
                // Look ahead in window for a different item to swap with
                $swapIdx = $this->findSwapCandidate($items, $i, $currentType, $currentCommunity);
                if ($swapIdx !== null) {
                    [$items[$i], $items[$swapIdx]] = [$items[$swapIdx], $items[$i]];
                }
            }
        }

        return $items;
    }

    /**
     * Find the nearest item within the window that differs in type OR community.
     */
    private function findSwapCandidate(array $items, int $currentIdx, string $currentType, ?string $currentCommunity): ?int
    {
        $maxIdx = min($currentIdx + $this->windowSize, count($items) - 1);

        for ($j = $currentIdx + 1; $j <= $maxIdx; $j++) {
            if ($items[$j]->type !== $currentType || $items[$j]->communitySlug !== $currentCommunity) {
                return $j;
            }
        }

        return null;
    }
}
