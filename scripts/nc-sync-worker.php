#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * NC Content Sync Worker — polls NorthCloud Search API every 30 minutes.
 * Managed by systemd: minoo-nc-sync.service
 * Run: php scripts/nc-sync-worker.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Ingestion\EntityMapper\NcArticleToEventMapper;
use App\Ingestion\EntityMapper\NcArticleToTeachingMapper;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Waaseyaa\NorthCloud\Sync\NcSyncService;
use Waaseyaa\NorthCloud\Sync\NcSyncWorker;

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
$timeout = (int) ($config['search']['timeout'] ?? 15);
$registry = new MapperRegistry();
$registry->register(new NcArticleToTeachingMapper());
$registry->register(new NcArticleToEventMapper());

$client = new NorthCloudClient(
    baseUrl: $baseUrl,
    timeout: $timeout,
    apiToken: (string) ($config['northcloud']['api_token'] ?? ''),
    allowInsecure: str_starts_with($baseUrl, 'http://localhost')
        || str_starts_with($baseUrl, 'http://127.0.0.1')
        || str_starts_with($baseUrl, 'http://[::1]'),
);
$syncService = new NcSyncService($client, $kernel->getEntityTypeManager(), $registry);

$statusPath = dirname(__DIR__) . '/storage/nc-sync-status.json';

$loop = new NcSyncWorker(
    syncService: $syncService,
    statusPath: $statusPath,
    intervalSeconds: 1800,
    maxCycles: 0,
);

// Signal handling
pcntl_async_signals(true);
$shutdown = static function () use ($loop): void {
    fprintf(STDOUT, "Received shutdown signal, finishing current cycle...\n");
    $loop->stop();
};
pcntl_signal(SIGTERM, $shutdown);
pcntl_signal(SIGINT, $shutdown);

fprintf(STDOUT, "NC Sync Worker started (interval=30m, max_cycles=unlimited)\n");
$loop->run();
fprintf(STDOUT, "NC Sync Worker stopped.\n");
