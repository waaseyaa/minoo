<?php

declare(strict_types=1);

namespace App\Support;

final class FixtureLoader
{
    private const REQUIRED_FIELDS = [
        'businesses' => ['name', 'slug', 'type', 'community'],
        'people' => ['name', 'slug', 'community'],
        'events' => ['title', 'slug', 'type', 'community', 'starts_at'],
    ];

    public function __construct(private readonly string $contentDir) {}

    /** @return list<array<string, mixed>> */
    public function load(string $fixtureType): array
    {
        $path = $this->contentDir . '/' . $fixtureType . '.json';

        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in {$path}");
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string> Validation error messages
     */
    public function validate(array $records, string $fixtureType): array
    {
        $errors = [];
        $requiredFields = self::REQUIRED_FIELDS[$fixtureType] ?? [];
        $slugs = [];

        foreach ($records as $index => $record) {
            $label = $record['slug'] ?? $record['name'] ?? "record {$index}";

            foreach ($requiredFields as $field) {
                if (empty($record[$field])) {
                    $errors[] = "{$label}: missing required field '{$field}'";
                }
            }

            if ($fixtureType === 'people' && !array_key_exists('consent_public', $record)) {
                $errors[] = "{$label}: missing required field 'consent_public'";
            }

            if (isset($record['slug'])) {
                if (isset($slugs[$record['slug']])) {
                    $errors[] = "{$label}: duplicate slug '{$record['slug']}'";
                }
                $slugs[$record['slug']] = true;
            }

            if (isset($record['phone']) && !preg_match('/^\+[1-9]\d{6,14}$/', $record['phone'])) {
                $errors[] = "{$label}: invalid phone format (expected E.164)";
            }

            if (isset($record['email']) && !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$label}: invalid email format";
            }

            if (isset($record['booking_url']) && !filter_var($record['booking_url'], FILTER_VALIDATE_URL)) {
                $errors[] = "{$label}: invalid booking_url format";
            }

            if (isset($record['url']) && !filter_var($record['url'], FILTER_VALIDATE_URL)) {
                $errors[] = "{$label}: invalid url format";
            }
        }

        return $errors;
    }
}
