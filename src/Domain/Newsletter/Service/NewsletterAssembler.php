<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

use Minoo\Domain\Newsletter\Assembler\ItemCandidate;
use Minoo\Domain\Newsletter\ValueObject\EditionStatus;
use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterAssembler
{
    /**
     * @param list<SectionQuota> $quotas
     */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EditionLifecycle $lifecycle,
        private readonly array $quotas,
    ) {}

    public function assemble(EntityInterface $edition): void
    {
        // Wipe any existing items for re-assembly idempotency.
        $this->clearExistingItems($edition->id());

        $position = 0;
        $totalWritten = 0;

        foreach ($this->quotas as $quota) {
            $candidates = $this->candidatesForSection($quota, $edition);
            $taken = array_slice($candidates, 0, $quota->quota);

            foreach ($taken as $candidate) {
                $this->writeItem($edition, $candidate, ++$position);
                $totalWritten++;
            }
        }

        if ($totalWritten === 0) {
            // Caller checks status; leave in draft and surface to UI.
            return;
        }

        $this->lifecycle->transition($edition, EditionStatus::Curating);
    }

    // TODO(post-v1): use getQuery()->range(0, N)->sort('publish_date','DESC')
    // per source instead of loadMultiple() once EntityStorageInterface query
    // capabilities stabilize. v1 scale (1 community, monthly cadence, hundreds
    // of candidate entities) is fine with in-memory filtering.
    /**
     * @return list<ItemCandidate>
     */
    private function candidatesForSection(SectionQuota $quota, EntityInterface $edition): array
    {
        $candidates = [];
        foreach ($quota->sources as $source) {
            if ($source === 'newsletter_submission') {
                $candidates = [...$candidates, ...$this->submissionCandidates($quota, $edition)];
                continue;
            }

            $storage = $this->entityTypeManager->getStorage($source);
            $entities = $storage->loadMultiple();
            foreach ($entities as $entity) {
                $candidates[] = new ItemCandidate(
                    section: $quota->name,
                    sourceType: $source,
                    sourceId: (int) $entity->id(),
                    blurb: (string) ($entity->get('title') ?? $entity->label()),
                    score: $this->scoreByRecency($entity),
                );
            }
        }

        usort($candidates, fn(ItemCandidate $a, ItemCandidate $b) => $b->score <=> $a->score);
        return $candidates;
    }

    /**
     * @return list<ItemCandidate>
     */
    private function submissionCandidates(SectionQuota $quota, EntityInterface $edition): array
    {
        $editionCommunity = (string) ($edition->get('community_id') ?? '');
        if ($editionCommunity === '') {
            // Regional editions don't pull submissions in v1 — see TODO in task plan
            // for regional aggregation logic.
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $candidates = [];
        foreach ($storage->loadMultiple() as $sub) {
            if ((string) $sub->get('status') !== 'approved') {
                continue;
            }
            if ((string) $sub->get('community_id') !== $editionCommunity) {
                continue;
            }
            $candidates[] = new ItemCandidate(
                section: $quota->name,
                sourceType: 'newsletter_submission',
                sourceId: (int) $sub->id(),
                blurb: (string) $sub->get('title'),
                score: $this->scoreByRecency($sub),
            );
        }
        return $candidates;
    }

    private function scoreByRecency(EntityInterface $entity): float
    {
        $date = (string) ($entity->get('publish_date') ?? $entity->get('submitted_at') ?? $entity->get('created_at') ?? '');
        if ($date === '') {
            return 0.0;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return 0.0;
        }
        // More recent → higher score. 30 day half-life.
        $age = max(0, time() - $ts);
        return 1.0 / (1.0 + ($age / (30 * 86400)));
    }

    private function writeItem(EntityInterface $edition, ItemCandidate $candidate, int $position): void
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_item');
        $item = $storage->create([
            'edition_id' => (int) $edition->id(),
            'position' => $position,
            'section' => $candidate->section,
            'source_type' => $candidate->sourceType,
            'source_id' => $candidate->sourceId,
            'editor_blurb' => $candidate->blurb,
            'included' => 1,
        ]);
        $storage->save($item);
    }

    private function clearExistingItems(int|string $editionId): void
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_item');
        $existing = [];
        foreach ($storage->loadMultiple() as $item) {
            if ((string) $item->get('edition_id') === (string) $editionId) {
                $existing[] = $item;
            }
        }
        if ($existing !== []) {
            $storage->delete($existing);
        }
    }
}
