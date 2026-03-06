<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class DictionaryEntryMapper
{
    /**
     * Map a NorthCloud dictionary entry payload to Minoo entity fields.
     *
     * @param array<string, mixed> $data Payload data block
     * @param string $sourceUrl Envelope source_url
     * @return array<string, mixed> Mapped entity fields
     */
    public function map(array $data, string $sourceUrl): array
    {
        $lemma = (string) ($data['lemma'] ?? '');
        $definition = $data['definition'] ?? '';
        if (is_array($definition)) {
            $definition = implode('; ', array_filter($definition, 'is_string'));
        }

        return [
            'word' => $lemma,
            'definition' => (string) $definition,
            'part_of_speech' => (string) ($data['part_of_speech'] ?? ''),
            'stem' => (string) ($data['stem'] ?? ''),
            'language_code' => (string) ($data['language_code'] ?? 'oj') ?: 'oj',
            'inflected_forms' => isset($data['inflected_forms']) ? json_encode($data['inflected_forms']) : '',
            'source_url' => $sourceUrl,
            'slug' => self::generateSlug($lemma),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    public static function generateSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
