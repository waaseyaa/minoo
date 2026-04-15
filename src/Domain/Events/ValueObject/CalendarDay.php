<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

use DateTimeImmutable;
use Waaseyaa\Entity\ContentEntityBase;

final class CalendarDay
{
    /**
     * @param list<ContentEntityBase> $events
     */
    public function __construct(
        public readonly DateTimeImmutable $date,
        public readonly bool $inMonth,
        public readonly bool $isToday,
        public readonly array $events,
    ) {}
}
