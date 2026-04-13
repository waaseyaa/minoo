<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Api\JsonResponseTrait;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterAdminApiController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function listEditions(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = $storage->loadMultiple();

        $result = [];
        foreach ($editions as $edition) {
            $result[] = [
                'id' => $edition->id(),
                'headline' => $edition->get('headline'),
                'volume' => $edition->get('volume'),
                'issue_number' => $edition->get('issue_number'),
                'community_id' => $edition->get('community_id'),
                'status' => $edition->get('status'),
                'created_at' => $edition->get('created_at'),
            ];
        }

        return $this->json($result);
    }

    public function createEdition(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $body = $this->jsonBody($request);
        if ($body === []) {
            return $this->json(['error' => 'Request body must be valid JSON.'], 422);
        }

        $required = ['headline', 'volume', 'issue_number', 'community_id'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return $this->json(['error' => 'Missing required fields: ' . implode(', ', $missing)], 422);
        }

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $storage->create([
            'headline' => $body['headline'],
            'volume' => (int) $body['volume'],
            'issue_number' => (int) $body['issue_number'],
            'community_id' => $body['community_id'],
            'status' => 'draft',
            'created_by' => (int) $account->id(),
            'created_at' => time(),
        ]);
        $storage->save($edition);

        return $this->json([
            'id' => $edition->id(),
            'headline' => $edition->get('headline'),
            'volume' => $edition->get('volume'),
            'issue_number' => $edition->get('issue_number'),
            'community_id' => $edition->get('community_id'),
            'status' => $edition->get('status'),
            'created_by' => $edition->get('created_by'),
            'created_at' => $edition->get('created_at'),
        ], 201);
    }

    public function getEdition(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(['error' => 'Invalid id.'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $storage->load($id);
        if ($edition === null) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        // Build section order from config
        $config = $this->loadNewsletterConfig();
        $inlineSections = array_keys($config['inline_sections'] ?? []);
        $contentSections = array_keys($config['sections'] ?? []);
        $sectionOrder = array_merge($inlineSections, $contentSections);

        // Load items for this edition
        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $itemQuery = $itemStorage->getQuery();
        $items = $itemQuery->condition('edition_id', $id)->execute();

        // Group items by section, init all sections as empty
        $itemsBySection = [];
        foreach ($sectionOrder as $section) {
            $itemsBySection[$section] = [];
        }

        foreach ($items as $item) {
            $section = (string) $item->get('section');
            $itemsBySection[$section][] = [
                'id' => $item->id(),
                'edition_id' => $item->get('edition_id'),
                'section' => $section,
                'position' => $item->get('position'),
                'source_type' => $item->get('source_type'),
                'source_id' => $item->get('source_id'),
                'inline_title' => $item->get('inline_title'),
                'inline_body' => $item->get('inline_body'),
                'editor_blurb' => $item->get('editor_blurb'),
                'included' => $item->get('included'),
            ];
        }

        // Sort items within each section by position
        foreach ($itemsBySection as &$sectionItems) {
            usort($sectionItems, fn (array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        }
        unset($sectionItems);

        return $this->json([
            'edition' => [
                'id' => $edition->id(),
                'headline' => $edition->get('headline'),
                'volume' => $edition->get('volume'),
                'issue_number' => $edition->get('issue_number'),
                'community_id' => $edition->get('community_id'),
                'status' => $edition->get('status'),
                'created_by' => $edition->get('created_by'),
                'pdf_path' => $edition->get('pdf_path'),
                'pdf_hash' => $edition->get('pdf_hash'),
                'sent_at' => $edition->get('sent_at'),
                'created_at' => $edition->get('created_at'),
            ],
            'items_by_section' => $itemsBySection,
            'section_order' => $sectionOrder,
        ]);
    }

    /** @return array<string, mixed> */
    private function loadNewsletterConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/newsletter.php';
        if (!is_file($configPath)) {
            return ['sections' => [], 'inline_sections' => []];
        }

        return require $configPath;
    }
}
