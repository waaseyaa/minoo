<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use App\Entity\Language\DictionaryEntry;
use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

final class LanguageController
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $storage = $this->entityTypeManager->getStorage('dictionary_entry');

        $allIds = $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('word', 'ASC')
            ->execute();

        $total = count($allIds);
        $pageIds = array_slice($allIds, $offset, self::PAGE_SIZE);
        $entries = $pageIds !== [] ? array_values($storage->loadMultiple($pageIds)) : [];
        $totalPages = (int) ceil($total / self::PAGE_SIZE);

        $html = $this->twig->render('pages/language/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language',
            'entries' => $entries,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_entries' => $total,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $entry = $ids !== [] ? $storage->load(reset($ids)) : null;
        if (!$entry instanceof DictionaryEntry) {
            $entry = null;
        }

        $html = $this->twig->render('pages/language/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language/' . $slug,
            'entry' => $entry,
            'inflected_forms' => $entry !== null ? $this->parseInflectedForms((string) $entry->get('inflected_forms')) : [],
        ]));

        return new Response($html, $entry !== null ? 200 : 404);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function search(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $q = trim((string) ($query['q'] ?? ''));

        $searchResults = [];
        $searchTotal = 0;

        if ($q !== '') {
            // Local dictionary search (2026-06 slimming replaced the
            // NorthCloud-backed search). Word matches rank before
            // definition matches; both respect status + consent gates.
            $storage = $this->entityTypeManager->getStorage('dictionary_entry');
            $like = '%' . addcslashes($q, '%_\\') . '%';

            $wordIds = $storage->getQuery()->setAccount($account)
                ->condition('status', 1)
                ->condition('consent_public', 1)
                ->condition('word', $like, 'LIKE')
                ->sort('word', 'ASC')
                ->execute();

            $definitionIds = $storage->getQuery()->setAccount($account)
                ->condition('status', 1)
                ->condition('consent_public', 1)
                ->condition('definition', $like, 'LIKE')
                ->sort('word', 'ASC')
                ->execute();

            $ids = array_values(array_unique(array_merge($wordIds, $definitionIds)));
            $searchTotal = count($ids);
            $entries = $ids !== [] ? $storage->loadMultiple(array_slice($ids, 0, self::PAGE_SIZE)) : [];

            foreach ($entries as $entry) {
                // Pass the raw definition through; the view applies the shared
                // clean_definition filter so the dictionary and games render
                // identical text. Dialect is shown on every card.
                $searchResults[] = [
                    'word' => (string) $entry->get('word'),
                    'slug' => (string) $entry->get('slug'),
                    'part_of_speech' => (string) $entry->get('part_of_speech'),
                    'definition' => (string) $entry->get('definition'),
                    'language_code' => (string) ($entry->get('language_code') ?: 'oj'),
                    'attribution_source' => (string) $entry->get('attribution_source'),
                ];
            }
        }

        $html = $this->twig->render('pages/language/search.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language/search',
            'search_query' => $q,
            'search_results' => $searchResults,
            'search_total' => $searchTotal,
        ]));

        return new Response($html);
    }

    /**
     * @return list<string>
     */
    private function parseInflectedForms(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [$raw];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $form = trim((string) ($item['form'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($form === '') {
                continue;
            }

            $items[] = $label !== '' ? sprintf('%s: %s', $label, $form) : $form;
        }

        return $items;
    }
}
