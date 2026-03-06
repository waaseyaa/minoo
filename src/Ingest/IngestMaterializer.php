<?php

declare(strict_types=1);

namespace Minoo\Ingest;

use Minoo\Entity\IngestLog;
use Minoo\Ingest\EntityMapper\ExampleSentenceMapper;
use Minoo\Ingest\EntityMapper\SpeakerMapper;
use Minoo\Ingest\EntityMapper\WordPartMapper;
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

        $rawEnvelope = json_decode($log->get('payload_raw'), true) ?? [];
        $parsedFields = json_decode($log->get('payload_parsed'), true) ?? [];
        $entityType = (string) $log->get('entity_type_target');
        $data = $rawEnvelope['data'] ?? [];

        return match ($entityType) {
            'dictionary_entry' => $this->materializeDictionaryEntry($parsedFields, $data, $context, $result, $dryRun),
            'speaker' => $this->materializeSpeaker($parsedFields, $result, $dryRun),
            'cultural_collection' => $this->materializeCulturalCollection($parsedFields, $result, $dryRun),
            default => $result,
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
                $speakerFields = SpeakerMapper::fromCode($code);
                if ($dryRun) {
                    $result->addCreated('speaker', $speakerFields);
                    $context->setSpeakerId($code, 0);
                } else {
                    $id = $this->getOrCreateSpeaker($code, $speakerFields);
                    $context->setSpeakerId($code, $id);
                    $result->addCreated('speaker', $speakerFields, $id);
                }
            }
        }

        // 2. Resolve word parts.
        $wordPartMapper = new WordPartMapper();
        $sourceUrl = (string) ($parsedFields['source_url'] ?? '');
        foreach ($rawData['word_parts'] ?? [] as $wpData) {
            $wpFields = $wordPartMapper->map($wpData, $sourceUrl);
            if ($wpFields === null) {
                continue;
            }
            $form = $wpFields['form'];
            $type = $wpFields['type'];
            if ($context->getWordPartId($form, $type) === null) {
                if ($dryRun) {
                    $result->addCreated('word_part', $wpFields);
                    $context->setWordPartId($form, $type, 0);
                } else {
                    $id = $this->getOrCreateWordPart($form, $type, $wpFields);
                    $context->setWordPartId($form, $type, $id);
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
            $result->primaryEntityId = $entryId;
        }

        // 4. Create example sentences.
        $sentenceMapper = new ExampleSentenceMapper();
        $languageCode = (string) ($parsedFields['language_code'] ?? 'oj');
        foreach ($sentences as $sData) {
            $speakerCode = (string) ($sData['speaker_code'] ?? '');
            $speakerId = $speakerCode !== '' ? $context->getSpeakerId($speakerCode) : null;
            $entryId = $result->primaryEntityId ?? 0;
            $sFields = $sentenceMapper->map($sData, $entryId, $speakerId, $languageCode);

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
            $result->addCreated('speaker', $parsedFields);
            return $result;
        }

        $code = (string) ($parsedFields['code'] ?? '');
        $id = $this->getOrCreateSpeaker($code, $parsedFields);
        $result->addCreated('speaker', $parsedFields, $id);
        $result->primaryEntityId = $id;

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
        $result->primaryEntityId = (int) $entity->id();

        return $result;
    }

    private function getOrCreateSpeaker(string $code, array $fields): int
    {
        $storage = $this->entityTypeManager->getStorage('speaker');

        try {
            $ids = $storage->getQuery()->condition('code', $code)->execute();
            if ($ids !== []) {
                return (int) reset($ids);
            }
        } catch (\PDOException) {
            // Field-level querying not available (e.g. in-memory SQLite).
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
        } catch (\PDOException) {
            // Field-level querying not available (e.g. in-memory SQLite).
        }

        $entity = $storage->create($fields);
        $storage->save($entity);
        return (int) $entity->id();
    }
}
