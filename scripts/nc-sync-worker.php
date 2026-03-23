#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * NC Content Sync Worker — polls NorthCloud Search API every 30 minutes.
 * Managed by systemd: minoo-nc-sync.service
 * Run: php scripts/nc-sync-worker.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Minoo\Ingestion\NcContentSyncService;
use Minoo\Ingestion\NcSyncWorkerLoop;
use Minoo\Support\NorthCloudClient;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

// Boot kernel
$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

// Validate config
$config = require dirname(__DIR__) . '/config/waaseyaa.php';
$baseUrl = $config['northcloud']['base_url'] ?? '';
if ($baseUrl === '') {
    fprintf(STDERR, "FATAL: northcloud.base_url is not configured. Set NORTHCLOUD_BASE_URL.\n");
    exit(1);
}

// Build services
$searchTimeout = (int) ($config['search']['timeout'] ?? 15);
$client = new NorthCloudClient(baseUrl: $baseUrl, timeout: $searchTimeout);
$syncService = new NcContentSyncService($client, $kernel->getEntityTypeManager());

$statusPath = dirname(__DIR__) . '/storage/nc-sync-status.json';

$loop = new NcSyncWorkerLoop(
    syncService: $syncService,
    statusPath: $statusPath,
    intervalSeconds: 1800,
    maxCycles: 48,
);

// Signal handling
pcntl_async_signals(true);
$shutdown = static function () use ($loop): void {
    fprintf(STDOUT, "Received shutdown signal, finishing current cycle...\n");
    $loop->stop();
};
pcntl_signal(SIGTERM, $shutdown);
pcntl_signal(SIGINT, $shutdown);

fprintf(STDOUT, "NC Sync Worker started (interval=30m, max_cycles=48)\n");
$loop->run();
fprintf(STDOUT, "NC Sync Worker stopped.\n");
