<?php
declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class NewsletterItem extends ContentEntityBase
{
    protected string $entityTypeId = 'newsletter_item';

    protected array $entityKeys = [
        'id' => 'nitid',
        'uuid' => 'uuid',
        'label' => 'editor_blurb',
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
