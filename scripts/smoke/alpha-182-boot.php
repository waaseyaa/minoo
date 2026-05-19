<?php

declare(strict_types=1);

/*
 * Mission-internal smoke for adopt-waaseyaa-alpha-182-access-checking-01KS0WZ7.
 * Proves autoload + the new MissingQueryAccountException class are present
 * after the alpha.182 bump. Intentionally narrow — does NOT exercise request
 * handling; that surface is brought back to green by WP02..WP05.
 */

require __DIR__ . '/../../vendor/autoload.php';

$expected = \Waaseyaa\EntityStorage\Exception\MissingQueryAccountException::class;

if (! class_exists($expected)) {
    fwrite(STDERR, "FAIL: $expected missing — alpha.182 not loaded?\n");
    exit(1);
}

echo "OK: $expected loaded\n";

if (! method_exists(\Waaseyaa\Entity\Storage\EntityQueryInterface::class, 'setAccount')) {
    fwrite(STDERR, "FAIL: EntityQueryInterface::setAccount() missing — alpha.181 contract not present\n");
    exit(1);
}

echo "OK: EntityQueryInterface::setAccount() present\n";
echo "OK: kernel autoload works under alpha.182\n";
