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
}
