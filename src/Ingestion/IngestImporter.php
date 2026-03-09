<?php

declare(strict_types=1);

namespace Minoo\Ingestion;

use Minoo\Entity\IngestLog;
use Minoo\Ingestion\EntityMapper\CulturalCollectionMapper;
use Minoo\Ingestion\EntityMapper\DictionaryEntryMapper;
use Minoo\Ingestion\EntityMapper\SpeakerMapper;

final class IngestImporter
{
    public function __construct(
        private readonly PayloadValidator $validator,
    ) {}

    public function import(array $envelope): IngestLog
    {
        // Validate.
        $result = $this->validator->validate($envelope);
        if (!$result->isValid()) {
            return $this->createFailedLog($envelope, implode('; ', $result->errors));
        }

        // Map data to entity fields.
        $entityType = (string) $envelope['entity_type'];
        $sourceUrl = (string) $envelope['source_url'];
        $data = (array) $envelope['data'];

        $mapped = match ($entityType) {
            'dictionary_entry' => (new DictionaryEntryMapper())->map($data, $sourceUrl),
            'speaker' => (new SpeakerMapper())->map($data),
            'cultural_collection' => (new CulturalCollectionMapper())->map($data, $sourceUrl),
            default => throw new \LogicException(
                sprintf('No mapper registered for validated entity type: %s', $entityType),
            ),
        };

        $source = (string) ($envelope['source'] ?? 'unknown');
        $timestamp = (string) ($envelope['timestamp'] ?? date('c'));

        return new IngestLog([
            'title' => sprintf('%s — %s', $source, $timestamp),
            'source' => $source,
            'entity_type_target' => $entityType,
            'payload_raw' => json_encode($envelope, JSON_THROW_ON_ERROR),
            'payload_parsed' => json_encode($mapped->toArray(), JSON_THROW_ON_ERROR),
            'status' => IngestStatus::PendingReview->value,
        ]);
    }

    private function createFailedLog(array $envelope, string $error): IngestLog
    {
        return new IngestLog([
            'title' => sprintf('%s — failed', (string) ($envelope['source'] ?? 'unknown')),
            'source' => (string) ($envelope['source'] ?? 'unknown'),
            'entity_type_target' => (string) ($envelope['entity_type'] ?? ''),
            'payload_raw' => json_encode($envelope, JSON_THROW_ON_ERROR),
            'payload_parsed' => '{}',
            'status' => IngestStatus::Failed->value,
            'error_message' => $error,
        ]);
    }
}
