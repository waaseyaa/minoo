<?php

declare(strict_types=1);

// getenv() returns false when unset; ?? does not fall back on false (only null).
// Be explicit so hub URL and secrets match Laravel env()-style semantics.
$mercureHubUrl = getenv('MERCURE_HUB_URL');
if ($mercureHubUrl === false || $mercureHubUrl === '') {
    $mercureHubUrl = 'http://localhost:3000/.well-known/mercure';
}

$mercureJwtSecret = getenv('MERCURE_JWT_SECRET');
if ($mercureJwtSecret === false) {
    $mercureJwtSecret = '';
}

return [
    'max_message_length' => 2000,
    'typing_indicator_ttl' => 5,
    'digest_interval' => '4h',
    'digest_debounce' => 15,
    'digest_active_skip' => 30,
    'mercure_hub_url' => $mercureHubUrl,
    'mercure_jwt_secret' => $mercureJwtSecret,
    'polling_fallback_interval' => 10,
];
