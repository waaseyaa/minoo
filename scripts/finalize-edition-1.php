#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Finalize Edition 1 after the PDF was rendered out-of-band:
 * mark it as `generated` and stamp pdf_path + pdf_hash on the row.
 *
 * Run AFTER scripts/draft-newsletter-edition.php and after a PDF
 * has been written via bin/render-pdf.js.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$pdfPath = $argv[1] ?? '/tmp/edition-1.pdf';
$editionId = (int) ($argv[2] ?? 1);

if (! is_file($pdfPath)) {
    fwrite(STDERR, "PDF not found: $pdfPath\n");
    exit(1);
}

$kernel = new HttpKernel(dirname(__DIR__));
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
$etm = $kernel->getEntityTypeManager();

$storage = $etm->getStorage('newsletter_edition');
$edition = $storage->load($editionId);
if ($edition === null) {
    fwrite(STDERR, "Edition $editionId not found.\n");
    exit(1);
}

$lifecycle = new EditionLifecycle();
$lifecycle->markGenerated(
    $edition,
    $pdfPath,
    hash_file('sha256', $pdfPath),
);
$storage->save($edition);

echo "Edition $editionId marked generated.\n";
echo "  pdf_path: $pdfPath\n";
echo "  pdf_hash: " . $edition->get('pdf_hash') . "\n";
echo "  status:   " . $edition->get('status') . "\n";
echo "  size:     " . filesize($pdfPath) . " bytes\n";
