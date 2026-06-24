<?php

declare(strict_types=1);

namespace App\Ingestion\Curation;

use App\Anokii\Pipeline\PipelineStage;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Promotes a corpus utterance (an example_sentence) into structured vocabulary
 * (#855): a `dictionary_entry` (word ← Ojibwe, definition ← English) plus a
 * `word_part` per whitespace token for multi-word phrases. The source sentence
 * is linked back via `dictionary_entry_id`.
 *
 * Consent + publication state are COPIED from the source sentence, so an
 * unreviewed draft promotes to a draft entry and never leaks to public surfaces.
 * Ojibwe is carried verbatim — never normalized (ADR 0003). Idempotent: a
 * sentence already linked to a dictionary_entry is returned unchanged.
 */
final class UtterancePromoter
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    public function promote(int $esid): ?PromotionResult
    {
        $sentences = $this->entityTypeManager->getStorage('example_sentence');
        $sentence = $sentences->load($esid);
        if (!$sentence instanceof EntityInterface) {
            return null;
        }

        $existing = (int) ($sentence->get('dictionary_entry_id') ?? 0);
        if ($existing > 0) {
            return new PromotionResult($existing, [], false);
        }

        $word = (string) $sentence->get('ojibwe_text');
        if (trim($word) === '') {
            return null;
        }
        $definition = (string) $sentence->get('english_text');
        $sourceUrl = (string) $sentence->get('source_url');
        $consentPublic = (int) ($sentence->get('consent_public') ?? 0);
        $status = (int) ($sentence->get('status') ?? 0);
        $now = time();

        $entries = $this->entityTypeManager->getStorage('dictionary_entry');
        $entry = $entries->create([
            // Verbatim — never normalized (ADR 0003).
            'word' => $word,
            'slug' => $this->slugify($word),
            'definition' => $definition,
            'language_code' => 'oj',
            'source_url' => $sourceUrl,
            'attribution_source' => 'corpus',
            'attribution_url' => $sourceUrl,
            'consent_public' => $consentPublic,
            'consent_ai_training' => (int) ($sentence->get('consent_ai_training') ?? 0),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $entries->save($entry);
        $deid = (int) $entry->id();

        // Decompose a multi-word phrase into one word_part per token.
        $wordPartIds = [];
        $tokens = preg_split('/\s+/', trim($word), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) > 1) {
            $parts = $this->entityTypeManager->getStorage('word_part');
            foreach ($tokens as $token) {
                $part = $parts->create([
                    'form' => $token,
                    'slug' => $this->slugify($token),
                    'type' => '',
                    'definition' => '',
                    'source_url' => $sourceUrl,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $parts->save($part);
                $wordPartIds[] = (int) $part->id();
            }
        }

        // Link the source sentence back to the new entry and advance its pipeline
        // stage to curated (#878). Stays a draft (status unchanged) until published.
        $sentence->set('dictionary_entry_id', $deid);
        $sentence->set('pipeline_status', PipelineStage::CURATED);
        $sentence->set('updated_at', $now);
        $sentences->save($sentence);

        return new PromotionResult($deid, $wordPartIds, true);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/u', '-', $slug);

        return trim($slug, '-');
    }
}
