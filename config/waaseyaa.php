<?php

declare(strict_types=1);

return [
    // When true, registers entity types from #[AsEntityType] + DefinesEntityType scan (see ProviderRegistry).
    // Default false. Override with WAASEYAA_ENTITY_AUTO_REGISTER=true.
    'entity_auto_register' => filter_var(
        getenv('WAASEYAA_ENTITY_AUTO_REGISTER') ?: false,
        FILTER_VALIDATE_BOOLEAN,
    ),

    // SQLite database path. WAASEYAA_DB env var takes precedence.
    // Relative paths resolve against project root (not CWD) so `php -S` (docroot=public) works.
    'database' => (static function (): string {
        $env = getenv('WAASEYAA_DB');
        $default = dirname(__DIR__) . '/storage/waaseyaa.sqlite';
        if ($env === false || $env === '') {
            return $default;
        }
        if ($env === ':memory:' || str_starts_with($env, '/')) {
            return $env;
        }
        return dirname(__DIR__) . '/' . ltrim($env, './');
    })(),

    // File storage root for LocalFileRepository (media package).
    'files_dir' => getenv('WAASEYAA_FILES_DIR') ?: __DIR__ . '/../files',

    // Minimum log level. Default 'notice' so the framework's dispatcher.deprecation
    // shim notices (post-#1390 implicit-array migration backlog) reach error_log().
    // Without this, the framework default is 'warning' and notice-level signals are
    // dropped silently. Override with WAASEYAA_LOG_LEVEL (debug|info|notice|warning|error|...).
    'log_level' => getenv('WAASEYAA_LOG_LEVEL') ?: 'notice',

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

    // Anokii reel ingestion (#877): staff drop-zone video uploads are far larger
    // than the generic media cap. The PHP (upload_max_filesize / post_max_size)
    // and Caddy (request_body max_size) limits MUST be raised to match in the
    // deployment image (see waaseyaa-infra) or large uploads are truncated at the
    // web tier before reaching this validation.
    'corpus_upload_max_bytes' => 120 * 1024 * 1024, // 120 MiB (~100 MB videos + headroom)
    'corpus_upload_allowed_mime_types' => [
        'video/mp4',
        'video/quicktime', // .mov
        'video/webm',
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

    // Search (framework FTS5 provider; uses the app database by default).
    'search' => [
        'base_topics' => ['anishinaabemowin'],
    ],

    // Mail configuration (SendGrid).
    'mail' => [
        'sendgrid_api_key' => getenv('SENDGRID_API_KEY') ?: '',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'hello@minoo.live',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Minoo',
        'base_url' => getenv('MINOO_BASE_URL') ?: 'https://minoo.live',
    ],

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
