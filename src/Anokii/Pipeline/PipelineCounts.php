<?php

declare(strict_types=1);

namespace App\Anokii\Pipeline;

use Waaseyaa\Entity\EntityTypeManager;

/**
 * Computes the live pipeline funnel for the Anokii overview (#876): how many
 * corpus items sit at each {@see PipelineStage}, and which actionable stage has
 * the most pending work (the "do next" CTA).
 *
 * Scope is the corpus (example_sentence rows whose source_sentence_id is
 * `corpus:*`) — the same set the Transcribe/Curate tabs work on. Stage is taken
 * from {@see PipelineStageResolver}, so legacy rows count correctly without a
 * backfill.
 */
final class PipelineCounts
{
    /**
     * Actionable stages, in the order a reviewer should clear them. The "do
     * next" CTA picks the actionable stage with the most items.
     */
    private const array ACTIONABLE = [
        PipelineStage::DRAFTED,
        PipelineStage::TRANSCRIBED,
        PipelineStage::INGESTED,
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly PipelineStageResolver $resolver = new PipelineStageResolver(),
    ) {
    }

    /**
     * @return array{
     *   counts: array<string, int>,
     *   total: int,
     *   do_next: array{stage: string, label: string, href: string, count: int}|null
     * }
     */
    public function compute(): array
    {
        $counts = array_fill_keys(PipelineStage::ORDER, 0);

        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return ['counts' => $counts, 'total' => 0, 'do_next' => null];
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        // accessCheck(false): staff-gated overview, must count UNREVIEWED drafts
        // (status 0 / consent off) the same way the Transcribe/Curate tools show
        // them. The route's requireRole is the access boundary. See the bypass
        // audit doc.
        $ids = $storage->getQuery()
            ->accessCheck(false)
            ->condition('source_sentence_id', 'corpus:%', 'LIKE')
            ->execute();

        if ($ids === []) {
            return ['counts' => $counts, 'total' => 0, 'do_next' => null];
        }

        $total = 0;
        foreach ($storage->loadMultiple($ids) as $sentence) {
            $stage = $this->resolver->resolve($sentence);
            $counts[$stage] = ($counts[$stage] ?? 0) + 1;
            ++$total;
        }

        return ['counts' => $counts, 'total' => $total, 'do_next' => $this->doNext($counts)];
    }

    /**
     * @param array<string, int> $counts
     *
     * @return array{stage: string, label: string, href: string, count: int}|null
     */
    private function doNext(array $counts): ?array
    {
        $best = null;
        foreach (self::ACTIONABLE as $stage) {
            $count = $counts[$stage] ?? 0;
            if ($count > 0 && ($best === null || $count > $best['count'])) {
                $best = ['stage' => $stage, 'label' => PipelineStage::label($stage), 'href' => PipelineStage::href($stage), 'count' => $count];
            }
        }

        return $best;
    }
}
