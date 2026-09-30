-- N Events — Demo Content Seed
-- Populates a handful of real, published events so a fresh install has
-- browsable content instead of an empty homepage/search/discover page.
-- Safe to re-run: organizers/venues/events are matched by UNIQUE slug,
-- occurrences/category links are guarded with NOT EXISTS.
SET NAMES utf8mb4;

-- ============================================================
-- ORGANIZERS
-- ============================================================
INSERT IGNORE INTO organizers (slug, name, description, website, city_id, verification_status, is_trusted_publisher, trust_score, status) VALUES
('ai-builders-chennai',  'AI Builders Chennai',  'Community of ML/AI engineers and researchers building and sharing in Chennai.', 'https://example.com/ai-builders-chennai', 1, 'verified', 1, 90, 'active'),
('tn-startup-network',   'TN Startup Network',   'Tamil Nadu''s founder and investor community, connecting startups with capital and mentorship.', 'https://example.com/tn-startup-network', 1, 'verified', 1, 88, 'active'),
('chennai-cycling-club', 'Chennai Cycling Club', 'Weekly group rides across Chennai for riders of all levels.', 'https://example.com/chennai-cycling-club', 1, 'verified', 1, 85, 'active'),
('growthhack-tn',        'GrowthHack Tamil Nadu','Digital marketing and growth practitioners meetup.', 'https://example.com/growthhack-tn', 1, 'verified', 0, 70, 'active');

-- ============================================================
-- VENUES
-- ============================================================
INSERT IGNORE INTO venues (name, slug, address, city_id, is_online, is_verified) VALUES
('Anna Nagar Tower Park',        'anna-nagar-tower-park',   'Anna Nagar Tower Park, Anna Nagar, Chennai', 1, 0, 1),
('IIT Madras Research Park',     'iit-madras-research-park','Kanagam Road, Taramani, Chennai',            1, 0, 1),
('ECR Start Point',              'ecr-start-point',         'East Coast Road, Chennai',                   1, 0, 1),
('T Nagar Co-working Hub',       't-nagar-coworking-hub',   'Panagal Park, T Nagar, Chennai',             1, 0, 1);

-- ============================================================
-- EVENTS
-- ============================================================

-- 1) GenAI Builders Meetup — Technology / AI, tomorrow, free, offline
INSERT IGNORE INTO events
  (uuid, slug, title, short_summary, description, primary_organizer_id, event_type, format,
   status, data_origin, verification_status, trust_score, registration_url, timezone,
   pricing_type, currency, venue_id, city_id, is_featured,
   discovered_at, first_published_at, last_verified_at, last_seen_at)
SELECT
  UUID(), 'genai-builders-meetup', 'GenAI Builders Meetup',
  'Hands-on talks and demos from engineers building with LLMs and generative AI.',
  'Join fellow builders for an evening of lightning talks, live demos and open discussion on building production GenAI applications — covering RAG pipelines, agents, evaluation and deployment. Free pizza and networking after the talks.',
  o.id, 'meetup', 'offline',
  'published', 'demo', 'verified', 92, 'https://example.com/events/genai-builders-meetup', 'Asia/Kolkata',
  'free', 'INR', v.id, 1, 1,
  NOW(), NOW(), NOW(), NOW()
FROM organizers o, venues v
WHERE o.slug = 'ai-builders-chennai' AND v.slug = 'anna-nagar-tower-park';

-- 2) Chennai Startup Summit — Startup, this weekend, paid, offline, featured
INSERT IGNORE INTO events
  (uuid, slug, title, short_summary, description, primary_organizer_id, event_type, format,
   status, data_origin, verification_status, trust_score, registration_url, timezone,
   pricing_type, currency, min_price, max_price, venue_id, city_id, is_featured,
   discovered_at, first_published_at, last_verified_at, last_seen_at)
SELECT
  UUID(), 'chennai-startup-summit', 'Chennai Startup Summit',
  'A full-day summit bringing together founders, investors and operators from across Tamil Nadu.',
  'The Chennai Startup Summit brings together 500+ founders, angel investors and VCs for a day of keynotes, pitch sessions and structured networking. Tracks include fundraising, product-market fit and scaling operations.',
  o.id, 'conference', 'offline',
  'published', 'demo', 'verified', 95, 'https://example.com/events/chennai-startup-summit', 'Asia/Kolkata',
  'paid', 'INR', 499, 1999, v.id, 1, 1,
  NOW(), NOW(), NOW(), NOW()
FROM organizers o, venues v
WHERE o.slug = 'tn-startup-network' AND v.slug = 'iit-madras-research-park';

-- 3) Early Morning Cycling Ride — Fitness / Cycling, this weekend, free, offline
INSERT IGNORE INTO events
  (uuid, slug, title, short_summary, description, primary_organizer_id, event_type, format,
   status, data_origin, verification_status, trust_score, registration_url, timezone,
   pricing_type, currency, venue_id, city_id, is_featured,
   discovered_at, first_published_at, last_verified_at, last_seen_at)
SELECT
  UUID(), 'early-morning-cycling-ride', 'Early Morning Ride',
  'A relaxed 25km group ride along ECR — all fitness levels welcome.',
  'Meet at ECR Start Point for a scenic 25km group ride along the coast. Suitable for all fitness levels, with regroup stops every 8km. Bring your own bike, helmet mandatory.',
  o.id, 'community_ride', 'offline',
  'published', 'demo', 'verified', 80, 'https://example.com/events/early-morning-cycling-ride', 'Asia/Kolkata',
  'free', 'INR', v.id, 1, 0,
  NOW(), NOW(), NOW(), NOW()
FROM organizers o, venues v
WHERE o.slug = 'chennai-cycling-club' AND v.slug = 'ecr-start-point';

