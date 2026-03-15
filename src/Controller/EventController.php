<?php

declare(strict_types=1);

namespace Minoo\Controller;

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

        $communities = $this->buildCommunityLookup($events);

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

        $html = $this->twig->render('events.html.twig', [
            'path' => '/events/' . $slug,
            'event' => $event,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $event !== null ? 200 : 404,
        );
    }

    /** @param list<\Waaseyaa\Entity\EntityInterface> $entities */
    private function buildCommunityLookup(array $entities): array
    {
        $communityIds = array_filter(array_unique(array_map(
            fn ($e) => $e->get('community_id'),
            $entities
        )));

        if ($communityIds === []) {
            return [];
        }

        $communityStorage = $this->entityTypeManager->getStorage('community');
        $communities = $communityStorage->loadMultiple($communityIds);
        $lookup = [];
        foreach ($communities as $community) {
            $lookup[(string) $community->id()] = [
                'name' => $community->get('name') ?? $community->label(),
                'slug' => $community->get('slug'),
            ];
        }

        return $lookup;
    }
}
