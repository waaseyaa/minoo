<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Events\Service\EventFeedBuilder;
use App\Domain\Events\ValueObject\EventFeedResult;
use App\Domain\Events\ValueObject\EventFilters;
use App\Domain\Geo\Service\CommunityFinder;
use App\Domain\Geo\ValueObject\LocationContext;
use App\Service\LocationResolver;
use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class EventController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly EventFeedBuilder $eventFeedBuilder,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $filters  = EventFilters::fromRequest($request);
        $location = $this->resolveLocation($request);
        $result   = $this->eventFeedBuilder->build($filters, $location);

        // Compatibility shim for the existing template: expose a flat
        // `events` array + `communities` map until Task 9 rebuilds the
        // template around the sectioned `feed` result.
        $events      = $this->flattenFeedForTemplate($result);
        $communities = $result->communities;

        $html = $this->twig->render('events.html.twig', LayoutTwigContext::withAccount($account, [
            'path'        => $request->getPathInfo(),
            'events'      => $events,
            'communities' => $communities,
            'filters'     => $filters,
            'feed'        => $result,
        ]));

        return new Response($html);
    }

    /**
     * @return list<ContentEntityBase>
     */
    private function flattenFeedForTemplate(EventFeedResult $result): array
    {
        if ($result->flatList !== []) {
            return $result->flatList;
        }

        $seen = [];
        $out  = [];
        foreach (
            [
                $result->featured,
                $result->happeningNow,
                $result->thisWeek,
                $result->comingUp,
                $result->onTheHorizon,
            ] as $section
        ) {
            foreach ($section as $entity) {
                $id = (int) $entity->id();
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = $entity;
            }
        }

        return $out;
    }

    private function resolveLocation(HttpRequest $request): ?LocationContext
    {
        try {
            $resolver = new LocationResolver(
                $this->entityTypeManager,
                new CommunityFinder(),
            );
            return $resolver->resolveLocation($request);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $event = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($event !== null) {
            $mediaId = $event->get('media_id');
            if ($mediaId !== null && $mediaId !== '') {
                $status = $event->get('copyright_status');
                if (!in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $event = null;
                }
            }
        }

        $relatedTeachings = [];
        if ($event !== null && $event->get('community_id')) {
            $teachingStorage = $this->entityTypeManager->getStorage('teaching');
            $teachingIds = $teachingStorage->getQuery()
                ->condition('community_id', $event->get('community_id'))
                ->condition('status', 1)
                ->range(0, 4)
                ->execute();
            $relatedTeachings = $teachingIds ? $teachingStorage->loadMultiple($teachingIds) : [];
        }

        $connectedPeople = [];
        if ($event !== null && $event->get('community_id')) {
            $personStorage = $this->entityTypeManager->getStorage('resource_person');
            $personIds = $personStorage->getQuery()
                ->condition('community', $event->get('community_id'))
                ->condition('status', 1)
                ->range(0, 4)
                ->execute();
            $connectedPeople = $personIds ? $personStorage->loadMultiple($personIds) : [];
        }

        $hostCommunity = null;
        if ($event !== null && $event->get('community_id')) {
            $communityStorage = $this->entityTypeManager->getStorage('community');
            $communityIds = $communityStorage->getQuery()
                ->condition('cid', $event->get('community_id'))
                ->range(0, 1)
                ->execute();
            $hostCommunity = $communityIds ? $communityStorage->load(reset($communityIds)) : null;
        }

        $imageUrl = '';
        $imageCredit = '';
        if ($event !== null) {
            $mid = $event->get('media_id');
            if ($mid !== null && $mid !== '') {
                $status = $event->get('copyright_status');
                if (in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $urls = $this->resolvePhotoUrls([(int) $mid]);
                    $imageUrl = $urls[(int) $mid] ?? '';
                }
            }
        }

        $html = $this->twig->render('events.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/events/' . $slug,
            'event' => $event,
            'related_teachings' => $relatedTeachings,
            'connected_people' => $connectedPeople,
            'host_community' => $hostCommunity,
            'image_url' => $imageUrl,
            'image_credit' => $imageCredit,
        ]));

        return new Response($html, $event !== null ? 200 : 404);
    }

    /**
     * @param int[] $mediaIds
     * @return array<int, string> Map of media ID to file URL
     */
    private function resolvePhotoUrls(array $mediaIds): array
    {
        $mediaStorage = $this->entityTypeManager->getStorage('media');
        $mediaEntities = $mediaStorage->loadMultiple($mediaIds);

        $urls = [];
        foreach ($mediaEntities as $media) {
            /** @var EntityInterface $media */
            $url = $media->get('file_url');
            if (is_string($url) && $url !== '') {
                $urls[(int) $media->id()] = $url;
            }
        }

        return $urls;
    }
}
