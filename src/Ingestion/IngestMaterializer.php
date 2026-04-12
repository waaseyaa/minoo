<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\IngestLog;
use App\Ingestion\EntityMapper\ExampleSentenceMapper;
use App\Ingestion\EntityMapper\SpeakerMapper;
use App\Ingestion\EntityMapper\WordPartMapper;
use Waaseyaa\Entity\EntityTypeManagerInterface;

final class IngestMaterializer
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function materialize(IngestLog $log, bool $dryRun = false): MaterializationResult
    {
        $result = new MaterializationResult();
        $context = new MaterializationContext();

        $rawEnvelope = json_decode($log->get('payload_raw'), true);
        if ($rawEnvelope === null && $log->get('payload_raw') !== 'null') {
            throw new \RuntimeException(sprintf(
                'Failed to decode payload_raw: %s',
                json_last_error_msg(),
            ));
        }
        $rawEnvelope ??= [];

        $parsedFields = json_decode($log->get('payload_parsed'), true);
        if ($parsedFields === null && $log->get('payload_parsed') !== 'null') {
            throw new \RuntimeException(sprintf(
                'Failed to decode payload_parsed: %s',
                json_last_error_msg(),
            ));
        }
        $parsedFields ??= [];

        $entityType = (string) $log->get('entity_type_target');
        $data = $rawEnvelope['data'] ?? [];

        return match ($entityType) {
            'dictionary_entry' => $this->materializeDictionaryEntry($parsedFields, $data, $context, $result, $dryRun),
            'speaker', 'contributor' => $this->materializeSpeaker($parsedFields, $result, $dryRun),
            'cultural_collection' => $this->materializeCulturalCollection($parsedFields, $result, $dryRun),
            default => throw new \InvalidArgumentException(
                sprintf('Cannot materialize unsupported entity type: %s', $entityType),
            ),
        };
    }

    private function materializeDictionaryEntry(
        array $parsedFields,
        array $rawData,
        MaterializationContext $context,
        MaterializationResult $result,
        bool $dryRun,
    ): MaterializationResult {
        // 1. Resolve speakers from example sentences.
        $sentences = $rawData['example_sentences'] ?? [];
        foreach ($sentences as $sentence) {
            $code = (string) ($sentence['speaker_code'] ?? '');
            if ($code !== '' && $context->getSpeakerId($code) === null) {
                $speakerVO = SpeakerMapper::fromCode($code);
                $speakerFields = $speakerVO->toArray();
                if ($dryRun) {
                    $result->addCreated('contributor', $speakerFields);
                    $context->setSpeakerId($code, 0);
                } else {
                    $id = $this->getOrCreateSpeaker($code, $speakerFields);
                    $context->setSpeakerId($code, $id);
                    $result->addCreated('contributor', $speakerFields, $id);
                }
            }
        }

        // 2. Resolve word parts.
        $wordPartMapper = new WordPartMapper();
        $sourceUrl = (string) ($parsedFields['source_url'] ?? '');
        foreach ($rawData['word_parts'] ?? [] as $wpData) {
            $wpVO = $wordPartMapper->map($wpData, $sourceUrl);
            if ($wpVO === null) {
                $result->addSkipped(
                    'word_part',
                    (string) ($wpData['form'] ?? ''),
                    sprintf('Invalid morphological_role: %s', (string) ($wpData['morphological_role'] ?? '')),
                );
                continue;
            }
            $wpFields = $wpVO->toArray();
            if ($context->getWordPartId($wpVO->form, $wpVO->type) === null) {
                if ($dryRun) {
                    $result->addCreated('word_part', $wpFields);
                    $context->setWordPartId($wpVO->form, $wpVO->type, 0);
                } else {
                    $id = $this->getOrCreateWordPart($wpVO->form, $wpVO->type, $wpFields);
                    $context->setWordPartId($wpVO->form, $wpVO->type, $id);
                    $result->addCreated('word_part', $wpFields, $id);
                }
            }
        }

        // 3. Create dictionary entry.
        if ($dryRun) {
            $result->addCreated('dictionary_entry', $parsedFields);
        } else {
            $storage = $this->entityTypeManager->getStorage('dictionary_entry');
            $entity = $storage->create($parsedFields);
            $storage->save($entity);
            $entryId = (int) $entity->id();
            $result->addCreated('dictionary_entry', $parsedFields, $entryId);
            $result->setPrimaryEntityId($entryId);
        }

        // 4. Create example sentences.
        $sentenceMapper = new ExampleSentenceMapper();
        $languageCode = (string) ($parsedFields['language_code'] ?? 'oj');
        foreach ($sentences as $sData) {
            $speakerCode = (string) ($sData['speaker_code'] ?? '');
            $speakerId = $speakerCode !== '' ? $context->getSpeakerId($speakerCode) : null;
            $entryId = $result->getPrimaryEntityId() ?? 0;
            $sFields = $sentenceMapper->map($sData, $entryId, $speakerId, $languageCode)->toArray();

            if ($dryRun) {
                $result->addCreated('example_sentence', $sFields);
            } else {
                $storage = $this->entityTypeManager->getStorage('example_sentence');
                $entity = $storage->create($sFields);
                $storage->save($entity);
                $result->addCreated('example_sentence', $sFields, (int) $entity->id());
            }
        }

        return $result;
    }

    private function materializeSpeaker(array $parsedFields, MaterializationResult $result, bool $dryRun): MaterializationResult
    {
        if ($dryRun) {
            $result->addCreated('contributor', $parsedFields);
            return $result;
        }

        $code = (string) ($parsedFields['code'] ?? '');
        $id = $this->getOrCreateSpeaker($code, $parsedFields);
        $result->addCreated('contributor', $parsedFields, $id);
        $result->setPrimaryEntityId($id);

        return $result;
    }

    private function materializeCulturalCollection(array $parsedFields, MaterializationResult $result, bool $dryRun): MaterializationResult
    {
        if ($dryRun) {
            $result->addCreated('cultural_collection', $parsedFields);
            return $result;
        }

        $storage = $this->entityTypeManager->getStorage('cultural_collection');
        $entity = $storage->create($parsedFields);
        $storage->save($entity);
        $result->addCreated('cultural_collection', $parsedFields, (int) $entity->id());
        $result->setPrimaryEntityId((int) $entity->id());

        return $result;
    }

    private function getOrCreateSpeaker(string $code, array $fields): int
    {
        $storage = $this->entityTypeManager->getStorage('contributor');

        try {
            $ids = $storage->getQuery()->condition('code', $code)->execute();
            if ($ids !== []) {
                return (int) reset($ids);
            }
        } catch (\PDOException $e) {
            if (!str_contains($e->getMessage(), 'no such column')) {
                throw $e;
            }
        }

        $entity = $storage->create($fields);
        $storage->save($entity);
        return (int) $entity->id();
    }

    private function getOrCreateWordPart(string $form, string $type, array $fields): int
    {
        $storage = $this->entityTypeManager->getStorage('word_part');

        try {
            $ids = $storage->getQuery()
                ->condition('form', $form)
                ->condition('type', $type)
                ->execute();
            if ($ids !== []) {
                return (int) reset($ids);
            }
        } catch (\PDOException $e) {
            if (!str_contains($e->getMessage(), 'no such column')) {
                throw $e;
            }
        }

        $entity = $storage->create($fields);
        $storage->save($entity);
        return (int) $entity->id();
    }
}
