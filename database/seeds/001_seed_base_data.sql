-- N Events — Seed Data (Tamil Nadu / Chennai MVP)
SET NAMES utf8mb4;

-- Roles
INSERT IGNORE INTO roles (name, slug, description) VALUES
('Super Administrator', 'super_admin',  'Full system access'),
('Administrator',       'admin',        'Admin panel access'),
('Moderator',           'moderator',    'Content moderation'),
('Organizer',           'organizer',    'Event organizer'),
('User',                'user',         'Registered user'),
('Visitor',             'visitor',      'Public visitor');

-- Permissions
INSERT IGNORE INTO permissions (name, slug, group_name) VALUES
('Publish Events',        'events.publish',        'events'),
('Edit Any Event',        'events.edit_any',       'events'),
('Delete Any Event',      'events.delete_any',     'events'),
('Manage Sources',        'sources.manage',        'ingestion'),
('Manage Users',          'users.manage',          'users'),
('Manage Roles',          'roles.manage',          'users'),
('Manage Organizers',     'organizers.manage',     'organizers'),
('Verify Organizers',     'organizers.verify',     'organizers'),
('View Admin Panel',      'admin.view',            'admin'),
('Manage System',         'system.manage',         'admin'),
('Manage Feature Flags',  'flags.manage',          'admin'),
('View Analytics',        'analytics.view',        'analytics');

-- Countries
INSERT IGNORE INTO countries (id, name, iso2, iso3, phone_code, currency) VALUES
(1, 'India', 'IN', 'IND', '+91', 'INR');

-- States
INSERT IGNORE INTO states (id, country_id, name, slug) VALUES
(1, 1, 'Tamil Nadu', 'tamil-nadu');

-- Districts
INSERT IGNORE INTO districts (id, state_id, name, slug) VALUES
(1,  1, 'Chennai',      'chennai'),
(2,  1, 'Coimbatore',   'coimbatore'),
(3,  1, 'Madurai',      'madurai'),
(4,  1, 'Tiruchirappalli', 'tiruchirappalli'),
(5,  1, 'Salem',        'salem'),
(6,  1, 'Erode',        'erode'),
(7,  1, 'Tiruppur',     'tiruppur'),
(8,  1, 'Vellore',      'vellore'),
(9,  1, 'Thanjavur',    'thanjavur'),
(10, 1, 'Tirunelveli',  'tirunelveli'),
(11, 1, 'Kanchipuram',  'kanchipuram'),
(12, 1, 'Chengalpattu', 'chengalpattu');

-- Cities
INSERT IGNORE INTO cities (id, district_id, name, slug, latitude, longitude, is_featured, sort_order) VALUES
(1,  1,  'Chennai',         'chennai',         13.0827,  80.2707,  1, 1),
(2,  2,  'Coimbatore',      'coimbatore',      11.0168,  76.9558,  1, 2),
(3,  3,  'Madurai',         'madurai',          9.9252,  78.1198,  1, 3),
(4,  4,  'Tiruchirappalli', 'tiruchirappalli', 10.7905,  78.7047,  1, 4),
(5,  5,  'Salem',           'salem',           11.6643,  78.1460,  0, 5),
(6,  6,  'Erode',           'erode',           11.3410,  77.7172,  0, 6),
(7,  7,  'Tiruppur',        'tiruppur',        11.1085,  77.3411,  0, 7),
(8,  8,  'Vellore',         'vellore',         12.9165,  79.1325,  0, 8),
(9,  9,  'Thanjavur',       'thanjavur',       10.7870,  79.1378,  0, 9),
(10, 10, 'Tirunelveli',     'tirunelveli',      8.7139,  77.7567,  0, 10),
(11, 11, 'Kanchipuram',     'kanchipuram',     12.8333,  79.7000,  0, 11),
(12, 12, 'Chengalpattu',    'chengalpattu',    12.6919,  79.9760,  0, 12),
(13, 1,  'Tambaram',        'tambaram',        12.9249,  80.1000,  0, 13),
(14, 1,  'Anna Nagar',      'anna-nagar',      13.0850,  80.2101,  0, 14),
(15, 1,  'T Nagar',         't-nagar',         13.0418,  80.2341,  0, 15);

