#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Remove the generic "Nginaajiiw Salon Owner" placeholder person.
 * Larissa Toulouse is the real owner — she already exists in the DB
 * and in content/people.json.
 *
 * Usage: php scripts/cleanup_nginaajiiw_placeholder.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\ConsoleKernel;

$kernel = new ConsoleKernel(dirname(__DIR__));
(new ReflectionMethod($kernel, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$storage = $etm->getStorage('resource_person');

// Find and delete the placeholder
$ids = $storage->getQuery()->condition('slug', 'nginaajiiw-salon-owner')->execute();

if ($ids === []) {
    echo "No 'nginaajiiw-salon-owner' placeholder found — already cleaned up.\n";
    exit(0);
}

$person = $storage->load(reset($ids));
echo "Found placeholder: {$person->get('name')} (rpid: {$person->id()}, slug: {$person->get('slug')})\n";

$storage->delete([$person]);
echo "Deleted.\n";

// Verify Larissa Toulouse exists
$larisaIds = $storage->getQuery()->condition('slug', 'larissa-toulouse')->execute();
if ($larisaIds !== []) {
    $larissa = $storage->load(reset($larisaIds));
    echo "Verified: Larissa Toulouse exists (rpid: {$larissa->id()})\n";
    echo "  linked_group_id: " . ($larissa->get('linked_group_id') ?: '(not set)') . "\n";
} else {
    echo "Warning: Larissa Toulouse not found — run 'bin/seed-content --apply --file people' to create her.\n";
}

echo "\nDone.\n";
