<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class ExampleSentence extends ContentEntityBase
{
    protected string $entityTypeId = 'example_sentence';

    protected array $entityKeys = [
        'id' => 'esid',
        'uuid' => 'uuid',
        'label' => 'ojibwe_text',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('language_code', $values)) {
            $values['language_code'] = 'oj';
        }
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
