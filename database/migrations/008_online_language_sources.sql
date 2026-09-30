-- 008: Online events in Indian languages (owner's online rule, 2026-09-24:
-- online events only from India or Canada AND in English / Tamil / Hindi /
-- Malayalam / Telugu — enforced by Web\OnlineAudience at processing time).
--
-- Eventbrite's /d/online/<language>/ pages list genuinely online events
-- (dry run 2026-09-24: Tamil 8 online -> 5 kept, Hindi 12 -> 2, Telugu 6 -> 3,
-- Malayalam 0). Not added: /d/india/online--events/ and eventbrite.ca
-- /d/canada/online--events/ — they are keyword searches that mostly return
-- IN-PERSON events whose titles contain the word "online".
-- Enabled at the owner's request like the other public platforms - the
-- site's terms of service were not verified (terms_status 'unknown').

INSERT IGNORE INTO sources (name, slug, domain, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, rate_limit_rpm, refresh_interval, terms_status, requires_auth, health_status, notes, config_json) VALUES
('Eventbrite (online, Indian languages)', 'eventbrite-online-lang', 'www.eventbrite.com', 'web', 'event_platform', 75, 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 1, 20, 360, 'unknown', 0, 'unknown',
 'Online-event pages for Tamil, Hindi, Telugu and Malayalam. Only India/Canada-hosted events in English/Tamil/Hindi/Malayalam/Telugu are kept (OnlineAudience).',
 '{"start_urls": ["https://www.eventbrite.com/d/online/tamil/", "https://www.eventbrite.com/d/online/hindi/", "https://www.eventbrite.com/d/online/telugu/", "https://www.eventbrite.com/d/online/malayalam/"], "event_pattern": "#eventbrite.[a-z.]+/e/[^/?]+-[0-9]{9,}#", "strip_query": true, "max_depth": 0, "max_pages": 50, "region_scope": "india"}');
