<?php
declare(strict_types=1);

namespace App\Domain\Newsletter\Assembler;

final readonly class ItemCandidate
{
    public function __construct(
        public string $section,
        public string $sourceType,
        public int $sourceId,
        public string $blurb,
        public float $score,
    ) {}
}
