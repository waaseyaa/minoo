<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Minoo\Support\GeoDistance;
use Waaseyaa\Entity\ContentEntityBase;

final class FeedItemFactory
{
    private const int MAX_META_LENGTH = 60;

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
            date: $startsAt,
            distance: $distance,
            meta: $entity->get('location'),
        );
    }

    private function buildGroup(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'group:' . $entity->id();

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
        );
    }

    private function buildBusiness(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'business:' . $entity->id();

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
        );
    }

    private function buildPerson(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'person:' . $entity->id();

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
}
