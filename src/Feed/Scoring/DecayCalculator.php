<?php

declare(strict_types=1);

namespace App\Feed\Scoring;

final class DecayCalculator
{
    public function __construct(
        private readonly float $halfLifeHours = 96.0,
    ) {}

    public function compute(int $createdAt, ?int $now = null): float
    {
        $now ??= time();
        $ageHours = ($now - $createdAt) / 3600.0;

        return pow(0.5, $ageHours / $this->halfLifeHours);
    }
}
