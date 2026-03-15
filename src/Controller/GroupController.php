<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CommunityLookup;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class GroupController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
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

        $html = $this->twig->render('groups.html.twig', [
            'path' => '/groups',
            'groups' => $groups,
            'communities' => $communities,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $html = $this->twig->render('groups.html.twig', [
            'path' => '/groups/' . $slug,
            'group' => $group,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $group !== null ? 200 : 404,
        );
    }
}
