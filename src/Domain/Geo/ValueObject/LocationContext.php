<?php

declare(strict_types=1);

namespace Minoo\Domain\Geo\ValueObject;

final readonly class LocationContext
{
    public function __construct(
        public ?int $communityId,
        public ?string $communityName,
        public ?float $latitude,
        public ?float $longitude,
        public string $source,
    ) {
    }

    public static function none(): self
    {
        return new self(
            communityId: null,
            communityName: null,
            latitude: null,
            longitude: null,
            source: 'none',
        );
    }

    public function hasLocation(): bool
    {
        return $this->communityId !== null;
    }

    /**
     * @return array<string, int|string|float|null>
     */
    public function toArray(): array
    {
        return [
            'communityId' => $this->communityId,
            'communityName' => $this->communityName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => $this->source,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['communityId'])) {
            return self::none();
        }

        return new self(
            communityId: (int) $data['communityId'],
            communityName: $data['communityName'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            source: $data['source'] ?? 'none',
        );
    }
}
