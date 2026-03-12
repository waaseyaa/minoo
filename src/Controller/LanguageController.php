<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class LanguageController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('word', 'ASC')
            ->execute();
        $entries = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $html = $this->twig->render('language.html.twig', [
            'path' => '/language',
            'entries' => $entries,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $entry = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('language.html.twig', [
            'path' => '/language/' . $slug,
            'entry' => $entry,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $entry !== null ? 200 : 404,
        );
    }
}
