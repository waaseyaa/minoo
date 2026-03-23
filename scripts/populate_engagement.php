#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Populate the local database with engagement seed data (users, posts, reactions, comments, follows).
 * Run: php scripts/populate_engagement.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Minoo\Seed\EngagementSeeder;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$etm = $kernel->getEntityTypeManager();

// --- Resolve communities ---

$communityStorage = $etm->getStorage('community');
$communityIds = [];

foreach (EngagementSeeder::communityNames() as $index => $name) {
    $ids = $communityStorage->getQuery()->condition('name', $name)->execute();
    if ($ids === []) {
        echo "Warning: Community '{$name}' not found. Run community seeding first.\n";
        exit(1);
    }
    $communityIds[$index] = (int) reset($ids);
    echo "Found community: {$name} (id: {$communityIds[$index]})\n";
}

// --- Create users (idempotent) ---

$userStorage = $etm->getStorage('user');
$userIds = [];

foreach (EngagementSeeder::users() as $index => $userData) {
    $existing = $userStorage->getQuery()->condition('mail', $userData['mail'])->execute();

    if ($existing !== []) {
        $userIds[$index] = (int) reset($existing);
        echo "User '{$userData['name']}' already exists (uid: {$userIds[$index]}), skipping.\n";
        continue;
    }

    $user = $userStorage->create([
        'name' => $userData['name'],
        'mail' => $userData['mail'],
        'roles' => $userData['roles'],
        'status' => $userData['status'],
    ]);
    $user->setRawPassword('password123');
    $userStorage->save($user);
    $userIds[$index] = (int) $user->id();
    echo "Created user: {$userData['name']} (uid: {$userIds[$index]})\n";
}

// --- Create posts ---

$postStorage = $etm->getStorage('post');
$postIds = [];

foreach (EngagementSeeder::posts() as $index => $postData) {
    $post = $postStorage->create([
        'body' => $postData['body'],
        'user_id' => $userIds[$postData['user_index']],
        'community_id' => $communityIds[$postData['community_index']],
    ]);
    $postStorage->save($post);
    $postIds[$index] = (int) $post->id();
}
echo "Created " . count($postIds) . " posts.\n";

// --- Create reactions ---

$reactionStorage = $etm->getStorage('reaction');
$reactionCount = 0;

foreach (EngagementSeeder::reactions() as $data) {
    $reaction = $reactionStorage->create([
        'reaction_type' => $data['reaction_type'],
        'user_id' => $userIds[$data['user_index']],
        'target_type' => $data['target_type'],
        'target_id' => $postIds[$data['post_index']],
    ]);
    $reactionStorage->save($reaction);
    ++$reactionCount;
}
echo "Created {$reactionCount} reactions.\n";

// --- Create comments ---

$commentStorage = $etm->getStorage('comment');
$commentCount = 0;

foreach (EngagementSeeder::comments() as $data) {
    $comment = $commentStorage->create([
        'body' => $data['body'],
        'user_id' => $userIds[$data['user_index']],
        'target_type' => $data['target_type'],
        'target_id' => $postIds[$data['post_index']],
    ]);
    $commentStorage->save($comment);
    ++$commentCount;
}
echo "Created {$commentCount} comments.\n";

// --- Create follows ---

$followStorage = $etm->getStorage('follow');
$followCount = 0;

foreach (EngagementSeeder::follows() as $data) {
    $targetId = $data['target_type'] === 'community'
        ? $communityIds[$data['target_index']]
        : $postIds[$data['target_index']];

    $follow = $followStorage->create([
        'user_id' => $userIds[$data['user_index']],
        'target_type' => $data['target_type'],
        'target_id' => $targetId,
    ]);
    $followStorage->save($follow);
    ++$followCount;
}
echo "Created {$followCount} follows.\n";

echo "\nDone. Visit localhost:8081/feed to verify.\n";
