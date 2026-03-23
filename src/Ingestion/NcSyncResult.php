<?php

declare(strict_types=1);

namespace Minoo\Ingestion;

final class NcSyncResult
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly bool $fetchFailed = false,
    ) {}

    public function withCreated(): self
    {
        return new self($this->created + 1, $this->skipped, $this->failed);
    }

    public function withSkipped(): self
    {
        return new self($this->created, $this->skipped + 1, $this->failed);
    }

    public function withFailed(): self
    {
        return new self($this->created, $this->skipped, $this->failed + 1);
    }

    public function withFetchFailed(): self
    {
        return new self($this->created, $this->skipped, $this->failed, fetchFailed: true);
    }
}
