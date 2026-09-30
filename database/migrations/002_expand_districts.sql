-- N Events — Expand to all 38 current Tamil Nadu districts
-- Repairs the original migration, which only seeded 12 districts (the old
-- pre-2020 division), and adds the supporting structures the district
-- selector, event filtering and location-normalization pipeline need.
-- Idempotent: safe to re-run.

SET NAMES utf8mb4;

-- ============================================================
-- FIX EXISTING ROW: "Kanchipuram" -> official spelling "Kancheepuram"
-- (the old spelling becomes an alias, not a duplicate district)
-- ============================================================
UPDATE districts SET name = 'Kancheepuram', slug = 'kancheepuram' WHERE slug = 'kanchipuram';
UPDATE cities    SET name = 'Kancheepuram', slug = 'kancheepuram' WHERE slug = 'kanchipuram';

-- ============================================================
-- 26 MISSING DISTRICTS (bringing the total to the real 38)
-- ============================================================
INSERT IGNORE INTO districts (id, state_id, name, slug, is_active) VALUES
(13, 1, 'Ariyalur',        'ariyalur',        1),
(14, 1, 'Cuddalore',       'cuddalore',       1),
(15, 1, 'Dharmapuri',      'dharmapuri',      1),
(16, 1, 'Dindigul',        'dindigul',        1),
(17, 1, 'Kallakurichi',    'kallakurichi',    1),
(18, 1, 'Kanyakumari',     'kanyakumari',     1),
(19, 1, 'Karur',           'karur',           1),
(20, 1, 'Krishnagiri',     'krishnagiri',     1),
(21, 1, 'Mayiladuthurai',  'mayiladuthurai',  1),
(22, 1, 'Nagapattinam',    'nagapattinam',    1),
(23, 1, 'Namakkal',        'namakkal',        1),
(24, 1, 'Perambalur',      'perambalur',      1),
(25, 1, 'Pudukkottai',     'pudukkottai',     1),
(26, 1, 'Ramanathapuram',  'ramanathapuram',  1),
(27, 1, 'Ranipet',         'ranipet',         1),
(28, 1, 'Sivaganga',       'sivaganga',       1),
(29, 1, 'Tenkasi',         'tenkasi',         1),
(30, 1, 'Theni',           'theni',           1),
(31, 1, 'The Nilgiris',    'the-nilgiris',    1),
(32, 1, 'Thiruvallur',     'thiruvallur',     1),
(33, 1, 'Thiruvarur',      'thiruvarur',      1),
(34, 1, 'Thoothukudi',     'thoothukudi',     1),
(35, 1, 'Tirupattur',      'tirupattur',      1),
(36, 1, 'Tiruvannamalai',  'tiruvannamalai',  1),
(37, 1, 'Viluppuram',      'viluppuram',      1),
(38, 1, 'Virudhunagar',    'virudhunagar',    1);

-- Every district needs a representative city row so events/venues (which
-- reference cities.id, not districts.id directly) can belong to it.
INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order) VALUES
(13, 'Ariyalur',        'ariyalur',        1, 0, 13),
(14, 'Cuddalore',       'cuddalore',       1, 0, 14),
(15, 'Dharmapuri',      'dharmapuri',      1, 0, 15),
(16, 'Dindigul',        'dindigul',        1, 0, 16),
(17, 'Kallakurichi',    'kallakurichi',    1, 0, 17),
(18, 'Kanyakumari',     'kanyakumari',     1, 0, 18),
(19, 'Karur',           'karur',           1, 0, 19),
(20, 'Krishnagiri',     'krishnagiri',     1, 0, 20),
(21, 'Mayiladuthurai',  'mayiladuthurai',  1, 0, 21),
(22, 'Nagapattinam',    'nagapattinam',    1, 0, 22),
(23, 'Namakkal',        'namakkal',        1, 0, 23),
(24, 'Perambalur',      'perambalur',      1, 0, 24),
(25, 'Pudukkottai',     'pudukkottai',     1, 0, 25),
(26, 'Ramanathapuram',  'ramanathapuram',  1, 0, 26),
(27, 'Ranipet',         'ranipet',         1, 0, 27),
(28, 'Sivaganga',       'sivaganga',       1, 0, 28),
(29, 'Tenkasi',         'tenkasi',         1, 0, 29),
(30, 'Theni',           'theni',           1, 0, 30),
(31, 'The Nilgiris',    'the-nilgiris',    1, 0, 31),
(32, 'Thiruvallur',     'thiruvallur',     1, 0, 32),
(33, 'Thiruvarur',      'thiruvarur',      1, 0, 33),
(34, 'Thoothukudi',     'thoothukudi',     1, 0, 34),
(35, 'Tirupattur',      'tirupattur',      1, 0, 35),
(36, 'Tiruvannamalai',  'tiruvannamalai',  1, 0, 36),
(37, 'Viluppuram',      'viluppuram',      1, 0, 37),
(38, 'Virudhunagar',    'virudhunagar',    1, 0, 38);

