<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CommunityController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function autocomplete(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        $storage = $this->entityTypeManager->getStorage('community');

        $queryBuilder = $storage->getQuery()
            ->sort('name', 'ASC')
            ->range(0, 10);

        if ($q !== '') {
            $queryBuilder->condition('name', $q . '%', 'LIKE');
        }

        $ids = $queryBuilder->execute();
        $communities = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $results = [];
        foreach ($communities as $community) {
            $results[] = [
                'id' => $community->id(),
                'name' => $community->get('name'),
            ];
        }

        return new SsrResponse(
            content: json_encode($results, \JSON_THROW_ON_ERROR),
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
