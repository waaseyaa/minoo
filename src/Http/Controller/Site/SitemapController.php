<?php

declare(strict_types=1);

namespace App\Http\Controller\Site;

use App\Domain\Community\MamaweswenNations;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * XML sitemap (#807). Lists the public, indexable surface: section pages, the
 * community living-map, games, and a bounded set of dictionary entries. Capped
 * so the response stays well inside the performance budget (#808) — a future
 * sitemap-index can page the full 21k dictionary if needed.
 */
final class SitemapController
{
    /** Upper bound on dictionary-entry URLs to keep the response light. */
    private const int ENTRY_LIMIT = 1000;

    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function xml(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        // /language/search is intentionally excluded (it is noindex). Lessons
        // are indexable learning surfaces (#865).
        $paths = [
            '/', '/lessons', '/lessons/the-kitchen', '/language', '/communities', '/games',
            '/games/shkoda', '/games/matcher', '/games/crossword', '/games/agim', '/games/journey',
            '/about', '/data-sovereignty', '/legal/privacy',
        ];

        foreach (MamaweswenNations::all() as $nation) {
            $paths[] = '/communities/' . $nation['slug'];
        }

        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('word', 'ASC')
            ->range(0, self::ENTRY_LIMIT)
            ->execute();

        if ($ids !== []) {
            foreach ($storage->loadMultiple($ids) as $entry) {
                $slug = (string) $entry->get('slug');
                if ($slug !== '') {
                    $paths[] = '/language/' . $slug;
                }
            }
        }

        $xml = $this->twig->render('sitemap.xml.twig', ['paths' => $paths]);

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
