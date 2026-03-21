<?php

declare(strict_types=1);

namespace Minoo\Feed;

/**
 * Counts reactions and comments for feed items.
 * Implementation will be provided by another worker.
 */
interface EngagementCounter
{
    /**
     * Get engagement counts for a batch of feed item IDs.
     *
     * @param list<string> $feedItemIds Feed item IDs (e.g. "event:42", "post:7")
     * @return array<string, array{reactions: int, comments: int}> Keyed by feed item ID
     */
    public function getCounts(array $feedItemIds): array;
}
