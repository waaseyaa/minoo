<?php

declare(strict_types=1);

return [
    'max_message_length' => 2000,
    'typing_indicator_ttl' => 5,
    'digest_interval' => '4h',
    'digest_debounce' => 15,
    'digest_active_skip' => 30,
    'mercure_hub_url' => env('MERCURE_HUB_URL', 'http://localhost:3000/.well-known/mercure'),
    'mercure_jwt_secret' => env('MERCURE_JWT_SECRET', ''),
    'polling_fallback_interval' => 10,
];
