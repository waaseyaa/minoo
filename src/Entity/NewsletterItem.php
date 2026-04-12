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
}
