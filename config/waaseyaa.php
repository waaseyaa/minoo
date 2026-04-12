<?php

declare(strict_types=1);

return [
    // Sovereignty profile for this deployment: 'local', 'self_hosted', or 'northops'.
    // Override per environment with WAASEYAA_SOVEREIGNTY_PROFILE.
    'sovereignty_profile' => getenv('WAASEYAA_SOVEREIGNTY_PROFILE') ?: 'northops',

    // SQLite database path. WAASEYAA_DB env var takes precedence.
    'database' => getenv('WAASEYAA_DB') ?: dirname(__DIR__) . '/storage/waaseyaa.sqlite',

    // Config sync directory. Override with WAASEYAA_CONFIG_DIR env var.
    'config_dir' => getenv('WAASEYAA_CONFIG_DIR') ?: __DIR__ . '/sync',

    // File storage root for LocalFileRepository (media package).
    'files_dir' => getenv('WAASEYAA_FILES_DIR') ?: __DIR__ . '/../files',

    // Application identity — used by AuthMailer for reset/verify URL generation.
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Minoo',
        'url'  => getenv('APP_URL') ?: 'https://minoo.live',
    ],

    // Bearer auth settings for machine clients.
    // JWT uses HS256 with this shared secret.
    'jwt_secret' => getenv('WAASEYAA_JWT_SECRET') ?: '',
    // API key map: raw key => uid. Example: ['dev-machine-key' => 1].
    'api_keys' => [],
    // Dev-only fallback account for local built-in server workflows.
    // Must remain false outside local development.
    'auth' => [
        'dev_fallback_account' => filter_var(
            getenv('WAASEYAA_DEV_FALLBACK_ACCOUNT') ?: false,
            FILTER_VALIDATE_BOOLEAN,
        ),
        'registration' => 'open',
        'require_verified_email' => false,
        'mail_missing_policy' => null, // auto-resolves: dev_log in dev, fail in production
        'token_ttls' => [
            'password_reset' => 3600,       // 1 hour
            'email_verification' => 86400,  // 24 hours
        ],
    ],

    // Session cookie ini (applied in SessionMiddleware before session_start).
    'session' => [
        'cookie' => [
            'httponly' => true,
            'secure' => 'auto',
            'samesite' => 'Lax',
            'use_strict_mode' => true,
        ],
    ],

    // Upload validation (POST /api/media/upload).
    'upload_max_bytes' => 10 * 1024 * 1024, // 10 MiB
    'upload_allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'text/plain',
        'application/octet-stream',
    ],

    // Allowed CORS origins for the admin SPA.
    'cors_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000'],

    // Locale negotiation defaults used by public SSR path resolution.
    'i18n' => [
        'languages' => [
            ['id' => 'en', 'label' => 'English', 'is_default' => true],
            ['id' => 'oj', 'label' => 'Anishinaabemowin'],
        ],
    ],

    // SSR theme id discovered from Composer package metadata.
    // Theme packages expose extra.waaseyaa.theme in composer.json.
    'ssr' => [
        'theme' => getenv('WAASEYAA_SSR_THEME') ?: '',
        'cache_max_age' => (int) (getenv('WAASEYAA_SSR_CACHE_MAX_AGE') ?: 300),
    ],

    // Search provider configuration.
    'search' => [
        'base_url' => getenv('NORTHCLOUD_SEARCH_URL') ?: 'https://northcloud.one',
        'timeout' => (int) (getenv('NORTHCLOUD_SEARCH_TIMEOUT') ?: 15),
        'cache_ttl' => (int) (getenv('NORTHCLOUD_SEARCH_CACHE_TTL') ?: 60),
        'base_topics' => ['indigenous'],
    ],

    // NorthCloud community data API.
    'northcloud' => [
        'base_url' => getenv('NORTHCLOUD_BASE_URL') ?: 'https://api.northcloud.one',
        'timeout' => (int) (getenv('NORTHCLOUD_TIMEOUT') ?: 5),
        'cache_ttl' => (int) (getenv('NORTHCLOUD_CACHE_TTL') ?: 3600),
        'api_token' => getenv('NORTHCLOUD_API_TOKEN') ?: '',
    ],

    // Location detection.
    'location' => [
        'geoip_db' => getenv('GEOIP_DB_PATH') ?: __DIR__ . '/../storage/geoip/GeoLite2-City.mmdb',
        'default_coordinates' => [46.49, -81.00], // Sudbury fallback for dev/private IPs
        'cookie_name' => 'minoo_location',
        'cookie_ttl' => 86400 * 30, // 30 days
    ],

    // Mail configuration (SendGrid).
    'mail' => [
        'sendgrid_api_key' => getenv('SENDGRID_API_KEY') ?: '',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'hello@minoo.live',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Minoo',
        'base_url' => getenv('MINOO_BASE_URL') ?: 'https://minoo.live',
    ],

    // Messaging (digests, Mercure, etc.) — see config/messaging.php.
    'messaging' => require __DIR__ . '/messaging.php',

    // AI embedding pipeline configuration.
    'ai' => [
        // 'ollama' or 'openai'. Empty disables embedding generation.
        'embedding_provider' => getenv('WAASEYAA_EMBEDDING_PROVIDER') ?: '',
        'ollama_endpoint' => getenv('WAASEYAA_OLLAMA_ENDPOINT') ?: 'http://127.0.0.1:11434/api/embeddings',
        'ollama_model' => getenv('WAASEYAA_OLLAMA_MODEL') ?: 'nomic-embed-text',
        'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
        'openai_embedding_model' => getenv('WAASEYAA_OPENAI_EMBEDDING_MODEL') ?: 'text-embedding-3-small',
        // Per-entity field selection used for embedding text extraction.
        'embedding_fields' => [
            'node' => ['title', 'body'],
        ],
    ],
];
