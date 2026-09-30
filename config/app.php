<?php

declare(strict_types=1);

return [
    'name'     => $_ENV['APP_NAME']     ?? 'N Events',
    'env'      => $_ENV['APP_ENV']      ?? 'production',
    'url'      => $_ENV['APP_URL']      ?? 'https://nevents.in',
    'secret'   => $_ENV['APP_SECRET']   ?? '',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata',
    'locale'   => $_ENV['APP_LOCALE']   ?? 'en',

    'session' => [
        'name'     => $_ENV['SESSION_NAME']     ?? 'nevents_session',
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
        'secure'   => filter_var($_ENV['SESSION_SECURE']   ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => filter_var($_ENV['SESSION_HTTPONLY'] ?? true,  FILTER_VALIDATE_BOOLEAN),
        'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'Lax',
    ],

    'rate_limit' => [
        'login'  => (int)($_ENV['RATE_LIMIT_LOGIN']  ?? 5),
        'window' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 900),
    ],

    // Central authentication policy — checked in one place (AuthService) rather
    // than scattered `if ($user['email_verified_at'])` checks across controllers.
    'auth' => [
        'require_email_verification'    => filter_var($_ENV['REQUIRE_EMAIL_VERIFICATION'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'verify_token_ttl_hours'        => (int)($_ENV['VERIFY_TOKEN_TTL_HOURS'] ?? 24),
        'resend_verification_cooldown'  => (int)($_ENV['RESEND_VERIFICATION_COOLDOWN_SECONDS'] ?? 60),
    ],

    'mail' => [
        'driver'      => $_ENV['MAIL_PROVIDER']    ?? 'smtp',
        'host'        => $_ENV['MAIL_HOST']        ?? '',
        'port'        => (int)($_ENV['MAIL_PORT']  ?? 587),
        'encryption'  => $_ENV['MAIL_ENCRYPTION']  ?? 'tls',
        'username'    => $_ENV['MAIL_USERNAME']    ?? '',
        'password'    => $_ENV['MAIL_PASSWORD']    ?? '',
        'from_address'=> $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@nevents.in',
        'from_name'   => $_ENV['MAIL_FROM_NAME']    ?? 'N Events',
    ],

    'features' => [
        'google_calendar_oauth'     => filter_var($_ENV['FEATURE_GOOGLE_CALENDAR_OAUTH']     ?? false, FILTER_VALIDATE_BOOLEAN),
        'ai_extraction'             => filter_var($_ENV['FEATURE_AI_EXTRACTION']             ?? false, FILTER_VALIDATE_BOOLEAN),
        'organizer_portal'          => filter_var($_ENV['FEATURE_ORGANIZER_PORTAL']          ?? true,  FILTER_VALIDATE_BOOLEAN),
        'recommendation_score_public' => filter_var($_ENV['FEATURE_RECOMMENDATION_SCORE_PUBLIC'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'tamil_ui'                  => filter_var($_ENV['FEATURE_TAMIL_UI']                  ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    // Logged-in user event posting (see UserEventService)
    'user_events' => [
        'max_per_hour'   => (int)($_ENV['USER_EVENT_MAX_PER_HOUR'] ?? 5),
        'max_per_day'    => (int)($_ENV['USER_EVENT_MAX_PER_DAY']  ?? 20),
        'image_max_mb'   => (int)($_ENV['EVENT_IMAGE_MAX_MB']      ?? 5),
        'image_min_px'   => 300,
        'image_max_px'   => 6000,
        'upload_dir'     => dirname(__DIR__) . '/public/uploads/events',
        'upload_url'     => 'uploads/events',
        'max_reports_per_day' => (int)($_ENV['REPORT_MAX_PER_DAY'] ?? 10),
    ],

    // Discovery pipeline thresholds (see IngestionService / DuplicateDetectionService)
    'discovery' => [
        'auto_publish_min_confidence' => (int)($_ENV['DISCOVERY_AUTO_PUBLISH_MIN_CONFIDENCE'] ?? 80),
        'auto_publish_min_trust'      => (int)($_ENV['DISCOVERY_AUTO_PUBLISH_MIN_TRUST']      ?? 70),
        'duplicate_merge_score'       => 85,
        'duplicate_review_score'      => 60,
        'social_raw_retention_days'   => (int)($_ENV['SOCIAL_RAW_RETENTION_DAYS'] ?? 30),
        'missing_runs_before_stale'   => 3,
        // Keyless web crawling (WebCrawlerAdapter, sitemaps, RSS item pages, ICS feeds)
        'user_agent'      => $_ENV['DISCOVERY_USER_AGENT'] ?? 'NEventsBot/1.0 (event discovery; +' . rtrim($_ENV['APP_URL'] ?? 'https://nevents.in', '/') . '/about)',
        'min_delay_ms'    => (int)($_ENV['DISCOVERY_MIN_DELAY_MS'] ?? 1500),   // per-host politeness; robots Crawl-delay wins if larger
        'max_pages'       => (int)($_ENV['DISCOVERY_MAX_PAGES'] ?? 40),        // per source per run
        // non-production only: hosts the test suite may crawl despite the SSRF guard (e.g. "127.0.0.1:8099")
        'test_hosts'      => $_ENV['DISCOVERY_TEST_HOSTS'] ?? '',
    ],

    // Approved social/search API access. Every connector stays disabled
    // (and reports "not configured") unless its credentials are present.
    'social' => [
        'meta_graph_version'      => $_ENV['META_GRAPH_API_VERSION']      ?? 'v21.0',
        'instagram_access_token'  => $_ENV['INSTAGRAM_ACCESS_TOKEN']      ?? '',
        'instagram_account_ids'   => $_ENV['INSTAGRAM_ACCOUNT_IDS']       ?? '',
        'facebook_page_token'     => $_ENV['FACEBOOK_PAGE_ACCESS_TOKEN']  ?? '',
        'facebook_page_ids'       => $_ENV['FACEBOOK_PAGE_IDS']           ?? '',
        'x_bearer_token'          => $_ENV['X_BEARER_TOKEN']              ?? '',
        'search_provider'         => $_ENV['SEARCH_PROVIDER']             ?? 'none',
    ],

    'upload' => [
        'max_size_mb'   => (int)($_ENV['UPLOAD_MAX_SIZE_MB'] ?? 10),
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'allowed_ext'   => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'path'          => dirname(__DIR__) . '/storage/uploads',
    ],
];
