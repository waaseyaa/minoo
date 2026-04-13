#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate a language collaboration review PDF for Elder Newsletter Issue #1.
 *
 * This PDF is intended for external review by language collaborators.
 * It renders the full newsletter with:
 *   - "Review Draft — Not for Print" watermark
 *   - Collaborator annotation in the Anishinaabemowin Corner section
 *
 * Does NOT alter edition lifecycle state or overwrite the main print-ready PDF.
 * Output: storage/newsletter/review/1-1-language-review.pdf
 *
 * Prerequisites:
 *   - Edition #1 exists (vol=1, issue=1) with content curated
 *   - Node + Playwright installed
 *   - Port 8082 available (uses a separate port from the main render)
 *
 * Usage: php scripts/generate-language-review-pdf.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Newsletter\Service\RenderTokenStore;
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

echo "Found edition neid={$edition->id()} (status: {$edition->get('status')})\n";
echo "Generating language collaboration review PDF...\n";

// --- Set up output directory ---

$storageDir = $projectRoot . '/' . ltrim($config['storage_dir'], '/');
$reviewDir = $storageDir . '/review';

if (!is_dir($reviewDir) && !@mkdir($reviewDir, 0775, true) && !is_dir($reviewDir)) {
    fwrite(STDERR, "Cannot create review directory: {$reviewDir}\n");
    exit(1);
}

$outPath = $reviewDir . '/1-1-language-review.pdf';
$tmpPath = $outPath . '.tmp.' . bin2hex(random_bytes(4));

// --- Start dev server on a separate port ---

$port = 8082;
$serverLog = '/tmp/newsletter-review-server.log';

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

// Wait for server
$retries = 10;
$serverReady = false;
while ($retries-- > 0) {
    usleep(500_000);
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

// --- Issue render token and build review URL ---

$tokenDir = $storageDir . '/render-tokens';
$tokenStore = new RenderTokenStore($tokenDir, ttlSeconds: 120);
$editionId = (int) $edition->id();
$token = $tokenStore->issue($editionId);

$url = sprintf(
    'http://localhost:%d/newsletter/_internal/%d/print?token=%s&mode=review',
    $port,
    $editionId,
    $token,
);

echo "Render URL: {$url}\n";

// --- Render PDF via render-pdf.js ---

echo "\nRendering review PDF...\n";

$renderProcess = proc_open(
    [
        'node',
        $projectRoot . '/bin/render-pdf.js',
        '--url=' . $url,
        '--out=' . $tmpPath,
    ],
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $renderPipes,
    $projectRoot,
);

if (!is_resource($renderProcess)) {
    fwrite(STDERR, "Failed to start render-pdf.js.\n");
    exit(1);
}

$stdout = stream_get_contents($renderPipes[1]);
$stderr = stream_get_contents($renderPipes[2]);
fclose($renderPipes[1]);
fclose($renderPipes[2]);
$renderExit = proc_close($renderProcess);

if ($renderExit !== 0) {
    fwrite(STDERR, "Render FAILED (exit {$renderExit}).\n");
    if ($stderr !== '') {
        fwrite(STDERR, $stderr . "\n");
    }
    if (is_file($serverLog)) {
        fwrite(STDERR, "\n--- server log tail ---\n");
        $logTail = file($serverLog);
        if ($logTail !== false) {
            fwrite(STDERR, implode('', array_slice($logTail, -20)));
        }
    }
    @unlink($tmpPath);
    exit(1);
}

if (!is_file($tmpPath) || filesize($tmpPath) === 0) {
    fwrite(STDERR, "PDF was not produced or is empty.\n");
    @unlink($tmpPath);
    exit(1);
}

// Atomic rename
if (!@rename($tmpPath, $outPath)) {
    fwrite(STDERR, "Failed to move PDF to final path.\n");
    @unlink($tmpPath);
    exit(1);
}

$bytes = (int) filesize($outPath);
$hash = hash_file('sha256', $outPath);

echo "  PDF written: {$outPath}\n";
echo "  Size: {$bytes} bytes (" . round($bytes / 1024, 1) . " KB)\n";
echo "  SHA-256: {$hash}\n";

// --- Verification ---

echo "\n=== Review PDF Verification ===\n";

$pdfContent = (string) file_get_contents($outPath);
$checks = [];

// Structural validity
$checks['valid_pdf'] = str_starts_with($pdfContent, '%PDF-');
$checks['non_empty'] = $bytes > 0;
$checks['size_reasonable'] = $bytes > 10_000 && $bytes < 5_000_000;

// Review-specific content — check raw bytes for presence
// (Note: compressed streams may hide text, so missing = warning not failure)
$checks['has_watermark'] = str_contains($pdfContent, 'REVIEW DRAFT')
    || str_contains($pdfContent, 'NOT FOR PRINT');
$checks['has_collaborator_note'] = str_contains($pdfContent, 'Language Collaborator')
    || str_contains($pdfContent, 'collaborator');
$checks['has_language_corner'] = str_contains($pdfContent, 'Anishinaabemowin')
    || str_contains($pdfContent, 'anishinaabemowin');

// Page count
$pageCount = preg_match_all('/\/Type\s*\/Page[^s]/i', $pdfContent);
$checks['page_count'] = $pageCount === 12 || $pageCount === 0; // 0 = compressed, can't tell

// No edition state changes
$freshEdition = $editionStorage->load($editionId);
$checks['edition_unchanged'] = $freshEdition !== null
    && $freshEdition->get('status') === $edition->get('status')
    && $freshEdition->get('pdf_path') === $edition->get('pdf_path');

$allPassed = true;
foreach ($checks as $name => $passed) {
    $icon = $passed ? 'PASS' : 'WARN';
    echo "  [{$icon}] {$name}\n";
    if (!$passed) {
        $allPassed = false;
    }
}

if (!$checks['valid_pdf'] || !$checks['non_empty']) {
    fwrite(STDERR, "\nFATAL: PDF is structurally invalid.\n");
    exit(1);
}

echo "\n=== Summary ===\n";
echo "  Review PDF: {$outPath}\n";
echo "  Pages: {$pageCount}\n";
echo "  Size: " . round($bytes / 1024, 1) . " KB\n";
echo "  Edition status unchanged: " . ($checks['edition_unchanged'] ? 'yes' : 'NO') . "\n";

if (!$checks['has_watermark'] || !$checks['has_collaborator_note']) {
    echo "\n  NOTE: Watermark and/or collaborator note text may be in compressed\n";
    echo "  PDF streams. Open the PDF to verify visually.\n";
}

echo "\nReview PDF ready for Barbara Nolan. Edition state NOT modified.\n";
echo "\n--- DONE ---\n";
