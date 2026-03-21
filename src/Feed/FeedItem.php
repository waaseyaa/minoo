<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Waaseyaa\Entity\ContentEntityBase;

final readonly class FeedItem
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $url,
        public string $badge,
        public int $weight,
        public \DateTimeImmutable $createdAt,
        public string $sortKey,
        public ?ContentEntityBase $entity = null,
        public ?string $subtitle = null,
        public ?string $date = null,
        public ?float $distance = null,
        public ?string $communityName = null,
        public ?string $meta = null,
        public array $payload = [],
    ) {}

    public function isSynthetic(): bool
    {
        return in_array($this->type, ['welcome', 'communities'], true);
    }

    /**
     * JSON-safe array for API responses. Excludes internal sort fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'badge' => $this->badge,
            'title' => $this->title,
            'url' => $this->url,
        ];

        if ($this->subtitle !== null) {
            $data['subtitle'] = $this->subtitle;
        }
        if ($this->distance !== null) {
            $data['distance'] = $this->distance;
        }
        if ($this->communityName !== null) {
            $data['communityName'] = $this->communityName;
        }
        if ($this->meta !== null) {
            $data['meta'] = $this->meta;
        }
        if ($this->date !== null) {
            $data['date'] = $this->date;
        }
        if ($this->payload !== []) {
            $data['payload'] = $this->payload;
        }

        return $data;
    }
}
