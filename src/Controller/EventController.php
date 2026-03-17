<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CommunityLookup;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class EventController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('starts_at', 'DESC')
            ->execute();
        $events = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $events = array_filter($events, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        });
        $events = array_values($events);

        $communities = CommunityLookup::build($this->entityTypeManager, $events);

        $html = $this->twig->render('events.html.twig', [
            'path' => '/events',
            'events' => $events,
            'communities' => $communities,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $html = $this->twig->render('events.html.twig', [
            'path' => '/events/' . $slug,
            'event' => $event,
            'related_teachings' => $relatedTeachings,
            'connected_people' => $connectedPeople,
            'host_community' => $hostCommunity,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $event !== null ? 200 : 404,
        );
    }
}
