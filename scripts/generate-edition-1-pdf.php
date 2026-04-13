#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate the print-ready PDF for Elder Newsletter Issue #1.
 *
 * Lifecycle transitions: Curating → Approved → Generated
 * Render pipeline: tokenized URL → Playwright headless → atomic write → SHA-256
 *
 * Prerequisites:
 *   - Edition #1 exists (vol=1, issue=1) in curating state
 *   - Content curated via scripts/curate-edition-1.php
 *   - Inline sections seeded
 *   - Dev server NOT already running on port 8081
 *   - Node + Playwright installed (node_modules/playwright)
 *
 * Usage: php scripts/generate-edition-1-pdf.php
 *
 * The script starts its own dev server on :8081, renders the PDF, then stops it.
 * Do NOT mark the edition as Sent — that is a separate step.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

// --- Boot kernel ---

$projectRoot = dirname(__DIR__);
$kernel = new HttpKernel($projectRoot);
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$config = require $projectRoot . '/config/newsletter.php';

// --- Find edition #1 ---

$editionStorage = $etm->getStorage('newsletter_edition');
$edition = null;

foreach ($editionStorage->loadMultiple() as $e) {
    if ((int) $e->get('volume') === 1 && (int) $e->get('issue_number') === 1) {
        $edition = $e;
        break;
    }
}

if ($edition === null) {
    fwrite(STDERR, "Edition vol=1, issue=1 not found. Run scripts/curate-edition-1.php first.\n");
    exit(1);
}

$status = EditionStatus::fromEntity($edition);
echo "Found edition neid={$edition->id()} (status: {$status->value})\n";

// --- Lifecycle: Curating → Approved ---

$lifecycle = new EditionLifecycle();

if ($status === EditionStatus::Generated) {
    echo "Edition already generated. PDF at: {$edition->get('pdf_path')}\n";
    echo "Re-run after resetting to curating if you need a fresh render.\n";
    exit(0);
}

if ($status === EditionStatus::Sent) {
    fwrite(STDERR, "Edition already sent — cannot re-generate.\n");
    exit(1);
}

if ($status === EditionStatus::Draft) {
    fwrite(STDERR, "Edition is still in draft. Run scripts/curate-edition-1.php to curate first.\n");
    exit(1);
}

if ($status === EditionStatus::Curating) {
    echo "Transitioning: curating → approved...\n";
    $lifecycle->approve($edition, 0); // 0 = script-driven approval
    $editionStorage->save($edition);
    echo "  Approved at: {$edition->get('approved_at')}\n";
} elseif ($status === EditionStatus::Approved) {
    echo "Edition already approved — proceeding to render.\n";
}

// --- Start dev server ---

$port = 8081;
$serverLog = '/tmp/newsletter-generate-server.log';

echo "\nStarting dev server on localhost:{$port}...\n";

$descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', $serverLog, 'w'],
    2 => ['file', $serverLog, 'a'],
];

$serverProcess = proc_open(
    [
        PHP_BINARY,
        '-S', "localhost:{$port}",
        '-t', $projectRoot . '/public',
        $projectRoot . '/public/index.php',
    ],
    $descriptors,
    $pipes,
    $projectRoot,
);

if (!is_resource($serverProcess)) {
    fwrite(STDERR, "Failed to start dev server.\n");
    exit(1);
}

$serverStatus = proc_get_status($serverProcess);
$serverPid = $serverStatus['pid'];

register_shutdown_function(function () use (&$serverProcess): void {
    if (is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        proc_close($serverProcess);
        echo "Stopped dev server.\n";
    }
});

// Wait for server to come up
$retries = 10;
$serverReady = false;
while ($retries-- > 0) {
    usleep(500_000); // 500ms
    $conn = @fsockopen('localhost', $port, $errno, $errstr, 1);
    if ($conn !== false) {
        fclose($conn);
        $serverReady = true;
        break;
    }
}

if (!$serverReady) {
    fwrite(STDERR, "Dev server failed to start on port {$port}. Check {$serverLog}\n");
    exit(1);
}

echo "Dev server ready (PID {$serverPid}).\n";

// --- Render PDF via NewsletterRenderer ---

$storageDir = $projectRoot . '/' . ltrim($config['storage_dir'], '/');
$tokenDir = $storageDir . '/render-tokens';

$tokenStore = new RenderTokenStore($tokenDir, ttlSeconds: 120);

$renderer = new NewsletterRenderer(
    tokenStore: $tokenStore,
    storageDir: $storageDir,
    baseUrl: "http://localhost:{$port}",
    nodeBinary: 'node',
    scriptPath: $projectRoot . '/bin/render-pdf.js',
    timeoutSeconds: (int) ($config['pdf']['timeout_seconds'] ?? 60),
);

echo "\nRendering PDF...\n";

try {
    $artifact = $renderer->render($edition);
} catch (\Throwable $e) {
    fwrite(STDERR, "\nRender FAILED: {$e->getMessage()}\n");
    if (is_file($serverLog)) {
        fwrite(STDERR, "\n--- server log tail ---\n");
        $logTail = file($serverLog);
        if ($logTail !== false) {
            $tail = array_slice($logTail, -20);
            fwrite(STDERR, implode('', $tail));
        }
    }
    exit(1);
}

echo "  PDF written: {$artifact->path}\n";
echo "  Size: {$artifact->bytes} bytes (" . round($artifact->bytes / 1024, 1) . " KB)\n";
echo "  SHA-256: {$artifact->sha256}\n";

// --- Lifecycle: Approved → Generated ---

echo "\nTransitioning: approved → generated...\n";
$lifecycle->markGenerated($edition, $artifact->path, $artifact->sha256);
$editionStorage->save($edition);

echo "  pdf_path: {$edition->get('pdf_path')}\n";
echo "  pdf_hash: {$edition->get('pdf_hash')}\n";
echo "  status:   {$edition->get('status')}\n";

// --- Basic verification ---

echo "\n=== Quick Verification ===\n";

$checks = [];

// File exists and non-empty
$checks['file_exists'] = is_file($artifact->path);
$checks['non_empty'] = $artifact->bytes > 0;

// Hash consistency
$freshHash = hash_file('sha256', $artifact->path);
$checks['hash_matches'] = $freshHash === $artifact->sha256;

// Reasonable file size (12-page B&W PDF should be 50KB–5MB)
$checks['size_reasonable'] = $artifact->bytes > 50_000 && $artifact->bytes < 5_000_000;

// Edition entity state
$checks['status_generated'] = $edition->get('status') === 'generated';
$checks['pdf_path_set'] = $edition->get('pdf_path') !== null && $edition->get('pdf_path') !== '';
$checks['pdf_hash_set'] = $edition->get('pdf_hash') !== null && $edition->get('pdf_hash') !== '';

$allPassed = true;
foreach ($checks as $name => $passed) {
    $icon = $passed ? 'PASS' : 'FAIL';
    echo "  [{$icon}] {$name}\n";
    if (!$passed) {
        $allPassed = false;
    }
}

if (!$allPassed) {
    fwrite(STDERR, "\nSome checks failed. Review the PDF manually.\n");
    exit(1);
}

echo "\nAll checks passed. Run scripts/verify-edition-1-pdf.php for detailed validation.\n";
echo "Edition is NOT marked as Sent — that is a separate step.\n";
echo "\n--- DONE ---\n";
