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
}
