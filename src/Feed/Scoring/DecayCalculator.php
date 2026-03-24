<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

/**
 * Time-decay function for feed scoring.
 *
 * Uses exponential decay: score = e^(-lambda * age_hours)
 * where lambda = ln(2) / halfLifeHours.
 */
final class DecayCalculator
{
    private readonly float $lambda;

    public function __construct(
        private readonly float $halfLifeHours = 24.0,
    ) {
        $this->lambda = log(2) / $this->halfLifeHours;
    }

    /**
     * Compute decay factor for a given creation time.
     *
     * @return float Between 0.0 and 1.0 (1.0 = brand new)
     */
    public function compute(\DateTimeImmutable $createdAt, ?\DateTimeImmutable $now = null): float
    {
        $now ??= new \DateTimeImmutable();
        $ageSeconds = $now->getTimestamp() - $createdAt->getTimestamp();

        if ($ageSeconds <= 0) {
            return 1.0;
        }

        $ageHours = $ageSeconds / 3600.0;

        return exp(-$this->lambda * $ageHours);
    }
}
