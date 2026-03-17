<?php

declare(strict_types=1);

/**
 * Populate community_id on entities that lack it, by matching against community records.
 *
 * Matching strategies:
 * 1. Community name appears in entity title/description (case-insensitive)
 * 2. Geographic proximity if entity has lat/lon (future — currently name-only)
 *
 * Usage: php scripts/populate_relationships.php [--dry-run]
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();

// ── Load all communities ────────────────────────────────────────────────────

fprintf(STDOUT, "Loading communities...\n");

try {
    $communityStorage = $entityTypeManager->getStorage('community');
} catch (\Throwable $e) {
    fprintf(STDERR, "Cannot load community storage: %s\n", $e->getMessage());
    exit(1);
}

$communityIds = $communityStorage->getQuery()->execute();
$communities = $communityStorage->loadMultiple($communityIds);

if ($communities === []) {
    fprintf(STDERR, "No communities found. Run community sync first.\n");
    exit(1);
}

// Build lookup: lowercase community name => community id
$communityIndex = [];
foreach ($communities as $community) {
    $name = trim((string) $community->get('name'));
    if ($name !== '') {
        $communityIndex[mb_strtolower($name)] = $community->id();
    }
}

fprintf(STDOUT, "Loaded %d communities.\n\n", count($communityIndex));

// ── Entity types to process ─────────────────────────────────────────────────

$entityConfigs = [
    'event' => [
        'community_field' => 'community_id',
        'label_field' => 'title',
        'description_field' => 'description',
    ],
    'teaching' => [
        'community_field' => 'community_id',
        'label_field' => 'title',
        'description_field' => 'content',
    ],
    'group' => [
        'community_field' => 'community_id',
        'label_field' => 'name',
        'description_field' => 'description',
    ],
    'resource_person' => [
        'community_field' => 'community',
        'label_field' => 'name',
        'description_field' => 'bio',
        'is_string_field' => true, // community is a string, not an ID reference
    ],
];

$summary = [];

foreach ($entityConfigs as $entityType => $config) {
    fprintf(STDOUT, "Processing %s entities...\n", $entityType);

    try {
        $storage = $entityTypeManager->getStorage($entityType);
    } catch (\Throwable $e) {
        fprintf(STDOUT, "  Skipping %s: %s\n\n", $entityType, $e->getMessage());
        continue;
    }

    $ids = $storage->getQuery()->execute();
    $entities = $storage->loadMultiple($ids);

    $updated = 0;
    $isStringField = $config['is_string_field'] ?? false;

    foreach ($entities as $entity) {
        $currentValue = $entity->get($config['community_field']);

        // Skip if already has a community reference
        if ($currentValue !== null && $currentValue !== '' && $currentValue !== 0 && $currentValue !== '0') {
            continue;
        }

        // Gather searchable text from the entity
        $label = trim((string) $entity->get($config['label_field']));
        $description = trim((string) $entity->get($config['description_field']));
        $searchText = mb_strtolower($label . ' ' . $description);

        // Try to match by community name appearing in text
        $matchedCommunityId = null;
        $matchedCommunityName = null;
        $longestMatch = 0;

        foreach ($communityIndex as $communityName => $communityId) {
            // Skip very short names to avoid false positives (< 4 chars)
            if (mb_strlen($communityName) < 4) {
                continue;
            }

            if (mb_strpos($searchText, $communityName) !== false) {
                // Prefer longest match to avoid partial hits
                if (mb_strlen($communityName) > $longestMatch) {
                    $longestMatch = mb_strlen($communityName);
                    $matchedCommunityId = $communityId;
                    $matchedCommunityName = $communityName;
                }
            }
        }

        if ($matchedCommunityId !== null) {
            $displayLabel = mb_substr($label, 0, 50);

            if ($isStringField) {
                // resource_person: community is a string field, store the name
                $entity->set($config['community_field'], $matchedCommunityName);
                fprintf(STDOUT, "  Linked %s '%s' to community '%s' (matched by: name)\n",
                    $entityType, $displayLabel, $matchedCommunityName);
            } else {
                // Other entities: community_id is an entity reference
                $entity->set($config['community_field'], $matchedCommunityId);
                fprintf(STDOUT, "  Linked %s '%s' to community '%s' (id: %s, matched by: name)\n",
                    $entityType, $displayLabel, $matchedCommunityName, $matchedCommunityId);
            }

            if (!$dryRun) {
                $storage->save($entity);
            }
            $updated++;
        }
    }

    $summary[$entityType] = $updated;
    $total = count($entities);
    $missing = $total - $updated;
    fprintf(STDOUT, "  %s: %d/%d updated, %d already had community, %d unmatched\n\n",
        $entityType, $updated, $total,
        count(array_filter($entities, fn($e) =>
            ($v = $e->get($config['community_field'])) !== null && $v !== '' && $v !== 0 && $v !== '0'
        )) - $updated,
        $missing - (count(array_filter($entities, fn($e) =>
            ($v = $e->get($config['community_field'])) !== null && $v !== '' && $v !== 0 && $v !== '0'
        )) - $updated)
    );
}

// ── Summary ─────────────────────────────────────────────────────────────────

fprintf(STDOUT, "Summary%s:\n", $dryRun ? ' (DRY RUN — no changes saved)' : '');
foreach ($summary as $type => $count) {
    fprintf(STDOUT, "  Updated %d %s(s)\n", $count, $type);
}
$totalUpdated = array_sum($summary);
fprintf(STDOUT, "  Total: %d entities linked to communities\n", $totalUpdated);