-- Areas in Chennai
INSERT IGNORE INTO areas (city_id, name, slug, latitude, longitude) VALUES
(1, 'T Nagar',         't-nagar',          13.0418, 80.2341),
(1, 'Anna Nagar',      'anna-nagar',       13.0850, 80.2101),
(1, 'Nungambakkam',    'nungambakkam',     13.0569, 80.2425),
(1, 'Velachery',       'velachery',        12.9815, 80.2176),
(1, 'OMR / Sholinganallur', 'omr-sholinganallur', 12.9010, 80.2279),
(1, 'Perungudi',       'perungudi',        12.9630, 80.2446),
(1, 'Porur',           'porur',            13.0356, 80.1574),
(1, 'Guindy',          'guindy',           13.0069, 80.2206),
(1, 'Egmore',          'egmore',           13.0762, 80.2639),
(1, 'Adyar',           'adyar',            13.0012, 80.2565),
(1, 'Mylapore',        'mylapore',         13.0336, 80.2704),
(1, 'Thiruvanmiyur',   'thiruvanmiyur',    12.9833, 80.2667),
(1, 'Ambattur',        'ambattur',         13.1143, 80.1548),
(1, 'Alwarpet',        'alwarpet',         13.0291, 80.2538),
(1, 'Kodambakkam',     'kodambakkam',      13.0530, 80.2267);

-- Categories (hierarchical)
INSERT IGNORE INTO categories (id, parent_id, name, slug, icon, color, sort_order) VALUES
-- Top-level
(1,  NULL, 'Technology',        'technology',        'fa-microchip',       '#6366f1', 1),
(2,  NULL, 'Marketing',         'marketing',         'fa-bullhorn',        '#f59e0b', 2),
(3,  NULL, 'Startup',           'startup',           'fa-rocket',          '#10b981', 3),
(4,  NULL, 'Business',          'business',          'fa-briefcase',       '#3b82f6', 4),
(5,  NULL, 'Fitness',           'fitness',           'fa-dumbbell',        '#ef4444', 5),
(6,  NULL, 'Culture',           'culture',           'fa-palette',         '#8b5cf6', 6),
(7,  NULL, 'Education',         'education',         'fa-graduation-cap',  '#0ea5e9', 7),
(8,  NULL, 'Career',            'career',            'fa-user-tie',        '#14b8a6', 8),
(9,  NULL, 'Community',         'community',         'fa-users',           '#f97316', 9),
(10, NULL, 'Professional',      'professional',      'fa-handshake',       '#84cc16', 10),
(11, NULL, 'Conference',        'conference',        'fa-chalkboard-user', '#a855f7', 11),
(12, NULL, 'Workshop',          'workshop',          'fa-tools',           '#06b6d4', 12),
(13, NULL, 'Expo',              'expo',              'fa-building-columns','#d97706', 13),

-- Technology subcategories
(20, 1, 'Artificial Intelligence', 'artificial-intelligence', 'fa-brain',       '#6366f1', 1),
(21, 1, 'Machine Learning',        'machine-learning',        'fa-network-wired','#6366f1', 2),
(22, 1, 'Software Development',    'software-development',    'fa-code',         '#6366f1', 3),
(23, 1, 'Data Science',            'data-science',            'fa-chart-bar',    '#6366f1', 4),
(24, 1, 'Cybersecurity',           'cybersecurity',           'fa-shield-halved','#6366f1', 5),
(25, 1, 'Cloud Computing',         'cloud-computing',         'fa-cloud',        '#6366f1', 6),
(26, 1, 'DevOps',                  'devops',                  'fa-gear',         '#6366f1', 7),
(27, 1, 'Web Development',         'web-development',         'fa-globe',        '#6366f1', 8),
(28, 1, 'Mobile Development',      'mobile-development',      'fa-mobile-screen','#6366f1', 9),
(29, 1, 'Blockchain',              'blockchain',              'fa-link',         '#6366f1', 10),

