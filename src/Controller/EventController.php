<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class EventController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('starts_at', 'DESC')
            ->execute();
        $events = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $html = $this->twig->render('events.html.twig', [
            'path' => '/events',
            'events' => $events,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $event = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('events.html.twig', [
            'path' => '/events/' . $slug,
            'event' => $event,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $event !== null ? 200 : 404,
        );
    }
}
