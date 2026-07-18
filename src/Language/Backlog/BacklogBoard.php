<?php

declare(strict_types=1);

namespace App\Language\Backlog;

use Waaseyaa\Entity\EntityTypeManager;

/**
 * The staff-only read model for the translation backlog board (issue #906),
 * rendered into the /admin/anokii/language overview. Returns the awaiting rows
 * ranked by demand (distinct sites, then total occurrences) and grouped by
 * concept_key so surface variants sit together.
 *
 * Read-only and admin-only: the route's requireRole(admin,elder_coordinator) is
 * the access boundary, so the query uses accessCheck(false) (see the bypass
 * audit doc). Nothing here is exposed on /api/lang or any public route.
 */
final class BacklogBoard
{
    public function __construct(private readonly EntityTypeManager $entityTypeManager)
    {
    }

    /**
     * @return array{
     *   total: int,
     *   by_category: array<string, int>,
     *   concepts: list<array{concept_key: string, demand_sites: int, demand_total: int, category: string, forms: list<array{english_text: string, demand_sites: int, demand_total: int, category: string}>}>
     * }
     */
    public function summarize(): array
    {
        $empty = ['total' => 0, 'by_category' => [], 'concepts' => []];
        if (!$this->entityTypeManager->hasDefinition('tm_backlog')) {
            return $empty;
        }

        $repository = $this->entityTypeManager->getRepository('tm_backlog');
        $ids = $repository->getQuery()
            ->accessCheck(false)
            ->condition('status', 'awaiting_translation')
            ->execute();
        if ($ids === []) {
            return $empty;
        }

        $byCategory = [];
        $groups = [];
        $total = 0;
        foreach ($repository->findMany($ids) as $row) {
            $text = (string) $row->get('english_text');
            if ($text === '') {
                continue;
            }
            ++$total;
            $category = (string) ($row->get('category') ?? 'other');
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            $concept = (string) ($row->get('concept_key') ?? $text);
            $sites = (int) $row->get('demand_sites');
            $totalCount = (int) $row->get('demand_total');

            $groups[$concept] ??= ['concept_key' => $concept, 'demand_sites' => 0, 'demand_total' => 0, 'category' => $category, 'forms' => []];
            $groups[$concept]['forms'][] = ['english_text' => $text, 'demand_sites' => $sites, 'demand_total' => $totalCount, 'category' => $category];
            $groups[$concept]['demand_sites'] = max($groups[$concept]['demand_sites'], $sites);
            $groups[$concept]['demand_total'] = max($groups[$concept]['demand_total'], $totalCount);
        }

        foreach ($groups as &$group) {
            usort($group['forms'], static fn (array $a, array $b): int => $b['demand_sites'] <=> $a['demand_sites']
                ?: $b['demand_total'] <=> $a['demand_total']
                ?: strcmp($a['english_text'], $b['english_text']));
            // The concept's headline category is its strongest form's category.
            $group['category'] = $group['forms'][0]['category'];
        }
        unset($group);

        $concepts = array_values($groups);
        usort($concepts, static fn (array $a, array $b): int => $b['demand_sites'] <=> $a['demand_sites']
            ?: $b['demand_total'] <=> $a['demand_total']
            ?: strcmp($a['concept_key'], $b['concept_key']));

        ksort($byCategory);

        return ['total' => $total, 'by_category' => $byCategory, 'concepts' => $concepts];
    }
}
