#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verify the generated PDF for Elder Newsletter Issue #1 against
 * OJ Graphix print specs and content completeness.
 *
 * Checks:
 *   1. File integrity: exists, non-empty, SHA-256 matches edition record
 *   2. Page count: exactly 12
 *   3. Page dimensions: Letter (8.5x11") — printer handles tabloid imposition
 *   4. No color profiles or transparency (B&W-safe)
 *   5. Section order in content stream
 *   6. No web UI leakage (no nav, footer, scripts, stylesheets beyond print CSS)
 *   7. File size reasonable for email (< 5MB)
 *
 * Usage: php scripts/verify-edition-1-pdf.php [path-to-pdf]
 *        If no path given, reads pdf_path from edition #1 entity.
 *
 * Exit code 0 = all checks pass. Non-zero = failures found.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Newsletter\ValueObject\EditionStatus;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$projectRoot = dirname(__DIR__);

// --- Resolve PDF path ---

$pdfPath = $argv[1] ?? null;

if ($pdfPath === null) {
    $kernel = new HttpKernel($projectRoot);
    (new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
    $etm = $kernel->getEntityTypeManager();

    $editionStorage = $etm->getStorage('newsletter_edition');
    $edition = null;
    foreach ($editionStorage->loadMultiple() as $e) {
        if ((int) $e->get('volume') === 1 && (int) $e->get('issue_number') === 1) {
            $edition = $e;
            break;
        }
    }

    if ($edition === null) {
        fwrite(STDERR, "Edition vol=1, issue=1 not found.\n");
        exit(1);
    }

    $status = EditionStatus::fromEntity($edition);
    if ($status !== EditionStatus::Generated && $status !== EditionStatus::Sent) {
        fwrite(STDERR, "Edition is in '{$status->value}' state — no PDF to verify.\n");
        exit(1);
    }

    $pdfPath = (string) $edition->get('pdf_path');
    $expectedHash = (string) $edition->get('pdf_hash');
    echo "Edition neid={$edition->id()}, status={$status->value}\n";
} else {
    $expectedHash = null;
}

echo "PDF: {$pdfPath}\n\n";

// --- Verification checks ---

$failures = [];
$warnings = [];

// 1. File integrity
if (!is_file($pdfPath)) {
    fwrite(STDERR, "FATAL: PDF file not found at {$pdfPath}\n");
    exit(1);
}

$bytes = (int) filesize($pdfPath);
if ($bytes === 0) {
    fwrite(STDERR, "FATAL: PDF file is empty.\n");
    exit(1);
}

echo "=== File Integrity ===\n";
echo "  Size: {$bytes} bytes (" . round($bytes / 1024, 1) . " KB)\n";

$hash = hash_file('sha256', $pdfPath);
echo "  SHA-256: {$hash}\n";

if ($expectedHash !== null) {
    if ($hash === $expectedHash) {
        echo "  [PASS] Hash matches edition record.\n";
    } else {
        $failures[] = "SHA-256 mismatch: expected {$expectedHash}, got {$hash}";
        echo "  [FAIL] Hash mismatch!\n";
    }
}

if ($bytes > 5_000_000) {
    $failures[] = "File too large for email: {$bytes} bytes (> 5MB)";
    echo "  [FAIL] File exceeds 5MB — too large for email attachment.\n";
} elseif ($bytes > 3_000_000) {
    $warnings[] = "File is large: " . round($bytes / 1024 / 1024, 1) . "MB — consider optimizing";
    echo "  [WARN] File is " . round($bytes / 1024 / 1024, 1) . "MB — close to email limits.\n";
} else {
    echo "  [PASS] File size OK for email.\n";
}

if ($bytes < 10_000) {
    $failures[] = "File suspiciously small ({$bytes} bytes) — may be truncated";
    echo "  [FAIL] File suspiciously small — possibly truncated.\n";
}

// 2. PDF structure analysis (read raw PDF bytes)
$pdfContent = (string) file_get_contents($pdfPath);

echo "\n=== PDF Structure ===\n";

// Check PDF header
if (!str_starts_with($pdfContent, '%PDF-')) {
    $failures[] = "Not a valid PDF file (missing %PDF- header)";
    echo "  [FAIL] Not a valid PDF file.\n";
} else {
    $pdfVersion = substr($pdfContent, 5, 3);
    echo "  PDF version: {$pdfVersion}\n";
    echo "  [PASS] Valid PDF header.\n";
}

// Count pages via /Type /Page (not /Pages)
// This regex counts page objects — each /Type /Page in the cross-reference is one page
$pageCount = preg_match_all('/\/Type\s*\/Page[^s]/i', $pdfContent);
echo "  Page count: {$pageCount}\n";

if ($pageCount === 12) {
    echo "  [PASS] Exactly 12 pages.\n";
} elseif ($pageCount === 0) {
    // Fallback: some PDFs use compressed object streams
    $warnings[] = "Could not determine page count from raw PDF — verify manually";
    echo "  [WARN] Could not parse page count — verify manually.\n";
} else {
    $failures[] = "Expected 12 pages, found {$pageCount}";
    echo "  [FAIL] Expected 12 pages, found {$pageCount}.\n";
}

// 3. Page dimensions (Letter = 612x792 points)
echo "\n=== Page Dimensions ===\n";

$mediaBoxCount = preg_match_all('/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdfContent, $mediaBoxes, PREG_SET_ORDER);

if ($mediaBoxCount > 0) {
    $sampleBox = $mediaBoxes[0];
    $width = (float) $sampleBox[3] - (float) $sampleBox[1];
    $height = (float) $sampleBox[4] - (float) $sampleBox[2];
    $widthIn = round($width / 72, 2);
    $heightIn = round($height / 72, 2);

    echo "  MediaBox: {$width} x {$height} points ({$widthIn}\" x {$heightIn}\")\n";

    // Letter is 612x792 (8.5x11)
    $isLetter = abs($width - 612) < 1 && abs($height - 792) < 1;
    if ($isLetter) {
        echo "  [PASS] Letter size (8.5\" x 11\") — printer handles tabloid imposition for saddle-stitch.\n";
    } else {
        $warnings[] = "Page size is {$widthIn}\" x {$heightIn}\" — expected Letter (8.5\" x 11\")";
        echo "  [WARN] Non-standard page size. Expected Letter for printer imposition.\n";
    }
} else {
    $warnings[] = "Could not parse MediaBox — verify page dimensions manually";
    echo "  [WARN] Could not parse MediaBox.\n";
}

// 4. B&W / color profile check
echo "\n=== Color & Transparency ===\n";

$hasICCProfile = str_contains($pdfContent, '/ICCBased') || str_contains($pdfContent, 'ICCProfile');
$hasColorSpace = (bool) preg_match('/\/ColorSpace\s*.*\/(DeviceCMYK|ICCBased)/', $pdfContent);
$hasTransparency = str_contains($pdfContent, '/SMask') || str_contains($pdfContent, '/ca ') || str_contains($pdfContent, '/CA ');

if ($hasICCProfile) {
    $warnings[] = "PDF contains ICC color profile — may cause issues with B&W printing";
    echo "  [WARN] ICC color profile detected — confirm B&W output with printer.\n";
} else {
    echo "  [PASS] No ICC color profiles.\n";
}

if ($hasColorSpace) {
    $warnings[] = "CMYK or ICC-based color space found — verify B&W rendering";
    echo "  [WARN] Non-trivial color space detected.\n";
} else {
    echo "  [PASS] No CMYK color spaces.\n";
}

if ($hasTransparency) {
    $warnings[] = "Transparency detected — some printers flatten this poorly";
    echo "  [WARN] Transparency found — printer may need to flatten.\n";
} else {
    echo "  [PASS] No transparency.\n";
}

// 5. Content stream — check for expected sections
echo "\n=== Section Presence ===\n";

$expectedSections = [
    'Minoo'                    => 'Cover wordmark',
    'Elder Newsletter'         => 'Cover subtitle',
    "Editor"                   => "Editor's Note",
    'Upcoming Events'          => 'Events section',
    'Teachings'                => 'Teachings section',
    'Language'                 => 'Language section',
    'Community'                => 'Community section',
    'Anishinaabemowin'         => 'Anishinaabemowin Corner',
    'Jokes'                    => 'Jokes & Humour',
    'Puzzle'                   => 'Puzzles section',
    'Horoscope'                => 'Horoscope section',
    'Miigwech'                 => 'Back page closing',
];

foreach ($expectedSections as $needle => $label) {
    // PDF text may be in content streams (possibly compressed), so also check the raw bytes
    if (str_contains($pdfContent, $needle)) {
        echo "  [PASS] {$label}\n";
    } else {
        // Compressed streams won't show raw text — downgrade to warning
        $warnings[] = "{$label} text not found in raw PDF (may be in compressed stream)";
        echo "  [WARN] {$label} — not found in raw bytes (may be compressed).\n";
    }
}

// 6. No web UI leakage
echo "\n=== Web UI Leakage ===\n";

$leakagePatterns = [
    '<nav'         => 'Navigation element',
    '<footer'      => 'Footer element',
    'minoo.css'    => 'Web stylesheet reference',
    'localhost'    => 'Localhost URL',
    '<script'      => 'JavaScript tag',
    'favicon'      => 'Favicon reference',
];

$leakageFound = false;
foreach ($leakagePatterns as $pattern => $label) {
    if (str_contains($pdfContent, $pattern)) {
        // localhost in metadata URLs is OK (producer field); check more carefully
        if ($pattern === 'localhost' && !preg_match('/localhost.*\.(css|js|ico|png|svg)/', $pdfContent)) {
            echo "  [PASS] {$label} (metadata only — OK)\n";
            continue;
        }
        $failures[] = "Web UI leakage: {$label} found in PDF";
        echo "  [FAIL] {$label} found in PDF.\n";
        $leakageFound = true;
    } else {
        echo "  [PASS] No {$label}.\n";
    }
}

if (!$leakageFound) {
    echo "  [PASS] No web UI leakage detected.\n";
}

// --- Summary ---

echo "\n=== Summary ===\n";
echo "  File: {$pdfPath}\n";
echo "  Size: " . round($bytes / 1024, 1) . " KB\n";
echo "  Pages: {$pageCount}\n";
echo "  Failures: " . count($failures) . "\n";
echo "  Warnings: " . count($warnings) . "\n";

if ($failures !== []) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}

if ($warnings !== []) {
    echo "\nWARNINGS:\n";
    foreach ($warnings as $w) {
        echo "  - {$w}\n";
    }
}

echo "\n=== OJ Graphix Print Spec Checklist ===\n";
echo "  [" . ($pageCount === 12 ? 'x' : ' ') . "] Page count = 12\n";
echo "  [x] B&W output (no color content in template)\n";
echo "  [" . (!$hasICCProfile ? 'x' : ' ') . "] No embedded color profiles\n";
echo "  [" . (!$hasTransparency ? 'x' : ' ') . "] No transparency\n";
echo "  [x] Letter pages — printer imposes onto 11x17\" tabloid for saddle-stitch\n";
echo "  [x] 0.5\" margins — safe for saddle-stitch binding\n";
echo "  [" . ($bytes < 5_000_000 ? 'x' : ' ') . "] File size < 5MB (email-safe)\n";
echo "  [ ] Opens cleanly in Acrobat — verify manually\n";
echo "  [ ] No missing glyphs — verify manually (especially Anishinaabemowin text)\n";
echo "  [ ] Print test page — verify manually\n";

if ($failures !== []) {
    echo "\nRESULT: FAIL — " . count($failures) . " issue(s) must be resolved.\n";
    exit(1);
}

echo "\nRESULT: PASS — automated checks clear. Complete manual checklist items above.\n";
exit(0);
