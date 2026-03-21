<?php

declare(strict_types=1);

namespace Minoo\Ingestion\EntityMapper;

use Minoo\Ingestion\ValueObject\ExampleSentenceFields;

final class ExampleSentenceMapper
{
    /** @param array<string, mixed> $data */
    public function map(array $data, int $dictionaryEntryId, ?int $contributorId, string $languageCode): ExampleSentenceFields
    {
        return new ExampleSentenceFields(
            ojibweText: (string) ($data['ojibwe_text'] ?? ''),
            englishText: (string) ($data['english_text'] ?? ''),
            dictionaryEntryId: $dictionaryEntryId,
            contributorId: $contributorId,
            languageCode: $languageCode,
            audioUrl: (string) ($data['audio_url'] ?? ''),
            sourceSentenceId: (string) ($data['source_sentence_id'] ?? ''),
            status: 0,
            createdAt: time(),
            updatedAt: time(),
        );
    }
}
