<?php

declare(strict_types=1);

namespace Minoo\Controller;

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

        $html = $this->twig->render('groups.html.twig', [
            'path' => '/groups',
            'groups' => $groups,
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
