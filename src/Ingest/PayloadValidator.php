<?php

declare(strict_types=1);

namespace Minoo\Ingest;

final class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}

final class PayloadValidator
{
    private const array SUPPORTED_VERSIONS = ['1.0'];

    private const array SUPPORTED_ENTITY_TYPES = [
        'dictionary_entry',
        'speaker',
        'cultural_collection',
    ];

    private const array REQUIRED_ENVELOPE_FIELDS = [
        'payload_id',
        'version',
        'source',
        'snapshot_type',
        'timestamp',
        'entity_type',
        'source_url',
        'data',
    ];

    private const array VALID_PARTS_OF_SPEECH = [
        'na', 'ni', 'nad', 'nid', 'vai', 'vti', 'vta', 'vii',
        'pc', 'adv', 'pron', 'num',
    ];

    public function validate(array $envelope): ValidationResult
    {
        $errors = [];

        // Required envelope fields.
        foreach (self::REQUIRED_ENVELOPE_FIELDS as $field) {
            if (!array_key_exists($field, $envelope) || $envelope[$field] === '' || $envelope[$field] === null) {
                $errors[] = sprintf('Missing required field: %s', $field);
            }
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

        // Version check.
        if (!in_array($envelope['version'], self::SUPPORTED_VERSIONS, true)) {
            $errors[] = sprintf('Unsupported version: %s', $envelope['version']);
        }

        // Entity type check.
        if (!in_array($envelope['entity_type'], self::SUPPORTED_ENTITY_TYPES, true)) {
            $errors[] = sprintf('Unsupported entity type: %s', $envelope['entity_type']);
        }

        // Data must be an array.
        if (!is_array($envelope['data'])) {
            $errors[] = 'Data field must be an array.';
            return new ValidationResult($errors);
        }

        // Entity-type-specific validation.
        if ($envelope['entity_type'] === 'dictionary_entry') {
            $errors = [...$errors, ...$this->validateDictionaryEntry($envelope['data'])];
        }

        return new ValidationResult($errors);
    }

    /** @return list<string> */
    private function validateDictionaryEntry(array $data): array
    {
        $errors = [];

        if (empty($data['lemma'])) {
            $errors[] = 'Dictionary entry requires lemma.';
        }

        if (isset($data['part_of_speech']) && !in_array($data['part_of_speech'], self::VALID_PARTS_OF_SPEECH, true)) {
            $errors[] = sprintf('Invalid part_of_speech: %s', $data['part_of_speech']);
        }

        return $errors;
    }
}
