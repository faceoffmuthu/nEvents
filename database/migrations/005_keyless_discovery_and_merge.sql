-- N Events — keyless web discovery (crawler, ICS feeds) + trust-ranked
-- updates of existing events. Idempotent.
-- NOTE: bin/migrate.php splits on semicolons — none inside string literals.

SET NAMES utf8mb4;

-- Provenance of an event's current details: which source last set them and
-- how trustworthy that was (0-100, = avg(source trust_level, extraction
-- confidence)). A later source only overwrites details if it scores at least
-- as high — see EventMergeService.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'content_score');
SET @sql = IF(@c = 0, 'ALTER TABLE events ADD COLUMN content_score TINYINT UNSIGNED NULL AFTER trust_score, ADD COLUMN content_source_id INT UNSIGNED NULL AFTER content_score', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE events SET content_score = trust_score WHERE content_score IS NULL AND data_origin = 'discovered';

-- Conflict log de-duplication lookups
SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_conflicts' AND INDEX_NAME = 'idx_event_field_status');
SET @sql = IF(@c = 0, 'ALTER TABLE event_conflicts ADD INDEX idx_event_field_status (event_id, field, status)', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE sources MODIFY COLUMN acquisition_method
  ENUM('api','json_ld','rss','sitemap','permitted_html','partner_feed','manual','crawler','ics')
  NOT NULL DEFAULT 'manual';

-- Keyless source templates (DISABLED — enable after adding permitted sites in config_json)
INSERT IGNORE INTO sources (name, slug, domain, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, rate_limit_rpm, refresh_interval, terms_status, requires_auth, health_status, notes, config_json) VALUES
('Public Event Websites (crawler)', 'web-crawler', NULL, 'web', 'event_website', 75, 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 0, 20, 360, 'unknown', 0, 'unknown',
 'Crawls public event listing pages and extracts schema.org Event data (JSON-LD/microdata). Obeys robots.txt. Add one source row per website so each keeps its own trust level and health.',
 '{"start_urls": [], "event_pattern": "#/(events?|e)/[^/?]+#i", "follow_pattern": "#[?&]page=\\\\d+#", "max_depth": 2, "max_pages": 40}'),
('Public Calendar Feeds (ICS)', 'ics-feeds', NULL, 'web', 'calendar_feed', 85, 'ics', 'NEvents\\Services\\Ingestion\\IcsFeedAdapter', 0, 20, 360, 'unknown', 0, 'unknown',
 'Public .ics / webcal calendar feeds published by organizers, colleges and communities. Keyless and precise (exact times, UID, cancellations).',
 '{"urls": []}');
