#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Render edition template to a static HTML file for visual inspection.
 * Usage: php scripts/render-edition-html.php [edition_id]
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$editionId = (int) ($argv[1] ?? 1);

$kernel = new HttpKernel(dirname(__DIR__));
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$edition = $etm->getStorage('newsletter_edition')->load($editionId);
if ($edition === null) {
    fwrite(STDERR, "Edition {$editionId} not found.\n");
    exit(1);
}

$items = array_filter(
    $etm->getStorage('newsletter_item')->loadMultiple(),
    fn($i) => (int) $i->get('edition_id') === $editionId && $i->get('included'),
);
usort($items, fn($a, $b) => (int) $a->get('position') - (int) $b->get('position'));

$bySection = [];
foreach ($items as $item) {
    $bySection[$item->get('section')][] = $item;
}

// Create Twig environment directly
$loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/templates');
$twig = new \Twig\Environment($loader);

$html = $twig->render('newsletter/edition.html.twig', [
    'edition' => $edition,
    'items_by_section' => $bySection,
    'source_entities' => [],
]);

$outPath = dirname(__DIR__) . '/storage/tmp/rendered-edition.html';
file_put_contents($outPath, $html);
echo "Rendered to {$outPath} (" . strlen($html) . " bytes)\n";
