<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class PeopleController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        $ids = $queryBuilder->execute();
        $people = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people',
            'people' => array_values($people),
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $person = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people/' . $slug,
            'person' => $person,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $person !== null ? 200 : 404,
        );
    }
}
