-- Add is_municipality boolean column to community table.
--
-- is_municipality provides a clean boolean split used by the /communities
-- filter UI (?type=first-nations / ?type=municipalities).
--
-- Defaults to 0. Values are populated by the NorthCloud ingestion sync,
-- not backfilled here because community_type may not exist on all deployments
-- (it is a schema-drift candidate on older databases).

ALTER TABLE community ADD COLUMN is_municipality INTEGER NOT NULL DEFAULT 0;
