#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Populate Nginaajiiw Salon & Spa business entity from scraped data.
 * Run: php scripts/populate_nginaajiiw.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

// Boot kernel
$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();

// Load scraped data
$websiteData = json_decode(file_get_contents(dirname(__DIR__) . '/data/nginaajiiw_website.json'), true);
$socialData = json_decode(file_get_contents(dirname(__DIR__) . '/data/nginaajiiw_social.json'), true);

// 1. Find and update Nginaajiiw group entity
$groupStorage = $entityTypeManager->getStorage('group');
$ids = $groupStorage->getQuery()->condition('slug', 'nginaajiiw-salon-spa')->execute();

if ($ids === []) {
    echo "Warning: Nginaajiiw group entity not found by slug. Check database.\n";
    exit(1);
}

$group = $groupStorage->load(reset($ids));
echo "Found group: {$group->get('name')} (gid: {$group->id()})\n";

// Update type to business
$group->set('type', 'business');
$group->set('description', $websiteData['description'] ?? $group->get('description'));
$group->set('url', $websiteData['website'] ?? '');
$group->set('phone', $websiteData['phone'] ?? '');
$group->set('email', $websiteData['email'] ?? '');
$group->set('address', $websiteData['address'] ?? '');
$group->set('booking_url', $websiteData['booking_url'] ?? '');
$group->set('source', 'scrape:nginaajiiw:2026-03-17');
$group->set('updated_at', time());

// Merge social posts into a flat array
$socialPosts = [];
foreach ($socialData['instagram']['posts'] ?? [] as $post) {
    $socialPosts[] = [
        'source' => 'instagram',
        'text' => $post['caption'] ?? '',
        'image_url' => $post['image_url'] ?? '',
        'date' => $post['date'] ?? '',
        'permalink' => $post['permalink'] ?? '',
    ];
}
foreach ($socialData['facebook']['posts'] ?? [] as $post) {
    $socialPosts[] = [
        'source' => 'facebook',
        'text' => $post['text'] ?? '',
        'image_url' => $post['image_url'] ?? '',
        'date' => $post['date'] ?? '',
        'permalink' => $post['permalink'] ?? '',
    ];
}
$group->set('social_posts', json_encode(array_slice($socialPosts, 0, 12)));

$groupStorage->save($group);
$count = count($socialPosts);
echo "Updated Nginaajiiw: type=business, fields populated, {$count} social posts\n";

// 2. Create or update Larissa Toulouse ResourcePerson
$personStorage = $entityTypeManager->getStorage('resource_person');
$existingIds = $personStorage->getQuery()->condition('slug', 'larissa-toulouse')->execute();

if ($existingIds !== []) {
    $person = $personStorage->load(reset($existingIds));
    echo "Found existing person: {$person->get('name')} (rpid: {$person->id()})\n";
} else {
    $person = new \App\Entity\ResourcePerson([
        'name' => 'Larissa Toulouse',
        'slug' => 'larissa-toulouse',
    ]);
    echo "Creating new person: Larissa Toulouse\n";
}

$person->set('business_name', 'Nginaajiiw Salon & Spa');
$person->set('linked_group_id', $group->id());
$person->set('website', $websiteData['website'] ?? '');
$person->set('source', 'scrape:nginaajiiw:2026-03-17');
$person->set('updated_at', time());

// Note: roles and offerings are entity_reference fields — need term IDs
$termStorage = $entityTypeManager->getStorage('taxonomy_term');
$roleIds = $termStorage->getQuery()
    ->condition('name', 'Small Business Owner')
    ->condition('vid', 'person_roles')
    ->execute();
if ($roleIds !== []) {
    $person->set('roles', [reset($roleIds)]);
    echo "  Linked role: Small Business Owner (tid: " . reset($roleIds) . ")\n";
}

$offeringNames = ['Hair Services', 'Esthetics'];
$offeringIds = [];
foreach ($offeringNames as $offeringName) {
    $tids = $termStorage->getQuery()
        ->condition('name', $offeringName)
        ->condition('vid', 'person_offerings')
        ->execute();
    if ($tids !== []) {
        $offeringIds[] = reset($tids);
    }
}
if ($offeringIds !== []) {
    $person->set('offerings', $offeringIds);
    echo "  Linked offerings: " . implode(', ', $offeringNames) . "\n";
}

$personStorage->save($person);
echo "Saved Larissa Toulouse (rpid: {$person->id()})\n";

echo "\nDone. Verify at:\n";
echo "  /businesses/nginaajiiw-salon-spa\n";
echo "  /people/larissa-toulouse\n";
