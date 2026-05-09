#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Export Sagamok-relevant content from Minoo SQLite into raw + curated JSON artifacts.
 *
 * Usage:
 *   php scripts/export_sagamok_knowledge.php
 *   php scripts/export_sagamok_knowledge.php --dictionary-limit=500
 *   php scripts/export_sagamok_knowledge.php --output-dir=/tmp/sagamok-export
 */

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_DB_PATH = __DIR__ . '/../storage/waaseyaa.sqlite';
const DEFAULT_OUTPUT_DIR = __DIR__ . '/../storage/exports/sagamok';
const SAGAMOK_SLUG = 'sagamok-anishnawbek';

$options = getopt('', ['db::', 'output-dir::', 'dictionary-limit::']);
$dbPath = isset($options['db']) && is_string($options['db']) && $options['db'] !== ''
    ? $options['db']
    : DEFAULT_DB_PATH;
$outputDir = isset($options['output-dir']) && is_string($options['output-dir']) && $options['output-dir'] !== ''
    ? $options['output-dir']
    : DEFAULT_OUTPUT_DIR;
$dictionaryLimit = isset($options['dictionary-limit']) ? max(0, (int) $options['dictionary-limit']) : null;

if (!is_file($dbPath)) {
    fwrite(STDERR, "Database not found: {$dbPath}\n");
    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Failed to create output directory: {$outputDir}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath, options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$sagamok = fetchSagamokCommunity($pdo);
if ($sagamok === null) {
    fwrite(STDERR, "Could not find Sagamok community row (slug " . SAGAMOK_SLUG . ").\n");
    exit(1);
}

$sagamokId = (int) $sagamok['cid'];
$sagamokName = (string) $sagamok['name'];
$sagamokSlug = (string) jsonExtractString((string) $sagamok['_data'], 'slug', SAGAMOK_SLUG);
$needle = '%sagamok%';

$rawEvents = fetchRows($pdo, <<<SQL
SELECT eid, uuid, type, title, langcode, source_url, consent_public, consent_ai_training, _data
FROM event
WHERE CAST(json_extract(_data, '$.community_id') AS INTEGER) = :communityId
   OR LOWER(title) LIKE :needle
   OR LOWER(_data) LIKE :needle
ORDER BY eid ASC
SQL, [
    ':communityId' => $sagamokId,
    ':needle' => $needle,
]);

$rawGroups = fetchRows($pdo, <<<SQL
SELECT gid, uuid, type, name, langcode, _data
FROM "group"
WHERE LOWER(name) LIKE :needle
   OR LOWER(_data) LIKE :needle
ORDER BY gid ASC
SQL, [':needle' => $needle]);

$rawResourcePeople = fetchRows($pdo, <<<SQL
SELECT rpid, uuid, bundle, name, langcode, consent_public, consent_ai_training, _data
FROM resource_person
WHERE LOWER(name) LIKE :needle
   OR LOWER(_data) LIKE :needle
ORDER BY rpid ASC
SQL, [':needle' => $needle]);

$rawTeachings = fetchRows($pdo, <<<SQL
SELECT tid, uuid, type, title, langcode, source_url, _data
FROM teaching
WHERE LOWER(title) LIKE :needle
   OR LOWER(_data) LIKE :needle
ORDER BY tid ASC
SQL, [':needle' => $needle]);

$dictionarySql = <<<SQL
SELECT deid, uuid, bundle, word, langcode, _data
FROM dictionary_entry
WHERE json_extract(_data, '$.language_code') = 'oj'
ORDER BY deid ASC
SQL;
if ($dictionaryLimit !== null && $dictionaryLimit > 0) {
    $dictionarySql .= ' LIMIT ' . $dictionaryLimit;
}
$rawDictionary = fetchRows($pdo, $dictionarySql);

$rawByTable = [
    'event' => array_map(static function (array $row): array {
        $row['_data_parsed'] = decodeJson((string) ($row['_data'] ?? ''));
        return $row;
    }, $rawEvents),
    'group' => array_map(static function (array $row): array {
        $row['_data_parsed'] = decodeJson((string) ($row['_data'] ?? ''));
        return $row;
    }, $rawGroups),
    'resource_person' => array_map(static function (array $row): array {
        $row['_data_parsed'] = decodeJson((string) ($row['_data'] ?? ''));
        return $row;
    }, $rawResourcePeople),
    'teaching' => array_map(static function (array $row): array {
        $row['_data_parsed'] = decodeJson((string) ($row['_data'] ?? ''));
        return $row;
    }, $rawTeachings),
    'dictionary_entry' => array_map(static function (array $row): array {
        $row['_data_parsed'] = decodeJson((string) ($row['_data'] ?? ''));
        return $row;
    }, $rawDictionary),
];

$curatedContent = [];
foreach ($rawEvents as $row) {
    $curatedContent[] = curateEvent($row, $sagamokName, $sagamokSlug);
}
foreach ($rawGroups as $row) {
    $curatedContent[] = curateGroup($row, $sagamokName, $sagamokSlug);
}
foreach ($rawResourcePeople as $row) {
    $curatedContent[] = curateResourcePerson($row, $sagamokName, $sagamokSlug);
}
foreach ($rawTeachings as $row) {
    $curatedContent[] = curateTeaching($row, $sagamokName, $sagamokSlug);
}

$curatedDictionary = [];
foreach ($rawDictionary as $row) {
    $curatedDictionary[] = curateDictionaryEntry($row, $sagamokName, $sagamokSlug);
}

$summary = [
    'exported_at' => date('c'),
    'source' => [
        'database' => realpath($dbPath) ?: $dbPath,
        'community' => [
            'id' => $sagamokId,
            'name' => $sagamokName,
            'slug' => $sagamokSlug,
        ],
    ],
    'counts' => [
        'raw' => [
            'event' => count($rawEvents),
            'group' => count($rawGroups),
            'resource_person' => count($rawResourcePeople),
            'teaching' => count($rawTeachings),
            'dictionary_entry' => count($rawDictionary),
        ],
        'curated' => [
            'content' => count($curatedContent),
            'dictionary' => count($curatedDictionary),
            'total' => count($curatedContent) + count($curatedDictionary),
        ],
    ],
];

writeJson($outputDir . '/raw-event.json', $rawByTable['event']);
writeJson($outputDir . '/raw-group.json', $rawByTable['group']);
writeJson($outputDir . '/raw-resource-person.json', $rawByTable['resource_person']);
writeJson($outputDir . '/raw-teaching.json', $rawByTable['teaching']);
writeJson($outputDir . '/raw-dictionary-entry.json', $rawByTable['dictionary_entry']);
writeJson($outputDir . '/curated-content.json', $curatedContent);
writeJson($outputDir . '/curated-dictionary.json', $curatedDictionary);
writeJson($outputDir . '/summary.json', $summary);

echo "Export complete.\n";
echo "- Output directory: {$outputDir}\n";
echo "- Curated content rows: " . count($curatedContent) . "\n";
echo "- Curated dictionary rows: " . count($curatedDictionary) . "\n";

/**
 * @return array<int, array<string, mixed>>
 */
function fetchRows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

/**
 * @return array{cid:int, name:string, _data:string}|null
 */
function fetchSagamokCommunity(PDO $pdo): ?array
{
    $rows = fetchRows(
        $pdo,
        'SELECT cid, name, _data FROM community WHERE json_extract(_data, \'$.slug\') = :slug ORDER BY cid ASC LIMIT 1',
        [':slug' => SAGAMOK_SLUG],
    );
    if ($rows !== []) {
        return [
            'cid' => (int) ($rows[0]['cid'] ?? 0),
            'name' => (string) ($rows[0]['name'] ?? ''),
            '_data' => (string) ($rows[0]['_data'] ?? '{}'),
        ];
    }

    $fallback = fetchRows(
        $pdo,
        'SELECT cid, name, _data FROM community WHERE LOWER(name) LIKE :needle ORDER BY cid ASC LIMIT 1',
        [':needle' => '%sagamok%'],
    );

    if ($fallback === []) {
        return null;
    }

    return [
        'cid' => (int) ($fallback[0]['cid'] ?? 0),
        'name' => (string) ($fallback[0]['name'] ?? ''),
        '_data' => (string) ($fallback[0]['_data'] ?? '{}'),
    ];
}

/**
 * @return array<string, mixed>
 */
function decodeJson(string $json): array
{
    if ($json === '') {
        return [];
    }
    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (JsonException) {
        return [];
    }
}

/**
 * @return string|int|float|bool|null
 */
function jsonExtractString(string $json, string $key, string $fallback = ''): string
{
    $decoded = decodeJson($json);
    $value = $decoded[$key] ?? $fallback;
    return is_scalar($value) ? (string) $value : $fallback;
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $data
 * @param array<string> $tags
 * @param array<string, mixed> $metadata
 * @param array<string, mixed> $rights
 * @return array<string, mixed>
 */
function curatedRecord(
    string $table,
    int $sourceId,
    string $uuid,
    string $title,
    string $content,
    string $knowledgeType,
    string $communityName,
    string $communitySlug,
    string $langcode,
    ?string $sourceUrl,
    ?string $createdAt,
    ?string $updatedAt,
    array $tags,
    array $metadata,
    array $rights,
): array {
    return [
        'source' => [
            'system' => 'minoo',
            'database' => 'waaseyaa.sqlite',
            'table' => $table,
            'id' => $sourceId,
            'uuid' => $uuid,
            'fingerprint' => sprintf('minoo:%s:%d', $table, $sourceId),
        ],
        'community' => [
            'name' => $communityName,
            'slug' => $communitySlug,
        ],
        'title' => $title,
        'content' => $content,
        'knowledge_type' => $knowledgeType,
        'access_tier' => 'public',
        'langcode' => $langcode,
        'source_url' => $sourceUrl,
        'tags' => array_values(array_unique($tags)),
        'rights' => $rights,
        'timestamps' => [
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ],
        'metadata' => $metadata,
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function curateEvent(array $row, string $communityName, string $communitySlug): array
{
    $data = decodeJson((string) ($row['_data'] ?? '{}'));

    $description = trim((string) ($data['description'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $startsAt = normalizeTimestamp($data['starts_at'] ?? null);
    $endsAt = normalizeTimestamp($data['ends_at'] ?? null);

    $parts = [];
    if ($description !== '') {
        $parts[] = $description;
    }
    if ($location !== '') {
        $parts[] = 'Location: ' . $location;
    }
    if ($startsAt !== null) {
        $parts[] = 'Starts: ' . $startsAt;
    }
    if ($endsAt !== null) {
        $parts[] = 'Ends: ' . $endsAt;
    }

    $content = implode("\n\n", $parts);
    $sourceUrl = pickNonEmptyString((string) ($row['source_url'] ?? ''), (string) ($data['source_url'] ?? ''));

    return curatedRecord(
        table: 'event',
        sourceId: (int) ($row['eid'] ?? 0),
        uuid: (string) ($row['uuid'] ?? ''),
        title: (string) ($row['title'] ?? ''),
        content: $content,
        knowledgeType: 'event',
        communityName: $communityName,
        communitySlug: $communitySlug,
        langcode: (string) ($row['langcode'] ?? 'en'),
        sourceUrl: $sourceUrl !== '' ? $sourceUrl : null,
        createdAt: normalizeTimestamp($data['created_at'] ?? null),
        updatedAt: normalizeTimestamp($data['updated_at'] ?? null),
        tags: ['event', (string) ($row['type'] ?? 'gathering'), 'sagamok'],
        metadata: [
            'event_type' => (string) ($row['type'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'community_id' => isset($data['community_id']) ? (int) $data['community_id'] : null,
            'verified_at' => normalizeTimestamp($data['verified_at'] ?? null),
        ],
        rights: [
            'consent_public' => normalizeBool($row['consent_public'] ?? $data['consent_public'] ?? 1, true),
            'consent_ai_training' => normalizeBool($row['consent_ai_training'] ?? $data['consent_ai_training'] ?? 0, false),
            'copyright_status' => (string) ($data['copyright_status'] ?? ''),
            'license' => null,
        ],
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function curateGroup(array $row, string $communityName, string $communitySlug): array
{
    $data = decodeJson((string) ($row['_data'] ?? '{}'));
    $description = trim((string) ($data['description'] ?? ''));
    $website = trim((string) ($data['website'] ?? $data['url'] ?? ''));

    $parts = [];
    if ($description !== '') {
        $parts[] = $description;
    }
    if ($website !== '') {
        $parts[] = 'Website: ' . $website;
    }

    return curatedRecord(
        table: 'group',
        sourceId: (int) ($row['gid'] ?? 0),
        uuid: (string) ($row['uuid'] ?? ''),
        title: (string) ($row['name'] ?? ''),
        content: implode("\n\n", $parts),
        knowledgeType: 'relationship',
        communityName: $communityName,
        communitySlug: $communitySlug,
        langcode: (string) ($row['langcode'] ?? 'en'),
        sourceUrl: $website !== '' ? $website : null,
        createdAt: normalizeTimestamp($data['created_at'] ?? null),
        updatedAt: normalizeTimestamp($data['updated_at'] ?? null),
        tags: ['group', (string) ($row['type'] ?? 'community'), 'sagamok'],
        metadata: [
            'group_type' => (string) ($row['type'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'linked_group_id' => isset($data['linked_group_id']) ? (int) $data['linked_group_id'] : null,
            'community' => (string) ($data['community'] ?? ''),
            'source' => (string) ($data['source'] ?? ''),
        ],
        rights: [
            'consent_public' => normalizeBool($data['consent_public'] ?? 1, true),
            'consent_ai_training' => normalizeBool($data['consent_ai_training'] ?? 0, false),
            'copyright_status' => (string) ($data['copyright_status'] ?? ''),
            'license' => null,
        ],
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function curateResourcePerson(array $row, string $communityName, string $communitySlug): array
{
    $data = decodeJson((string) ($row['_data'] ?? '{}'));

    $bio = trim((string) ($data['bio'] ?? ''));
    $website = trim((string) ($data['website'] ?? ''));
    $roles = normalizeList($data['roles'] ?? []);
    $offerings = normalizeList($data['offerings'] ?? []);

    $parts = [];
    if ($bio !== '') {
        $parts[] = $bio;
    }
    if ($roles !== []) {
        $parts[] = 'Role IDs: ' . implode(', ', array_map(strval(...), $roles));
    }
    if ($offerings !== []) {
        $parts[] = 'Offering IDs: ' . implode(', ', array_map(strval(...), $offerings));
    }
    if ($website !== '') {
        $parts[] = 'Website: ' . $website;
    }

    return curatedRecord(
        table: 'resource_person',
        sourceId: (int) ($row['rpid'] ?? 0),
        uuid: (string) ($row['uuid'] ?? ''),
        title: (string) ($row['name'] ?? ''),
        content: implode("\n\n", $parts),
        knowledgeType: 'relationship',
        communityName: $communityName,
        communitySlug: $communitySlug,
        langcode: (string) ($row['langcode'] ?? 'en'),
        sourceUrl: $website !== '' ? $website : null,
        createdAt: normalizeTimestamp($data['created_at'] ?? null),
        updatedAt: normalizeTimestamp($data['updated_at'] ?? null),
        tags: ['resource-person', 'sagamok'],
        metadata: [
            'bundle' => (string) ($row['bundle'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'community' => (string) ($data['community'] ?? ''),
            'roles' => $roles,
            'offerings' => $offerings,
            'verified_at' => normalizeTimestamp($data['verified_at'] ?? null),
            'source' => (string) ($data['source'] ?? ''),
        ],
        rights: [
            'consent_public' => normalizeBool($row['consent_public'] ?? $data['consent_public'] ?? 1, true),
            'consent_ai_training' => normalizeBool($row['consent_ai_training'] ?? $data['consent_ai_training'] ?? 0, false),
            'copyright_status' => (string) ($data['copyright_status'] ?? ''),
            'license' => null,
        ],
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function curateTeaching(array $row, string $communityName, string $communitySlug): array
{
    $data = decodeJson((string) ($row['_data'] ?? '{}'));
    $description = trim((string) ($data['description'] ?? ''));
    $content = trim((string) ($data['content'] ?? ''));

    $parts = [];
    if ($description !== '') {
        $parts[] = $description;
    }
    if ($content !== '') {
        $parts[] = $content;
    }

    $sourceUrl = pickNonEmptyString((string) ($row['source_url'] ?? ''), (string) ($data['source_url'] ?? ''));

    return curatedRecord(
        table: 'teaching',
        sourceId: (int) ($row['tid'] ?? 0),
        uuid: (string) ($row['uuid'] ?? ''),
        title: (string) ($row['title'] ?? ''),
        content: implode("\n\n", $parts),
        knowledgeType: 'cultural',
        communityName: $communityName,
        communitySlug: $communitySlug,
        langcode: (string) ($row['langcode'] ?? 'en'),
        sourceUrl: $sourceUrl !== '' ? $sourceUrl : null,
        createdAt: normalizeTimestamp($data['created_at'] ?? null),
        updatedAt: normalizeTimestamp($data['updated_at'] ?? null),
        tags: ['teaching', (string) ($row['type'] ?? 'culture'), 'sagamok'],
        metadata: [
            'teaching_type' => (string) ($row['type'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'source' => (string) ($data['source'] ?? ''),
        ],
        rights: [
            'consent_public' => normalizeBool($data['consent_public'] ?? 1, true),
            'consent_ai_training' => normalizeBool($data['consent_ai_training'] ?? 0, false),
            'copyright_status' => (string) ($data['copyright_status'] ?? ''),
            'license' => null,
        ],
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function curateDictionaryEntry(array $row, string $communityName, string $communitySlug): array
{
    $data = decodeJson((string) ($row['_data'] ?? '{}'));
    $definition = normalizeDictionaryDefinition($data['definition'] ?? '');
    $partOfSpeech = trim((string) ($data['part_of_speech'] ?? ''));
    $stem = trim((string) ($data['stem'] ?? ''));
    $languageCode = trim((string) ($data['language_code'] ?? 'oj'));
    $inflected = trim((string) ($data['inflected_forms'] ?? ''));
    $sourceUrl = trim((string) ($data['source_url'] ?? ''));

    $parts = [];
    $parts[] = 'Word: ' . (string) ($row['word'] ?? '');
    if ($definition !== '') {
        $parts[] = 'Definition: ' . $definition;
    }
    if ($partOfSpeech !== '') {
        $parts[] = 'Part of speech: ' . $partOfSpeech;
    }
    if ($stem !== '') {
        $parts[] = 'Stem: ' . $stem;
    }
    if ($inflected !== '' && $inflected !== '{}') {
        $parts[] = 'Inflected forms: ' . $inflected;
    }
    if ($sourceUrl !== '') {
        $parts[] = 'Source URL: ' . $sourceUrl;
    }

    return curatedRecord(
        table: 'dictionary_entry',
        sourceId: (int) ($row['deid'] ?? 0),
        uuid: (string) ($row['uuid'] ?? ''),
        title: (string) ($row['word'] ?? ''),
        content: implode("\n", $parts),
        knowledgeType: 'cultural',
        communityName: $communityName,
        communitySlug: $communitySlug,
        langcode: (string) ($row['langcode'] ?? 'en'),
        sourceUrl: $sourceUrl !== '' ? $sourceUrl : null,
        createdAt: normalizeTimestamp($data['created_at'] ?? null),
        updatedAt: normalizeTimestamp($data['updated_at'] ?? null),
        tags: ['dictionary', 'language', $languageCode],
        metadata: [
            'word' => (string) ($row['word'] ?? ''),
            'language_code' => $languageCode,
            'part_of_speech' => $partOfSpeech,
            'stem' => $stem,
            'inflected_forms' => $inflected,
            'attribution_source' => (string) ($data['attribution_source'] ?? ''),
            'attribution_url' => (string) ($data['attribution_url'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
        ],
        rights: [
            'consent_public' => normalizeBool($data['consent_public'] ?? 1, true),
            'consent_ai_training' => normalizeBool($data['consent_ai_training'] ?? 0, false),
            'copyright_status' => (string) ($data['copyright_status'] ?? ''),
            'license' => null,
        ],
    );
}

function normalizeDictionaryDefinition(mixed $raw): string
{
    if (is_array($raw)) {
        return implode('; ', array_map(static fn (mixed $value): string => trim((string) $value), $raw));
    }

    $value = trim((string) $raw);
    if ($value === '') {
        return '';
    }

    if (str_starts_with($value, '[')) {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return implode('; ', array_map(static fn (mixed $part): string => trim((string) $part), $decoded));
            }
        } catch (JsonException) {
            // fall through
        }
    }

    return $value;
}

/**
 * @return list<mixed>
 */
function normalizeList(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    return array_values($value);
}

function pickNonEmptyString(string ...$values): string
{
    foreach ($values as $value) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            return $trimmed;
        }
    }
    return '';
}

function normalizeBool(mixed $value, bool $default): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }
    $asString = strtolower(trim((string) $value));
    if (in_array($asString, ['1', 'true', 'yes'], true)) {
        return true;
    }
    if (in_array($asString, ['0', 'false', 'no'], true)) {
        return false;
    }
    return $default;
}

function normalizeTimestamp(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $timestamp = (int) $value;
        if ($timestamp > 0) {
            return gmdate('c', $timestamp);
        }
        return null;
    }
    if (is_numeric($value)) {
        $timestamp = (int) $value;
        if ($timestamp > 0) {
            return gmdate('c', $timestamp);
        }
        return null;
    }

    $asString = trim((string) $value);
    if ($asString === '') {
        return null;
    }
    $parsed = strtotime($asString);
    if ($parsed === false) {
        return null;
    }
    return gmdate('c', $parsed);
}

function writeJson(string $path, mixed $data): void
{
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
    );
}
