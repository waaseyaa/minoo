<?php
declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class NewsletterSubmission extends ContentEntityBase
{
    protected string $entityTypeId = 'newsletter_submission';

    protected array $entityKeys = [
        'id' => 'nsuid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
            $fieldDefinitions,
        );
    }
}
