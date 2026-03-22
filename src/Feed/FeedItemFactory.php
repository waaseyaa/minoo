<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Minoo\Support\GeoDistance;
use Waaseyaa\Entity\ContentEntityBase;

final class FeedItemFactory
{
    private const int MAX_META_LENGTH = 60;

    /** @var array<int|string, array{slug: string, name: string}> */
    private array $communityCache = [];

    /**
     * Inject community lookup data for slug/initial resolution.
     *
     * @param array<int|string, array{slug: string, name: string}> $communities keyed by community ID
     */
    public function setCommunityMap(array $communities): void
    {
        $this->communityCache = $communities;
    }

    public function fromEntity(
        string $type,
        ContentEntityBase $entity,
        int $typeSlot,
        ?float $lat = null,
        ?float $lon = null,
        ?float $entityLat = null,
        ?float $entityLon = null,
    ): FeedItem {
        $distance = ($lat !== null && $lon !== null && $entityLat !== null && $entityLon !== null)
            ? GeoDistance::haversine($lat, $lon, $entityLat, $entityLon)
            : null;

        $createdAt = $this->resolveCreatedAt($entity);

        return match ($type) {
            'event' => $this->buildEvent($entity, $typeSlot, $distance, $createdAt),
            'group' => $this->buildGroup($entity, $typeSlot, $distance, $createdAt),
            'business' => $this->buildBusiness($entity, $typeSlot, $distance, $createdAt),
            'person' => $this->buildPerson($entity, $typeSlot, $distance, $createdAt),
            'featured' => $this->buildFeatured($entity, $typeSlot, $distance, $createdAt),
            'post' => $this->buildPost($entity, $typeSlot, $distance, $createdAt),
            default => throw new \InvalidArgumentException("Unknown feed item type: {$type}"),
        };
    }

    public function createWelcome(): FeedItem
    {
        return new FeedItem(
            id: 'welcome:global',
            type: 'welcome',
            title: 'Welcome to Minoo',
            url: '/about',
            badge: 'Welcome',
            weight: 999,
            createdAt: new \DateTimeImmutable(),
            sortKey: $this->buildSortKey(999, null, 0, new \DateTimeImmutable(), 'welcome:global'),
        );
    }

    /**
     * @param list<array{name: string, slug: string}> $communities
     */
    public function createCommunities(array $communities): FeedItem
    {
        return new FeedItem(
            id: 'communities:global',
            type: 'communities',
            title: 'Communities Near You',
            url: '/communities',
            badge: 'Communities',
            weight: 500,
            createdAt: new \DateTimeImmutable(),
            sortKey: $this->buildSortKey(500, null, 0, new \DateTimeImmutable(), 'communities:global'),
            payload: ['communities' => $communities],
        );
    }

    public function buildSortKey(
        int $weight,
        ?float $distance,
        int $typeSlot,
        \DateTimeImmutable $createdAt,
        string $id,
    ): string {
        return sprintf(
            '%04d:%010.2f:%02d:%020d:%s',
            9999 - $weight,
            $distance ?? 99999.99,
            $typeSlot,
            PHP_INT_MAX - $createdAt->getTimestamp(),
            $id,
        );
    }

    private function buildEvent(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'event:' . $entity->id();
        $startsAt = $entity->get('starts_at');
        $communityId = $entity->get('community_id');

        return new FeedItem(
            id: $id,
            type: 'event',
            title: (string) ($entity->get('title') ?? ''),
            url: '/events/' . $entity->get('slug'),
            badge: 'Event',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            subtitle: $startsAt ? (new \DateTimeImmutable($startsAt))->format('F j, Y \a\t g:i A') : null,
            date: $startsAt ? (new \DateTimeImmutable($startsAt))->format('M j, Y \a\t g:i A') : null,
            distance: $distance,
            meta: $entity->get('location'),
            communityName: $this->resolveCommunityName($communityId),
            relativeTime: $this->formatRelativeTime($createdAt),
            communitySlug: $this->resolveCommunitySlug($communityId),
            communityInitial: $this->resolveCommunityInitial($communityId),
        );
    }

    private function buildGroup(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'group:' . $entity->id();
        $communityId = $entity->get('community_id');

        return new FeedItem(
            id: $id,
            type: 'group',
            title: (string) ($entity->get('name') ?? ''),
            url: '/groups/' . $entity->get('slug'),
            badge: 'Group',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            meta: $this->truncate($entity->get('description')),
            communityName: $this->resolveCommunityName($communityId),
            relativeTime: $this->formatRelativeTime($createdAt),
            communitySlug: $this->resolveCommunitySlug($communityId),
            communityInitial: $this->resolveCommunityInitial($communityId),
        );
    }

