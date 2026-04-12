<?php

declare(strict_types=1);

namespace App\Ingestion;

final class MaterializationContext
{
    /** @var array<string, int> Speaker code → entity ID */
    private array $speakers = [];

    /** @var array<string, int> "form|type" → entity ID */
    private array $wordParts = [];

    public function getSpeakerId(string $code): ?int
    {
        return $this->speakers[$code] ?? null;
    }

    public function setSpeakerId(string $code, int $id): void
    {
        $this->speakers[$code] = $id;
    }

    public function getWordPartId(string $form, string $type): ?int
    {
        return $this->wordParts[$form . '|' . $type] ?? null;
    }

    public function setWordPartId(string $form, string $type, int $id): void
    {
        $this->wordParts[$form . '|' . $type] = $id;
    }
}
