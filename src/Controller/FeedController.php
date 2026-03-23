<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Feed\FeedAssemblerInterface;
use Minoo\Feed\FeedContext;
use Minoo\Feed\FeedResponse;
use Minoo\Support\GeoDistance;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\Middleware\CsrfMiddleware;
use Waaseyaa\User\User;

final class FeedController
{
    public function __construct(
        private readonly FeedAssemblerInterface $assembler,
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $resolved = self::resolveFilter($query['filter'] ?? 'all');
        $ctx = $this->buildContext($request, $query, $account, $resolved['filter']);
        $response = $this->assembler->assemble($ctx);

        $trending = $this->buildTrending($response);
        $upcomingEvents = $this->buildUpcomingEvents();
        $suggestedCommunities = $this->buildSuggestedCommunities($ctx->latitude, $ctx->longitude);
        $followedCommunities = $this->buildFollowedCommunities($account);
        $userCommunities = $this->buildUserCommunities($followedCommunities, $suggestedCommunities);
        $accountInitial = $this->buildAccountInitial($account);

        $html = $this->twig->render('feed.html.twig', [
            'path' => '/',
            'account' => $account,
            'response' => $response,
            'nextCursor' => $response->nextCursor,
            'activeFilter' => $response->activeFilter,
            'filterParam' => $resolved['filterParam'],
            'csrf_token' => CsrfMiddleware::token(),
            'trending' => $trending,
            'upcoming_events' => $upcomingEvents,
            'suggested_communities' => $suggestedCommunities,
            'followed_communities' => $followedCommunities,
            'user_communities' => $userCommunities,
            'account_initial' => $accountInitial,
        ]);

        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];

        if ($ctx->isFirstVisit) {
            $expires = gmdate('D, d M Y H:i:s T', time() + 86400 * 365);
            $headers['Set-Cookie'] = "minoo_fv=1; Path=/; Expires={$expires}; SameSite=Lax";
        }

