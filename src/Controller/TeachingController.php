<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class TeachingController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('teaching');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('title', 'ASC')
            ->execute();
        $teachings = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $html = $this->twig->render('teachings.html.twig', [
            'path' => '/teachings',
            'teachings' => $teachings,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('teaching');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $teaching = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('teachings.html.twig', [
            'path' => '/teachings/' . $slug,
            'teaching' => $teaching,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $teaching !== null ? 200 : 404,
        );
    }
}
