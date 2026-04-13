<?php

declare(strict_types=1);

/**
 * Dry-run PDF render: renders a minimal test edition to PDF via the print
 * template and validates the output structurally. Does NOT mark the edition
 * as Generated or send email.
 *
 * Requirements: Node.js + Playwright (npx playwright install chromium)
 *
 * Usage: php scripts/render-dry-run.php [edition_id]
 *        php scripts/render-dry-run.php          # defaults to edition 1
 *
 * Exit codes:
 *   0 — PDF rendered and validated successfully
 *   1 — Render or validation failure
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$editionId = (int) ($argv[1] ?? 1);
$projectRoot = dirname(__DIR__);

// Boot kernel
$kernel = new HttpKernel($projectRoot);
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$storage = $etm->getStorage('newsletter_edition');
$edition = $storage->load($editionId);

if ($edition === null) {
    fwrite(STDERR, "FAIL: No newsletter_edition with ID {$editionId} found.\n");
    fwrite(STDERR, "Hint: Run 'php scripts/curate-edition-1.php' first to create an edition.\n");
    exit(1);
}

echo "Dry-run render for edition {$editionId}: {$edition->get('headline')}\n";
echo "Status: {$edition->get('status')}\n";

// Render to a temp location (not the real storage dir)
$dryRunDir = sys_get_temp_dir() . '/newsletter-dry-run-' . bin2hex(random_bytes(4));
$tokenDir = $dryRunDir . '/tokens';

$tokenStore = new RenderTokenStore($tokenDir, ttlSeconds: 120);
$renderer = new NewsletterRenderer(
    tokenStore: $tokenStore,
    storageDir: $dryRunDir,
    baseUrl: 'http://localhost:8081',
    nodeBinary: 'node',
    scriptPath: $projectRoot . '/bin/render-pdf.js',
    timeoutSeconds: 60,
);

// Start dev server in background using proc_open (no shell injection)
$serverLog = sys_get_temp_dir() . '/newsletter-dry-run-server.log';
$serverProc = proc_open(
    [
        PHP_BINARY,
        '-S', 'localhost:8081',
        '-t', $projectRoot . '/public',
        $projectRoot . '/public/index.php',
    ],
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $serverLog, 'w'],
        2 => ['file', $serverLog, 'w'],
    ],
    $pipes,
);

if (! is_resource($serverProc)) {
    fwrite(STDERR, "FAIL: Could not start dev server.\n");
    exit(1);
}

$serverStatus = proc_get_status($serverProc);
$serverPid = $serverStatus['pid'] ?? 0;
echo "Dev server started (PID {$serverPid}) on localhost:8081\n";

// Give server a moment to start
usleep(1_500_000);

$exitCode = 0;

try {
    $artifact = $renderer->render($edition);

    echo "\n--- Render Results ---\n";
    echo "Path:   {$artifact->path}\n";
    echo "Bytes:  {$artifact->bytes}\n";
    echo "SHA256: {$artifact->sha256}\n";

    // Structural validation
    $errors = [];

    // 1. Non-zero size
    if ($artifact->bytes === 0) {
        $errors[] = 'PDF is zero bytes';
    }

    // 2. PDF magic bytes: first 5 bytes should be %PDF-
    $handle = fopen($artifact->path, 'rb');
    if ($handle === false) {
        $errors[] = 'Cannot open PDF file for reading';
    } else {
        $header = fread($handle, 5);
        fclose($handle);
        if ($header !== '%PDF-') {
            $errors[] = sprintf('Invalid PDF header: expected "%%PDF-", got "%s"', addcslashes((string) $header, "\0..\37"));
        }
    }

    // 3. SHA-256 matches re-computed hash
    $recomputedHash = hash_file('sha256', $artifact->path);
    if ($recomputedHash !== $artifact->sha256) {
        $errors[] = sprintf('SHA-256 mismatch: artifact=%s, recomputed=%s', $artifact->sha256, $recomputedHash);
    }

    // 4. Minimum viable size (a blank 1-page PDF is ~800 bytes; 12-page should be larger)
    if ($artifact->bytes < 500) {
        $errors[] = sprintf('PDF suspiciously small (%d bytes) — may be corrupt', $artifact->bytes);
    }

    if ($errors !== []) {
        echo "\nFAIL: Structural validation errors:\n";
        foreach ($errors as $err) {
            echo "  - {$err}\n";
        }
        $exitCode = 1;
    } else {
        echo "\nOK: PDF passes all structural checks.\n";
        echo "NOTE: Edition status NOT changed (dry-run). PDF at: {$artifact->path}\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("\nFAIL: %s\n%s\n", $e->getMessage(), $e->getTraceAsString()));
    $exitCode = 1;
} finally {
    // Kill the dev server
    proc_terminate($serverProc, SIGTERM);
    proc_close($serverProc);
    echo "Dev server stopped.\n";
}

exit($exitCode);
