-- N Events — category colors harmonized with the N Events brand
-- (logo teal #025D50 on cream #FFF6E5). The previous saturated Tailwind hues
-- (indigo, violet, lime, cyan…) clashed with the brand and several failed
-- WCAG contrast as label text on white. Every color below is >= 4.5:1 on
-- white, earthy/desaturated, and still distinct per category.
-- Subcategories take their parent's color (as before). Idempotent.

SET NAMES utf8mb4;

UPDATE categories SET color = '#2F5F95' WHERE slug = 'technology';
UPDATE categories SET color = '#9A5B12' WHERE slug = 'marketing';
UPDATE categories SET color = '#0B7560' WHERE slug = 'startup';
UPDATE categories SET color = '#2C4F72' WHERE slug = 'business';
UPDATE categories SET color = '#A8412F' WHERE slug = 'fitness';
UPDATE categories SET color = '#74488A' WHERE slug = 'culture';
UPDATE categories SET color = '#1F6A88' WHERE slug = 'education';
UPDATE categories SET color = '#4E6B2A' WHERE slug = 'career';
UPDATE categories SET color = '#A5521F' WHERE slug = 'community';
UPDATE categories SET color = '#5A6A1E' WHERE slug = 'professional';
UPDATE categories SET color = '#654A85' WHERE slug = 'conference';
UPDATE categories SET color = '#11706F' WHERE slug = 'workshop';
UPDATE categories SET color = '#8C6414' WHERE slug = 'expo';

UPDATE categories c JOIN categories p ON p.id = c.parent_id SET c.color = p.color;