-- 4) Digital Marketing Masterclass — Marketing, online, free
INSERT IGNORE INTO events
  (uuid, slug, title, short_summary, description, primary_organizer_id, event_type, format,
   status, data_origin, verification_status, trust_score, registration_url, timezone,
   pricing_type, currency, online_platform, online_url, city_id, is_featured,
   discovered_at, first_published_at, last_verified_at, last_seen_at)
SELECT
  UUID(), 'digital-marketing-masterclass', 'Digital Marketing Masterclass',
  'A live online session on SEO, paid acquisition and content strategy for 2026.',
  'A 90-minute live online masterclass covering modern SEO, paid acquisition channels and content strategy, with a Q&A session at the end.',
  o.id, 'webinar', 'online',
  'published', 'demo', 'verified', 75, 'https://example.com/events/digital-marketing-masterclass', 'Asia/Kolkata',
  'free', 'INR', 'Zoom', 'https://example.com/webinar/digital-marketing-masterclass', 1, 0,
  NOW(), NOW(), NOW(), NOW()
FROM organizers o
WHERE o.slug = 'growthhack-tn';

-- 5) Founders Coffee Meetup — Startup / Founders, today, free, offline
INSERT IGNORE INTO events
  (uuid, slug, title, short_summary, description, primary_organizer_id, event_type, format,
   status, data_origin, verification_status, trust_score, registration_url, timezone,
   pricing_type, currency, venue_id, city_id, is_featured,
   discovered_at, first_published_at, last_verified_at, last_seen_at)
SELECT
  UUID(), 'founders-coffee-meetup', 'Founders Coffee Meetup',
  'An informal coffee catch-up for early-stage founders.',
  'Drop in for coffee and conversation with other early-stage founders. No agenda, no pitches — just an informal weekly catch-up.',
  o.id, 'meetup', 'offline',
  'published', 'demo', 'verified', 70, 'https://example.com/events/founders-coffee-meetup', 'Asia/Kolkata',
  'free', 'INR', v.id, 1, 0,
  NOW(), NOW(), NOW(), NOW()
FROM organizers o, venues v
WHERE o.slug = 'tn-startup-network' AND v.slug = 't-nagar-coworking-hub';

-- ============================================================
-- OCCURRENCES (idempotent — skipped if this event already has one)
-- ============================================================
INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status)
SELECT e.id, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) + INTERVAL 2 HOUR, 'Asia/Kolkata', 'scheduled'
FROM events e WHERE e.slug = 'genai-builders-meetup'
  AND NOT EXISTS (SELECT 1 FROM event_occurrences eo WHERE eo.event_id = e.id);

INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status)
SELECT e.id,
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL (7 - WEEKDAY(UTC_TIMESTAMP())) DAY),
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL (7 - WEEKDAY(UTC_TIMESTAMP())) DAY) + INTERVAL 8 HOUR,
       'Asia/Kolkata', 'scheduled'
FROM events e WHERE e.slug = 'chennai-startup-summit'
  AND NOT EXISTS (SELECT 1 FROM event_occurrences eo WHERE eo.event_id = e.id);

INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status)
SELECT e.id,
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL (6 - WEEKDAY(UTC_TIMESTAMP())) DAY),
       DATE_ADD(UTC_TIMESTAMP(), INTERVAL (6 - WEEKDAY(UTC_TIMESTAMP())) DAY) + INTERVAL 2 HOUR,
       'Asia/Kolkata', 'scheduled'
FROM events e WHERE e.slug = 'early-morning-cycling-ride'
  AND NOT EXISTS (SELECT 1 FROM event_occurrences eo WHERE eo.event_id = e.id);

INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status)
SELECT e.id, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 DAY), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 DAY) + INTERVAL 90 MINUTE, 'Asia/Kolkata', 'scheduled'
FROM events e WHERE e.slug = 'digital-marketing-masterclass'
  AND NOT EXISTS (SELECT 1 FROM event_occurrences eo WHERE eo.event_id = e.id);

INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status)
SELECT e.id, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 HOUR), 'Asia/Kolkata', 'scheduled'
FROM events e WHERE e.slug = 'founders-coffee-meetup'
  AND NOT EXISTS (SELECT 1 FROM event_occurrences eo WHERE eo.event_id = e.id);

-- ============================================================
-- CATEGORY LINKS
-- ============================================================
INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 1 FROM events e, categories c
WHERE e.slug = 'genai-builders-meetup' AND c.slug = 'artificial-intelligence'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);
INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 0 FROM events e, categories c
WHERE e.slug = 'genai-builders-meetup' AND c.slug = 'technology'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);

INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 1 FROM events e, categories c
WHERE e.slug = 'chennai-startup-summit' AND c.slug = 'startup'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);

INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 1 FROM events e, categories c
WHERE e.slug = 'early-morning-cycling-ride' AND c.slug = 'cycling'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);
INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 0 FROM events e, categories c
WHERE e.slug = 'early-morning-cycling-ride' AND c.slug = 'fitness'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);

INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 1 FROM events e, categories c
WHERE e.slug = 'digital-marketing-masterclass' AND c.slug = 'digital-marketing'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);

INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 1 FROM events e, categories c
WHERE e.slug = 'founders-coffee-meetup' AND c.slug = 'founders'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);
INSERT INTO event_categories (event_id, category_id, is_primary)
SELECT e.id, c.id, 0 FROM events e, categories c
WHERE e.slug = 'founders-coffee-meetup' AND c.slug = 'startup'
  AND NOT EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = c.id);

-- ============================================================
-- Bump organizer event counts
-- ============================================================
UPDATE organizers o SET total_events = (
  SELECT COUNT(*) FROM events e WHERE e.primary_organizer_id = o.id
);
