<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

use Minoo\Ingest\SlugGenerator;
use Minoo\Ingest\ValueObject\DictionaryEntryFields;

final class DictionaryEntryMapper
{
    /**
     * Map a NorthCloud dictionary entry payload to Minoo entity fields.
     *
     * @param array<string, mixed> $data Payload data block
     * @param string $sourceUrl Envelope source_url
     */
    public function map(array $data, string $sourceUrl): DictionaryEntryFields
    {
        $lemma = (string) ($data['lemma'] ?? '');
        $definition = $data['definition'] ?? '';
        if (is_array($definition)) {
            $definition = implode('; ', array_filter($definition, 'is_string'));
        }

        return new DictionaryEntryFields(
            word: $lemma,
            definition: (string) $definition,
            partOfSpeech: (string) ($data['part_of_speech'] ?? ''),
            stem: (string) ($data['stem'] ?? ''),
            languageCode: (string) ($data['language_code'] ?? 'oj') ?: 'oj',
            inflectedForms: isset($data['inflected_forms']) ? json_encode($data['inflected_forms']) : '',
            sourceUrl: $sourceUrl,
            slug: SlugGenerator::generate($lemma),
            status: 0,
            createdAt: time(),
            updatedAt: time(),
        );
    }
}
