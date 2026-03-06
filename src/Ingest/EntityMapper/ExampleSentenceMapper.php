<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class ExampleSentenceMapper
{
    /** @return array<string, mixed> */
    public function map(array $data, int $dictionaryEntryId, ?int $speakerId, string $languageCode): array
    {
        return [
            'ojibwe_text' => (string) ($data['ojibwe_text'] ?? ''),
            'english_text' => (string) ($data['english_text'] ?? ''),
            'dictionary_entry_id' => $dictionaryEntryId,
            'speaker_id' => $speakerId,
            'language_code' => $languageCode,
            'audio_url' => (string) ($data['audio_url'] ?? ''),
            'source_sentence_id' => (string) ($data['source_sentence_id'] ?? ''),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
