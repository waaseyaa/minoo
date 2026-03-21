<?php

declare(strict_types=1);

namespace Minoo\Ingestion\ValueObject;

final readonly class ExampleSentenceFields
{
    public function __construct(
        public string $ojibweText,
        public string $englishText,
        public int $dictionaryEntryId,
        public ?int $contributorId,
        public string $languageCode,
        public string $audioUrl,
        public string $sourceSentenceId,
        public int $status,
        public int $createdAt,
        public int $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ojibwe_text' => $this->ojibweText,
            'english_text' => $this->englishText,
            'dictionary_entry_id' => $this->dictionaryEntryId,
            'contributor_id' => $this->contributorId,
            'language_code' => $this->languageCode,
            'audio_url' => $this->audioUrl,
            'source_sentence_id' => $this->sourceSentenceId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
