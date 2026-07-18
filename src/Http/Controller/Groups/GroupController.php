<?php

declare(strict_types=1);

namespace App\Http\Controller\Groups;

use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Lean Groups surface (#821).
 *
 * Lists published community groups and renders a single group detail page.
 * Businesses (type='business') stay dormant and de-surfaced (cut). The old
 * controller's related-people and related-teachings sections are dropped
 * (resource_person and teaching are not registered); related upcoming events
 * are shown when the event type is available.
 */
final class GroupController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params */
    public function list(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $groups = $this->loadPublishedGroups($account);
        $communities = $this->resolveCommunities($groups);

        $cards = array_map(fn (ContentEntityBase $group): array => $this->toCard($group, $communities), $groups);

        $html = $this->twig->render('pages/groups/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => $request->getPathInfo(),
            'groups' => $cards,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    public function show(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $group = $slug !== '' ? $this->loadGroupBySlug($slug, $account) : null;

        if ($group === null) {
            $html = $this->twig->render('pages/groups/not-found.html.twig', LayoutTwigContext::withAccount($account, [
                'path' => $request->getPathInfo(),
            ]));

            return new Response($html, 404);
        }

        $communities = $this->resolveCommunities([$group]);

        $html = $this->twig->render('pages/groups/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => $request->getPathInfo(),
            'group' => $this->toCard($group, $communities),
            'related_events' => $this->loadRelatedEvents($group, $account),
        ]));

        return new Response($html);
    }

    /**
     * Published, non-business groups ordered by name. Restrictive-copyright
     * media is filtered out, the same gate the feed loader applies.
     *
     * @return list<ContentEntityBase>
     */
    private function loadPublishedGroups(AccountInterface $account): array
    {
        if (!$this->entityTypeManager->hasDefinition('community_group')) {
            return [];
        }

        $repository = $this->entityTypeManager->getRepository('community_group');
        $ids = $repository->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('type', 'business', '!=')
            ->sort('name', 'ASC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $groups = [];
        foreach ($repository->findMany($ids) as $entity) {
            if ($entity instanceof ContentEntityBase && $this->mediaIsShowable($entity)) {
                $groups[] = $entity;
            }
        }

        return $groups;
    }

    private function loadGroupBySlug(string $slug, AccountInterface $account): ?ContentEntityBase
    {
        if (!$this->entityTypeManager->hasDefinition('community_group')) {
            return null;
        }

        $repository = $this->entityTypeManager->getRepository('community_group');
        $ids = $repository->getQuery()->setAccount($account)
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('type', 'business', '!=')
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        $group = $repository->find((string) reset($ids));

        return $group instanceof ContentEntityBase && $this->mediaIsShowable($group) ? $group : null;
    }

    /**
     * Up to four published upcoming events in the same community. Empty when the
     * event type is unavailable or the group has no community.
     *
     * @return list<ContentEntityBase>
     */
    private function loadRelatedEvents(ContentEntityBase $group, AccountInterface $account): array
    {
        $communityId = $group->get('community_id');
        if ($communityId === null || $communityId === '' || !$this->entityTypeManager->hasDefinition('event')) {
            return [];
        }

        $repository = $this->entityTypeManager->getRepository('event');
        $ids = $repository->getQuery()->setAccount($account)
            ->condition('community_id', $communityId)
            ->condition('status', 1)
            ->range(0, 4)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $events = [];
        foreach ($repository->findMany($ids) as $entity) {
            if ($entity instanceof ContentEntityBase) {
                $events[] = $entity;
            }
        }

        return $events;
    }

    private function mediaIsShowable(ContentEntityBase $group): bool
    {
        $mediaId = $group->get('media_id');
        if ($mediaId === null || $mediaId === '') {
            return true;
        }

        return in_array($group->get('copyright_status'), ['community_owned', 'cc_by_nc_sa'], true);
    }

    /**
     * @param list<ContentEntityBase> $groups
     * @return array<int, array{name: string, slug: string}>
     */
    private function resolveCommunities(array $groups): array
    {
        if (!$this->entityTypeManager->hasDefinition('community')) {
            return [];
        }

        $communityIds = [];
        foreach ($groups as $group) {
            $cid = $group->get('community_id');
            if ($cid !== null && $cid !== '') {
                $communityIds[(int) $cid] = true;
            }
        }

        if ($communityIds === []) {
            return [];
        }

        $repository = $this->entityTypeManager->getRepository('community');
        $map = [];
        foreach ($repository->findMany(array_keys($communityIds)) as $community) {
            $map[(int) $community->id()] = [
                'name' => (string) ($community->get('name') ?? $community->label()),
                'slug' => (string) ($community->get('slug') ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, array{name: string, slug: string}> $communities
     * @return array<string, mixed>
     */
    private function toCard(ContentEntityBase $group, array $communities): array
    {
        $cid = $group->get('community_id');
        $community = $cid !== null && $cid !== '' ? ($communities[(int) $cid] ?? null) : null;

        return [
            'name' => (string) ($group->get('name') ?? ''),
            'type' => (string) ($group->get('type') ?? 'Group'),
            'slug' => (string) ($group->get('slug') ?? ''),
            'url' => '/groups/' . (string) ($group->get('slug') ?? ''),
            'description' => (string) ($group->get('description') ?? ''),
            'excerpt' => $this->excerpt((string) ($group->get('description') ?? '')),
            'community_name' => $community['name'] ?? '',
            'community_slug' => $community['slug'] ?? '',
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
