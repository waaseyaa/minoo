<?php

declare(strict_types=1);

namespace App\Ingestion\Curation;

/** Outcome of promoting one utterance into vocabulary. */
final readonly class PromotionResult
{
    /**
     * @param list<int> $wordPartIds
     */
    public function __construct(
        public int $dictionaryEntryId,
        public array $wordPartIds,
        public bool $created,
    ) {
    }
}
