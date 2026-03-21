<?php

declare(strict_types=1);

namespace Minoo\Feed;

final readonly class FeedResponse
{
    /**
     * @param list<FeedItem> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public string $activeFilter,
        public ?int $totalHint = null,
    ) {}
}
