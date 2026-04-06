<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Entity\DictionaryEntry;
use Minoo\Support\LayoutTwigContext;
use Minoo\Support\NorthCloudClient;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class LanguageController
{
    private const int PAGE_SIZE = 50;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly NorthCloudClient $northCloudClient,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $storage = $this->entityTypeManager->getStorage('dictionary_entry');

        $allIds = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('word', 'ASC')
            ->execute();

        $total = count($allIds);
        $pageIds = array_slice($allIds, $offset, self::PAGE_SIZE);
        $entries = $pageIds !== [] ? array_values($storage->loadMultiple($pageIds)) : [];
        $totalPages = (int) ceil($total / self::PAGE_SIZE);

        $html = $this->twig->render('language.html.twig', LayoutTwigContext::withAccount($account, [
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
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $entry = $ids !== [] ? $storage->load(reset($ids)) : null;
        if (!$entry instanceof DictionaryEntry) {
            $entry = null;
        }

        $html = $this->twig->render('language.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language/' . $slug,
            'entry' => $entry,
            'inflected_forms' => $entry !== null ? $this->parseInflectedForms((string) $entry->get('inflected_forms')) : [],
        ]));

        return new Response($html, $entry !== null ? 200 : 404);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function search(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $q = trim((string) ($query['q'] ?? ''));

        $searchResults = [];
        $searchTotal = 0;

        if ($q !== '') {
            $response = $this->northCloudClient->searchDictionary($q);
            if ($response !== null) {
                $searchResults = array_map($this->normalizeSearchResult(...), $response['entries']);
                $searchTotal = $response['total'];
            }
        }

        $html = $this->twig->render('language.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language/search',
            'search_query' => $q,
            'search_results' => $searchResults,
            'search_total' => $searchTotal,
        ]));

        return new Response($html);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function normalizeSearchResult(array $entry): array
    {
        $defs = $entry['definitions'] ?? '';
        if (is_string($defs)) {
            $decoded = json_decode($defs, true);
            $entry['definitions'] = is_array($decoded) ? implode('; ', $decoded) : $defs;
        } elseif (is_array($defs)) {
            $entry['definitions'] = implode('; ', $defs);
        }
        return $entry;
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