        return new SsrResponse(content: $html, headers: $headers);
    }

    public function api(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $ctx = $this->buildContext($request, $query, $account);
        $response = $this->assembler->assemble($ctx);

        $items = array_map(function ($item) {
            $data = $item->toArray();
            $data['html'] = $this->twig->render('components/feed-card.html.twig', ['item' => $item]);
            return $data;
        }, $response->items);

        $json = json_encode([
            'items' => $items,
            'nextCursor' => $response->nextCursor,
            'activeFilter' => $response->activeFilter,
        ], JSON_THROW_ON_ERROR);

        return new SsrResponse(
            content: $json,
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    public function explore(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $type = $query['type'] ?? 'all';
        $q = trim($query['q'] ?? '');

        $targets = [
            'businesses' => '/groups',
            'people' => '/people',
            'events' => '/events',
            'all' => '/groups',
        ];

        $target = $targets[$type] ?? '/groups';

        if ($q !== '') {
            $target .= '?' . http_build_query(['q' => $q]);
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $target]);
    }

    /**
     * Top 5 trending items by reaction count (last 7 days).
     *
     * Falls back to the 5 newest feed items when no reactions exist.
     *
     * @return list<array{type: string, badge: string, title: string, url: string}>
     */
    private function buildTrending(FeedResponse $response): array
    {
        $trending = [];

        if ($this->entityTypeManager->hasDefinition('reaction')) {
            try {
                $storage = $this->entityTypeManager->getStorage('reaction');
                $sevenDaysAgo = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
                $ids = $storage->getQuery()
                    ->condition('created_at', $sevenDaysAgo, '>=')
                    ->execute();

                if ($ids !== []) {
                    $reactions = array_values($storage->loadMultiple($ids));
                    $counts = [];
                    foreach ($reactions as $reaction) {
                        $key = $reaction->get('target_type') . ':' . $reaction->get('target_id');
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                    arsort($counts);

                    $top = array_slice(array_keys($counts), 0, 5);
                    foreach ($top as $compositeKey) {
                        [$type, $id] = explode(':', $compositeKey, 2);
                        if ($this->entityTypeManager->hasDefinition($type)) {
                            try {
                                $entity = $this->entityTypeManager->getStorage($type)->load($id);
                                if ($entity !== null) {
                                    $trending[] = [
                                        'type' => $type,
                                        'badge' => ucfirst($type),
                                        'title' => $entity->label(),
                                        'url' => '/' . $type . 's/' . ($entity->get('slug') ?? $entity->id()),
                                    ];
                                }
                            } catch (\PDOException $e) {
                                error_log(sprintf('[FeedController::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
                            } catch (\RuntimeException $e) {
                                error_log(sprintf('[FeedController::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
                            }
                        }
                    }
                }
            } catch (\PDOException $e) {
                error_log(sprintf('[FeedController::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            } catch (\RuntimeException $e) {
                error_log(sprintf('[FeedController::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            }
        }

        if ($trending === []) {
            foreach (array_slice($response->items, 0, 5) as $item) {
                $trending[] = [
                    'type' => $item->type,
                    'badge' => $item->badge,
                    'title' => $item->title,
                    'url' => $item->url,
                ];
            }
        }

        return $trending;
    }

    /**
     * Next 3 upcoming events (starts_at > now).
     *
     * @return list<array{title: string, date: string, url: string}>
     */
    private function buildUpcomingEvents(): array
    {
        if (!$this->entityTypeManager->hasDefinition('event')) {
            return [];
        }

        try {
            $storage = $this->entityTypeManager->getStorage('event');
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $ids = $storage->getQuery()
                ->condition('status', 1)
                ->condition('starts_at', $now, '>')
                ->sort('starts_at', 'ASC')
                ->range(0, 3)
                ->execute();

            if ($ids === []) {
                return [];
            }

            $events = array_values($storage->loadMultiple($ids));
            $result = [];

            foreach ($events as $event) {
                $result[] = [
                    'title' => $event->label(),
                    'date' => $this->formatEventDate($event->get('starts_at')),
                    'url' => '/events/' . ($event->get('slug') ?? $event->id()),
                ];
            }

            return $result;
        } catch (\PDOException $e) {
            error_log(sprintf('[FeedController::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            error_log(sprintf('[FeedController::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }
    }

    private function formatEventDate(mixed $startsAt): string
    {
        if ($startsAt === null || $startsAt === '') {
            return '';
        }

        try {
            $dt = $startsAt instanceof \DateTimeImmutable
                ? $startsAt
                : new \DateTimeImmutable((string) $startsAt);
            return $dt->format('M j, Y \a\t g:i A');
        } catch (\Throwable) {
            return (string) $startsAt;
        }
    }

    /**
     * Up to 6 communities near the user's location.
     *
     * @return list<array{entity_id: int|string|null, name: string, slug: string, distance: float}>
     */
    private function buildSuggestedCommunities(?float $lat, ?float $lon): array
    {
        if ($lat === null || $lon === null) {
            return [];
        }

        if (!$this->entityTypeManager->hasDefinition('community')) {
            return [];
        }

        try {
            $storage = $this->entityTypeManager->getStorage('community');
            $ids = $storage->getQuery()
                ->condition('status', 1)
                ->execute();

            if ($ids === []) {
                return [];
            }

            $communities = array_values($storage->loadMultiple($ids));
            $withDistance = [];

            foreach ($communities as $community) {
                $cLat = $community->get('latitude');
                $cLon = $community->get('longitude');

                if ($cLat === null || $cLon === null) {
                    continue;
                }

                $distance = GeoDistance::haversine($lat, $lon, (float) $cLat, (float) $cLon);
                $withDistance[] = [
                    'entity_id' => $community->id(),
                    'name' => $community->get('name') ?? $community->label(),
                    'slug' => (string) ($community->get('slug') ?? ''),
                    'distance' => round($distance, 1),
                ];
            }

            usort($withDistance, fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);

            return array_slice($withDistance, 0, 6);
        } catch (\PDOException $e) {
            error_log(sprintf('[FeedController::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            error_log(sprintf('[FeedController::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }
    }

    /**
     * Communities the authenticated user follows.
     *
     * @return list<array{entity_id: int|string|null, name: string, slug: string}>
     */
    private function buildFollowedCommunities(AccountInterface $account): array
    {
        if (!$account->isAuthenticated()) {
            return [];
        }

        if (!$this->entityTypeManager->hasDefinition('follow')) {
            return [];
        }

        try {
            $followStorage = $this->entityTypeManager->getStorage('follow');
            $ids = $followStorage->getQuery()
                ->condition('user_id', $account->id())
                ->condition('target_type', 'community')
                ->execute();

            if ($ids === []) {
                return [];
            }

            $follows = array_values($followStorage->loadMultiple($ids));
            $communityIds = array_map(fn($f) => $f->get('target_id'), $follows);
            $communityIds = array_filter($communityIds);

            if ($communityIds === []) {
                return [];
            }

            $communityStorage = $this->entityTypeManager->getStorage('community');
            $communities = $communityStorage->loadMultiple($communityIds);
            $result = [];

            foreach ($communities as $community) {
                $result[] = [
                    'entity_id' => $community->id(),
                    'name' => $community->get('name') ?? $community->label(),
                    'slug' => (string) ($community->get('slug') ?? ''),
                ];
            }

            return $result;
        } catch (\PDOException $e) {
            error_log(sprintf('[FeedController::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            error_log(sprintf('[FeedController::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }
    }

    /**
     * Communities for the create-post selector.
     *
     * Uses followed communities if available, otherwise falls back to nearby communities.
     *
     * @param list<array{name: string, slug: string}> $followed
     * @param list<array{name: string, slug: string, distance: float}> $suggested
     * @return list<array{id: string, name: string, is_default: bool}>
     */
    private function buildUserCommunities(array $followed, array $suggested): array
    {
        $source = $followed !== [] ? $followed : $suggested;

        if ($source === []) {
            return [];
        }

        $result = [];
        $first = true;

        foreach ($source as $community) {
            $result[] = [
                'id' => $community['entity_id'],
                'name' => $community['name'],
                'is_default' => $first,
            ];
            $first = false;
        }

        return $result;
    }

    /**
     * First letter of the authenticated user's display name, uppercased.
     */
    private function buildAccountInitial(AccountInterface $account): string
    {
        if (!$account->isAuthenticated()) {
            return '';
        }

        // User entity has getName(); generic AccountInterface does not.
        if ($account instanceof User) {
            $name = $account->getName();
            if ($name !== '') {
                return mb_strtoupper(mb_substr($name, 0, 1));
            }
        }

        if (method_exists($account, 'label')) {
            $label = (string) $account->label();
            if ($label !== '') {
                return mb_strtoupper(mb_substr($label, 0, 1));
            }
        }

        return '?';
    }

    /**
     * URL-friendly filter names mapped to internal entity type identifiers.
     *
     * @var array<string, string>
     */
    private const FILTER_MAP = [
        'all' => 'all',
        'post' => 'post',
        'event' => 'event',
        'group' => 'group',
        'business' => 'business',
        'people' => 'resource_person',
        'person' => 'resource_person',
    ];

    /**
     * Resolve and validate the ?filter= query parameter.
     *
     * @return array{filterParam: string, filter: string} URL-facing param and internal entity type
     */
    public static function resolveFilter(string $filterParam): array
    {
        if (!array_key_exists($filterParam, self::FILTER_MAP)) {
            $filterParam = 'all';
        }

        return [
            'filterParam' => $filterParam,
            'filter' => self::FILTER_MAP[$filterParam],
        ];
    }

    private function buildContext(HttpRequest $request, array $query, ?AccountInterface $account = null, ?string $resolvedFilter = null): FeedContext
    {
        $locationCookie = $request->cookies->get('minoo_location');
        $lat = null;
        $lon = null;

        if ($locationCookie !== null) {
            try {
                $loc = json_decode($locationCookie, true, 4, JSON_THROW_ON_ERROR);
                $lat = isset($loc['latitude']) ? (float) $loc['latitude'] : null;
                $lon = isset($loc['longitude']) ? (float) $loc['longitude'] : null;
            } catch (\JsonException) {
                // Invalid cookie — ignore
            }
        }

        $isFirstVisit = $request->cookies->get('minoo_fv') === null;

        $activeFilter = $resolvedFilter ?? self::resolveFilter($query['filter'] ?? 'all')['filter'];

        return new FeedContext(
            latitude: $lat,
            longitude: $lon,
            activeFilter: $activeFilter,
            cursor: $query['cursor'] ?? null,
            limit: min((int) ($query['limit'] ?? 20), 50),
            isFirstVisit: $isFirstVisit,
            isAuthenticated: $account?->isAuthenticated() ?? false,
        );
    }
}
