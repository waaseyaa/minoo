<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel(dirname(__DIR__));
$response = $kernel->handle();

// Under php -S (built-in dev server) the response must be emitted explicitly.
// Production (Caddy + PHP-FPM) is left untouched.
if (PHP_SAPI === 'cli-server') {
    $response->send();
}
