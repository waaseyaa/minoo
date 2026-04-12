<?php
declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class NewsletterEdition extends ContentEntityBase
{
    protected string $entityTypeId = 'newsletter_edition';

    protected array $entityKeys = [
        'id' => 'neid',
        'uuid' => 'uuid',
        'label' => 'headline',
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
