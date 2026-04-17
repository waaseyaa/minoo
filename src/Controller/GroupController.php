<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\CommunityLookup;
use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class GroupController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('type', 'business', '!=')
            ->sort('name', 'ASC')
            ->execute();
        $groups = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $groups = array_filter($groups, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        });
        $groups = array_values($groups);

        $communities = CommunityLookup::build($this->entityTypeManager, $groups);

        $html = $this->twig->render('pages/groups/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/groups',
            'groups' => $groups,
            'communities' => $communities,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $group = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($group !== null) {
            $mediaId = $group->get('media_id');
            if ($mediaId !== null && $mediaId !== '') {
                $status = $group->get('copyright_status');
                if (!in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $group = null;
                }
            }
        }

        $relatedPeople = [];
        $relatedEvents = [];
        $relatedTeachings = [];

        if ($group !== null && $group->get('community_id')) {
            $communityId = $group->get('community_id');

            $personStorage = $this->entityTypeManager->getStorage('resource_person');
            $personIds = $personStorage->getQuery()
                ->condition('community', $communityId)
                ->condition('status', 1)
                ->range(0, 4)
                ->execute();
            $relatedPeople = $personIds ? array_values($personStorage->loadMultiple($personIds)) : [];

            $eventStorage = $this->entityTypeManager->getStorage('event');
            $eventIds = $eventStorage->getQuery()
                ->condition('community_id', $communityId)
                ->condition('status', 1)
                ->range(0, 4)
                ->execute();
            $relatedEvents = $eventIds ? array_values($eventStorage->loadMultiple($eventIds)) : [];

            $teachingStorage = $this->entityTypeManager->getStorage('teaching');
            $teachingIds = $teachingStorage->getQuery()
                ->condition('community_id', $communityId)
                ->condition('status', 1)
                ->range(0, 4)
                ->execute();
            $relatedTeachings = $teachingIds ? array_values($teachingStorage->loadMultiple($teachingIds)) : [];
        }

        $html = $this->twig->render('pages/groups/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/groups/' . $slug,
            'group' => $group,
            'related_people' => $relatedPeople,
            'related_events' => $relatedEvents,
            'related_teachings' => $relatedTeachings,
        ]));

        return new Response($html, $group !== null ? 200 : 404);
    }
}
