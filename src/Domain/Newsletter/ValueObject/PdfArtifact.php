<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\ValueObject;

final readonly class PdfArtifact
{
    public function __construct(
        public string $path,
        public int $bytes,
        public string $sha256,
    ) {
    }
}
