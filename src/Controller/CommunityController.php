<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Search\CommunityAutocompleteClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CommunityController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly CommunityAutocompleteClient $autocompleteClient,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('community');

        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        $typeFilter = $request->query->getString('type');
        if ($typeFilter !== '') {
            $queryBuilder->condition('community_type', $typeFilter);
        }

        $ids = $queryBuilder->execute();
        $communities = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $html = $this->twig->render('communities.html.twig', [
            'path' => '/communities',
            'communities' => array_values($communities),
            'type_filter' => $typeFilter,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $community = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('communities.html.twig', [
            'path' => '/communities/' . $slug,
            'community' => $community,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $community !== null ? 200 : 404,
        );
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function autocomplete(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $term = $request->query->getString('q');
        $suggestions = $this->autocompleteClient->suggest($term);

        return new JsonResponse($suggestions);
    }
}
