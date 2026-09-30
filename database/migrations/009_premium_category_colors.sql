-- 009: Category colors for the premium cream and brown palette (2026-09-24).
-- Five brown-family tones replace the orange/yellow-derived ones from 007.
-- Each is at least 5.2 : 1 as label text on Ivory, Cream and Sand:
--   #4B2E1E Brown  (was #632713)      #7A4E2A Bronze (was #A23F14 rust)
--   #6B4A2E Walnut (was #8A5406)      #6E3A2C Maroon (was #8C2A1C brick)
--   #5E4630 Olive-brown (was #6B4E0C)
-- Subcategories take their parent's color. Idempotent.

SET NAMES utf8mb4;

UPDATE categories SET color = '#4B2E1E' WHERE color = '#632713';
UPDATE categories SET color = '#7A4E2A' WHERE color = '#A23F14';
UPDATE categories SET color = '#6B4A2E' WHERE color = '#8A5406';
UPDATE categories SET color = '#6E3A2C' WHERE color = '#8C2A1C';
UPDATE categories SET color = '#5E4630' WHERE color = '#6B4E0C';

UPDATE categories c JOIN categories p ON p.id = c.parent_id SET c.color = p.color;
