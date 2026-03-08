<?php

declare(strict_types=1);

namespace Minoo\Geo;

use Waaseyaa\Entity\ContentEntityBase;

final readonly class RankedVolunteer
{
    public function __construct(
        public ContentEntityBase $volunteer,
        public ?float $distanceKm,
    ) {}

    public function hasDistance(): bool
    {
        return $this->distanceKm !== null;
    }

    public function formattedDistance(): string
    {
        if ($this->distanceKm === null) {
            return '';
        }

        if ($this->distanceKm < 1.0) {
            return '< 1 km';
        }

        return round($this->distanceKm) . ' km';
    }
}
