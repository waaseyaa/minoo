<?php

declare(strict_types=1);

namespace Minoo\Ingestion\ValueObject;

final readonly class DictionaryEntryFields
{
    public function __construct(
        public string $word,
        public string $definition,
        public string $partOfSpeech,
        public string $stem,
        public string $languageCode,
        public string $inflectedForms,
        public string $sourceUrl,
        public string $slug,
        public int $status,
        public int $createdAt,
        public int $updatedAt,
        public string $attributionSource = '',
        public string $attributionUrl = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'word' => $this->word,
            'definition' => $this->definition,
            'part_of_speech' => $this->partOfSpeech,
            'stem' => $this->stem,
            'language_code' => $this->languageCode,
            'inflected_forms' => $this->inflectedForms,
            'source_url' => $this->sourceUrl,
            'slug' => $this->slug,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'attribution_source' => $this->attributionSource,
            'attribution_url' => $this->attributionUrl,
        ];
    }
}
