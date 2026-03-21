<?php

declare(strict_types=1);

namespace Minoo\Feed;

final readonly class FeedContext
{
    /**
     * @param string[] $requestedTypes
     */
    public function __construct(
        public ?float $latitude = null,
        public ?float $longitude = null,
        public string $activeFilter = 'all',
        public array $requestedTypes = [],
        public ?string $cursor = null,
        public int $limit = 20,
        public bool $isFirstVisit = false,
        public bool $isAuthenticated = false,
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
