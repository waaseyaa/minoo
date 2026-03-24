<?php

/**
 * Update the first post on production to be a Minoo introduction.
 *
 * Usage (on production server):
 *   cd /home/deployer/minoo/current
 *   php scripts/update_intro_post.php
 *
 * This script:
 * 1. Backs up the database
 * 2. Finds the first post by Russell Jones
 * 3. Updates it to an introduction post
 */

declare(strict_types=1);

// Boot the kernel (ConsoleKernel is broken — use HttpKernel via reflection)
$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel($projectRoot);
$boot = new \ReflectionMethod($kernel, 'boot');
$boot->invoke($kernel);

// Resolve DB path
$dbPath = getenv('WAASEYAA_DB') ?: $projectRoot . '/storage/waaseyaa.sqlite';

if (!file_exists($dbPath)) {
    echo "ERROR: Database not found at {$dbPath}\n";
    exit(1);
}

// 1. Backup
$backupDir = dirname($dbPath) . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$backupPath = $backupDir . '/waaseyaa_' . date('Y-m-d_His') . '_pre_intro_post.sqlite';
if (!copy($dbPath, $backupPath)) {
    echo "ERROR: Failed to backup database to {$backupPath}\n";
    exit(1);
}
echo "Backed up database to {$backupPath}\n";

// 2. Find Russell's post
$entityTypeManager = $kernel->resolve(\Waaseyaa\Entity\EntityTypeManager::class);
$postStorage = $entityTypeManager->getStorage('post');

$postIds = $postStorage->getQuery()
    ->condition('status', 1)
    ->sort('created_at', 'ASC')
    ->range(0, 5)
    ->execute();

if ($postIds === []) {
    echo "ERROR: No posts found in database\n";
    exit(1);
}

$posts = $postStorage->loadMultiple($postIds);
$targetPost = null;

foreach ($posts as $post) {
    echo sprintf("Found post #%s by user %s: %s\n", $post->id(), $post->get('user_id'), substr((string) $post->get('body'), 0, 60));
    if ($targetPost === null) {
        $targetPost = $post;
    }
}

if ($targetPost === null) {
    echo "ERROR: No post to update\n";
    exit(1);
}

echo sprintf("\nUpdating post #%s...\n", $targetPost->id());

// 3. Update with introduction content
$introBody = "Welcome to Minoo. This is a place built for our communities, by our communities.\n\n"
    . "Here you can find local events, connect with groups and organizations, explore teachings from Knowledge Keepers and Elders, "
    . "and stay connected with what matters most to the people around you.\n\n"
    . "Minoo was built because we needed something of our own. A place where our stories, our languages, and our ways of helping each other "
    . "can live and grow without being shaped by someone else's idea of what a community platform should look like.\n\n"
    . "Look around. Share what you know. Ask for what you need. This is your space.";

$targetPost->set('body', $introBody);
$postStorage->save($targetPost);

echo "Post updated successfully.\n";
echo "New body:\n---\n{$introBody}\n---\n";
