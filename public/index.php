<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

$kernel = new Waaseyaa\Foundation\Kernel\HttpKernel(dirname(__DIR__));
$kernel->handle();
