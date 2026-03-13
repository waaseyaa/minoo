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
        return $this->communityId !== null && $this->communityId > 0;
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

        // Validate communityId is numeric and positive.
        if (!is_numeric($data['communityId']) || (int) $data['communityId'] <= 0) {
            return self::none();
        }

        // Validate latitude if present.
        if (isset($data['latitude'])) {
            if (!is_numeric($data['latitude'])) {
                return self::none();
            }
            $lat = (float) $data['latitude'];
            if ($lat < -90.0 || $lat > 90.0) {
                return self::none();
            }
        }

        // Validate longitude if present.
        if (isset($data['longitude'])) {
            if (!is_numeric($data['longitude'])) {
                return self::none();
            }
            $lon = (float) $data['longitude'];
            if ($lon < -180.0 || $lon > 180.0) {
                return self::none();
            }
        }

        return new self(
            communityId: (int) $data['communityId'],
            communityName: isset($data['communityName']) && is_string($data['communityName']) ? $data['communityName'] : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            source: isset($data['source']) && is_string($data['source']) ? $data['source'] : 'none',
        );
    }
}
