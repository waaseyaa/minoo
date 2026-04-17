<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class HomeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if ($account->isAuthenticated()) {
            return new RedirectResponse('/feed', 302);
        }

        $featured = $this->loadFeaturedItems();
        $events = $this->loadUpcomingEvents();
        $teachings = $this->loadRecentTeachings();

        $html = $this->twig->render('pages/home/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/',
            'featured' => $featured,
            'events' => $events,
            'teachings' => $teachings,
        ]));

        return new Response($html);
    }

    /** @return list<array{headline: string, subheadline: string, entity_type: string, url: string}> */
    private function loadFeaturedItems(): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('featured_item');
        } catch (\RuntimeException|\PDOException) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '<=')
            ->condition('ends_at', $now, '>=')
            ->sort('weight', 'DESC')
            ->range(0, 6)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $items = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            $entityType = $entity->get('entity_type') ?? 'teaching';
            $items[] = [
                'headline' => $entity->get('headline') ?? '',
                'subheadline' => $entity->get('subheadline') ?? '',
                'entity_type' => $entityType,
                'url' => '/' . $entityType . 's',
            ];
        }

        return $items;
    }

    /** @return list<\Waaseyaa\Entity\EntityInterface> */
    private function loadUpcomingEvents(): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('event');
        } catch (\RuntimeException|\PDOException) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '>=')
            ->sort('starts_at', 'ASC')
            ->range(0, 4)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    /** @return list<\Waaseyaa\Entity\EntityInterface> */
    private function loadRecentTeachings(): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('teaching');
        } catch (\RuntimeException|\PDOException) {
            return [];
        }

        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('created_at', 'DESC')
            ->range(0, 4)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }
}
