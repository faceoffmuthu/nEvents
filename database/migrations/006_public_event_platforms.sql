-- 006: Public event platforms collected keylessly by the web crawler
-- (robots.txt obeyed, structured schema.org Event data only).
-- Enabled at the site owner's request (2026-09-24). Each site's terms of
-- service were NOT verified by the developer - terms_status stays 'unknown'.
-- region_scope "india": events in India outside Tamil Nadu are kept (no TN
-- district, labelled "City, State"), events outside India are rejected.
--
-- Not added (checked 2026-09-24):
--   MeraEvents       every page is behind a Cloudflare bot challenge
--   Awwwards /websites/events/  502 to crawlers, and it is a gallery of event WEBSITES, not events
--   Wix /event/website          product marketing page with no events (Wix-built organizer sites can be added one by one)

INSERT IGNORE INTO sources (name, slug, domain, platform, source_type, trust_level, acquisition_method, adapter_class, enabled, rate_limit_rpm, refresh_interval, terms_status, requires_auth, health_status, notes, config_json) VALUES
('Meetup (public city pages)', 'meetup-web', 'www.meetup.com', 'web', 'event_platform', 75, 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 1, 20, 360, 'unknown', 0, 'unknown',
 'Public /find/in--city/ pages and linked event pages. robots.txt disallows ?source= and similar query URLs, which are never used.',
 '{"start_urls": ["https://www.meetup.com/find/in--chennai/", "https://www.meetup.com/find/in--coimbatore/", "https://www.meetup.com/find/in--madurai/"], "event_pattern": "#meetup.com/[^/?]+/events/[0-9]+/?#", "strip_query": true, "max_depth": 0, "max_pages": 45, "region_scope": "india"}'),
('Eventbrite (public city pages)', 'eventbrite-web', 'www.eventbrite.com', 'web', 'event_platform', 75, 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 1, 20, 360, 'unknown', 0, 'unknown',
 'City browse pages give dates only - the crawler follows each /e/ event page for exact times.',
 '{"start_urls": ["https://www.eventbrite.com/d/india--chennai/events/", "https://www.eventbrite.com/d/india--chennai/events/?page=2", "https://www.eventbrite.com/d/india--coimbatore/events/", "https://www.eventbrite.com/d/india--madurai/events/", "https://www.eventbrite.com/d/india--salem/events/"], "event_pattern": "#eventbrite.com/e/[^/?]+-[0-9]{9,}#", "strip_query": true, "max_depth": 0, "max_pages": 60, "region_scope": "india"}'),
('District (public city pages)', 'district-web', 'www.district.in', 'web', 'event_platform', 75, 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 1, 20, 360, 'unknown', 0, 'unknown',
 'Upcoming-events city pages listed in District''s own sitemap. Its /events/ page defaults to Delhi NCR and is not used.',
 '{"start_urls": ["https://www.district.in/events/upcoming-events-in-chennai", "https://www.district.in/events/upcoming-events-in-coimbatore", "https://www.district.in/events/upcoming-events-in-puducherry"], "event_pattern": "#district.in/events/[^/?]+-buy-tickets$#", "strip_query": true, "max_depth": 0, "max_pages": 45, "region_scope": "india"}'),
('EventsFlare', 'eventsflare-web', 'eventsflare.com', 'web', 'event_platform', 70, 'crawler', 'NEvents\\Services\\Ingestion\\WebCrawlerAdapter', 1, 20, 360, 'unknown', 0, 'unknown',
 'Mostly Bangalore and Delhi listings - kept because region_scope is India.',
 '{"start_urls": ["https://eventsflare.com/", "https://eventsflare.com/all-events", "https://eventsflare.com/online-events"], "event_pattern": "#eventsflare.com/[^/?]+/e/[0-9]+/#", "strip_query": true, "max_depth": 0, "max_pages": 30, "region_scope": "india"}');
