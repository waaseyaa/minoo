<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

require __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    (new \Symfony\Component\Dotenv\Dotenv())->loadEnv($projectRoot . '/.env');
}

$kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel($projectRoot);

try {
    $response = $kernel->handle();
} catch (\Throwable $e) {
    $payload = json_encode([
        'jsonapi' => ['version' => '1.1'],
        'errors' => [['status' => '500', 'title' => 'Internal Server Error', 'detail' => $e->getMessage()]],
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    $response = new Response($payload, 500, ['Content-Type' => 'application/vnd.api+json']);
}

$response->send();
