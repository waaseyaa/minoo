<?php
declare(strict_types=1);

namespace App\Domain\Newsletter\Exception;

use App\Domain\Newsletter\ValueObject\EditionStatus;

final class InvalidStateTransition extends \DomainException
{
    public static function illegal(EditionStatus $from, EditionStatus $to): self
    {
        return new self(sprintf(
            'Illegal newsletter edition transition: %s -> %s',
            $from->value,
            $to->value,
        ));
    }
}
