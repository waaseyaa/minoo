<?php

declare(strict_types=1);

namespace App\Http\Controller\Events;

use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Lean Events surface (#819).
 *
 * Lists published events chronologically (upcoming first, then past) and
 * renders a single event detail page. Geo ranking, calendar view, ICS export,
 * and the sectioned event feed are deliberately NOT wired here: Phase 5 holds
 * proximity/coordinate code dormant, and the platform is Pi-friendly (no
 * geoip). The home feed already surfaces upcoming events via FeedAssembler.
 */
final class EventController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params */
    public function list(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $events = $this->loadPublishedEvents($account);
        $communities = $this->resolveCommunities($events);

        $now = time();
        $cards = array_map(fn (ContentEntityBase $event): array => $this->toCard($event, $communities, $now), $events);

        $html = $this->twig->render('pages/events/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => $request->getPathInfo(),
            'events' => $cards,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    public function show(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $event = $slug !== '' ? $this->loadEventBySlug($slug, $account) : null;

        if ($event === null) {
            $html = $this->twig->render('pages/events/not-found.html.twig', LayoutTwigContext::withAccount($account, [
                'path' => $request->getPathInfo(),
            ]));

            return new Response($html, 404);
        }

        $communities = $this->resolveCommunities([$event]);

        $html = $this->twig->render('pages/events/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => $request->getPathInfo(),
            'event' => $this->toCard($event, $communities, time()),
        ]));

        return new Response($html);
    }

    /**
     * Published events, upcoming (starts_at ascending) before past
     * (starts_at descending). Restrictive-copyright media is filtered out, the
     * same gate the feed loader applies.
     *
     * @return list<ContentEntityBase>
     */
    private function loadPublishedEvents(AccountInterface $account): array
    {
        if (!$this->entityTypeManager->hasDefinition('event')) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $events = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            if ($entity instanceof ContentEntityBase && $this->mediaIsShowable($entity)) {
                $events[] = $entity;
            }
        }

        $now = date('Y-m-d\TH:i:s');
        usort($events, static function (ContentEntityBase $a, ContentEntityBase $b) use ($now): int {
            $aStart = (string) ($a->get('starts_at') ?? '');
            $bStart = (string) ($b->get('starts_at') ?? '');
            $aUpcoming = $aStart >= $now;
            $bUpcoming = $bStart >= $now;

            if ($aUpcoming !== $bUpcoming) {
                return $aUpcoming ? -1 : 1;
            }

            // Upcoming: soonest first. Past: most recent first.
            return $aUpcoming ? ($aStart <=> $bStart) : ($bStart <=> $aStart);
        });

        return $events;
    }

    private function loadEventBySlug(string $slug, AccountInterface $account): ?ContentEntityBase
    {
        if (!$this->entityTypeManager->hasDefinition('event')) {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        $event = $storage->load((int) reset($ids));

        return $event instanceof ContentEntityBase && $this->mediaIsShowable($event) ? $event : null;
    }

    /**
     * An event with no media is always showable; an event with media is only
     * shown when its copyright status permits public display.
     */
    private function mediaIsShowable(ContentEntityBase $event): bool
    {
        $mediaId = $event->get('media_id');
        if ($mediaId === null || $mediaId === '') {
            return true;
        }

        return in_array($event->get('copyright_status'), ['community_owned', 'cc_by_nc_sa'], true);
    }

    /**
     * @param list<ContentEntityBase> $events
     * @return array<int, array{name: string, slug: string}>
     */
    private function resolveCommunities(array $events): array
    {
        if (!$this->entityTypeManager->hasDefinition('community')) {
            return [];
        }

        $communityIds = [];
        foreach ($events as $event) {
            $cid = $event->get('community_id');
            if ($cid !== null && $cid !== '') {
                $communityIds[(int) $cid] = true;
            }
        }

        if ($communityIds === []) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('community');
        $communities = $storage->loadMultiple(array_keys($communityIds));

        $map = [];
        foreach ($communities as $community) {
            $map[(int) $community->id()] = [
                'name' => (string) ($community->get('name') ?? $community->label()),
                'slug' => (string) ($community->get('slug') ?? ''),
            ];
        }

        return $map;
    }

    /**
     * Build the view model the events card/detail templates expect.
     *
     * @param array<int, array{name: string, slug: string}> $communities
     * @return array<string, mixed>
     */
    private function toCard(ContentEntityBase $event, array $communities, int $now): array
    {
        $startsAtRaw = (string) ($event->get('starts_at') ?? '');
        $endsAtRaw = (string) ($event->get('ends_at') ?? '');
        $startTs = $startsAtRaw !== '' ? strtotime($startsAtRaw) : false;
        $endTs = $endsAtRaw !== '' ? strtotime($endsAtRaw) : false;

        $cid = $event->get('community_id');
        $community = $cid !== null && $cid !== '' ? ($communities[(int) $cid] ?? null) : null;

        $happeningNow = $startTs !== false
            && $startTs <= $now
            && ($endTs === false ? ($startTs + 86400) >= $now : $endTs >= $now);

        $status = 'upcoming';
        if ($happeningNow) {
            $status = 'happening_now';
        } elseif ($startTs !== false && $startTs < $now) {
            $status = 'past';
        }

        return [
            'title' => (string) ($event->get('title') ?? ''),
            'type' => (string) ($event->get('type') ?? 'Event'),
            'slug' => (string) ($event->get('slug') ?? ''),
            'url' => '/events/' . (string) ($event->get('slug') ?? ''),
            'description' => (string) ($event->get('description') ?? ''),
            'excerpt' => $this->excerpt((string) ($event->get('description') ?? '')),
            'location' => (string) ($event->get('location') ?? ''),
            'starts_at_raw' => $startsAtRaw,
            'starts_at_iso' => $startTs !== false ? date('c', $startTs) : '',
            'date' => $startTs !== false ? date('F j, Y \a\t g:i A', $startTs) : '',
            'ends_at_iso' => $endTs !== false ? date('c', $endTs) : '',
            'community_name' => $community['name'] ?? '',
            'community_slug' => $community['slug'] ?? '',
            'source_url' => (string) ($event->get('source_url') ?? ''),
            'source_name' => (string) ($event->get('source') ?? ''),
            'happening_now' => $happeningNow,
            'status' => $status,
        ];
    }

    private function excerpt(string $text, int $max = 180): string
    {
        $text = trim(strip_tags($text));
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . '…';
    }
}
