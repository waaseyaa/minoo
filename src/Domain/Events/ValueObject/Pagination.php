<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

final class Pagination
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    public function totalPages(): int
    {
        return $this->total === 0 ? 1 : (int) ceil($this->total / $this->perPage);
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages();
    }
}
