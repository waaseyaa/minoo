<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Feed\FeedItem;

final class DiversityReranker
{
    private const SCAN_LIMIT = 10;

    public function __construct(
        private readonly int $maxConsecutiveType = 3,
        private readonly int $maxConsecutiveCommunity = 5,
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

        return $items;
    }
}
