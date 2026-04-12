<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Exception;

use Minoo\Domain\Newsletter\ValueObject\EditionStatus;

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
