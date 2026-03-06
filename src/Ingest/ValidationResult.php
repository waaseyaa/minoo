<?php

declare(strict_types=1);

namespace Minoo\Ingest;

final class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
