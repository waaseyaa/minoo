<?php

declare(strict_types=1);

namespace App\Language\Backlog;

/**
 * Turns the recon ranked output into the curated backlog dataset (issue #906),
 * applying {@see BacklogRuleSet}: keep strings on >= MIN_SITES distinct sites,
 * hard-drop theme/date noise, exclude already-Anishinaabemowin strings (logging
 * them), cluster surface forms under a concept_key, and categorise each row.
 *
 * Pure: takes the ranked rows in, returns the dataset out. The dev generator
 * ({@see \scripts\build_translation_backlog_seed.php}) feeds it the recon
 * out.json and writes the committed seed file; the seeder never re-derives.
 */
final class BacklogBuilder
{
    /**
     * @param list<array<string, mixed>> $ranked Recon ranked rows (untrusted JSON shape)
     *
     * @return array{
     *   rows: list<array{english_text: string, concept_key: string, demand_sites: int, demand_total: int, category: string}>,
     *   excluded_anishinaabemowin: list<string>,
     *   stats: array{considered: int, below_floor: int, dropped_noise: int, excluded_ojibwe: int, kept: int}
     * }
     */
    public function build(array $ranked): array
    {
        $rows = [];
        $excluded = [];
        $belowFloor = 0;
        $droppedNoise = 0;

        foreach ($ranked as $r) {
            $text = trim((string) ($r['string'] ?? ''));
            $sites = (int) ($r['distinct_sites'] ?? 0);
            $total = (int) ($r['total'] ?? 0);

            if ($text === '') {
                continue;
            }
            if ($sites < BacklogRuleSet::MIN_SITES) {
                ++$belowFloor;
                continue;
            }
            if (BacklogRuleSet::shouldDrop($text)) {
                ++$droppedNoise;
                continue;
            }
            if (BacklogRuleSet::isAnishinaabemowin($text)) {
                $excluded[] = $text;
                continue;
            }

            $rows[] = [
                'english_text' => $text,
                'concept_key' => BacklogRuleSet::conceptKey($text),
                'demand_sites' => $sites,
                'demand_total' => $total,
                'category' => BacklogRuleSet::categoryFor($text),
            ];
        }

        // Primary rank: distinct sites; secondary: total occurrences.
        usort($rows, static fn (array $a, array $b): int => $b['demand_sites'] <=> $a['demand_sites']
            ?: $b['demand_total'] <=> $a['demand_total']
            ?: strcmp($a['english_text'], $b['english_text']));

        return [
            'rows' => $rows,
            'excluded_anishinaabemowin' => $excluded,
            'stats' => [
                'considered' => count($ranked),
                'below_floor' => $belowFloor,
                'dropped_noise' => $droppedNoise,
                'excluded_ojibwe' => count($excluded),
                'kept' => count($rows),
            ],
        ];
    }
}
