<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\ValueObject;

enum EditionStatus: string
{
    case Draft = 'draft';
    case Curating = 'curating';
    case Approved = 'approved';
    case Generated = 'generated';
    case Sent = 'sent';

    public static function fromEntity(\Waaseyaa\Entity\EntityInterface $edition): self
    {
        $status = (string) $edition->get('status');
        if ($status === '') {
            return self::Draft;
        }
        return self::from($status);
    }
}
