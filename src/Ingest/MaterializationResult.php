<?php

declare(strict_types=1);

namespace Minoo\Ingest;

final class MaterializationResult
{
    /** @var list<array{type: string, fields: array<string, mixed>, id?: int}> */
    public array $created = [];

    /** @var list<array{type: string, key: string, reason: string}> */
    public array $skipped = [];

    /** @var list<array{type: string, id: int, fields: array<string, mixed>}> */
    public array $updated = [];

    public ?int $primaryEntityId = null;

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
}
