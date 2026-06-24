<?php

declare(strict_types=1);

namespace App\Anokii\Pipeline;

use Waaseyaa\Entity\EntityInterface;

/**
 * Resolves the effective {@see PipelineStage} of a corpus example_sentence (#876).
 *
 * An explicit `pipeline_status` value (set by the workspace transitions) always
 * wins. For legacy rows — imported before the field existed — the stage is
 * derived from the row's shape so the dashboard works without a backfill, and
 * the backfill command ({@see \App\Console\AnokiiBackfillStatusHandler}) can
 * persist what this derives.
 */
final class PipelineStageResolver
{
    public function resolve(EntityInterface $sentence): string
    {
        $explicit = trim((string) ($sentence->get('pipeline_status') ?? ''));
        if ($explicit !== '' && PipelineStage::isValid($explicit)) {
            return $explicit;
        }

        return $this->derive($sentence);
    }

    /**
     * Best-effort stage for a legacy row with no explicit pipeline_status.
     */
    private function derive(EntityInterface $sentence): string
    {
        $status = (int) ($sentence->get('status') ?? 0);
        $consentPublic = (int) ($sentence->get('consent_public') ?? 0);
        $dictionaryEntryId = (int) ($sentence->get('dictionary_entry_id') ?? 0);
        $ojibwe = trim((string) $sentence->get('ojibwe_text'));
        $english = trim((string) $sentence->get('english_text'));

        // Promoted to vocabulary: curated, or published once it is publicly live.
        if ($dictionaryEntryId > 0) {
            return ($status === 1 && $consentPublic === 1) ? PipelineStage::PUBLISHED : PipelineStage::CURATED;
        }

        // Both languages confirmed but not yet promoted: ready to curate.
        if ($ojibwe !== '' && $english !== '') {
            return PipelineStage::TRANSCRIBED;
        }

        // Some draft text or media exists but the transcription is incomplete.
        $hasMedia = trim((string) $sentence->get('thumbnail_url')) !== ''
            || trim((string) $sentence->get('audio_url')) !== ''
            || trim((string) $sentence->get('video_url')) !== '';
        if ($ojibwe !== '' || $english !== '' || $hasMedia) {
            return PipelineStage::DRAFTED;
        }

        return PipelineStage::INGESTED;
    }
}
