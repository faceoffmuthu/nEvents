-- N Events Platform — Core Database Schema
-- MySQL 8.0+ | utf8mb4 | InnoDB
-- Run in order. Each migration is idempotent with IF NOT EXISTS.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- ROLES & PERMISSIONS (RBAC)
-- ============================================================

CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,
    slug        VARCHAR(50)  NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    group_name  VARCHAR(50),
    description VARCHAR(255),
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       TINYINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid               CHAR(36)     NOT NULL UNIQUE,
    name               VARCHAR(100) NOT NULL,
    email              VARCHAR(180) NOT NULL UNIQUE,
    email_verified_at  DATETIME,
    password_hash      VARCHAR(255) NOT NULL,
    phone              VARCHAR(20),
    phone_verified_at  DATETIME,
    whatsapp_number    VARCHAR(20),
    whatsapp_opt_in    TINYINT(1)   NOT NULL DEFAULT 0,
    status             ENUM('active','inactive','suspended','deleted') NOT NULL DEFAULT 'active',
    onboarding_step    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    onboarding_done    TINYINT(1)   NOT NULL DEFAULT 0,
    locale             VARCHAR(10)  NOT NULL DEFAULT 'en',
    last_login_at      DATETIME,
    last_login_ip      VARCHAR(45),
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_phone  (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id    INT UNSIGNED    NOT NULL,
    role_id    TINYINT UNSIGNED NOT NULL,
    granted_by INT UNSIGNED,
    granted_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id       INT UNSIGNED NOT NULL PRIMARY KEY,
    bio           TEXT,
    avatar_url    VARCHAR(500),
    website       VARCHAR(300),
    twitter       VARCHAR(100),
    linkedin      VARCHAR(200),
    date_of_birth DATE,
    age_verified  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUTH TOKENS
-- ============================================================

CREATE TABLE IF NOT EXISTS auth_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       ENUM('email_verify','password_reset','api') NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONSENT
-- ============================================================

CREATE TABLE IF NOT EXISTS consents (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    consent_type   VARCHAR(50)  NOT NULL,
    version        VARCHAR(20)  NOT NULL DEFAULT '1.0',
    purpose        VARCHAR(200),
    granted        TINYINT(1)   NOT NULL DEFAULT 1,
    granted_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    withdrawn_at   DATETIME,
    ip_address     VARCHAR(45),
    user_agent     VARCHAR(500),
    INDEX idx_user_type (user_id, consent_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOGIN ATTEMPTS (rate limiting)
-- ============================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(200) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    attempted_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success    TINYINT(1)   NOT NULL DEFAULT 0,
    INDEX idx_identifier (identifier, attempted_at),
    INDEX idx_ip         (ip_address,  attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GEOGRAPHY
-- ============================================================

CREATE TABLE IF NOT EXISTS countries (
    id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    iso2         CHAR(2)      NOT NULL UNIQUE,
    iso3         CHAR(3)      NOT NULL UNIQUE,
    phone_code   VARCHAR(10),
    currency     CHAR(3),
    is_active    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS states (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country_id SMALLINT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_state_slug (country_id, slug),
    FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS districts (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_id   SMALLINT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_district_slug (state_id, slug),
    FOREIGN KEY (state_id) REFERENCES states(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district_id SMALLINT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    latitude    DECIMAL(10,7),
    longitude   DECIMAL(10,7),
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    is_featured TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_city_slug (district_id, slug),
    FOREIGN KEY (district_id) REFERENCES districts(id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS areas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_id    SMALLINT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL,
    latitude   DECIMAL(10,7),
    longitude  DECIMAL(10,7),
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_area_slug (city_id, slug),
    FOREIGN KEY (city_id) REFERENCES cities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    slug       VARCHAR(200) NOT NULL UNIQUE,
    address    TEXT,
    area_id    INT UNSIGNED,
    city_id    SMALLINT UNSIGNED,
    district_id SMALLINT UNSIGNED,
    state_id   SMALLINT UNSIGNED,
    country_id SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    postal_code VARCHAR(20),
    latitude   DECIMAL(10,7),
    longitude  DECIMAL(10,7),
    map_url    VARCHAR(1000),
    is_online  TINYINT(1)   NOT NULL DEFAULT 0,
    is_verified TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_city     (city_id),
    INDEX idx_lat_lon  (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER LOCATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS user_locations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    label        VARCHAR(50)  NOT NULL DEFAULT 'Home',
    city_id      SMALLINT UNSIGNED,
    area_id      INT UNSIGNED,
    latitude     DECIMAL(10,7),
    longitude    DECIMAL(10,7),
    radius_km    SMALLINT UNSIGNED NOT NULL DEFAULT 25,
    is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CATEGORIES & TAGS
-- ============================================================

CREATE TABLE IF NOT EXISTS categories (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id   SMALLINT UNSIGNED,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon        VARCHAR(100),
    color       VARCHAR(20),
    image_url   VARCHAR(500),
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_slug   (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    slug       VARCHAR(100) NOT NULL UNIQUE,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER INTERESTS
-- ============================================================

CREATE TABLE IF NOT EXISTS user_interests (
    user_id     INT UNSIGNED    NOT NULL,
    category_id SMALLINT UNSIGNED NOT NULL,
    weight      TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, category_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORGANIZERS
-- ============================================================

CREATE TABLE IF NOT EXISTS organizers (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(200) NOT NULL UNIQUE,
    name                VARCHAR(200) NOT NULL,
    logo_url            VARCHAR(500),
    description         TEXT,
    website             VARCHAR(300),
    email               VARCHAR(180),
    phone               VARCHAR(20),
    city_id             SMALLINT UNSIGNED,
    address             TEXT,
    verification_status ENUM('not_requested','pending','verified','rejected','suspended') NOT NULL DEFAULT 'not_requested',
    is_trusted_publisher TINYINT(1) NOT NULL DEFAULT 0,
    trust_score         TINYINT UNSIGNED NOT NULL DEFAULT 50,
    total_events        INT UNSIGNED NOT NULL DEFAULT 0,
    status              ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug   (slug),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizer_users (
    organizer_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    role         ENUM('owner','editor','viewer') NOT NULL DEFAULT 'owner',
    joined_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (organizer_id, user_id),
    FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizer_social_links (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT UNSIGNED NOT NULL,
    platform     VARCHAR(50)  NOT NULL,
    url          VARCHAR(500) NOT NULL,
    FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizer_verifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizer_id  INT UNSIGNED NOT NULL,
    status        ENUM('not_requested','pending','verified','rejected','suspended') NOT NULL DEFAULT 'pending',
    submitted_at  DATETIME,
    reviewed_by   INT UNSIGNED,
    reviewed_at   DATETIME,
    rejection_reason TEXT,
    documents_json   JSON,
    notes         TEXT,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EVENTS (CANONICAL)
-- ============================================================

CREATE TABLE IF NOT EXISTS events (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                 CHAR(36)     NOT NULL UNIQUE,
    slug                 VARCHAR(300) NOT NULL UNIQUE,
    title                VARCHAR(300) NOT NULL,
    normalized_title     VARCHAR(300),
    short_summary        VARCHAR(500),
    description          LONGTEXT,
    primary_organizer_id INT UNSIGNED,
    event_type           VARCHAR(50),
    format               ENUM('offline','online','hybrid','unknown') NOT NULL DEFAULT 'unknown',
    status               ENUM('draft','pending','published','postponed','cancelled','completed','expired','rejected','archived') NOT NULL DEFAULT 'pending',
    verification_status  ENUM('unverified','pending','verified','conflict','outdated') NOT NULL DEFAULT 'unverified',
    trust_score          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    primary_language     VARCHAR(10) NOT NULL DEFAULT 'en',
    registration_url     VARCHAR(2000),
    registration_deadline DATETIME,
    timezone             VARCHAR(50) NOT NULL DEFAULT 'Asia/Kolkata',
    pricing_type         ENUM('free','paid','donation','unknown') NOT NULL DEFAULT 'unknown',
    currency             CHAR(3) NOT NULL DEFAULT 'INR',
    min_price            DECIMAL(10,2),
    max_price            DECIMAL(10,2),
    venue_id             INT UNSIGNED,
    city_id              SMALLINT UNSIGNED,
    online_platform      VARCHAR(100),
    online_url           VARCHAR(2000),
    featured_image_url   VARCHAR(2000),
    is_featured          TINYINT(1) NOT NULL DEFAULT 0,
    discovered_at        DATETIME,
    first_published_at   DATETIME,
    last_verified_at     DATETIME,
    last_seen_at         DATETIME,
    missing_count        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    view_count           INT UNSIGNED NOT NULL DEFAULT 0,
    save_count           INT UNSIGNED NOT NULL DEFAULT 0,
    click_count          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (primary_organizer_id) REFERENCES organizers(id) ON DELETE SET NULL,
    FOREIGN KEY (venue_id)  REFERENCES venues(id)  ON DELETE SET NULL,
    FOREIGN KEY (city_id)   REFERENCES cities(id)  ON DELETE SET NULL,
    INDEX idx_status      (status),
    INDEX idx_trust       (trust_score),
    INDEX idx_featured    (is_featured),
    INDEX idx_city        (city_id),
    INDEX idx_format      (format),
    INDEX idx_pricing     (pricing_type),
    INDEX idx_verified    (verification_status),
    FULLTEXT idx_ft_title (title, short_summary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_occurrences (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id              INT UNSIGNED NOT NULL,
    start_at_utc          DATETIME     NOT NULL,
    end_at_utc            DATETIME,
    timezone              VARCHAR(50)  NOT NULL DEFAULT 'Asia/Kolkata',
    status                ENUM('scheduled','postponed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    registration_deadline DATETIME,
    capacity              INT UNSIGNED,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event      (event_id),
    INDEX idx_start      (start_at_utc),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_categories (
    event_id    INT UNSIGNED    NOT NULL,
    category_id SMALLINT UNSIGNED NOT NULL,
    is_primary  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (event_id, category_id),
    FOREIGN KEY (event_id)    REFERENCES events(id)     ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_tags (
    event_id INT UNSIGNED NOT NULL,
    tag_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, tag_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)   REFERENCES tags(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_images (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    source_url   VARCHAR(2000),
    local_path   VARCHAR(500),
    alt_text     VARCHAR(300),
    is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
    usage_status ENUM('pending','approved','rejected','external') NOT NULL DEFAULT 'external',
    width        SMALLINT UNSIGNED,
    height       SMALLINT UNSIGNED,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_speakers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(200) NOT NULL,
    bio        TEXT,
    photo_url  VARCHAR(500),
    title      VARCHAR(200),
    company    VARCHAR(200),
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_agenda (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    occurrence_id INT UNSIGNED,
    time_label VARCHAR(50),
    title      VARCHAR(300) NOT NULL,
    description TEXT,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INGESTION / SOURCE MANAGEMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS sources (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name               VARCHAR(200) NOT NULL,
    slug               VARCHAR(200) NOT NULL UNIQUE,
    domain             VARCHAR(200),
    acquisition_method ENUM('api','json_ld','rss','sitemap','permitted_html','partner_feed','manual') NOT NULL DEFAULT 'manual',
    adapter_class      VARCHAR(200),
    enabled            TINYINT(1)   NOT NULL DEFAULT 0,
    rate_limit_rpm     SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    refresh_interval   SMALLINT UNSIGNED NOT NULL DEFAULT 360,
    terms_status       ENUM('unknown','compliant','restricted','blocked') NOT NULL DEFAULT 'unknown',
    robots_status      ENUM('unknown','allowed','disallowed') NOT NULL DEFAULT 'unknown',
    requires_auth      TINYINT(1)   NOT NULL DEFAULT 0,
    credentials_ref    VARCHAR(100),
    base_url           VARCHAR(500),
    config_json        JSON,
    last_checked_at    DATETIME,
    last_success_at    DATETIME,
    health_status      ENUM('unknown','healthy','degraded','failing','disabled') NOT NULL DEFAULT 'unknown',
    notes              TEXT,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled (enabled),
    INDEX idx_health  (health_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_runs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id       INT UNSIGNED NOT NULL,
    status          ENUM('running','completed','failed','partial') NOT NULL DEFAULT 'running',
    started_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME,
    records_fetched INT UNSIGNED NOT NULL DEFAULT 0,
    records_new     INT UNSIGNED NOT NULL DEFAULT 0,
    records_updated INT UNSIGNED NOT NULL DEFAULT 0,
    records_error   INT UNSIGNED NOT NULL DEFAULT 0,
    error_message   TEXT,
    memory_mb       DECIMAL(8,2),
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_source    (source_id, started_at),
    INDEX idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS raw_event_records (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id        INT UNSIGNED NOT NULL,
    external_id      VARCHAR(500),
    source_url       VARCHAR(2000),
    raw_payload      LONGTEXT,
    content_hash     CHAR(64),
    parser_version   VARCHAR(20),
    discovered_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fetched_at       DATETIME,
    processing_status ENUM('pending','processing','normalized','duplicate','rejected','error') NOT NULL DEFAULT 'pending',
    error_message    TEXT,
    retry_count      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_source_status  (source_id, processing_status),
    INDEX idx_hash           (content_hash),
    INDEX idx_status         (processing_status),
    UNIQUE KEY uq_source_ext (source_id, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_sources (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id            INT UNSIGNED NOT NULL,
    source_id           INT UNSIGNED NOT NULL,
    raw_event_record_id BIGINT UNSIGNED,
    external_id         VARCHAR(500),
    source_url          VARCHAR(2000),
    registration_url    VARCHAR(2000),
    source_status       ENUM('active','stale','missing','removed') NOT NULL DEFAULT 'active',
    first_seen_at       DATETIME,
    last_seen_at        DATETIME,
    last_verified_at    DATETIME,
    is_primary          TINYINT(1)   NOT NULL DEFAULT 0,
    confidence          TINYINT UNSIGNED NOT NULL DEFAULT 50,
    FOREIGN KEY (event_id)  REFERENCES events(id)  ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_event  (event_id),
    INDEX idx_source (source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DUPLICATE DETECTION
-- ============================================================

CREATE TABLE IF NOT EXISTS duplicate_candidates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_a_id   INT UNSIGNED NOT NULL,
    event_b_id   INT UNSIGNED NOT NULL,
    score        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    signals_json JSON,
    status       ENUM('pending','confirmed','rejected','merged') NOT NULL DEFAULT 'pending',
    reviewed_by  INT UNSIGNED,
    reviewed_at  DATETIME,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_a_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (event_b_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pair (event_a_id, event_b_id),
    INDEX idx_score  (score),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_merge_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survivor_id     INT UNSIGNED NOT NULL,
    merged_id       INT UNSIGNED NOT NULL,
    merged_by       INT UNSIGNED,
    merge_reason    VARCHAR(200),
    snapshot_json   JSON,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_survivor (survivor_id),
    INDEX idx_merged   (merged_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_conflicts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT UNSIGNED NOT NULL,
    field       VARCHAR(50)  NOT NULL,
    value_a     TEXT,
    value_b     TEXT,
    source_a_id INT UNSIGNED,
    source_b_id INT UNSIGNED,
    status      ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    resolved_by INT UNSIGNED,
    resolved_at DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event  (event_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER INTERACTIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS saved_events (
    user_id    INT UNSIGNED NOT NULL,
    event_id   INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hidden_events (
    user_id    INT UNSIGNED NOT NULL,
    event_id   INT UNSIGNED NOT NULL,
    reason     VARCHAR(50),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_interactions (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED,
    session_id VARCHAR(100),
    event_id   INT UNSIGNED NOT NULL,
    action     ENUM('view','save','unsave','register_click','calendar_add','share','hide','not_interested') NOT NULL,
    metadata_json JSON,
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event  (event_id, action),
    INDEX idx_user   (user_id, created_at),
    INDEX idx_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followed_organizers (
    user_id      INT UNSIGNED NOT NULL,
    organizer_id INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, organizer_id),
    FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id            INT UNSIGNED NOT NULL PRIMARY KEY,
    email_enabled      TINYINT(1)   NOT NULL DEFAULT 1,
    whatsapp_enabled   TINYINT(1)   NOT NULL DEFAULT 0,
    daily_digest       TINYINT(1)   NOT NULL DEFAULT 1,
    weekly_digest      TINYINT(1)   NOT NULL DEFAULT 0,
    alert_high_match   TINYINT(1)   NOT NULL DEFAULT 1,
    reminder_24h       TINYINT(1)   NOT NULL DEFAULT 1,
    reminder_2h        TINYINT(1)   NOT NULL DEFAULT 0,
    digest_time        TIME         NOT NULL DEFAULT '08:00:00',
    digest_day         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    quiet_start        TIME,
    quiet_end          TIME,
    min_score          TINYINT UNSIGNED NOT NULL DEFAULT 40,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_templates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    channel     ENUM('email','whatsapp','push') NOT NULL,
    subject     VARCHAR(300),
    body_html   LONGTEXT,
    body_text   TEXT,
    body_wa     TEXT,
    version     VARCHAR(20)  NOT NULL DEFAULT '1.0',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_jobs (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    template_id    INT UNSIGNED,
    channel        ENUM('email','whatsapp','push') NOT NULL,
    subject        VARCHAR(300),
    payload_json   JSON,
    fingerprint    VARCHAR(64) UNIQUE,
    status         ENUM('queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
    scheduled_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at        DATETIME,
    error_message  TEXT,
    retry_count    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status    (status, scheduled_at),
    INDEX idx_user      (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_deliveries (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_job_id BIGINT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    channel             ENUM('email','whatsapp','push') NOT NULL,
    provider_message_id VARCHAR(200),
    status              ENUM('sent','delivered','read','failed','bounced') NOT NULL DEFAULT 'sent',
    sent_at             DATETIME,
    delivered_at        DATETIME,
    read_at             DATETIME,
    error_message       TEXT,
    INDEX idx_job    (notification_job_id),
    INDEX idx_user   (user_id, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CALENDAR INTEGRATION
-- ============================================================

CREATE TABLE IF NOT EXISTS calendar_connections (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    provider     VARCHAR(50)  NOT NULL DEFAULT 'google',
    access_token_enc  TEXT,
    refresh_token_enc TEXT,
    token_expires_at  DATETIME,
    calendar_id  VARCHAR(200),
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_provider (user_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_event_mappings (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    event_id            INT UNSIGNED NOT NULL,
    event_occurrence_id INT UNSIGNED,
    provider            VARCHAR(50)  NOT NULL DEFAULT 'google',
    external_calendar_id VARCHAR(200),
    external_event_id   VARCHAR(500) NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_event_provider (user_id, event_id, provider),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REPORTING
-- ============================================================

CREATE TABLE IF NOT EXISTS reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED,
    reason       ENUM('incorrect_info','cancelled','broken_link','scam','duplicate','wrong_venue','wrong_datetime','inappropriate') NOT NULL,
    description  TEXT,
    status       ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    reviewed_by  INT UNSIGNED,
    reviewed_at  DATETIME,
    resolution   VARCHAR(200),
    ip_address   VARCHAR(45),
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event  (event_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS moderation_actions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INT UNSIGNED NOT NULL,
    before_json JSON,
    after_json  JSON,
    notes       TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_actor  (actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BACKGROUND JOBS
-- ============================================================

CREATE TABLE IF NOT EXISTS jobs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type     VARCHAR(100) NOT NULL,
    payload_json JSON,
    priority     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    available_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    locked_at    DATETIME,
    locked_by    VARCHAR(100),
    completed_at DATETIME,
    failed_at    DATETIME,
    error_message TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_available (available_at, locked_at),
    INDEX idx_type      (job_type),
    INDEX idx_failed    (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS failed_jobs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type     VARCHAR(100) NOT NULL,
    payload_json JSON,
    error_message TEXT,
    failed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name   VARCHAR(100) NOT NULL UNIQUE,
    value      TEXT,
    type       ENUM('string','boolean','integer','json') NOT NULL DEFAULT 'string',
    group_name VARCHAR(50),
    updated_by INT UNSIGNED,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feature_flags (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    description VARCHAR(300),
    updated_by INT UNSIGNED,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT UNSIGNED,
    actor_type  ENUM('user','system','cron') NOT NULL DEFAULT 'user',
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id   INT UNSIGNED,
    summary     VARCHAR(500),
    meta_json   JSON,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor  (actor_id, created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verification_checks (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id         INT UNSIGNED NOT NULL,
    check_type       VARCHAR(50)  NOT NULL,
    result           ENUM('pass','fail','warn','skip') NOT NULL DEFAULT 'skip',
    detail           VARCHAR(500),
    checked_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_link_checks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT UNSIGNED NOT NULL,
    url         VARCHAR(2000) NOT NULL,
    http_status SMALLINT UNSIGNED,
    final_url   VARCHAR(2000),
    status      ENUM('working','redirected','temporarily_failed','broken','unknown') NOT NULL DEFAULT 'unknown',
    checked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event  (event_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
