-- Add is_municipality boolean column to community table.
--
-- The community_type field historically encoded both entity classification
-- (first_nation vs municipality) and subtype (city, town, settlement, etc).
-- is_municipality provides a clean boolean split used by the /communities
-- filter UI (?type=first-nations / ?type=municipalities).
--
-- SQLite does not support ALTER TABLE ... ADD COLUMN with a NOT NULL constraint
-- without a default, so we add with default 0 (false) and then backfill.

ALTER TABLE community ADD COLUMN is_municipality INTEGER NOT NULL DEFAULT 0;

UPDATE community
SET is_municipality = 1
WHERE community_type IN ('municipality', 'town', 'city', 'region');
