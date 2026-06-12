<?php

declare(strict_types=1);

namespace App\Http\Controller\Home;

use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Language-platform homepage: dictionary stats, featured items, and the
 * games hub. The old social feed (and its authenticated redirect) is gone.
 */
final class HomeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/home/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/',
            'featured' => $this->loadFeaturedItems(),
            'entry_count' => $this->countDictionaryEntries(),
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
        $ids = $storage->getQuery()->accessCheck(false)
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
            $entityType = $entity->get('entity_type') ?? 'dictionary_entry';
            $items[] = [
                'headline' => $entity->get('headline') ?? '',
                'subheadline' => $entity->get('subheadline') ?? '',
                'entity_type' => $entityType,
                'url' => '/language',
            ];
        }

        return $items;
    }

    private function countDictionaryEntries(): int
    {
        try {
            $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        } catch (\RuntimeException|\PDOException) {
            return 0;
        }

        $result = $storage->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->count()
            ->execute();

        return (int) ($result[0] ?? 0);
    }
}
