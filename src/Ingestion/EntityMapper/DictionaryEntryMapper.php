<?php

declare(strict_types=1);

namespace App\Ingestion\EntityMapper;

use Waaseyaa\Foundation\SlugGenerator;
use App\Ingestion\ValueObject\DictionaryEntryFields;

final class DictionaryEntryMapper
{
    /**
     * Map a NorthCloud dictionary entry payload to Minoo entity fields.
     *
     * Accepts both the ingest-pipeline format (definition, part_of_speech, stem,
     * inflected_forms) and the NC dictionary API format (definitions, word_class_normalized,
     * inflections, source_url, attribution).
     *
     * @param array<string, mixed> $data Payload data block
     * @param string $sourceUrl Envelope source_url (overridden by $data['source_url'] if present)
     * @param string $attribution Attribution string from the API (X-Attribution header)
     */
    public function map(array $data, string $sourceUrl = '', string $attribution = ''): DictionaryEntryFields
    {
        $lemma = (string) ($data['lemma'] ?? '');

        // NC API uses "definitions" (JSON-encoded array string or plain string);
        // ingest pipeline uses "definition" (string|array).
        $definition = $data['definitions'] ?? $data['definition'] ?? '';
        if (is_string($definition) && str_starts_with($definition, '[')) {
            $decoded = json_decode($definition, true);
            if (is_array($decoded)) {
                $definition = $decoded;
            }
        }
        if (is_array($definition)) {
            $definition = implode('; ', array_filter($definition, 'is_string'));
        }

        // NC API uses "word_class_normalized" or "word_class"; ingest pipeline uses "part_of_speech".
        $partOfSpeech = (string) ($data['word_class_normalized'] ?? $data['word_class'] ?? $data['part_of_speech'] ?? '');

        // NC API uses "inflections" (JSON-encoded string or plain string);
        // ingest pipeline uses "inflected_forms" (array).
        $inflectedForms = '';
        if (isset($data['inflections'])) {
            $raw = $data['inflections'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if ($decoded === null) {
                    // Plain text inflections (e.g. "makwag pl; makoons dim").
                    $inflectedForms = $raw;
                } elseif (is_array($decoded) && $decoded !== []) {
                    $inflectedForms = json_encode($decoded);
                }
                // else: "{}" or "[]" → remains empty string.
            } else {
                $inflectedForms = json_encode($raw);
            }
        } elseif (isset($data['inflected_forms'])) {
            $inflectedForms = json_encode($data['inflected_forms']);
        }

        // Prefer source_url from the entry itself (NC API includes it per-entry).
        $resolvedSourceUrl = (string) ($data['source_url'] ?? $sourceUrl);

        // Attribution from entry or header.
        $entryAttribution = (string) ($data['attribution'] ?? $attribution);

        // NC API provides consent_public_display; publish entries that have public consent.
        $consentPublic = !empty($data['consent_public_display']) ? 1 : 0;
        $status = $consentPublic;

        return new DictionaryEntryFields(
            word: $lemma,
            definition: (string) $definition,
            partOfSpeech: $partOfSpeech,
            stem: (string) ($data['stem'] ?? ''),
            languageCode: (string) ($data['language_code'] ?? 'oj') ?: 'oj',
            inflectedForms: $inflectedForms,
            sourceUrl: $resolvedSourceUrl,
            slug: SlugGenerator::generate($lemma),
            status: $status,
            consentPublic: $consentPublic,
            createdAt: time(),
            updatedAt: time(),
            attributionSource: $entryAttribution,
            attributionUrl: $resolvedSourceUrl,
        );
    }
}
