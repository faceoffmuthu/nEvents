-- N Events — category colors moved to the Orange / Yellow / Cream / Brown
-- palette (2026-09-24). Five warm tones derived from the palette, each
-- >= 4.5:1 as label text on card (#FEF2E9), Cream (#FDE3CF) and #F2D6C2:
--   #632713 Brown          10.4 / 9.3 / 8.3
--   #A23F14 Rust  (Orange)  5.9 / 5.2 / 4.7
--   #8A5406 Amber (Yellow)  5.7 / 5.1 / 4.5
--   #8C2A1C Brick           7.8 / 6.9 / 6.2
--   #6B4E0C Olive-amber     7.0 / 6.3 / 5.6
-- Subcategories take their parent's color (as before). Idempotent.

SET NAMES utf8mb4;

UPDATE categories SET color = '#632713' WHERE slug = 'technology';
UPDATE categories SET color = '#8A5406' WHERE slug = 'marketing';
UPDATE categories SET color = '#A23F14' WHERE slug = 'startup';
UPDATE categories SET color = '#6B4E0C' WHERE slug = 'business';
UPDATE categories SET color = '#8C2A1C' WHERE slug = 'fitness';
UPDATE categories SET color = '#8C2A1C' WHERE slug = 'culture';
UPDATE categories SET color = '#6B4E0C' WHERE slug = 'education';
UPDATE categories SET color = '#632713' WHERE slug = 'career';
UPDATE categories SET color = '#A23F14' WHERE slug = 'community';
UPDATE categories SET color = '#6B4E0C' WHERE slug = 'professional';
UPDATE categories SET color = '#632713' WHERE slug = 'conference';
UPDATE categories SET color = '#A23F14' WHERE slug = 'workshop';
UPDATE categories SET color = '#8A5406' WHERE slug = 'expo';

UPDATE categories c JOIN categories p ON p.id = c.parent_id SET c.color = p.color;
