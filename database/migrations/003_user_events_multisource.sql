-- N Events — User-created events, moderation, email-only notifications and
-- multi-source (web + approved social) discovery.
-- Idempotent: every ALTER is guarded through information_schema, so this file
-- is safe to re-run with bin/migrate.php (which re-runs every migration).
-- NOTE: bin/migrate.php splits on semicolons, so no string literal below may
-- contain one.

SET NAMES utf8mb4;

-- ============================================================
-- EVENTS — ownership, moderation, origin, organizer contact
-- ============================================================

-- data_origin: add user_submitted + partner_feed (existing values kept —
-- 'discovered' remains the web/social-discovered origin)
ALTER TABLE events MODIFY COLUMN data_origin
  ENUM('discovered','organizer_submitted','admin_created','demo','seed','user_submitted','partner_feed')
  NOT NULL DEFAULT 'admin_created';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'created_by_user_id');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD COLUMN created_by_user_id INT UNSIGNED NULL AFTER data_origin, ADD INDEX idx_created_by (created_by_user_id)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND CONSTRAINT_NAME = 'fk_events_created_by');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD CONSTRAINT fk_events_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'moderation_status');
SET @sql = IF(@c = 0, "ALTER TABLE events ADD COLUMN moderation_status ENUM('clean','flagged','needs_review','suspended','rejected') NOT NULL DEFAULT 'clean' AFTER created_by_user_id, ADD COLUMN moderation_reason VARCHAR(500) NULL AFTER moderation_status, ADD INDEX idx_moderation (moderation_status)", 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- published_at: when the event most recently became publicly visible
-- (first_published_at keeps the very first time)
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'published_at');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD COLUMN published_at DATETIME NULL AFTER first_published_at', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Organizer contact details for events that are not linked to an organizers
-- row (community submissions, social candidates). Keeps the public organizer
-- directory free of one-off records.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'organizer_display_name');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD COLUMN organizer_display_name VARCHAR(200) NULL AFTER primary_organizer_id, ADD COLUMN organizer_email VARCHAR(180) NULL AFTER organizer_display_name, ADD COLUMN organizer_phone VARCHAR(20) NULL AFTER organizer_email, ADD COLUMN organizer_website VARCHAR(500) NULL AFTER organizer_phone, ADD COLUMN organizer_social_url VARCHAR(500) NULL AFTER organizer_website', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'registration_required');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD COLUMN registration_required TINYINT(1) NOT NULL DEFAULT 0 AFTER registration_url', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_status_origin');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD INDEX idx_status_origin (status, data_origin)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- VENUES — free-text city/area as entered by the submitter
-- ============================================================
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'locality');
SET @sql = IF(@c = 0, 'ALTER TABLE venues ADD COLUMN locality VARCHAR(150) NULL AFTER address', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- USERS — one canonical WhatsApp/mobile number + posting suspension
-- ============================================================
UPDATE users SET whatsapp_number = phone WHERE (whatsapp_number IS NULL OR whatsapp_number = '') AND phone IS NOT NULL AND phone <> '';
-- phone held an identical second copy of the same number — drop the duplicate
UPDATE users SET phone = NULL WHERE phone IS NOT NULL AND phone = whatsapp_number;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'can_post_events');
SET @sql = IF(@c = 0, 'ALTER TABLE users ADD COLUMN can_post_events TINYINT(1) NOT NULL DEFAULT 1 AFTER status', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_whatsapp');
SET @sql = IF(@c = 0, 'ALTER TABLE users ADD INDEX idx_whatsapp (whatsapp_number)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- NOTIFICATIONS — email only. whatsapp_enabled is left in place but is never
-- read or written by the application any more.
-- ============================================================
UPDATE notification_preferences SET whatsapp_enabled = 0;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_preferences' AND COLUMN_NAME = 'event_updates');
SET @sql = IF(@c = 0, 'ALTER TABLE notification_preferences ADD COLUMN event_updates TINYINT(1) NOT NULL DEFAULT 1 AFTER reminder_2h', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- event_id lets queued reminders be cancelled when an event changes
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_jobs' AND COLUMN_NAME = 'event_id');
SET @sql = IF(@c = 0, 'ALTER TABLE notification_jobs ADD COLUMN event_id INT UNSIGNED NULL AFTER user_id, ADD COLUMN kind VARCHAR(50) NULL AFTER event_id, ADD INDEX idx_event_kind (event_id, kind)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- REPORTS — additional reasons
-- ============================================================
ALTER TABLE reports MODIFY COLUMN reason
  ENUM('incorrect_info','cancelled','broken_link','scam','duplicate','wrong_venue','wrong_datetime','inappropriate','fake','spam')
  NOT NULL;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND INDEX_NAME = 'idx_user_event');
SET @sql = IF(@c = 0, 'ALTER TABLE reports ADD INDEX idx_user_event (user_id, event_id)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- SOURCE REGISTRY — platform / type / trust / error tracking
-- (enabled, requires_auth, credentials_ref, rate_limit_rpm, refresh_interval,
--  last_checked_at, last_success_at, health_status, terms_status already exist)
-- ============================================================
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sources' AND COLUMN_NAME = 'platform');
SET @sql = IF(@c = 0, "ALTER TABLE sources ADD COLUMN platform VARCHAR(50) NOT NULL DEFAULT 'web' AFTER domain, ADD COLUMN source_type VARCHAR(50) NOT NULL DEFAULT 'event_website' AFTER platform, ADD COLUMN trust_level TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER source_type, ADD COLUMN error_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER health_status, ADD INDEX idx_platform (platform)", 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- RAW CANDIDATES — extraction confidence + review state
-- ============================================================
ALTER TABLE raw_event_records MODIFY COLUMN processing_status
  ENUM('pending','processing','normalized','duplicate','rejected','error','needs_review')
  NOT NULL DEFAULT 'pending';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_event_records' AND COLUMN_NAME = 'confidence');
SET @sql = IF(@c = 0, 'ALTER TABLE raw_event_records ADD COLUMN confidence TINYINT UNSIGNED NULL AFTER content_hash', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- EVENT SOURCES — social traceability. external_id (post ID), source_url
-- (post URL), first_seen_at (discovered), last_seen_at, confidence already
-- exist and are reused. Added: the account identity and last-checked time.
-- ============================================================
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_sources' AND COLUMN_NAME = 'account_handle');
SET @sql = IF(@c = 0, 'ALTER TABLE event_sources ADD COLUMN account_handle VARCHAR(200) NULL AFTER external_id, ADD COLUMN last_checked_at DATETIME NULL AFTER last_verified_at, ADD COLUMN missing_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_checked_at, ADD INDEX idx_external (source_id, external_id(191))', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- SOURCE REGISTRY SEED — web adapters get platform metadata, social/search
-- connectors are registered DISABLED. Enabling one requires the credentials
-- documented in README "Event Discovery Sources".
-- ============================================================
UPDATE sources SET platform = 'web', source_type = 'structured_data', trust_level = 75 WHERE slug = 'jsonld-adapter';
UPDATE sources SET platform = 'web', source_type = 'rss_feed',        trust_level = 50 WHERE slug = 'rss-adapter';
UPDATE sources SET platform = 'internal', source_type = 'manual',     trust_level = 60 WHERE slug = 'manual-submission';

INSERT IGNORE INTO sources (name, slug, domain, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, rate_limit_rpm, refresh_interval, terms_status, requires_auth, credentials_ref, health_status, notes) VALUES
('Sitemap + JSON-LD Crawler', 'sitemap-jsonld', NULL, 'web', 'sitemap', 70, 'sitemap', 'NEvents\\Services\\Ingestion\\SitemapJsonLdAdapter', 0, 10, 720, 'unknown', 0, NULL, 'unknown', 'Reads an XML sitemap, keeps URLs matching config_json.url_pattern and extracts schema.org Event JSON-LD from each page. Set config_json.url (sitemap URL) for a site whose terms permit it.'),
('Instagram (Graph API)', 'instagram-graph', 'instagram.com', 'instagram', 'social', 55, 'api', 'NEvents\\Services\\Ingestion\\Social\\InstagramSourceAdapter', 0, 30, 360, 'restricted', 1, 'INSTAGRAM_ACCESS_TOKEN', 'disabled', 'Organizer-authorized Instagram professional accounts via the Instagram Graph API. Requires an approved Meta app and access token. Hashtag mode additionally requires Meta approval.'),
('Facebook Pages (Graph API)', 'facebook-graph', 'facebook.com', 'facebook', 'social', 55, 'api', 'NEvents\\Services\\Ingestion\\Social\\FacebookSourceAdapter', 0, 30, 360, 'restricted', 1, 'FACEBOOK_PAGE_ACCESS_TOKEN', 'disabled', 'Posts from Facebook Pages the app is authorized for (Page access token). No global Facebook event search is used or assumed.'),
('X (API v2 recent search)', 'x-api', 'x.com', 'x', 'social', 45, 'api', 'NEvents\\Services\\Ingestion\\Social\\XSourceAdapter', 0, 15, 360, 'restricted', 1, 'X_BEARER_TOKEN', 'disabled', 'Official X API v2 recent search. Requires a paid access tier that includes search. Queries are generated from districts x categories.'),
('Search Provider Discovery', 'search-provider', NULL, 'search', 'search_provider', 35, 'api', 'NEvents\\Services\\Ingestion\\Search\\SearchDiscoveryAdapter', 0, 10, 1440, 'unknown', 1, 'SEARCH_PROVIDER', 'disabled', 'Approved/licensed search provider -> candidate URLs -> JSON-LD extraction. No provider is bundled. Google result pages are never scraped.');
