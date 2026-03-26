<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class ContributorController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('contributor');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('name', 'ASC')
            ->execute();
        $contributors = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $html = $this->twig->render('contributors.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/contributors',
            'contributors' => $contributors,
        ]));

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('contributor');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $contributor = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('contributors.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/contributors/' . $slug,
            'contributor' => $contributor,
        ]));

        return new SsrResponse(
            content: $html,
            statusCode: $contributor !== null ? 200 : 404,
        );
    }
}