    private function buildBusiness(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'business:' . $entity->id();
        $communityId = $entity->get('community_id');

        return new FeedItem(
            id: $id,
            type: 'business',
            title: (string) ($entity->get('name') ?? ''),
            url: '/businesses/' . $entity->get('slug'),
            badge: 'Business',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            meta: $this->truncate($entity->get('description')),
            communityName: $this->resolveCommunityName($communityId),
            relativeTime: $this->formatRelativeTime($createdAt),
            communitySlug: $this->resolveCommunitySlug($communityId),
            communityInitial: $this->resolveCommunityInitial($communityId),
        );
    }

    private function buildPerson(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'person:' . $entity->id();
        $communityId = $entity->get('community_id');

        return new FeedItem(
            id: $id,
            type: 'person',
            title: (string) ($entity->get('name') ?? ''),
            url: '/people/' . $entity->get('slug'),
            badge: 'Person',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            communityName: $entity->get('community'),
            meta: $entity->get('role'),
            relativeTime: $this->formatRelativeTime($createdAt),
            communitySlug: $this->resolveCommunitySlug($communityId),
            communityInitial: $this->resolveCommunityInitial($communityId),
        );
    }

    private function buildFeatured(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'featured:' . $entity->id();

        return new FeedItem(
            id: $id,
            type: 'featured',
            title: (string) ($entity->get('headline') ?? $entity->label()),
            url: '/',
            badge: 'Featured',
            weight: 1000,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(1000, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            subtitle: $entity->get('subheadline'),
            distance: $distance,
            relativeTime: $this->formatRelativeTime($createdAt),
        );
    }

    private function resolveCreatedAt(ContentEntityBase $entity): \DateTimeImmutable
    {
        $ts = $entity->get('created_at');
        if ($ts !== null && is_numeric($ts) && (int) $ts > 0) {
            return (new \DateTimeImmutable())->setTimestamp((int) $ts);
        }

        return new \DateTimeImmutable();
    }

    private function truncate(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::MAX_META_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_META_LENGTH) . '…';
    }

    /**
     * Format a timestamp as a human-readable relative time string.
     * Falls back to simple calculation if RelativeTime class is not yet available.
     */
    public function formatRelativeTime(\DateTimeImmutable $createdAt): string
    {
        if (class_exists(RelativeTime::class)) {
            return RelativeTime::format($createdAt->getTimestamp());
        }

        $diff = (new \DateTimeImmutable())->getTimestamp() - $createdAt->getTimestamp();

        return match (true) {
            $diff < 60 => 'just now',
            $diff < 3600 => (int) ($diff / 60) . 'm ago',
            $diff < 86400 => (int) ($diff / 3600) . 'h ago',
            $diff < 604800 => (int) ($diff / 86400) . 'd ago',
            default => $createdAt->format('M j'),
        };
    }

    /**
     * Resolve community slug from community ID using the cached map.
     */
    public function resolveCommunitySlug(mixed $communityId): ?string
    {
        if ($communityId === null) {
            return null;
        }

        return $this->communityCache[(int) $communityId]['slug'] ?? null;
    }

    /**
     * Resolve community name from community ID using the cached map.
     */
    public function resolveCommunityName(mixed $communityId): ?string
    {
        if ($communityId === null) {
            return null;
        }

        return $this->communityCache[(int) $communityId]['name'] ?? null;
    }

    /**
     * Resolve first letter of community name, uppercased, for avatar display.
     */
    public function resolveCommunityInitial(mixed $communityId): ?string
    {
        if ($communityId === null) {
            return null;
        }

        $name = $this->communityCache[(int) $communityId]['name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    private function buildPost(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'post:' . $entity->id();
        $communityId = $entity->get('community_id');

        $images = [];
        $imagesJson = $entity->get('images');
        if ($imagesJson !== null && $imagesJson !== '') {
            try {
                $decoded = json_decode((string) $imagesJson, true, 4, JSON_THROW_ON_ERROR);
                $images = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                // Ignore malformed JSON
            }
        }

        $payload = [];
        if ($images !== []) {
            $payload['images'] = $images;
        }

        return new FeedItem(
            id: $id,
            type: 'post',
            title: (string) ($entity->get('title') ?? ''),
            url: '/posts/' . $entity->get('slug'),
            badge: 'Post',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            meta: $this->truncate($entity->get('body')),
            relativeTime: $this->formatRelativeTime($createdAt),
            communitySlug: $this->resolveCommunitySlug($communityId),
            communityInitial: $this->resolveCommunityInitial($communityId),
            communityName: $entity->get('community_name'),
            payload: $payload,
        );
    }
}
