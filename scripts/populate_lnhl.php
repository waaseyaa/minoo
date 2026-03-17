#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create LNHL event and teaching entities from scraped data.
 * Run: php scripts/populate_lnhl.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$ref = new ReflectionMethod($kernel, 'boot');
$ref->invoke($kernel);

$entityTypeManager = $kernel->getContainer()->get(Waaseyaa\Entity\EntityTypeManager::class);

// Load scraped data
$eventData = json_decode(file_get_contents(dirname(__DIR__) . '/data/lnhl_event.json'), true);
$teachingData = json_decode(file_get_contents(dirname(__DIR__) . '/data/lnhl_teaching.json'), true);

// 1. Create LNHL Event
echo "Creating LNHL 2026 event...\n";
$eventStorage = $entityTypeManager->getStorage('event');

$existingIds = $eventStorage->getQuery()->condition('slug', 'little-nhl-2026')->execute();
if ($existingIds !== []) {
    $event = $eventStorage->load(reset($existingIds));
    echo "  Found existing event (eid: {$event->id()}), updating...\n";
} else {
    $event = new \Minoo\Entity\Event([
        'title' => $eventData['title'] ?? $eventData['name'],
        'slug' => 'little-nhl-2026',
    ]);
}

$event->set('title', $eventData['title'] ?? $eventData['name']);
$event->set('type', 'tournament');
$event->set('description', $eventData['description'] ?? '');
$event->set('location', $eventData['location'] ?? 'Markham, Ontario');
$event->set('starts_at', $eventData['dates']['start'] ?? '2026-03-15');
$event->set('ends_at', $eventData['dates']['end'] ?? '2026-03-19');
$event->set('source', 'scrape:lnhl:2026-03-17');
$event->set('status', 1);
$event->set('copyright_status', 'community_owned');
$event->set('created_at', time());
$event->set('updated_at', time());

$eventStorage->save($event);
echo "Saved LNHL 2026 event (eid: {$event->id()})\n";

// 2. Create LNHL Teaching
echo "\nCreating LNHL teaching...\n";
$teachingStorage = $entityTypeManager->getStorage('teaching');

$existingIds = $teachingStorage->getQuery()->condition('slug', 'the-little-native-hockey-league')->execute();
if ($existingIds !== []) {
    $teaching = $teachingStorage->load(reset($existingIds));
    echo "  Found existing teaching (tid: {$teaching->id()}), updating...\n";
} else {
    $teaching = new \Minoo\Entity\Teaching([
        'title' => $teachingData['title'],
        'slug' => 'the-little-native-hockey-league',
    ]);
}

$teaching->set('title', $teachingData['title']);
$teaching->set('type', 'history');
$teaching->set('description', $teachingData['body'] ?? '');
$teaching->set('source', 'scrape:lnhl:2026-03-17');
$teaching->set('status', 1);
$teaching->set('copyright_status', 'community_owned');
$teaching->set('created_at', time());
$teaching->set('updated_at', time());

$teachingStorage->save($teaching);
echo "Saved LNHL teaching (tid: {$teaching->id()})\n";

// 3. Create Crystal Shawanda as ResourcePerson
echo "\nCreating Crystal Shawanda resource person...\n";
$personStorage = $entityTypeManager->getStorage('resource_person');
$existingIds = $personStorage->getQuery()->condition('slug', 'crystal-shawanda')->execute();

if ($existingIds !== []) {
    $person = $personStorage->load(reset($existingIds));
    echo "  Found existing person (rpid: {$person->id()}), updating...\n";
} else {
    $person = new \Minoo\Entity\ResourcePerson([
        'name' => 'Crystal Shawanda',
        'slug' => 'crystal-shawanda',
    ]);
}

$person->set('bio', 'Ojibwe country and blues artist from Wiikwemkoong Unceded Territory. Award-winning musician based in Nashville who maintains deep ties to her community and the Little NHL.');
$person->set('community', 'Wiikwemkoong Unceded Territory');
$person->set('website', 'https://crystalshawanda.com');
$person->set('source', 'observed:lnhl:2026-03-17');
$person->set('status', 1);
$person->set('copyright_status', 'community_owned');
$person->set('updated_at', time());

// Look up Artist role
$termStorage = $entityTypeManager->getStorage('taxonomy_term');
$roleIds = $termStorage->getQuery()
    ->condition('name', 'Artist')
    ->condition('vid', 'person_roles')
    ->execute();
if ($roleIds !== []) {
    $person->set('roles', [reset($roleIds)]);
}

$personStorage->save($person);
echo "Saved Crystal Shawanda (rpid: {$person->id()})\n";

echo "\nDone. Verify at:\n";
echo "  /events/little-nhl-2026\n";
echo "  /teachings/the-little-native-hockey-league\n";
echo "  /people/crystal-shawanda\n";