-- Marketing subcategories
(40, 2, 'Digital Marketing', 'digital-marketing', 'fa-chart-line',  '#f59e0b', 1),
(41, 2, 'SEO',               'seo',               'fa-magnifying-glass','#f59e0b', 2),
(42, 2, 'Social Media',      'social-media',      'fa-share-nodes', '#f59e0b', 3),
(43, 2, 'Branding',          'branding',          'fa-star',        '#f59e0b', 4),
(44, 2, 'Content Marketing', 'content-marketing', 'fa-pen-nib',     '#f59e0b', 5),

-- Startup subcategories
(50, 3, 'Founders',      'founders',       'fa-lightbulb',  '#10b981', 1),
(51, 3, 'Funding',       'funding',        'fa-coins',      '#10b981', 2),
(52, 3, 'Pitching',      'pitching',       'fa-microphone', '#10b981', 3),
(53, 3, 'Incubation',    'incubation',     'fa-seedling',   '#10b981', 4),

-- Business subcategories
(60, 4, 'Networking',    'networking',     'fa-people-group','#3b82f6', 1),
(61, 4, 'MSME',          'msme',           'fa-shop',       '#3b82f6', 2),
(62, 4, 'Leadership',    'leadership',     'fa-chess-king', '#3b82f6', 3),
(63, 4, 'Sales',         'sales',          'fa-handshake',  '#3b82f6', 4),
(64, 4, 'Finance',       'finance',        'fa-piggy-bank', '#3b82f6', 5),

-- Fitness subcategories
(70, 5, 'Cycling',       'cycling',        'fa-bicycle',    '#ef4444', 1),
(71, 5, 'Running',       'running',        'fa-person-running','#ef4444', 2),
(72, 5, 'Marathon',      'marathon',       'fa-flag-checkered','#ef4444', 3),
(73, 5, 'Yoga',          'yoga',           'fa-spa',        '#ef4444', 4);

-- Feature flags
INSERT IGNORE INTO feature_flags (name, enabled, description) VALUES
('whatsapp_notifications',     0, 'WhatsApp event alerts via Business Platform'),
('google_calendar_oauth',      0, 'OAuth-based Google Calendar integration'),
('ai_extraction',              0, 'AI-assisted event data extraction'),
('organizer_portal',           1, 'Organizer self-service portal'),
('recommendation_score_public',0, 'Show recommendation % score publicly'),
('tamil_ui',                   0, 'Tamil language UI');

-- Sources (manual/verified only for MVP)
-- adapter_class must be set for jsonld-adapter/rss-adapter or IngestionService::runSource()
-- has no adapter to resolve, even once an admin enables the source and sets config_json.
INSERT IGNORE INTO sources (name, slug, acquisition_method, adapter_class, enabled, rate_limit_rpm, terms_status, health_status, notes) VALUES
('Manual Submission',      'manual-submission',  'manual',  NULL, 1, 1000, 'compliant', 'healthy', 'Organizer and admin-submitted events'),
('JSON-LD Web Adapter',    'jsonld-adapter',     'json_ld', 'NEvents\\Services\\Ingestion\\JsonLdEventAdapter', 0, 10, 'unknown', 'unknown', 'Generic JSON-LD schema.org/Event extractor — set config_json.url to a permitted source before enabling'),
('RSS Feed Adapter',       'rss-adapter',        'rss',     'NEvents\\Services\\Ingestion\\RssEventAdapter',    0, 20, 'unknown', 'unknown', 'Generic RSS/Atom event feed reader — set config_json.url to a permitted feed before enabling');

-- System settings
INSERT IGNORE INTO system_settings (key_name, value, type, group_name) VALUES
('site_name',             'N Events',             'string',  'general'),
('site_tagline',          'Stop searching for events. Let the right events find you.', 'string', 'general'),
('default_city',          'chennai',              'string',  'general'),
('default_city_id',       '1',                    'integer', 'general'),
('default_radius_km',     '25',                   'integer', 'general'),
('events_per_page',       '20',                   'integer', 'general'),
('digest_default_time',   '08:00:00',             'string',  'notifications'),
('max_digest_events',     '10',                   'integer', 'notifications'),
('recommendation_weights','{"interest":30,"category":15,"distance":15,"date":10,"format":10,"trust":8,"behavior":7,"popularity":5}', 'json', 'recommendation');
