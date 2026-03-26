<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\FeedItem;

final class DiversityReranker
{
    private const SCAN_LIMIT = 10;

    public function __construct(
        private readonly int $maxConsecutiveType = 2,
        private readonly int $maxConsecutiveCommunity = 2,
        private readonly int $postGuaranteeSlot = 3,
    ) {}

    /**
     * @param FeedItem[] $sortedItems
     * @return FeedItem[]
     */
    public function rerank(array $sortedItems): array
    {
        $items = array_values($sortedItems);
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            if ($items[$i]->isSynthetic()) {
                continue;
            }

            $consecutiveType = 1;
            $consecutiveCommunity = 1;
            $currentType = $items[$i]->type;
            $currentCommunity = $items[$i]->communitySlug;

            // Count consecutive same-type and same-community items ending at $i
            for ($k = $i - 1; $k >= 0; $k--) {
                if ($items[$k]->isSynthetic()) {
                    break;
                }
                $typeMatch = $items[$k]->type === $currentType;
                $communityMatch = $items[$k]->communitySlug === $currentCommunity;

                if ($typeMatch) {
                    $consecutiveType++;
                }
                if ($communityMatch) {
                    $consecutiveCommunity++;
                }
                if (!$typeMatch && !$communityMatch) {
                    break;
                }
            }

            $typeExceeded = $consecutiveType > $this->maxConsecutiveType;
            $communityExceeded = $consecutiveCommunity > $this->maxConsecutiveCommunity;

            if (!$typeExceeded && !$communityExceeded) {
                continue;
            }

            // Scan forward for a different-type OR different-community item
            $scanEnd = min($i + self::SCAN_LIMIT, $count - 1);
            for ($j = $i + 1; $j <= $scanEnd; $j++) {
                if ($items[$j]->isSynthetic()) {
                    continue;
                }

                if ($items[$j]->type !== $currentType || $items[$j]->communitySlug !== $currentCommunity) {
                    // Swap $j into position $i
                    $swapped = $items[$j];
                    array_splice($items, $j, 1);
                    array_splice($items, $i, 0, [$swapped]);
                    break;
                }
            }
        }

        return $this->guaranteePost($items);
    }

    /**
     * Ensure at least one post appears in the first N non-synthetic slots.
     *
     * @param FeedItem[] $items
     * @return FeedItem[]
     */
    private function guaranteePost(array $items): array
    {
        if ($this->postGuaranteeSlot <= 0) {
            return $items;
        }

        $nonSyntheticSeen = 0;
        $hasPost = false;

        foreach ($items as $item) {
            if ($item->isSynthetic()) {
                continue;
            }
            $nonSyntheticSeen++;
            if ($item->type === 'post') {
                $hasPost = true;
                break;
            }
            if ($nonSyntheticSeen >= $this->postGuaranteeSlot) {
                break;
            }
        }

        if ($hasPost) {
            return $items;
        }

        // Find the first post anywhere in the list
        $postIdx = null;
        foreach ($items as $idx => $item) {
            if (!$item->isSynthetic() && $item->type === 'post') {
                $postIdx = $idx;
                break;
            }
        }

        if ($postIdx === null) {
            return $items; // No posts at all
        }

        // Find the target slot (the Nth non-synthetic position)
        $targetIdx = null;
        $nonSyntheticSeen = 0;
        foreach ($items as $idx => $item) {
            if ($item->isSynthetic()) {
                continue;
            }
            $nonSyntheticSeen++;
            if ($nonSyntheticSeen === $this->postGuaranteeSlot) {
                $targetIdx = $idx;
                break;
            }
        }

        if ($targetIdx === null || $postIdx <= $targetIdx) {
            return $items;
        }

        // Pull the post into the target slot
        $post = $items[$postIdx];
        array_splice($items, $postIdx, 1);
        array_splice($items, $targetIdx, 0, [$post]);

        return $items;
    }
}