-- ============================================================
-- DISTRICT ALIASES — common alternate spellings used by source websites,
-- resolved during location normalization. NOT shown as separate districts.
-- ============================================================
CREATE TABLE IF NOT EXISTS district_aliases (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alias       VARCHAR(100) NOT NULL,
    alias_norm  VARCHAR(100) NOT NULL,
    district_id SMALLINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alias_norm (alias_norm),
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    INDEX idx_district (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO district_aliases (alias, alias_norm, district_id)
SELECT 'Kanchipuram', 'kanchipuram', id FROM districts WHERE slug = 'kancheepuram'
UNION ALL SELECT 'Kanniyakumari', 'kanniyakumari', id FROM districts WHERE slug = 'kanyakumari'
UNION ALL SELECT 'Nagercoil', 'nagercoil', id FROM districts WHERE slug = 'kanyakumari'
UNION ALL SELECT 'Villupuram', 'villupuram', id FROM districts WHERE slug = 'viluppuram'
UNION ALL SELECT 'Tirupathur', 'tirupathur', id FROM districts WHERE slug = 'tirupattur'
UNION ALL SELECT 'Nilgiris', 'nilgiris', id FROM districts WHERE slug = 'the-nilgiris'
UNION ALL SELECT 'Ooty', 'ooty', id FROM districts WHERE slug = 'the-nilgiris'
UNION ALL SELECT 'Udhagamandalam', 'udhagamandalam', id FROM districts WHERE slug = 'the-nilgiris'
UNION ALL SELECT 'Trichy', 'trichy', id FROM districts WHERE slug = 'tiruchirappalli'
UNION ALL SELECT 'Trichirappalli', 'trichirappalli', id FROM districts WHERE slug = 'tiruchirappalli'
UNION ALL SELECT 'Tiruchi', 'tiruchi', id FROM districts WHERE slug = 'tiruchirappalli'
UNION ALL SELECT 'Tuticorin', 'tuticorin', id FROM districts WHERE slug = 'thoothukudi'
UNION ALL SELECT 'Madras', 'madras', id FROM districts WHERE slug = 'chennai'
UNION ALL SELECT 'Pondicherry Road Villupuram', 'pondicherry-road-villupuram', id FROM districts WHERE slug = 'viluppuram';

-- ============================================================
-- USER LOCATION PREFERENCE: add district_id (the primary preference going
-- forward — city_id/area_id stay for finer-grained precision where known)
-- ============================================================
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_locations' AND COLUMN_NAME = 'district_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE user_locations ADD COLUMN district_id SMALLINT UNSIGNED NULL AFTER city_id, ADD INDEX idx_district (district_id)',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- EVENT DATA ORIGIN — distinguishes genuinely discovered/organizer/admin
-- events from demo/seed content, so demo data is never presented to users
-- as if it were a real automatically-verified discovery.
-- ============================================================
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'data_origin'
);
SET @sql = IF(@col_exists = 0,
  "ALTER TABLE events ADD COLUMN data_origin ENUM('discovered','organizer_submitted','admin_created','demo','seed') NOT NULL DEFAULT 'admin_created' AFTER status, ADD INDEX idx_data_origin (data_origin)",
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
