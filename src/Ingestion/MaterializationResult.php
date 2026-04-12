<?php

declare(strict_types=1);

namespace App\Ingestion;

final class MaterializationResult
{
    /** @var list<array{type: string, fields: array<string, mixed>, id?: int}> */
    private array $created = [];

    /** @var list<array{type: string, key: string, reason: string}> */
    private array $skipped = [];

    /** @var list<array{type: string, id: int, fields: array<string, mixed>}> */
    private array $updated = [];

    private ?int $primaryEntityId = null;

    /** @param array<string, mixed> $fields */
    public function addCreated(string $type, array $fields, ?int $id = null): void
    {
        $entry = ['type' => $type, 'fields' => $fields];
        if ($id !== null) {
            $entry['id'] = $id;
        }
        $this->created[] = $entry;
    }

    public function addSkipped(string $type, string $key, string $reason): void
    {
        $this->skipped[] = ['type' => $type, 'key' => $key, 'reason' => $reason];
    }

    /** @param array<string, mixed> $fields */
    public function addUpdated(string $type, int $id, array $fields): void
    {
        $this->updated[] = ['type' => $type, 'id' => $id, 'fields' => $fields];
    }

    public function setPrimaryEntityId(int $id): void
    {
        $this->primaryEntityId = $id;
    }

    public function getPrimaryEntityId(): ?int
    {
        return $this->primaryEntityId;
    }

    /** @return list<array{type: string, fields: array<string, mixed>, id?: int}> */
    public function getCreated(): array
    {
        return $this->created;
    }

    /** @return list<array{type: string, key: string, reason: string}> */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /** @return list<array{type: string, id: int, fields: array<string, mixed>}> */
    public function getUpdated(): array
    {
        return $this->updated;
    }
}
